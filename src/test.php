<?php

require_once("config.php");

require_once("config.php");
    $user_context = context_user::instance(2, MUST_EXIST);
    $fs = get_file_storage();
    $files = $fs->get_area_files($user_context->id, 'user', 'icon', 0, 'sortorder DESC, id ASC', false);
    // var_dump($files);
        if (count($files) < 1) {
        $image = 'not_set';
    } else {
        $file = reset($files);
        unset($files);
        $path = '/' . $user_context->id . '/user/icon/0' . $file->get_filepath() . $file->get_filename();
        $image = $CFG->wwwroot . '/pluginfile.php' . $path;
        
    }
    echo $image;