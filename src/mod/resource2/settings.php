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
 * resource2 module admin settings and defaults
 *
 * @package    mod_resource2
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

// if ($ADMIN->fulltree) {
//     require_once("$CFG->libdir/resource2lib.php");

//     $displayoptions = resource2lib_get_displayoptions(array(resource2LIB_DISPLAY_AUTO,
//                                                            resource2LIB_DISPLAY_EMBED,
//                                                            resource2LIB_DISPLAY_FRAME,
//                                                            resource2LIB_DISPLAY_DOWNLOAD,
//                                                            resource2LIB_DISPLAY_OPEN,
//                                                            resource2LIB_DISPLAY_NEW,
//                                                            resource2LIB_DISPLAY_POPUP,
//                                                           ));
//     $defaultdisplayoptions = array(resource2LIB_DISPLAY_AUTO,
//                                    resource2LIB_DISPLAY_EMBED,
//                                    resource2LIB_DISPLAY_DOWNLOAD,
//                                    resource2LIB_DISPLAY_OPEN,
//                                    resource2LIB_DISPLAY_POPUP,
//                                   );

//     //--- general settings -----------------------------------------------------------------------------------
//     $settings->add(new admin_setting_configtext('resource2/framesize',
//         get_string('framesize', 'resource2'), get_string('configframesize', 'resource2'), 130, PARAM_INT));
//     $settings->add(new admin_setting_configmultiselect('resource2/displayoptions',
//         get_string('displayoptions', 'resource2'), get_string('configdisplayoptions', 'resource2'),
//         $defaultdisplayoptions, $displayoptions));

//     //--- modedit defaults -----------------------------------------------------------------------------------
//     $settings->add(new admin_setting_heading('resource2modeditdefaults', get_string('modeditdefaults', 'admin'), get_string('condifmodeditdefaults', 'admin')));

//     $settings->add(new admin_setting_configcheckbox('resource2/printintro',
//         get_string('printintro', 'resource2'), get_string('printintroexplain', 'resource2'), 1));
//     $settings->add(new admin_setting_configselect('resource2/display',
//         get_string('displayselect', 'resource2'), get_string('displayselectexplain', 'resource2'), resource2LIB_DISPLAY_AUTO,
//         $displayoptions));
//     $settings->add(new admin_setting_configcheckbox('resource2/showsize',
//         get_string('showsize', 'resource2'), get_string('showsize_desc', 'resource2'), 0));
//     $settings->add(new admin_setting_configcheckbox('resource2/showtype',
//         get_string('showtype', 'resource2'), get_string('showtype_desc', 'resource2'), 0));
//     $settings->add(new admin_setting_configcheckbox('resource2/showdate',
//         get_string('showdate', 'resource2'), get_string('showdate_desc', 'resource2'), 0));
//     $settings->add(new admin_setting_configtext('resource2/popupwidth',
//         get_string('popupwidth', 'resource2'), get_string('popupwidthexplain', 'resource2'), 620, PARAM_INT, 7));
//     $settings->add(new admin_setting_configtext('resource2/popupheight',
//         get_string('popupheight', 'resource2'), get_string('popupheightexplain', 'resource2'), 450, PARAM_INT, 7));
//     $options = array('0' => get_string('none'), '1' => get_string('allfiles'), '2' => get_string('htmlfilesonly'));
//     $settings->add(new admin_setting_configselect('resource2/filterfiles',
//         get_string('filterfiles', 'resource2'), get_string('filterfilesexplain', 'resource2'), 0, $options));
// }
