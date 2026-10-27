"""Speech-to-text service for the NIT AI video assistant.

Turns a lesson video into a timed WebVTT transcript with faster-whisper,
running entirely on this machine: no AI provider, no per-token cost, and the
video never leaves the server.

One job at a time, on purpose. The model shares the CPU with Moodle and its
database, so a queue that works through uploads in order is the whole point —
two videos at once would only make both slower and the site with them.

The HTTP surface is what local_nit_ai talks to (classes/stt/client.php):

    PUT    /jobs?name=<file name>   body = the media file   -> {"id": ...}
    GET    /jobs/<id>                                         -> job status
    GET    /jobs/<id>/vtt                                     -> the transcript
    DELETE /jobs/<id>
    GET    /health

Every call except /health carries the shared secret in an X-Token header.
"""

import hmac
import json
import os
import shutil
import subprocess
import threading
import time
import uuid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import parse_qs, urlparse

TOKEN = os.environ.get("WHISPER_TOKEN", "")
MODEL = os.environ.get("WHISPER_MODEL", "large-v3-turbo")
THREADS = int(os.environ.get("WHISPER_THREADS", "2"))
DATA = os.environ.get("WHISPER_DATA", "/data")
PORT = int(os.environ.get("WHISPER_PORT", "9000"))
MAX_UPLOAD = int(os.environ.get("WHISPER_MAX_UPLOAD_MB", "4096")) * 1024 * 1024
KEEP_DAYS = int(os.environ.get("WHISPER_KEEP_DAYS", "7"))

JOBS = os.path.join(DATA, "jobs")
LOCK = threading.Lock()
WAKE = threading.Event()


def job_dir(job_id):
    return os.path.join(JOBS, job_id)


def read_job(job_id):
    try:
        with open(os.path.join(job_dir(job_id), "job.json"), encoding="utf-8") as f:
            return json.load(f)
    except (OSError, ValueError):
        return None


def write_job(job):
    path = os.path.join(job_dir(job["id"]), "job.json")
    tmp = path + ".tmp"
    with open(tmp, "w", encoding="utf-8") as f:
        json.dump(job, f)
    os.replace(tmp, path)  # Never leave a half-written status for a poll to read.


def all_jobs():
    jobs = []
    for name in os.listdir(JOBS):
        job = read_job(name)
        if job:
            jobs.append(job)
    return sorted(jobs, key=lambda j: j["created"])


def queue_position(job):
    if job["status"] != "queued":
        return 0
    ahead = [j for j in all_jobs() if j["status"] in ("queued", "running") and j["created"] < job["created"]]
    return len(ahead) + 1


def timecode(seconds):
    h, rest = divmod(seconds, 3600)
    m, s = divmod(rest, 60)
    return f"{int(h):02d}:{int(m):02d}:{s:06.3f}"


class Worker(threading.Thread):
    """Works through the queue, oldest first, one job at a time."""

    daemon = True

    def __init__(self):
        super().__init__()
        self.model = None

    def load_model(self):
        # Loaded on the first job, not at start-up: a container that is up but
        # idle should not hold ~2 GB of RAM it may not need for hours.
        if self.model is None:
            from faster_whisper import WhisperModel
            self.model = WhisperModel(MODEL, device="cpu", compute_type="int8", cpu_threads=THREADS)
        return self.model

    def next_job(self):
        with LOCK:
            for job in all_jobs():
                if job["status"] == "queued":
                    job["status"] = "running"
                    job["started"] = time.time()
                    write_job(job)
                    return job
        return None

    def run(self):
        while True:
            job = self.next_job()
            if not job:
                cleanup()
                WAKE.wait(30)
                WAKE.clear()
                continue
            self.process(job)

    def process(self, job):
        folder = job_dir(job["id"])
        media = os.path.join(folder, "media")
        wav = os.path.join(folder, "audio.wav")
        try:
            # Whisper wants 16 kHz mono; extracting it first also means the
            # model never has to care what container or codec came in.
            subprocess.run(
                ["ffmpeg", "-y", "-loglevel", "error", "-i", media, "-vn", "-ac", "1", "-ar", "16000", wav],
                check=True, capture_output=True, timeout=3600,
            )

            segments, info = self.load_model().transcribe(wav, beam_size=5, vad_filter=True)
            job["duration"] = round(info.duration)
            job["language"] = info.language
            write_job(job)

            lines = ["WEBVTT", ""]
            lastsave = time.time()
            for seg in segments:
                text = seg.text.strip()
                if text:
                    lines += [f"{timecode(seg.start)} --> {timecode(seg.end)}", text, ""]
                if time.time() - lastsave > 10:
                    job["progress"] = round(min(seg.end / max(info.duration, 1), 1) * 100)
                    write_job(job)
                    lastsave = time.time()

            with open(os.path.join(folder, "transcript.vtt"), "w", encoding="utf-8") as f:
                f.write("\n".join(lines))

            job.update(status="done", progress=100, finished=time.time())
        except subprocess.CalledProcessError as e:
            job.update(status="failed", error="ffmpeg: " + e.stderr.decode("utf-8", "replace").strip()[-500:])
        except Exception as e:  # noqa: BLE001 - any failure must end the job, not the worker.
            job.update(status="failed", error=f"{type(e).__name__}: {e}"[:500])
        finally:
            for path in (media, wav):
                if os.path.exists(path):
                    os.remove(path)
            job.setdefault("finished", time.time())
            write_job(job)


