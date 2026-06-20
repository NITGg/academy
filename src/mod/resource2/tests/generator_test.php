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

/**
 * PHPUnit data generator testcase.
 *
 * @package    mod_resource2
 * @category phpunit
 * @copyright 2013 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator_test extends \advanced_testcase {
    public function test_generator() {
        global $DB, $SITE;

        $this->resetAfterTest(true);

        // Must be a non-guest user to create resource2s.
        $this->setAdminUser();

        // There are 0 resource2s initially.
        $this->assertEquals(0, $DB->count_records('resource2'));

        // Create the generator object and do standard checks.
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_resource2');
        $this->assertInstanceOf('mod_resource2_generator', $generator);
        $this->assertEquals('resource2', $generator->get_modulename());

        // Create three instances in the site course.
        $generator->create_instance(array('course' => $SITE->id));
        $generator->create_instance(array('course' => $SITE->id));
        $resource2 = $generator->create_instance(array('course' => $SITE->id));
        $this->assertEquals(3, $DB->count_records('resource2'));

        // Check the course-module is correct.
        $cm = get_coursemodule_from_instance('resource2', $resource2->id);
        $this->assertEquals($resource2->id, $cm->instance);
        $this->assertEquals('resource2', $cm->modname);
        $this->assertEquals($SITE->id, $cm->course);

        // Check the context is correct.
        $context = \context_module::instance($cm->id);
        $this->assertEquals($resource2->cmid, $context->instanceid);

        // Check that generated resource2 module contains a file.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource2', 'content', false, '', false);
        $file = array_values($files)[0];
        $this->assertCount(1, $files);
        $this->assertEquals('resource23.txt', $file->get_filename());
        $this->assertEquals('Test resource2 resource23.txt file', $file->get_content());

        // Create a new resource2 specifying the file name.
        $resource2 = $generator->create_instance(['course' => $SITE->id, 'defaultfilename' => 'myfile.pdf']);

        // Check that generated resource2 module contains a file with the specified name.
        $cm = get_coursemodule_from_instance('resource2', $resource2->id);
        $context = \context_module::instance($cm->id);
        $files = $fs->get_area_files($context->id, 'mod_resource2', 'content', false, '', false);
        $file = array_values($files)[0];
        $this->assertCount(1, $files);
        $this->assertEquals('myfile.pdf', $file->get_filename());
        $this->assertEquals('Test resource2 myfile.pdf file', $file->get_content());
    }
}
