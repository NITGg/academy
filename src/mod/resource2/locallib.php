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
 * Private resource2 module utility functions
 *
 * @package    mod_resource2
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/filelib.php");
require_once("$CFG->libdir/resource2lib.php");
require_once("$CFG->dirroot/mod/resource2/lib.php");

/**
 * Redirected to migrated resource2 if needed,
 * return if incorrect parameters specified
 * @param int $oldid
 * @param int $cmid
 * @return void
 */
function resource2_redirect_if_migrated($oldid, $cmid) {
    global $DB, $CFG;

    if ($oldid) {
        $old = $DB->get_record('resource2_old', array('oldid'=>$oldid));
    } else {
        $old = $DB->get_record('resource2_old', array('cmid'=>$cmid));
    }

    if (!$old) {
        return;
    }

    redirect("$CFG->wwwroot/mod/$old->newmodule/view.php?id=".$old->cmid);
}

/**
 * Display embedded resource2 file.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @param stored_file $file main file
 * @return does not return
 */
function resource2_display_embed($resource2, $cm, $course, $file) {
    global $PAGE, $OUTPUT, $USER;

    $clicktoopen = resource2_get_clicktoopen($file, $resource2->revision);

    $context = context_module::instance($cm->id);
    $moodleurl = moodle_url::make_pluginfile_url($context->id, 'mod_resource2', 'content', $resource2->revision,
            $file->get_filepath(), $file->get_filename());

    $mimetype = $file->get_mimetype();
    $title    = $resource2->name;

    $extension = resource2lib_get_extension($file->get_filename());

    $mediamanager = core_media_manager::instance($PAGE);
    $embedoptions = array(
        core_media_manager::OPTION_TRUSTED => true,
        core_media_manager::OPTION_BLOCK => true,
    );

    if (file_mimetype_in_typegroup($mimetype, 'web_image')) {  // It's an image
        $code = resource2lib_embed_image($moodleurl->out(), $title);

    } else if ($mimetype === 'application/pdf') {
        // PDF document
        $code = resource2lib_embed_pdf($moodleurl->out(), $title, $clicktoopen);

    } else if ($mediamanager->can_embed_url($moodleurl, $embedoptions)) {
        // Media (audio/video) file.
        $code = $mediamanager->embed_url($moodleurl, $title, 0, 0, $embedoptions);

    } else {
        // We need a way to discover if we are loading remote docs inside an iframe.
        $moodleurl->param('embed', 1);

        // anything else - just try object tag enlarged as much as possible
        $code = resource2lib_embed_general($moodleurl, $title, $clicktoopen, $mimetype);
    }

    resource2_print_header($resource2, $cm, $course);
    resource2_print_heading($resource2, $cm, $course);

    // Display any activity information (eg completion requirements / dates).
    $cminfo = cm_info::create($cm);
    $completiondetails = \core_completion\cm_completion_details::get_instance($cminfo, $USER->id);
    $activitydates = \core\activity_dates::get_dates_for_module($cminfo, $USER->id);
    echo $OUTPUT->activity_information($cminfo, $completiondetails, $activitydates);

    echo format_text($code, FORMAT_HTML, ['noclean' => true]);

    resource2_print_intro($resource2, $cm, $course);

    echo $OUTPUT->footer();
    die;
}

/**
 * Display resource2 frames.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @param stored_file $file main file
 * @return does not return
 */
function resource2_display_frame($resource2, $cm, $course, $file) {
    global $PAGE, $OUTPUT, $CFG;

    $frame = optional_param('frameset', 'main', PARAM_ALPHA);

    if ($frame === 'top') {
        $PAGE->set_pagelayout('frametop');
        resource2_print_header($resource2, $cm, $course);
        resource2_print_heading($resource2, $cm, $course);
        resource2_print_intro($resource2, $cm, $course);
        echo $OUTPUT->footer();
        die;

    } else {
        $config = get_config('resource2');
        $context = context_module::instance($cm->id);
        $path = '/'.$context->id.'/mod_resource2/content/'.$resource2->revision.$file->get_filepath().$file->get_filename();
        $fileurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
        $navurl = "$CFG->wwwroot/mod/resource2/view.php?id=$cm->id&amp;frameset=top";
        $title = strip_tags(format_string($course->shortname.': '.$resource2->name));
        $framesize = $config->framesize;
        $contentframetitle = s(format_string($resource2->name));
        $modulename = s(get_string('modulename','resource2'));
        $dir = get_string('thisdirection', 'langconfig');

        $file = <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Frameset//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd">
<html dir="$dir">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <title>$title</title>
  </head>
  <frameset rows="$framesize,*">
    <frame src="$navurl" title="$modulename" />
    <frame src="$fileurl" title="$contentframetitle" />
  </frameset>
</html>
EOF;

        @header('Content-Type: text/html; charset=utf-8');
        echo $file;
        die;
    }
}

