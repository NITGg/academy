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
 * English strings for local_nit_category.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'NIT Categories';

$string['categoryimage'] = 'Category image';
$string['categoryimage_help'] = 'The picture shown for this category on the category page. One web image (JPG, PNG, GIF, SVG, WebP).

If you leave this empty, the first image found inside the category Description is used instead, then the image of the nearest parent category, and finally the site logo.';
$string['currentimage'] = 'Currently shown';
$string['imagesaved'] = 'Category image saved.';
$string['fallbackinfo'] = 'Moodle categories have no picture of their own, so this page adds one. The category page picks the first available of: this uploaded image, the first image inside the category Description, the nearest parent category\'s image, the site logo.';
$string['sourceuploaded'] = 'Source: uploaded on this page.';
$string['sourcedescription'] = 'Source: the first image inside this category\'s Description. Upload a file below to override it.';
$string['sourceinherited'] = 'Source: inherited from a parent category. Upload a file below to give this category its own image.';
$string['sourcelogo'] = 'Source: the site logo (this category has no image of its own).';

$string['privacy:metadata'] = 'The NIT Categories plugin stores category images only. It stores no personal data.';
