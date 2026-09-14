<?php
require(__DIR__ . '/config.php');
if ($CFG->wwwroot !== 'http://localhost:8080') { die(); }
complete_user_login(get_complete_user_data('id', 2));
redirect('/mod/assign/view.php?id=49&action=grading');
