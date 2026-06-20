<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file is the entry point to the assign module. All pages are rendered from here
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/webservice/lib.php');

$id = required_param('id', PARAM_INT);
$token = optional_param('token',  0, PARAM_TEXT); //token from mobile api

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');
if(!empty($token) ){
    $CFG->token=$token;
   
    $api = new webservice();
    $array = array();
    try{
        $array = $api->authenticate_user($token);
    if (!empty($array)){
      
    }
    else 
       echo json_encode( ['message'=>'invalide token']);

    }catch(Exception $e){
        echo json_encode( ['message'=>'invalide token']);
    }
    $ins = new stdClass();
    $record=$DB->get_record('token_check',array('user'=>$USER->id));
    if(empty( $record))
   {
    $ins->token=1;
    $ins->login=0;
    $ins->user = $USER->id;
    $ins->id = $DB->insert_record('token_check', $ins);}
    else{
        $ins->id=$record->id;
        $ins->login = 0;
        $ins->token = 1;
        $ins->id = $DB->update_record('token_check', $ins);
    }
    

}
else {
    $ins = new stdClass();
    $record=$DB->get_record('token_check',array('user'=>$USER->id));
    if(empty( $record))
   { 
    $ins->token=0;
    $ins->login=1;
    $ins->user = $USER->id;
    $ins->id = $DB->insert_record('token_check', $ins);}
    else{
        $ins->id=$record->id;
        $ins->token = 0;
        $ins->login = 1;
        $ins->id = $DB->update_record('token_check', $ins);
    }
}
require_login($course, true, $cm);
$context = context_module::instance($cm->id);

require_capability('mod/assign:view', $context);

$assign = new assign($context, $cm, $course);
$urlparams = array('id' => $id,
                  'action' => optional_param('action', '', PARAM_ALPHA),
                  'rownum' => optional_param('rownum', 0, PARAM_INT),
                  'useridlistid' => optional_param('useridlistid', $assign->get_useridlist_key_id(), PARAM_ALPHANUM));

$url = new moodle_url('/mod/assign/view.php', $urlparams);
$PAGE->set_url($url);

// Update module completion status.
$assign->set_module_viewed();

// Apply overrides.
$assign->update_effective_access($USER->id);

// Get the assign class to
// render the page.
echo $assign->view(optional_param('action', '', PARAM_ALPHA));
echo "
<style>

.add-assignment .mform .fp-toolbar .fp-btn-mkdir{ display: none; }
.add-assignment .mform .fp-toolbar .fp-btn-add a,
.add-assignment .mform .fp-toolbar .fp-btn-download a{ font-size:25px;}
.add-assignment .mform .filemanager-toolbar .fp-viewbar{display:none !important;}



</style>
";


