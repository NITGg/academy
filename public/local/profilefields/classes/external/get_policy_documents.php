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

namespace local_profilefields\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\util;
use local_profilefields\policies;

defined('MOODLE_INTERNAL') || die();

/**
 * The text of the policy documents shown on sign-up.
 *
 * The web form links to `/admin/tool/policy/view.php`, which is a page; a client
 * that draws its own screens wants the words, not a page to open. Core's
 * `tool_policy_get_policy_version` returns one version at a time and is not part
 * of the mobile service, so a pre-login client cannot reach it over REST without
 * an admin adding it to a service by hand. This returns all the sign-up
 * documents at once, from the same plugin the rest of the sign-up API lives in.
 *
 * The content is whatever an admin wrote in tool_policy - it is HTML, and it is
 * the same text the web page shows.
 *
 * Pre-login by design (`loginrequired => false` in db/services.php).
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_policy_documents extends external_api {

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'versionid' => new external_value(PARAM_INT,
                'Return only this policy version. 0 (the default) returns every document shown on sign-up.',
                VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * The sign-up policy documents, with their text.
     *
     * @param int $versionid a single version to return, or 0 for all of them
     * @return array
     */
    public static function execute($versionid = 0): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['versionid' => $versionid]);

        // format_text() rewrites the pluginfile URLs inside the document against a
        // context, so the page needs one before we touch the content.
        $context = context_system::instance();
        $PAGE->set_context($context);

        $documents = [];
        foreach (policies::signup_document_records() as $doc) {
            if ($params['versionid'] && $doc->versionid !== $params['versionid']) {
                continue;
            }

            [$content, $contentformat] = util::format_text(
                $doc->version->content,
                $doc->version->contentformat,
                $context,
                'tool_policy',
                'policydocumentcontent',
                $doc->versionid
            );

            $documents[] = [
                'policyid'      => $doc->policyid,
                'versionid'     => $doc->versionid,
                'name'          => $doc->name,
                'url'           => $doc->url,
                'content'       => $content,
                'contentformat' => (int) $contentformat,
            ];
        }

        return ['documents' => $documents, 'warnings' => []];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'documents' => new external_multiple_structure(
                new external_single_structure([
                    'policyid' => new external_value(PARAM_INT, 'Policy id'),
                    'versionid' => new external_value(PARAM_INT, 'Policy version id'),
                    'name' => new external_value(PARAM_RAW, 'Document name, as the title of the screen'),
                    'url' => new external_value(PARAM_URL, 'The same document as a web page, if you prefer to open it'),
                    'content' => new external_value(PARAM_RAW, 'The document text (HTML), ready to render'),
                    'contentformat' => new external_value(PARAM_INT, 'Moodle text format of content (1 = HTML)'),
                ]), 'The policy documents shown on sign-up.'
            ),
            'warnings' => new \core_external\external_warnings(),
        ]);
    }
}