/**
 * Internal function - create click to open text with link.
 */
function resource2_get_clicktoopen($file, $revision, $extra='') {
    global $CFG;

    $filename = $file->get_filename();
    $path = '/'.$file->get_contextid().'/mod_resource2/content/'.$revision.$file->get_filepath().$file->get_filename();
    $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);

    $string = get_string('clicktoopen2', 'resource2', "<a href=\"$fullurl\" $extra>$filename</a>");

    return $string;
}

/**
 * Internal function - create click to open text with link.
 */
function resource2_get_clicktodownload($file, $revision) {
    global $CFG;

    $filename = $file->get_filename();
    $path = '/'.$file->get_contextid().'/mod_resource2/content/'.$revision.$file->get_filepath().$file->get_filename();
    $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, true);

    $string = get_string('clicktodownload', 'resource2', "<a href=\"$fullurl\">$filename</a>");

    return $string;
}

/**
 * Print resource2 info and workaround link when JS not available.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @param stored_file $file main file
 * @return does not return
 */
function resource2_print_workaround($resource2, $cm, $course, $file) {
    global $CFG, $OUTPUT, $USER;
    resource2_print_header($resource2, $cm, $course);
    resource2_print_heading($resource2, $cm, $course, true);

    // Display any activity information (eg completion requirements / dates).
    $cminfo = cm_info::create($cm);
    $completiondetails = \core_completion\cm_completion_details::get_instance($cminfo, $USER->id);
    $activitydates = \core\activity_dates::get_dates_for_module($cminfo, $USER->id);
    echo $OUTPUT->activity_information($cminfo, $completiondetails, $activitydates);

    resource2_print_intro($resource2, $cm, $course, true);

    $resource2->mainfile = $file->get_filename();
    echo '<div class="resource2workaround">';
    switch (resource2_get_final_display_type($resource2)) {
        case resource2LIB_DISPLAY_POPUP:
            $path = '/'.$file->get_contextid().'/mod_resource2/content/'.$resource2->revision.$file->get_filepath().$file->get_filename();
            $fullurl = file_encode_url($CFG->wwwroot.'/pluginfile.php', $path, false);
            $options = empty($resource2->displayoptions) ? [] : (array) unserialize_array($resource2->displayoptions);
            $width  = empty($options['popupwidth'])  ? 620 : $options['popupwidth'];
            $height = empty($options['popupheight']) ? 450 : $options['popupheight'];
            $wh = "width=$width,height=$height,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,directories=no,scrollbars=yes,resizable=yes";
            $extra = "onclick=\"window.open('$fullurl', '', '$wh'); return false;\"";
            echo resource2_get_clicktoopen($file, $resource2->revision, $extra);
            break;

        case resource2LIB_DISPLAY_NEW:
            $extra = 'onclick="this.target=\'_blank\'"';
            echo resource2_get_clicktoopen($file, $resource2->revision, $extra);
            break;

        case resource2LIB_DISPLAY_DOWNLOAD:
            echo resource2_get_clicktodownload($file, $resource2->revision);
            break;

        case resource2LIB_DISPLAY_OPEN:
        default:
            echo resource2_get_clicktoopen($file, $resource2->revision);
            break;
    }
    echo '</div>';

    echo $OUTPUT->footer();
    die;
}

/**
 * Print resource2 header.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @return void
 */
function resource2_print_header($resource2, $cm, $course) {
    global $PAGE, $OUTPUT;

    $PAGE->set_title($course->shortname.': '.$resource2->name);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_activity_record($resource2);
    echo $OUTPUT->header();
}

/**
 * Print resource2 heading.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @param bool $notused This variable is no longer used
 * @return void
 */
function resource2_print_heading($resource2, $cm, $course, $notused = false) {
    global $OUTPUT;
    echo $OUTPUT->heading(format_string($resource2->name), 2);
}


/**
 * Gets details of the file to cache in course cache to be displayed using {@link resource2_get_optional_details()}
 *
 * @param object $resource2 resource2 table row (only property 'displayoptions' is used here)
 * @param object $cm Course-module table row
 * @return string Size and type or empty string if show options are not enabled
 */
