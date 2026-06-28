<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Academy Flex platform';
$string['academy:managepackages'] = 'Manage lesson (Flex) packages';
$string['managepackages'] = 'Manage lesson packages';

// Error messages (surfaced in API JSON via getMessage()).
$string['err_namerequired']  = 'Package name is required';
$string['err_nameempty']     = 'Package name cannot be empty';
$string['err_flexpositive']  = 'Flex count must be greater than zero';
$string['err_pricenegative'] = 'Price cannot be negative';
$string['err_expnegative']   = 'Expiration days cannot be negative';
$string['err_status']        = 'Status must be "active" or "inactive"';
$string['err_notfound']      = 'Package not found';
$string['err_haspurchases']  = 'This package has purchase records and cannot be deleted. Deactivate it instead.';
$string['err_packagenotavailable'] = 'This package is not available for purchase';
$string['err_alreadyhaspackage']   = 'You already have an active package';
