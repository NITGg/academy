<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(

    // Manage lesson packages (create / update / deactivate / delete). Site-level admin action.
    'local/academy:managepackages' => array(
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => array(
            'manager' => CAP_ALLOW,
        ),
    ),
);
