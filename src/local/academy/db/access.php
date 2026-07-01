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

    // Manage subscription plans + course access (create / update / deactivate / delete / course map).
    'local/academy:managesubscriptions' => array(
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => array(
            'manager' => CAP_ALLOW,
        ),
    ),

    // Manage the platform: lesson settings, reports, assign packages, process withdrawals, reversals.
    'local/academy:manageplatform' => array(
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => array(
            'manager' => CAP_ALLOW,
        ),
    ),
);
