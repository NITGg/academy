<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/config.php');
global $CFG;
require_once($CFG->dirroot.'/message/lib.php');

echo "MESSAGE_DISALLOWED=".MESSAGE_DISALLOWED." PERMITTED=".MESSAGE_PERMITTED." FORCED=".MESSAGE_FORCED."\n\n";
foreach (get_message_providers() as $p) {
    $comp = $p->component; $name = $p->name;
    $dir = ($comp === 'moodle') ? $CFG->dirroot.'/lib/db/messages.php'
        : core_component::get_component_directory($comp).'/db/messages.php';
    $messageproviders = [];
    if ($dir && file_exists($dir)) { include($dir); }
    $decl = $messageproviders[$name] ?? null;
    $d = $decl['defaults'] ?? [];
    $flags = [];
    foreach (['email','popup'] as $ch) {
        if (!array_key_exists($ch, $d)) { $flags[] = "$ch:NOT-DECLARED"; continue; }
        $v = $d[$ch];
        $perm = $v & (MESSAGE_DISALLOWED | MESSAGE_PERMITTED | MESSAGE_FORCED);
        $flags[] = "$ch:" . ($perm === MESSAGE_DISALLOWED ? 'DISALLOWED'
            : ($perm === MESSAGE_FORCED ? 'FORCED' : ($perm === MESSAGE_PERMITTED ? 'permitted' : "raw$perm")));
    }
    $cap = isset($decl['capability']) ? " cap={$decl['capability']}" : '';
    printf("%-52s %s%s\n", "{$comp}_{$name}", implode('  ', $flags), $cap);
}
