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
 * NIT override of the mod_customcert renderer.
 *
 * It exists for one line: the certificate verification page must name the same
 * person the certificate itself names.
 *
 * AC-4.5.1 freezes the holder's name at the moment of issue, and the PDF honours
 * that - customcertelement_nitstudentname prints the name local_academy captured
 * (see \local_academy\certificate_names). The verification page did not:
 * \mod_customcert\output\verify_certificate_result::__construct() calls
 * fullname() on the live {user} row joined in verify_certificate.php. So after a
 * rename the downloaded certificate said one name and the page that is supposed
 * to prove it said another - the exact discrepancy that makes an employer
 * checking a code think the document is forged.
 *
 * Overriding the renderer from the theme is the extension point Moodle offers
 * here. mod_customcert is third-party code we do not edit (same reason
 * nitstudentname is a subplugin rather than a patch), and the name cannot be
 * fixed in a template override because it is computed in PHP - the mustache only
 * prints {{userfullname}}. theme_nit already runs the
 * theme_overridden_renderer_factory (config.php), which picks up this class for
 * $PAGE->get_renderer('mod_customcert').
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_nit\output;

use mod_customcert\output\renderer as customcert_renderer;
use mod_customcert\output\verify_certificate_results;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders mod_customcert output, with the verification page corrected.
 *
 * Everything except render_verify_certificate_results() is inherited unchanged.
 */
class mod_customcert_renderer extends customcert_renderer {

    /**
     * Renders the verify certificate results, naming each holder as their
     * certificate names them.
     *
     * The renderable exposes the raw issue rows it was built from ($page->issues,
     * straight out of verify_certificate.php's query, each carrying ci.id), and
     * export_for_template() walks that same array in order - so the exported
     * entry at index N belongs to the issue at index N. That pairing is what lets
     * the captured name be looked up per row; the exported data alone carries no
     * issue id.
     *
     * A missing snapshot leaves the live name in place. That is the stock
     * behaviour and the right fallback: an issue from before the feature existed,
     * or a site running this theme without local_academy, should still verify
     * normally rather than show a blank name.
     *
     * @param verify_certificate_results $page the verification result
     * @return string html for the page
     */
    public function render_verify_certificate_results(verify_certificate_results $page): string {
        $data = $page->export_for_template($this);

        if (class_exists('\local_academy\certificate_names')) {
            $rows = array_values($page->issues);

            foreach ($data->issues as $i => $exported) {
                if (!isset($rows[$i]->id)) {
                    continue;
                }

                $captured = \local_academy\certificate_names::for_issue((int) $rows[$i]->id);

                if ($captured !== null) {
                    $exported->userfullname = $captured;
                }
            }
        }

        return $this->render_from_template('mod_customcert/verify_certificate_results', $data);
    }
}
