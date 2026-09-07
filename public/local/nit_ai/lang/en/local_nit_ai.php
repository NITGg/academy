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
 * Strings for local_nit_ai.
 *
 * @package    local_nit_ai
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI video assistant';

// Capabilities.
$string['nit_ai:use'] = 'Ask the video assistant questions';
$string['nit_ai:manage'] = 'Upload and approve video transcripts';

// Activity form.
$string['formheader'] = 'AI assistant';
$string['enabled'] = 'Enable the AI assistant for this video';
$string['enabled_help'] = 'Students get a chat panel beside the player and can ask about the lesson. It only appears once a transcript has been uploaded and approved.';
$string['transcriptfile'] = 'Transcript file';
$string['transcriptfile_help'] = 'A .vtt, .srt, .json or .txt transcript of this video. Timestamps are strongly preferred: with them the assistant knows where the student is and can link back to the moment something was explained. Uploading a new file resets the approval.';
$string['hasonscreen'] = 'The transcript includes on-screen text (code, slides)';
$string['hasonscreen_help'] = 'Tick this if the transcript covers what is written on screen and not just what is said. If it is speech only, the assistant tells students it can hear the lesson but not see it, instead of guessing.';

// Review panel.
$string['reviewtitle'] = 'AI assistant — transcript review';
$string['reviewintro'] = 'This is what we read from the file. Check it, then approve.';
$string['detectedformat'] = 'Format';
$string['detectedsegments'] = 'Segments';
$string['detectedtimestamps'] = 'Timestamps';
$string['detectedlanguage'] = 'Language';
$string['detectedlast'] = 'Last timestamp';
$string['videolength'] = 'Video length';
$string['lengthunknown'] = 'not reported yet';
$string['lengthpassed'] = 'Transcript covers the whole video';
$string['lengthfailed'] = 'Transcript does not match the video length';
$string['lengthskipped'] = 'Length check skipped';
$string['approve'] = 'Approve and enable';
$string['approved'] = 'Approved — students can use the assistant';
$string['notapproved'] = 'Waiting for your approval — students cannot see the assistant yet';
$string['approvedblocked'] = 'Approved, but the assistant is still not running — see what is blocking it below.';
$string['notenabled'] = 'The assistant is switched off for this activity in its settings.';
$string['notranscript'] = 'No transcript uploaded yet. Add one in the activity settings to switch the assistant on.';
$string['problemsfound'] = 'Check these before approving';
$string['langar'] = 'Arabic';
$string['langen'] = 'English';
$string['langmixed'] = 'Arabic and English';
$string['langunknown'] = 'Could not tell';

// Checks.
$string['check_stale'] = 'The video has been replaced since this transcript was uploaded. The assistant is switched off until a matching transcript is uploaded.';
$string['check_notimestamps'] = 'No timestamps were found. The assistant will still answer questions, but it cannot tell where the student is or link back to a moment in the video.';
$string['check_empty'] = 'No readable text was found in the file.';
$string['check_noprovider'] = 'No AI provider is set up on this site yet, so the assistant cannot answer. An administrator needs to configure one under Site administration > AI.';
$string['check_lengthmismatch'] = 'The transcript ends at {$a->transcript} but the video runs {$a->video}. This usually means the transcript is truncated, or belongs to a different video.';

// Chat.
$string['chattitle'] = 'Ask about this lesson';
$string['chatintro'] = 'Ask anything about this video. I answer from what is actually said in it.';
$string['chatplaceholder'] = 'Ask a question…';
$string['chatsend'] = 'Send';
$string['chatthinking'] = 'Thinking…';
$string['chatopen'] = 'Open the assistant';
$string['chatclose'] = 'Close the assistant';
$string['chatdisclaimer'] = 'AI generated. This conversation is not saved — it starts fresh each time you open the page.';
$string['jumpto'] = 'Jump to {$a}';

// Errors.
$string['err_emptyquestion'] = 'Type a question first.';
$string['err_aifailed'] = 'The assistant could not answer right now. Please try again.';
$string['err_unavailable'] = 'The assistant is not available for this video.';

$string['privacy:metadata'] = 'The AI video assistant does not store conversations. Questions are sent to the site\'s configured AI provider to be answered and are not kept afterwards.';
