<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for the Academy platform.
 */
class observer {

    /**
     * When Moodle sends any notification, nudge the recipient's browser in realtime so it can
     * chime + refresh without polling. Fire-and-forget: this must never disrupt message sending.
     *
     * @param \core\event\notification_sent $event
     */
    public static function notification_sent(\core\event\notification_sent $event): void {
        $userid = (int) $event->relateduserid; // The recipient.
        if (empty($userid)) {
            return;
        }
        try {
            realtime::emit($userid, ['id' => (int) $event->objectid]);
        } catch (\Throwable $e) {
            debugging('academy realtime emit failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
