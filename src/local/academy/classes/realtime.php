<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper for the realtime notification relay (notify-ws Socket.IO server).
 *
 * Config (all optional, sensible local-docker defaults; override in config.php for prod):
 *   $CFG->academy_notify_enabled     bool   — master switch (default: on)
 *   $CFG->academy_notify_secret      string — HMAC secret shared with the socket server
 *   $CFG->academy_notify_internalurl string — server-to-server base URL Moodle POSTs to
 *   $CFG->academy_notify_publicurl   string — base URL the browser connects to
 *   $CFG->academy_notify_path        string — Socket.IO path (matches the server's SOCKET_PATH)
 */
class realtime {

    /** @return bool whether realtime push is enabled. */
    public static function enabled(): bool {
        global $CFG;
        return !isset($CFG->academy_notify_enabled) || !empty($CFG->academy_notify_enabled);
    }

    /** @return string shared secret (HMAC + internal /emit key). */
    public static function secret(): string {
        global $CFG;
        return !empty($CFG->academy_notify_secret) ? $CFG->academy_notify_secret : 'academy-internal-secret';
    }

    /** @return string base URL Moodle uses to reach the socket server (docker network). */
    public static function internal_url(): string {
        global $CFG;
        return !empty($CFG->academy_notify_internalurl) ? $CFG->academy_notify_internalurl : 'http://notify-ws:3100';
    }

    /** @return string base URL the browser connects to. */
    public static function public_url(): string {
        global $CFG;
        return !empty($CFG->academy_notify_publicurl) ? $CFG->academy_notify_publicurl : 'http://localhost:3100';
    }

    /** @return string Socket.IO path (must match the server's SOCKET_PATH). */
    public static function socket_path(): string {
        global $CFG;
        return !empty($CFG->academy_notify_path) ? $CFG->academy_notify_path : '/socket.io';
    }

    /**
     * Mint a short-lived, self-verifying token for a user: "<userid>.<exp>.<sig>".
     *
     * @param int $userid
     * @return string
     */
    public static function mint_token(int $userid): string {
        $exp = time() + 6 * HOURSECS;
        $payload = $userid . '.' . $exp;
        $sig = hash_hmac('sha256', $payload, self::secret());
        return $payload . '.' . $sig;
    }

    /**
     * Fire-and-forget push of a notification to one user's room. Never throws to the caller.
     *
     * @param int   $userid       recipient
     * @param array $notification small payload (e.g. ['id' => notificationid])
     */
    public static function emit(int $userid, array $notification = []): void {
        if (!self::enabled() || $userid <= 0) {
            return;
        }
        $url = rtrim(self::internal_url(), '/') . '/emit';
        $body = json_encode(['userid' => $userid, 'notification' => $notification]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-internal-key: ' . self::secret(),
            ],
            CURLOPT_RETURNTRANSFER => true,
            // Keep it snappy so a slow/absent socket server can never delay message sending.
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT        => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
