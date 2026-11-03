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
 * Strings for local_nit_tts.
 *
 * @package    local_nit_tts
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Text to speech';
$string['settings'] = 'Text to speech settings';
$string['privacy:metadata'] = 'The text-to-speech page stores no personal data. Text typed on it is sent to Google Gemini to be spoken and is not kept.';

// Settings.
$string['setting_apikey'] = 'Gemini API key';
$string['setting_apikey_desc'] = 'Key from Google AI Studio used to call the Gemini text-to-speech model. Leave empty to use the key of the Gemini provider under Site administration > AI > AI providers.';
$string['setting_model'] = 'Gemini TTS model';
$string['setting_model_desc'] = 'Name of the Gemini model that speaks the text, e.g. gemini-3.8-flash-tts, gemini-3.8-flash-lite-tts or gemini-2.5-flash-preview-tts. Each model has its own daily quota on the free tier.';
$string['setting_public'] = 'Open to everyone';
$string['setting_public_desc'] = 'Let anyone use the page at /local/nit_tts/index.php, including guests and visitors who are not logged in. Every conversion uses the site\'s Gemini quota. Untick to make it an admin-only page again.';

// Page.
$string['text'] = 'Text';
$string['voice'] = 'Voice';
$string['voice_female'] = 'female';
$string['voice_male'] = 'male';
$string['style'] = 'Style instructions';
$string['style_help'] = 'Optional. Tells the voice how to read, e.g. "Read calmly in Modern Standard Arabic" or "Say it cheerfully in Egyptian dialect". Leave empty for a neutral reading.';
$string['convert'] = 'Convert to speech';
$string['converting'] = 'Converting…';
$string['download'] = 'Download audio';

// Errors.
$string['err_nokey'] = 'No Gemini API key is set for text to speech.';
$string['err_nokey_link'] = 'No Gemini API key is set yet. <a href="{$a}">Add it in the settings</a>.';
$string['err_api'] = 'Gemini could not convert the text: {$a}';
$string['err_toolong'] = 'The text is too long. The limit is {$a} characters.';
