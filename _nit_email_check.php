<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->libdir . '/clilib.php');

use local_nit_emails\mailer;
use local_nit_emails\templates;

$admin = get_admin();
foreach (templates::events() as $event) {
    foreach (templates::LANGS as $lang) {
        $old = force_current_language($lang);
        $r = mailer::render($event, $lang, \local_nit_emails\context_builder::sample($event, $admin));
        force_current_language($old);
        echo "=== $event / $lang ===\n";
        echo "SUBJECT: {$r['subject']}\n";
        echo "HTML bytes: " . strlen($r['html']) . "\n";
        if (strpos($r['html'], '{') !== false && preg_match_all('/\{[a-z_]+\}/', $r['html'], $m)) {
            echo "UNRESOLVED: " . implode(',', array_unique($m[0])) . "\n";
        }
        echo "TEXT:\n" . substr(trim($r['text']), 0, 700) . "\n\n";
    }
}
echo "OK\n";
