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

namespace mod_resource2;

use externallib_advanced_testcase;
use mod_resource2_external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External mod_resource2 functions unit tests
 *
 * @package    mod_resource2
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class externallib_test extends externallib_advanced_testcase {

    /**
     * Test view_resource2
     */
    public function test_view_resource2() {
        global $DB;

        $this->resetAfterTest(true);

        $this->setAdminUser();
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $resource2 = $this->getDataGenerator()->create_module('resource2', array('course' => $course->id));
        $context = \context_module::instance($resource2->cmid);
        $cm = get_coursemodule_from_instance('resource2', $resource2->id);

        // Test invalid instance id.
        try {
            mod_resource2_external::view_resource2(0);
            $this->fail('Exception expected due to invalid mod_resource2 instance id.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_resource2_external::view_resource2($resource2->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_resource2_external::view_resource2($resource2->id);
        $result = \external_api::clean_returnvalue(mod_resource2_external::view_resource2_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_resource2\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/resource2/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/resource2:view', CAP_PROHIBIT, $studentrole->id, $context->id);
        // Empty all the caches that may be affected by this change.
        accesslib_clear_all_caches_for_unit_testing();
        \course_modinfo::clear_instance_cache();

        try {
            mod_resource2_external::view_resource2($resource2->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

    }

    /**
     * Test test_mod_resource2_get_resource2s_by_courses
     */
    public function test_mod_resource2_get_resource2s_by_courses() {
        global $DB;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        $student = self::getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($student->id, $course1->id, $studentrole->id);

        self::setUser($student);

        // First resource2.
        $record = new \stdClass();
        $record->course = $course1->id;
        $resource21 = self::getDataGenerator()->create_module('resource2', $record);

        // Second resource2.
        $record = new \stdClass();
        $record->course = $course2->id;
        $resource22 = self::getDataGenerator()->create_module('resource2', $record);

        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $student->id, $studentrole->id);

        $returndescription = mod_resource2_external::get_resource2s_by_courses_returns();

        // Create what we expect to be returned when querying the two courses.
        $expectedfields = array('id', 'coursemodule', 'course', 'name', 'intro', 'introformat', 'introfiles',
                                'contentfiles', 'tobemigrated', 'legacyfiles', 'legacyfileslast', 'display', 'displayoptions',
                                'filterfiles', 'revision', 'timemodified', 'section', 'visible', 'groupmode', 'groupingid');

        // Add expected coursemodule and data.
        $resource21->coursemodule = $resource21->cmid;
        $resource21->introformat = 1;
        $resource21->contentformat = 1;
        $resource21->section = 0;
        $resource21->visible = true;
        $resource21->groupmode = 0;
        $resource21->groupingid = 0;
        $resource21->introfiles = [];
        $resource21->contentfiles = [];

        $resource22->coursemodule = $resource22->cmid;
        $resource22->introformat = 1;
        $resource22->contentformat = 1;
        $resource22->section = 0;
        $resource22->visible = true;
        $resource22->groupmode = 0;
        $resource22->groupingid = 0;
        $resource22->introfiles = [];
        $resource22->contentfiles = [];

        foreach ($expectedfields as $field) {
            $expected1[$field] = $resource21->{$field};
            $expected2[$field] = $resource22->{$field};
        }

        $expectedresource2s = array($expected2, $expected1);

        // Call the external function passing course ids.
        $result = mod_resource2_external::get_resource2s_by_courses(array($course2->id, $course1->id));
        $result = \external_api::clean_returnvalue($returndescription, $result);

        // Remove the contentfiles (to be checked bellow).
        $result['resource2s'][0]['contentfiles'] = [];
        $result['resource2s'][1]['contentfiles'] = [];

        // Now, check that we retrieve the same data we created.
        $this->assertEquals($expectedresource2s, $result['resource2s']);
        $this->assertCount(0, $result['warnings']);

        // Call the external function without passing course id.
        $result = mod_resource2_external::get_resource2s_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);

        // Remove the contentfiles (to be checked bellow).
        $result['resource2s'][0]['contentfiles'] = [];
        $result['resource2s'][1]['contentfiles'] = [];

        // Check that without course ids we still get the correct data.
        $this->assertEquals($expectedresource2s, $result['resource2s']);
        $this->assertCount(0, $result['warnings']);

        // Add a file to the intro.
        $fileintroname = "fileintro.txt";
        $filerecordinline = array(
            'contextid' => \context_module::instance($resource22->cmid)->id,
            'component' => 'mod_resource2',
            'filearea'  => 'intro',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $fileintroname,
        );
        $fs = get_file_storage();
        $timepost = time();
        $fs->create_file_from_string($filerecordinline, 'image contents (not really)');

        $result = mod_resource2_external::get_resource2s_by_courses(array($course2->id, $course1->id));
        $result = \external_api::clean_returnvalue($returndescription, $result);

        // Check that we receive correctly the files.
        $this->assertCount(1, $result['resource2s'][0]['introfiles']);
        $this->assertEquals($fileintroname, $result['resource2s'][0]['introfiles'][0]['filename']);
        $this->assertCount(1, $result['resource2s'][0]['contentfiles']);
        $this->assertCount(1, $result['resource2s'][1]['contentfiles']);
        // Test autogenerated resource2.
        $this->assertEquals('resource22.txt', $result['resource2s'][0]['contentfiles'][0]['filename']);
        $this->assertEquals('resource21.txt', $result['resource2s'][1]['contentfiles'][0]['filename']);

        // Unenrol user from second course.
        $enrol->unenrol_user($instance2, $student->id);
        array_shift($expectedresource2s);

        // Call the external function without passing course id.
        $result = mod_resource2_external::get_resource2s_by_courses();
        $result = \external_api::clean_returnvalue($returndescription, $result);

        // Remove the contentfiles (to be checked bellow).
        $result['resource2s'][0]['contentfiles'] = [];
        $this->assertEquals($expectedresource2s, $result['resource2s']);

        // Call for the second course we unenrolled the user from, expected warning.
        $result = mod_resource2_external::get_resource2s_by_courses(array($course2->id));
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('1', $result['warnings'][0]['warningcode']);
        $this->assertEquals($course2->id, $result['warnings'][0]['itemid']);
    }
}
