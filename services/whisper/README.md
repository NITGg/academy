# Whisper transcription service

Turns lesson videos into timed WebVTT transcripts for `local_nit_ai`, on our own
server: no AI provider, no tokens, and the video never leaves the machine.

A teacher uploads the video in the activity's **AI assistant** section → an adhoc
task sends it here → this service transcribes it (one video at a time) → the
`stt_poll` scheduled task collects the `.vtt`, saves it as the activity's
transcript and notifies the teacher, who reviews and approves it as usual.

## Model and cost

`large-v3-turbo`, int8, on CPU. Measured on 2 cores: about **1 minute of work per
minute of video**, ~1.7 GB RAM while running. The model (~1.5 GB) downloads on the
first job into the `/models` volume.

Keep it at 2 cores on the production server (4 cores shared with Moodle and the
database): going from 2 to 4 cores only made it ~1.5× faster.

## Environment

| Variable | Default | |
|---|---|---|
| `WHISPER_TOKEN` | — (required) | Shared secret; same value as the plugin setting *Transcription service token* |
| `WHISPER_MODEL` | `large-v3-turbo` | |
| `WHISPER_THREADS` | `2` | Match the container's `cpus` |
| `WHISPER_MAX_UPLOAD_MB` | `4096` | |
| `WHISPER_KEEP_DAYS` | `7` | Finished jobs nobody collected are deleted after this |

## Add it to a docker-compose.yml

The service must be on the same Docker network as the Moodle **and** cron
containers, and its port must not be published.

```yaml
  whisper:
    build: ./services/whisper        # path relative to the compose file
    container_name: moodle_whisper
    restart: unless-stopped
    cpus: 2
    mem_limit: 4g
    environment:
      WHISPER_TOKEN: ${WHISPER_TOKEN}   # put WHISPER_TOKEN=... in .env next to the compose file
      WHISPER_THREADS: "2"
    volumes:
      - whisper-models:/models
      - whisper-data:/data

volumes:
  whisper-models:
  whisper-data:
```

Then in Moodle: *Site administration > Plugins > Local plugins > AI video assistant*
→ **Automatic transcripts**: address `http://whisper:9000` (the service name),
token = the same `WHISPER_TOKEN`.

## Check it

```bash
docker compose exec moodle curl -s http://whisper:9000/health
```

`{"ok": true, "model": "large-v3-turbo", "threads": 2, "queue": 0}`

## Upload size

The video goes through Moodle's file picker, so PHP's `upload_max_filesize` and
`post_max_size` (and the web server's body limit) cap it. An hour of lesson video
is often 200 MB–1 GB.
