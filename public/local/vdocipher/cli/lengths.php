<?php
/**
 * VdoCipher playing times (CLI): show them, and fill in the ones we do not have.
 *
 * The course page prints a video's length beside the activity name. That number
 * comes from VdoCipher, which only reports it once transcoding finishes — so a
 * row is created with length 0 and filled in later by the refresh_lengths
 * scheduled task. This script is the same job on demand: it is what to run right
 * after deploying, or whenever a video shows no time and you want to know
 * whether it is still processing or something else is wrong.
 *
 * Usage (inside the container):
 *   php local/vdocipher/cli/lengths.php --status            # list every video
 *   php local/vdocipher/cli/lengths.php --status --course=5  # just one course
 *   php local/vdocipher/cli/lengths.php --refresh            # ask about the missing ones
 *   php local/vdocipher/cli/lengths.php --refresh --all      # re-ask about every video
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    ['status' => false, 'refresh' => false, 'all' => false, 'course' => 0, 'limit' => 200, 'help' => false],
    ['h' => 'help', 's' => 'status', 'r' => 'refresh']
);

if ($options['help'] || (!$options['status'] && !$options['refresh'])) {
    cli_writeln("VdoCipher playing times\n");
    cli_writeln("  --status         list videos with their stored length");
    cli_writeln("  --refresh        ask VdoCipher about videos with no length yet");
    cli_writeln("  --all            with --refresh: re-ask about every video, not just the missing ones");
    cli_writeln("  --course=ID      restrict --status to one course");
    cli_writeln("  --limit=N        how many rows to refresh in one run (default 200)");
    exit(0);
}

/**
 * Render seconds the way the course page does: m:ss, or h:mm:ss past the hour.
 *
 * @param int $seconds
 * @return string
 */
function vdocipher_cli_hms(int $seconds): string {
    if ($seconds <= 0) {
        return '—';
    }
    $hours = intdiv($seconds, 3600);
    return $hours > 0
        ? sprintf('%d:%02d:%02d', $hours, intdiv($seconds % 3600, 60), $seconds % 60)
        : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
}

$table = \local_vdocipher\video_service::TABLE;

// ── Refresh ──────────────────────────────────────────────────────────────────
if ($options['refresh']) {
    if (!\local_vdocipher\api_client::is_configured()) {
        cli_error('VdoCipher API secret is not configured (settings page).');
    }

    $limit = max(1, (int) $options['limit']);
    $rows = $options['all']
        ? $DB->get_records_select($table, "videoid <> ?", [''], 'timemodified ASC',
            'id, videoid, status, length, timemodified', 0, $limit)
        : \local_vdocipher\video_service::rows_missing_length($limit);

    if (!$rows) {
        cli_writeln('Nothing to refresh — every video already has a playing time.');
    } else {
        cli_heading('Asking VdoCipher about ' . count($rows) . ' video(s)');
        $filled = 0;
        foreach ($rows as $row) {
            $seconds = \local_vdocipher\video_service::fetch_length($row);
            $after   = $DB->get_record($table, ['id' => $row->id], 'status, length');
            cli_writeln(sprintf('  %-24s %-12s %s', $row->videoid, $after->status, vdocipher_cli_hms((int) $seconds)));
            if ($seconds > 0) {
                $filled++;
            }
        }
        cli_writeln("\n{$filled} of " . count($rows) . ' now have a playing time.');
        if ($filled < count($rows)) {
            cli_writeln('The rest are still being processed by VdoCipher — try again in a few minutes.');
        }
    }
}

// ── Status ───────────────────────────────────────────────────────────────────
if ($options['status']) {
    $conditions = $options['course'] ? ['courseid' => (int) $options['course']] : [];
    $rows = $DB->get_records($table, $conditions, 'courseid ASC, id ASC');

    cli_heading('VdoCipher videos' . ($options['course'] ? ' in course ' . (int) $options['course'] : ''));
    if (!$rows) {
        cli_writeln('No videos recorded.');
        exit(0);
    }

    cli_writeln(sprintf('%-22s %-6s %-7s %-12s %-9s %s', 'VIDEO ID', 'COURSE', 'CMID', 'STATUS', 'LENGTH', 'TITLE'));
    $missing = 0;
    foreach ($rows as $row) {
        // A row with no cmid is attached to no activity, so no course page can
        // ever show it — worth seeing at a glance next to the lengths.
        cli_writeln(sprintf('%-22s %-6d %-7s %-12s %-9s %s',
            $row->videoid,
            $row->courseid,
            $row->cmid ?: '—',
            \core_text::substr($row->status, 0, 12),
            vdocipher_cli_hms((int) $row->length),
            \core_text::substr($row->title, 0, 40)));
        if ((int) $row->length <= 0) {
            $missing++;
        }
    }
    cli_writeln("\n" . count($rows) . ' video(s), ' . $missing . ' without a playing time.');
    if ($missing) {
        cli_writeln('Run with --refresh to ask VdoCipher for them now.');
    }
}
