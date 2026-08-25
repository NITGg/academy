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

namespace local_profilefields;

use moodle_url;

defined('MOODLE_INTERNAL') || die();

/**
 * Bridges the tool_policy documents into a single sign-up consent checkbox.
 *
 * tool_policy is deliberately built around a separate acceptance page shown before
 * the sign-up form (its own `signup_form()` only adds a hidden `policyagreed`). The
 * academy wants one checkbox on the form itself with the documents linked from it -
 * the pattern common to most sites. This class reads the documents tool_policy holds
 * and turns them into the links for that checkbox; the documents themselves stay
 * authored and versioned in tool_policy.
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class policies {

    /**
     * Whether the tool_policy plugin is installed.
     *
     * @return bool
     */
    public static function tool_available(): bool {
        return \core_component::get_component_directory('tool_policy') !== null;
    }

    /**
     * The policy documents a person signing up should see.
     *
     * Only current versions aimed at guests (or everyone) are relevant to sign-up.
     * Returns an empty array when tool_policy is absent or defines no such document.
     *
     * Each entry carries the identifiers as well as the link, because a client that
     * renders the document itself (a mobile app with no browser view) needs the
     * version id, not a URL to open.
     *
     * @return stdClass[] objects with policyid, versionid, name, url and the raw
     *                    tool_policy version record
     */
    public static function signup_document_records(): array {
        if (!self::tool_available()) {
            return [];
        }

        try {
            $versions = \tool_policy\api::list_current_versions(\tool_policy\policy_version::AUDIENCE_GUESTS);
        } catch (\Throwable $e) {
            return [];
        }

        $docs = [];
        foreach ($versions as $version) {
            $url = new moodle_url('/admin/tool/policy/view.php', [
                'policyid' => $version->policyid,
                'versionid' => $version->id,
            ]);
            $docs[] = (object) [
                'policyid'  => (int) $version->policyid,
                'versionid' => (int) $version->id,
                'name'      => format_string($version->name),
                'url'       => $url->out(false),
                'version'   => $version,
            ];
        }

        return $docs;
    }

    /**
     * The same documents as name => view URL, for building the checkbox label.
     *
     * @return array<string,string> document name => absolute URL
     */
    public static function signup_documents(): array {
        $docs = [];
        foreach (self::signup_document_records() as $doc) {
            $docs[$doc->name] = $doc->url;
        }
        return $docs;
    }

    /**
     * The label for the consent checkbox, with the policy names as links.
     *
     * Falls back to plain wording when no documents are configured yet, so the
     * checkbox still makes sense while an admin is still writing the policies.
     *
     * @return string HTML
     */
    public static function consent_label(): string {
        $docs = self::signup_documents();

        if (empty($docs)) {
            return get_string('consentlabelplain', 'local_profilefields');
        }

        $links = [];
        foreach ($docs as $name => $url) {
            $links[] = \html_writer::link($url, s($name), ['target' => '_blank', 'rel' => 'noopener']);
        }

        // "I agree to X and Y" - a localisable list separator and connector.
        $last = array_pop($links);
        if (empty($links)) {
            $list = $last;
        } else {
            $list = implode(get_string('listsep', 'langconfig') . ' ', $links)
                . ' ' . get_string('and', 'local_profilefields') . ' ' . $last;
        }

        return get_string('consentlabel', 'local_profilefields', $list);
    }
}