function resource2_get_file_details($resource2, $cm) {
    $options = empty($resource2->displayoptions) ? [] : (array) unserialize_array($resource2->displayoptions);
    $filedetails = array();
    if (!empty($options['showsize']) || !empty($options['showtype']) || !empty($options['showdate'])) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource2', 'content', 0, 'sortorder DESC, id ASC', false);
        // For a typical file resource2, the sortorder is 1 for the main file
        // and 0 for all other files. This sort approach is used just in case
        // there are situations where the file has a different sort order.
        $mainfile = $files ? reset($files) : null;
        if (!empty($options['showsize'])) {
            $filedetails['size'] = 0;
            foreach ($files as $file) {
                // This will also synchronize the file size for external files if needed.
                $filedetails['size'] += $file->get_filesize();
                if ($file->get_repository_id()) {
                    // If file is a reference the 'size' attribute can not be cached.
                    $filedetails['isref'] = true;
                }
            }
        }
        if (!empty($options['showtype'])) {
            if ($mainfile) {
                $filedetails['type'] = get_mimetype_description($mainfile);
                $filedetails['mimetype'] = $mainfile->get_mimetype();
                // Only show type if it is not unknown.
                if ($filedetails['type'] === get_mimetype_description('document/unknown')) {
                    $filedetails['type'] = '';
                }
            } else {
                $filedetails['type'] = '';
            }
        }
        if (!empty($options['showdate'])) {
            if ($mainfile) {
                // Modified date may be up to several minutes later than uploaded date just because
                // teacher did not submit the form promptly. Give teacher up to 5 minutes to do it.
                if ($mainfile->get_timemodified() > $mainfile->get_timecreated() + 5 * MINSECS) {
                    $filedetails['modifieddate'] = $mainfile->get_timemodified();
                } else {
                    $filedetails['uploadeddate'] = $mainfile->get_timecreated();
                }
                if ($mainfile->get_repository_id()) {
                    // If main file is a reference the 'date' attribute can not be cached.
                    $filedetails['isref'] = true;
                }
            } else {
                $filedetails['uploadeddate'] = '';
            }
        }
    }
    return $filedetails;
}

/**
 * Gets optional details for a resource2, depending on resource2 settings.
 *
 * Result may include the file size and type if those settings are chosen,
 * or blank if none.
 *
 * @param object $resource2 resource2 table row (only property 'displayoptions' is used here)
 * @param object $cm Course-module table row
 * @return string Size and type or empty string if show options are not enabled
 */
function resource2_get_optional_details($resource2, $cm) {
    global $DB;

    $details = '';

    $options = empty($resource2->displayoptions) ? [] : (array) unserialize_array($resource2->displayoptions);
    if (!empty($options['showsize']) || !empty($options['showtype']) || !empty($options['showdate'])) {
        if (!array_key_exists('filedetails', $options)) {
            $filedetails = resource2_get_file_details($resource2, $cm);
        } else {
            $filedetails = $options['filedetails'];
        }
        $size = '';
        $type = '';
        $date = '';
        $langstring = '';
        $infodisplayed = 0;
        if (!empty($options['showsize'])) {
            if (!empty($filedetails['size'])) {
                $size = display_size($filedetails['size']);
                $langstring .= 'size';
                $infodisplayed += 1;
            }
        }
        if (!empty($options['showtype'])) {
            if (!empty($filedetails['type'])) {
                $type = $filedetails['type'];
                $langstring .= 'type';
                $infodisplayed += 1;
            }
        }
        if (!empty($options['showdate']) && (!empty($filedetails['modifieddate']) || !empty($filedetails['uploadeddate']))) {
            if (!empty($filedetails['modifieddate'])) {
                $date = get_string('modifieddate', 'mod_resource2', userdate($filedetails['modifieddate'],
                    get_string('strftimedatetimeshort', 'langconfig')));
            } else if (!empty($filedetails['uploadeddate'])) {
                $date = get_string('uploadeddate', 'mod_resource2', userdate($filedetails['uploadeddate'],
                    get_string('strftimedatetimeshort', 'langconfig')));
            }
            $langstring .= 'date';
            $infodisplayed += 1;
        }

        if ($infodisplayed > 1) {
            $details = get_string("resource2details_{$langstring}", 'resource2',
                    (object)array('size' => $size, 'type' => $type, 'date' => $date));
        } else {
            // Only one of size, type and date is set, so just append.
            $details = $size . $type . $date;
        }
    }

    return $details;
}

/**
 * Print resource2 introduction.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @param bool $ignoresettings print even if not specified in modedit
 * @return void
 */
