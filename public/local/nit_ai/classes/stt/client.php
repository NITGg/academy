<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_nit_ai\stt;

/**
 * Talks to the speech-to-text service in services/whisper.
 *
 * The service runs Whisper on our own server, so a transcript costs CPU time
 * and nothing else — no AI provider and no tokens. Its address is a private
 * one (a Docker service name), which Moodle's curl security blocks by default;
 * the address is set by an administrator, so that check is switched off here
 * and only here.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {

    /** @var string Base URL, without a trailing slash. */
    protected string $url;

    /** @var string Shared secret the service checks on every call. */
    protected string $token;

    /**
     * @param string|null $url defaults to the site setting
     * @param string|null $token defaults to the site setting
     */
    public function __construct(?string $url = null, ?string $token = null) {
        $this->url = rtrim($url ?? (string) get_config('local_nit_ai', 'stt_url'), '/');
        $this->token = $token ?? (string) get_config('local_nit_ai', 'stt_token');
    }

    /**
     * Is there a service to talk to?
     *
     * @return bool
     */
    public static function is_configured(): bool {
        return trim((string) get_config('local_nit_ai', 'stt_url')) !== ''
            && trim((string) get_config('local_nit_ai', 'stt_token')) !== '';
    }

    /**
     * Hand a video to the service.
     *
     * @param \stored_file $file
     * @return array the new job: id, status, position
     * @throws \moodle_exception when the service refuses it or cannot be reached
     */
    public function submit(\stored_file $file): array {
        // Streamed from a temporary copy: curl's PUT reads a path, and a lesson
        // video is far too large to hold in memory as a request body.
        $path = $file->copy_content_to_temp('local_nit_ai');
        try {
            return $this->submit_path($path, $file->get_filename());
        } finally {
            @unlink($path);
        }
    }

    /**
     * Hand a video already sitting on this server's disk to the service.
     *
     * @param string $path
     * @param string $filename shown in the service's job, and the transcript is named after it
     * @return array the new job: id, status, position
     * @throws \moodle_exception
     */
    public function submit_path(string $path, string $filename): array {
        $curl = $this->curl(3600);
        $body = $curl->put(
            $this->url . '/jobs?name=' . rawurlencode($filename),
            ['file' => $path],
            ['CURLOPT_USERPWD' => false]
        );
        return $this->decode($curl, $body, 201);
    }

    /**
     * Where a job stands.
     *
     * @param string $jobid
     * @return array|null the job, or null when the service no longer knows it
     * @throws \moodle_exception when the service cannot be reached
     */
    public function status(string $jobid): ?array {
        $curl = $this->curl(30);
        $body = $curl->get($this->url . '/jobs/' . rawurlencode($jobid));
        if (($curl->get_info()['http_code'] ?? 0) == 404) {
            return null;
        }
        return $this->decode($curl, $body, 200);
    }

    /**
     * The finished transcript.
     *
     * @param string $jobid
     * @return string WebVTT
     * @throws \moodle_exception
     */
    public function vtt(string $jobid): string {
        $curl = $this->curl(60);
        $body = $curl->get($this->url . '/jobs/' . rawurlencode($jobid) . '/vtt');
        if (($curl->get_info()['http_code'] ?? 0) != 200 || $body === '') {
            throw new \moodle_exception('stt_err_service', 'local_nit_ai', '', $this->describe_failure($curl, $body));
        }
        return $body;
    }

    /**
     * Tell the service to drop a job. Best effort: a job left behind is
     * cleaned up by the service itself after a few days.
     *
     * @param string $jobid
     * @return void
     */
    public function delete(string $jobid): void {
        try {
            $this->curl(30)->delete($this->url . '/jobs/' . rawurlencode($jobid));
        } catch (\Throwable $e) {
            debugging('local_nit_ai: could not delete transcription job ' . $jobid . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * A curl handle carrying the token.
     *
     * @param int $timeout seconds
     * @return \curl
     */
    protected function curl(int $timeout): \curl {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader('X-Token: ' . $this->token);
        $curl->setopt(['CURLOPT_TIMEOUT' => $timeout, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        return $curl;
    }

    /**
     * Read a JSON reply, or explain why there is none.
     *
     * @param \curl $curl
     * @param string|bool|null $body
     * @param int $expected HTTP status that means success
     * @return array
     * @throws \moodle_exception
     */
    protected function decode(\curl $curl, $body, int $expected): array {
        $data = is_string($body) ? json_decode($body, true) : null;
        if (($curl->get_info()['http_code'] ?? 0) != $expected || !is_array($data)) {
            throw new \moodle_exception('stt_err_service', 'local_nit_ai', '', $this->describe_failure($curl, $body));
        }
        return $data;
    }

    /**
     * One line saying what went wrong, for the log and the teacher's panel.
     *
     * @param \curl $curl
     * @param string|bool|null $body
     * @return string
     */
    protected function describe_failure(\curl $curl, $body): string {
        if ($curl->get_errno()) {
            return $curl->error;
        }
        $data = is_string($body) ? json_decode($body, true) : null;
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        return 'HTTP ' . $code . (is_array($data) && !empty($data['error']) ? ': ' . $data['error'] : '');
    }
}
