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
 * Upgrade steps for block_nit_section.
 *
 * @package    block_nit_section
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade block_nit_section.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_nit_section_upgrade($oldversion) {
    if ($oldversion < 2026102700) {
        // Home-page sections were pasted with links and images under the old
        // .../moodle-new address, which answers 404 now the site lives at the
        // domain root. Make every link to this site wwwroot-relative, so the
        // content keeps working whatever the domain is.
        block_nit_section_relativise_site_links();
        upgrade_block_savepoint(true, 2026102700, 'nit_section');
    }

    return true;
}

/**
 * Turn links to this site, under any address it has had, into wwwroot-relative ones.
 *
 * `https://academy2026.nitg-eg.com/moodle-new/theme/x.png`, `/moodle-new/theme/x.png` and
 * `{wwwroot}/theme/x.png` all become `/theme/x.png`. A bare address becomes "/" in
 * href/src/action and empty elsewhere (data-base="" is how the home blocks say
 * "use this site"). Links to anywhere else are left exactly as written.
 */
function block_nit_section_relativise_site_links(): void {
    global $CFG, $DB;

    $boundary = '(?=[\/?#"\'\s<>)]|$)';
    $roots = ['//academy2026.nitg-eg.com/moodle-new', '//eaac-learning.com/moodle-new',
        preg_replace('~^https?:~', '', rtrim($CFG->wwwroot, '/'))];
    $patterns = [];
    $replacements = [];
    foreach (array_unique($roots) as $root) {
        // A link that is only the address itself points home, not nowhere.
        $patterns[] = '~(\b(?:href|src|action)\s*=\s*["\'])(?:https?:)?' . preg_quote($root, '~') . '/?(?=["\'])~i';
        $replacements[] = '$1/';
        $patterns[] = '~(?:https?:)?' . preg_quote($root, '~') . $boundary . '~i';
        $replacements[] = '';
    }
    // Root-relative links that still carry the old sub-directory.
    $patterns[] = '~(?<=[="\'(])/moodle-new(?=[\/?#"\')])~i';
    $replacements[] = '';

    $instances = $DB->get_recordset('block_instances', ['blockname' => 'nit_section']);
    foreach ($instances as $instance) {
        $config = unserialize_object(base64_decode($instance->configdata));
        $changed = false;
        foreach (['htmltext', 'visualtext', 'text'] as $field) {
            if (!isset($config->$field) || !is_string($config->$field)) {
                continue;
            }
            $new = preg_replace($patterns, $replacements, $config->$field);
            if ($new !== null && $new !== $config->$field) {
                $config->$field = $new;
                $changed = true;
            }
        }
        if ($changed) {
            $DB->update_record('block_instances', ['id' => $instance->id,
                'configdata' => base64_encode(serialize($config)), 'timemodified' => time()]);
        }
    }
    $instances->close();
}