def cleanup():
    """Forget finished jobs nobody collected. Moodle deletes the ones it does."""
    cutoff = time.time() - KEEP_DAYS * 86400
    for job in all_jobs():
        if job["status"] in ("done", "failed") and job.get("finished", job["created"]) < cutoff:
            shutil.rmtree(job_dir(job["id"]), ignore_errors=True)


class Handler(BaseHTTPRequestHandler):
    server_version = "nit-whisper/1"

    def send_json(self, code, payload):
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def authorised(self):
        given = self.headers.get("X-Token", "")
        if TOKEN and hmac.compare_digest(given.encode(), TOKEN.encode()):
            return True
        self.send_json(401, {"error": "bad token"})
        return False

    def route(self):
        parts = [p for p in urlparse(self.path).path.split("/") if p]
        return parts

    def do_GET(self):
        parts = self.route()
        if parts == ["health"]:
            busy = [j for j in all_jobs() if j["status"] in ("queued", "running")]
            return self.send_json(200, {"ok": True, "model": MODEL, "threads": THREADS, "queue": len(busy)})
        if not self.authorised():
            return
        if len(parts) >= 2 and parts[0] == "jobs":
            job = read_job(parts[1])
            if not job:
                return self.send_json(404, {"error": "no such job"})
            if len(parts) == 2:
                return self.send_json(200, dict(job, position=queue_position(job)))
            if parts[2:] == ["vtt"] and job["status"] == "done":
                with open(os.path.join(job_dir(job["id"]), "transcript.vtt"), "rb") as f:
                    body = f.read()
                self.send_response(200)
                self.send_header("Content-Type", "text/vtt; charset=utf-8")
                self.send_header("Content-Length", str(len(body)))
                self.end_headers()
                self.wfile.write(body)
                return
        self.send_json(404, {"error": "not found"})

    def do_PUT(self):
        if not self.authorised():
            return
        url = urlparse(self.path)
        if [p for p in url.path.split("/") if p] != ["jobs"]:
            return self.send_json(404, {"error": "not found"})

        length = int(self.headers.get("Content-Length") or 0)
        if length <= 0:
            return self.send_json(411, {"error": "Content-Length required"})
        if length > MAX_UPLOAD:
            return self.send_json(413, {"error": "file too large"})

        job_id = uuid.uuid4().hex
        folder = job_dir(job_id)
        os.makedirs(folder)
        # Streamed straight to disk: a lesson can run to gigabytes.
        with open(os.path.join(folder, "media"), "wb") as f:
            remaining = length
            while remaining:
                chunk = self.rfile.read(min(remaining, 1024 * 1024))
                if not chunk:
                    shutil.rmtree(folder, ignore_errors=True)
                    return self.send_json(400, {"error": "upload cut short"})
                f.write(chunk)
                remaining -= len(chunk)

        name = parse_qs(url.query).get("name", [""])[0][:255]
        job = {"id": job_id, "name": name, "status": "queued", "progress": 0, "created": time.time()}
        with LOCK:
            write_job(job)
        WAKE.set()
        self.send_json(201, dict(job, position=queue_position(job)))

    def do_DELETE(self):
        if not self.authorised():
            return
        parts = self.route()
        if len(parts) == 2 and parts[0] == "jobs":
            job = read_job(parts[1])
            if job and job["status"] == "running":
                return self.send_json(409, {"error": "job is running"})
            shutil.rmtree(job_dir(parts[1]), ignore_errors=True)
            return self.send_json(200, {"deleted": True})
        self.send_json(404, {"error": "not found"})

    def log_message(self, fmt, *args):
        # Polls arrive every minute; only say something when it is not one.
        if not (self.command == "GET" and " 200 " in (fmt % args)):
            super().log_message(fmt, *args)


def main():
    if not TOKEN:
        raise SystemExit("WHISPER_TOKEN is not set: refusing to start an open transcription service.")
    os.makedirs(JOBS, exist_ok=True)

    # A job that was running when the container stopped never finished: put it
    # back at the front of the queue rather than leave it "running" forever.
    for job in all_jobs():
        if job["status"] == "running":
            job.update(status="queued", progress=0)
            write_job(job)

    Worker().start()
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()


if __name__ == "__main__":
    main()
