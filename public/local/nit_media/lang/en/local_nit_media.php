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
 * English strings for local_nit_media.
 *
 * Admin setting descriptions are rendered as MARKDOWN, so these are written in
 * markdown, not HTML, and no continuation line may be indented — four leading
 * spaces would turn the paragraph into a code block. Paragraph breaks are a
 * blank line ("\n\n"), and the {$a} placeholder is kept in single quotes so PHP
 * does not try to interpolate it.
 *
 * @package    local_nit_media
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Site media';

$string['herovideoheading'] = 'Home page hero video';

$string['herovideoheading_desc'] =
    'The video that plays when a visitor clicks "See how it works" in the hero section of the front page.'
    . "\n\n"
    . 'The hero is a plain HTML block, so it cannot know the file name. It always points at one fixed '
    . 'address and this page decides what that address serves: {$a}'
    . "\n\n"
    . 'Upload a file to publish it, delete it to take the video down, or do both to replace it. '
    . 'The block needs no edit either way.';

$string['herovideo'] = 'Video file';

$string['herovideo_desc'] =
    'Use an **MP4 encoded with H.264 video and AAC audio**. That is the combination every browser can '
    . 'play. A .mov file, or an MP4 encoded with H.265/HEVC, is accepted by the upload but will not play '
    . 'in Chrome - the player reports that the format is not supported.'
    . "\n\n"
    . 'Encode with `-movflags +faststart` so playback can begin before the whole file has downloaded.'
    . "\n\n"
    . 'The file is streamed by PHP rather than a CDN, so keep it short. For a long clip, host it on '
    . 'YouTube or Vimeo and embed that in the block instead.';

$string['privacy:metadata'] =
    'The Site media plugin stores only files uploaded by an administrator for display on the site. '
    . 'It stores no personal data.';

$string['diagnostics'] = 'Hero video diagnostics';

$string['diagnostics_desc'] =
    'Not sure whether the file or the encoding is at fault? Open '
    . '{$a} '
    . 'as a site administrator. It reports what the server holds - file name, size and MIME type - '
    . 'without downloading the video.';
