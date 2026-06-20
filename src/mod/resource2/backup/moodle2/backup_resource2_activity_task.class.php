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
 * Defines backup_resource2_activity_task class
 *
 * @package     mod_resource2
 * @category    backup
 * @copyright   2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/resource2/backup/moodle2/backup_resource2_stepslib.php');

/**
 * Provides the steps to perform one complete backup of the resource2 instance
 */
class backup_resource2_activity_task extends backup_activity_task {

    /**
     * @param bool $resource2oldexists True if there are records in the resource2_old table.
     */
    protected static $resource2oldexists = null;

    /**
     * No specific settings for this activity
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the resource2.xml file
     */
    protected function define_my_steps() {
        $this->add_step(new backup_resource2_activity_structure_step('resource2_structure', 'resource2.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts
     *
     * @param string $content some HTML text that eventually contains URLs to the activity instance scripts
     * @return string the content with the URLs encoded
     */
    static public function encode_content_links($content) {
        global $CFG, $DB;

        $base = preg_quote($CFG->wwwroot,"/");

        // Link to the list of resource2s.
        $search="/(".$base."\/mod\/resource2\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@resource2INDEX*$2@$', $content);

        // Link to resource2 view by moduleid.
        $search = "/(".$base."\/mod\/resource2\/view.php\?id\=)([0-9]+)/";
        // Link to resource2 view by recordid
        $search2 = "/(".$base."\/mod\/resource2\/view.php\?r\=)([0-9]+)/";

        // Check whether there are contents in the resource2 old table.
        if (static::$resource2oldexists === null) {
            static::$resource2oldexists = $DB->record_exists('resource2_old', array());
        }

        // If there are links to items in the resource2_old table, rewrite them to be links to the correct URL
        // for their new module.
        if (static::$resource2oldexists) {
            // Match all of the resource2s.
            $result = preg_match_all($search, $content, $matches, PREG_PATTERN_ORDER);

            // Course module ID resource2 links.
            if ($result) {
                list($insql, $params) = $DB->get_in_or_equal($matches[2]);
                $oldrecs = $DB->get_records_select('resource2_old', "cmid $insql", $params, '', 'cmid, newmodule');

                for ($i = 0; $i < count($matches[0]); $i++) {
                    $cmid = $matches[2][$i];
                    if (isset($oldrecs[$cmid])) {
                        // resource2_old item, rewrite it
                        $replace = '$@' . strtoupper($oldrecs[$cmid]->newmodule) . 'VIEWBYID*' . $cmid . '@$';
                    } else {
                        // Not in the resource2 old table, don't rewrite
                        $replace = '$@resource2VIEWBYID*'.$cmid.'@$';
                    }
                    $content = str_replace($matches[0][$i], $replace, $content);
                }
            }

            $matches = null;
            $result = preg_match_all($search2, $content, $matches, PREG_PATTERN_ORDER);

            // No resource2 links.
            if (!$result) {
                return $content;
            }
            // resource2 ID links.
            list($insql, $params) = $DB->get_in_or_equal($matches[2]);
            $oldrecs = $DB->get_records_select('resource2_old', "oldid $insql", $params, '', 'oldid, cmid, newmodule');

            for ($i = 0; $i < count($matches[0]); $i++) {
                $recordid = $matches[2][$i];
                if (isset($oldrecs[$recordid])) {
                    // resource2_old item, rewrite it
                    $replace = '$@' . strtoupper($oldrecs[$recordid]->newmodule) . 'VIEWBYID*' . $oldrecs[$recordid]->cmid . '@$';
                    $content = str_replace($matches[0][$i], $replace, $content);
                }
            }
        } else {
            $content = preg_replace($search, '$@resource2VIEWBYID*$2@$', $content);
        }
        return $content;
    }
}