function resource2_print_intro($resource2, $cm, $course, $ignoresettings=false) {
    global $OUTPUT;

    $options = empty($resource2->displayoptions) ? [] : (array) unserialize_array($resource2->displayoptions);

    $extraintro = resource2_get_optional_details($resource2, $cm);
    if ($extraintro) {
        // Put a paragaph tag around the details
        $extraintro = html_writer::tag('p', $extraintro, array('class' => 'resource2details'));
    }

    if ($ignoresettings || !empty($options['printintro']) || $extraintro) {
        $gotintro = !html_is_blank($resource2->intro);
        if ($gotintro || $extraintro) {
            echo $OUTPUT->box_start('mod_introbox', 'resource2intro');
            if ($gotintro) {
                echo format_module_intro('resource2', $resource2, $cm->id);
            }
            echo $extraintro;
            echo $OUTPUT->box_end();
        }
    }
}

/**
 * Print warning that instance not migrated yet.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @return void, does not return
 */
function resource2_print_tobemigrated($resource2, $cm, $course) {
    global $DB, $OUTPUT;

    $resource2_old = $DB->get_record('resource2_old', array('oldid'=>$resource2->id));
    resource2_print_header($resource2, $cm, $course);
    resource2_print_heading($resource2, $cm, $course);
    resource2_print_intro($resource2, $cm, $course);
    echo $OUTPUT->notification(get_string('notmigrated', 'resource2', $resource2_old->type));
    echo $OUTPUT->footer();
    die;
}

/**
 * Print warning that file can not be found.
 * @param object $resource2
 * @param object $cm
 * @param object $course
 * @return void, does not return
 */
function resource2_print_filenotfound($resource2, $cm, $course) {
    global $DB, $OUTPUT;

    $resource2_old = $DB->get_record('resource2_old', array('oldid'=>$resource2->id));
    resource2_print_header($resource2, $cm, $course);
    resource2_print_heading($resource2, $cm, $course);
    resource2_print_intro($resource2, $cm, $course);
    if ($resource2_old) {
        echo $OUTPUT->notification(get_string('notmigrated', 'resource2', $resource2_old->type));
    } else {
        echo $OUTPUT->notification(get_string('filenotfound', 'resource2'));
    }
    echo $OUTPUT->footer();
    die;
}

/**
 * Decide the best display format.
 * @param object $resource2
 * @return int display type constant
 */
function resource2_get_final_display_type($resource2) {
    global $CFG, $PAGE;

    if ($resource2->display != resource2LIB_DISPLAY_AUTO) {
        return $resource2->display;
    }

    if (empty($resource2->mainfile)) {
        return resource2LIB_DISPLAY_DOWNLOAD;
    } else {
        $mimetype = mimeinfo('type', $resource2->mainfile);
    }

    if (file_mimetype_in_typegroup($mimetype, 'archive')) {
        return resource2LIB_DISPLAY_DOWNLOAD;
    }
    if (file_mimetype_in_typegroup($mimetype, array('web_image', '.htm', 'web_video', 'web_audio'))) {
        return resource2LIB_DISPLAY_EMBED;
    }

    // let the browser deal with it somehow
    return resource2LIB_DISPLAY_OPEN;
}

/**
 * File browsing support class
 */
class resource2_content_file_info extends file_info_stored {
    public function get_parent() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->browser->get_file_info($this->context);
        }
        return parent::get_parent();
    }
    public function get_visible_name() {
        if ($this->lf->get_filepath() === '/' and $this->lf->get_filename() === '.') {
            return $this->topvisiblename;
        }
        return parent::get_visible_name();
    }
}

function resource2_set_mainfile($data) {
    global $DB;
    $fs = get_file_storage();
    $cmid = $data->coursemodule;
    $draftitemid = $data->files;

    $context = context_module::instance($cmid);
    if ($draftitemid) {
        $options = array('subdirs' => true, 'embed' => false);
        if ($data->display == resource2LIB_DISPLAY_EMBED) {
            $options['embed'] = true;
        }
        file_save_draft_area_files($draftitemid, $context->id, 'mod_resource2', 'content', 0, $options);
    }
    $files = $fs->get_area_files($context->id, 'mod_resource2', 'content', 0, 'sortorder', false);
    if (count($files) == 1) {
        // only one file attached, set it as main file automatically
        $file = reset($files);
        file_set_sortorder($context->id, 'mod_resource2', 'content', 0, $file->get_filepath(), $file->get_filename(), 1);
    }
}
