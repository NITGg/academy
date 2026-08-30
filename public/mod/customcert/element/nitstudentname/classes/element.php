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
 * Student name as it was at the time of issue.
 *
 * @package    customcertelement_nitstudentname
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace customcertelement_nitstudentname;

use mod_customcert\element as base_element;
use mod_customcert\element\renderable_element_interface;
use mod_customcert\element\persistable_element_interface;
use mod_customcert\element\form_element_interface;
use mod_customcert\element\validatable_element_interface;
use mod_customcert\element_helper;
use mod_customcert\service\element_renderer;
use MoodleQuickForm;
use pdf;
use stdClass;

/**
 * Prints the name the holder had when the certificate was issued.
 *
 * The stock 'studentname' element prints fullname() from the live user record,
 * and mod_customcert rebuilds the PDF on every single download - so renaming a
 * profile silently reprints every certificate that person already holds, which
 * AC-4.5.1 forbids. This element prints the name local_academy captured at the
 * moment of issue instead.
 *
 * It exists as a separate element rather than as a patch to 'studentname'
 * because mod_customcert is third-party code we do not edit, and its element
 * registry (see mod_customcert\element\element_bootstrap) hard-codes the bundled
 * types and explicitly skips any discovered plugin whose type is already
 * registered - so the stock element cannot be overridden in place. A new
 * subplugin is the extension point the plugin actually offers, and existing
 * certificates are converted to it by db/install.php.
 *
 * Everything except the source of the name is inherited unchanged, so it
 * positions, styles and validates exactly like the element it replaces.
 */
class element extends base_element implements
    form_element_interface,
    persistable_element_interface,
    renderable_element_interface,
    validatable_element_interface
{
    /**
     * Build the configuration form for this element.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function build_form(MoodleQuickForm $mform): void {
        element_helper::render_common_form_elements($mform, $this->showposxy);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param stdClass $user the user we are rendering this for
     * @param element_renderer|null $renderer the renderer service
     */
    public function render(pdf $pdf, bool $preview, stdClass $user, ?element_renderer $renderer = null): void {
        $name = $this->name_for($preview, $user);

        if ($renderer) {
            $renderer->render_content($this, $name);
        } else {
            element_helper::render_content($pdf, $this, $name);
        }
    }

    /**
     * Render the element in html.
     *
     * Used by the drag-and-drop positioning interface, which is always the
     * administrator looking at a template rather than anyone's real
     * certificate - so the live name is the right thing to draw here.
     *
     * @param element_renderer|null $renderer the renderer service
     * @return string the html
     */
    public function render_html(?element_renderer $renderer = null): string {
        global $USER;

        if ($renderer) {
            return (string) $renderer->render_content($this, fullname($USER));
        }

        return element_helper::render_html_content($this, fullname($USER));
    }

    /**
     * The name to print.
     *
     * Falls back to the live name whenever there is no captured one: a preview
     * (no certificate has been issued to have a name from), a certificate issued
     * before this feature existed that somehow missed the backfill, or a site
     * running this element without local_academy. The fallback is the stock
     * behaviour, so the failure mode is "as before", never a blank certificate.
     *
     * @param bool $preview true when drawing a template preview
     * @param stdClass $user the user the certificate is for
     * @return string
     */
    private function name_for(bool $preview, stdClass $user): string {
        if ($preview || !class_exists('\local_academy\certificate_names')) {
            return fullname($user);
        }

        $issueid = \local_academy\certificate_names::issue_for_page(
            $this->get_pageid(),
            (int) ($user->id ?? 0)
        );

        return \local_academy\certificate_names::for_issue($issueid) ?? fullname($user);
    }

    /**
     * No unique data is persisted for this element.
     *
     * @param stdClass $formdata
     * @return array
     */
    public function normalise_data(stdClass $formdata): array {
        return [
            'font' => (string)($formdata->font ?? ''),
            'fontsize' => (int)($formdata->fontsize ?? 0),
            'colour' => (string)($formdata->colour ?? ''),
            'width' => (int)($formdata->width ?? 0),
        ];
    }

    /**
     * Validate submitted form data for this element.
     * Core validations are handled by validation_service; no extra rules here.
     *
     * @param array $data
     * @return array<string,string>
     */
    public function validate(array $data): array {
        return [];
    }
}
