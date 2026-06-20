<?php
// function testnew_pluginfile($course, $cm, $context, $component, $filearea, $itemid, $path, $filename) {
 
//     global $CFG, $DB;
//     require_course_login($course, true, $cm);

//         $fullpath = "/{$context->id}/$component/$filearea/$itemid/$path/$filename";
//         $fs = get_file_storage();
//         if (!$file = $fs->get_file_by_hash(sha1($fullpath))) {
//         return false;
//         }
//         send_stored_file($file, 0, 0, false);
        
    
// }
// function testnew_pluginfile($course, $cm, $context, $component, $filearea, $itemid, $path, $filename) {

//     global $CFG, $DB;
//     require_course_login($course, true, $cm);
//     $fullpath = "/{$context->id}/$component/$filearea/$itemid/$path/$filename";
//     $fs = get_file_storage();
//     $file = $fs->get_file($context->id, 'mod_testnew', $filearea, $itemid, $path, $filename);

//     if (!$file) {
//         return false; // The file does not exist.
//     }
//     send_stored_file($file, null, 0, false);
    
//     }
function testnew_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    // Check the contextlevel is as expected - if your plugin is a block, this becomes CONTEXT_BLOCK, etc.
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false; 
    }

    // Make sure the filearea is one of those used by the plugin.
    // if ($filearea !== 'expectedfilearea' && $filearea !== 'anotherexpectedfilearea') {
    //     return false;
    // }

    // Make sure the user is logged in and has access to the module (plugins that are not course modules should leave out the 'cm' part).
    require_login($course, true, $cm);

    // Check the relevant capabilities - these may vary depending on the filearea being accessed.
    if (!has_capability('mod/testnew:view', $context)) {
        return false;
    }

    // Leave this line out if you set the itemid to null in make_pluginfile_url (set $itemid to 0 instead).
    $itemid = array_shift($args); // The first item in the $args array.
    
    // Use the itemid to retrieve any relevant data records and perform any security checks to see if the
    // user really does have access to the file in question.

    // Extract the filename / filepath from the $args array.
    $filename = array_pop($args); // The last item in the $args array.
    if (!$args) {
        $filepath = '/'; // $args is empty => the path is '/'
    } else {
        $filepath = '/'.implode('/', $args).'/'; // $args contains elements of the filepath
    }

    // Retrieve the file from the Files API.
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_testnew', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false; // The file does not exist.
    }

    // We can now send the file back to the browser - in this case with a cache lifetime of 1 day and no filtering. 
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
function testnew_add_instance($data){
    global $DB;
    $cmid = $data->coursemodule;
    $data->timemodified =time();

    $data->id = $DB->insert_record('testnew', $data);
    $DB->set_field('course_modules', 'instance', $data->id, array('id'=>$cmid));
    $fs = get_file_storage();
    $draftitemid = $data->type;
    $context = context_module::instance($cmid);
    file_save_draft_area_files($draftitemid, $context->id, 'mod_testnew', 'content', 0);
    $files = $fs->get_area_files($context->id, 'mod_testnew', 'content', 0, 'sortorder', false);
    if (count($files) == 1) {
        // only one file attached, set it as main file automatically
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_testnew', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($cmid, 'testnew', $data->id, $completiontimeexpected);
    return $data->id;
}
function testnew_update_instance($data){
    global $DB;
    $cmid=$data->coursemodule;
    $data->timemodified = time();
    $data->id           = $data->instance;
    $data->introformat++;
    $DB->update_record('testnew', $data);
    $fs = get_file_storage();
    $draftitemid = $data->type;
  
    $context = context_module::instance($cmid);
    file_save_draft_area_files($draftitemid, $context->id, 'mod_testnew', 'content', 0);
    $files = $fs->get_area_files($context->id, 'mod_testnew', 'content', 0, 'sortorder', false);
    if (count($files) == 1) {
        // only one file attached, set it as main file automatically
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_testnew', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
    $completiontimeexpected = !empty($data->completionexpected) ? $data->completionexpected : null;
    \core_completion\api::update_completion_date_event($data->coursemodule, 'testnew', $data->id, $completiontimeexpected);
    return true;
}
function testnew_delete_instance($id){

    global $DB;
    if (!$testnew = $DB->get_record('testnew', array('id'=>$id))) {
        return false;
    }
    // $filename='F:/moodle/server/moodle/mod/testnew/file.txt';
    // file_put_contents($filename,$testnew->id );
    $DB->delete_records('testnew', array('id'=>$testnew->id));

    return true;

}
function testnew_export_contents($cm, $baseurl) {
    global $CFG, $DB;
    $contents = array();
    $context = context_module::instance($cm->id);
    $testnew = $DB->get_record('testnew', array('id'=>$cm->instance), '*', MUST_EXIST);

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_testnew', 'content', 0, 'sortorder DESC, id ASC', false);

    foreach ($files as $fileinfo) {
        $file = array();
        $file['type'] = 'file';
        $file['filename']     = $fileinfo->get_filename();
        $file['filepath']     = $fileinfo->get_filepath();
        $file['filesize']     = $fileinfo->get_filesize();
        $file['fileurl']      = file_encode_url("$CFG->wwwroot/" . $baseurl, '/'.$context->id.'/mod_testnew/content/0'.$fileinfo->get_filepath().$fileinfo->get_filename(), true);
        $file['timecreated']  = $fileinfo->get_timecreated();
        $file['timemodified'] = $fileinfo->get_timemodified();
        $file['sortorder']    = $fileinfo->get_sortorder();
        $file['userid']       = $fileinfo->get_userid();
        $file['author']       = $fileinfo->get_author();
        $file['license']      = $fileinfo->get_license();
        $file['mimetype']     = $fileinfo->get_mimetype();
        $file['isexternalfile'] = $fileinfo->is_external_file();
        if ($file['isexternalfile']) {
            $file['repositorytype'] = $fileinfo->get_repository_type();
        }
        $contents[] = $file;
    }

    return $contents;
}
