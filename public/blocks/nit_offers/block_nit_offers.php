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
 * The NIT offers announcement bar.
 *
 * @package    block_nit_offers
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * A slim bar advertising the offers the site is currently running.
 *
 * The bar reads local_nit_commerce at render time rather than storing its own copy of
 * an offer, so starting, ending or deactivating an offer changes what visitors see
 * without anyone editing the block. A "custom message" mode is kept for announcements
 * that are not offers at all.
 */
class block_nit_offers extends block_base {

    /** @var int How many offers the bar cycles through unless configured otherwise. */
    const DEFAULT_MAX_OFFERS = 3;

    /**
     * Set the block's default title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_nit_offers');
    }

    /**
     * No site-wide settings: everything is per bar.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * The bar is an announcement — it belongs anywhere a page can carry one.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * Different pages can carry different announcements.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * Apply the configured title.
     */
    public function specialization() {
        $title = isset($this->config->blocktitle) ? trim((string) $this->config->blocktitle) : '';
        if ($title !== '') {
            $this->title = format_string($title, true, ['context' => $this->context]);
        }
    }

    /**
     * The bar is designed to sit flush on the page, so the block header is off unless
     * the admin explicitly asks for one.
     *
     * @return bool
     */
    public function hide_header() {
        return empty($this->config->showtitle);
    }

    /**
     * Build the bar.
     *
     * @return stdClass|null
     */
    public function get_content() {
        global $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $config = $this->config ?? new stdClass();
        $rows = $this->build_rows($config);

        if (empty($rows)) {
            // Nothing running. Everyone but an editor sees no block at all; an editor gets
            // a note saying where offers are created, otherwise the block looks broken to
            // the very person who just placed it.
            if (empty($config->hidewhenempty) || $this->page->user_is_editing()) {
                $message = $this->page->user_is_editing()
                    ? get_string('editingnooffers', 'block_nit_offers')
                    : get_string('nooffers', 'block_nit_offers');
                $this->content->text = html_writer::div($message, 'nitoff-empty');
            }
            return $this->content;
        }

        $dismissible = !empty($config->dismissible);
        $rotate = !isset($config->rotate) || !empty($config->rotate);

        $context = [
            'barid'        => 'nitoff-' . (int) $this->instance->id,
            'fingerprint'  => \block_nit_offers\bar::fingerprint($rows),
            'tone'         => $this->tone($config),
            'flag'         => get_string('offerflag', 'block_nit_offers'),
            'rotate'       => $rotate,
            'multiple'     => count($rows) > 1,
            'dismissible'  => $dismissible,
            'dismisslabel' => get_string('dismiss', 'block_nit_offers'),
            'showlabel'    => get_string('showoffer', 'block_nit_offers'),
            'rows'         => $rows,
        ];

        // The script is only worth loading when there is something for it to do. It is
        // requested for the footer on purpose: block content is generated long after the
        // <head> has been written.
        if ($dismissible || count($rows) > 1) {
            $this->page->requires->js(new moodle_url('/blocks/nit_offers/bar.js'));
        }

        $this->content->text = $OUTPUT->render_from_template('block_nit_offers/bar', $context);

        return $this->content;
    }

    /**
     * The rows the bar will cycle through, for whichever source this instance uses.
     *
     * @param stdClass $config the instance config
     * @return array[]
     */
    private function build_rows(stdClass $config): array {
        $linktext = trim((string) ($config->ctalabel ?? ''));
        if ($linktext === '') {
            $linktext = get_string('seecourses', 'block_nit_offers');
        }
        $fallbackurl = $this->fallback_url($config);

        if (($config->source ?? 'auto') === 'custom') {
            $headline = trim((string) ($config->customhtml ?? ''));
            if ($headline === '') {
                return [];
            }
            return [[
                'index'    => 1,
                'first'    => true,
                'headline' => format_text($headline, FORMAT_HTML, [
                    'context' => $this->context,
                    'overflowdiv' => false,
                ]),
                'badge'    => '',
                'meta'     => '',
                'scope'    => '',
                'url'      => $fallbackurl ? $fallbackurl->out(false) : '',
                'linktext' => $linktext,
            ]];
        }

        $max = (int) ($config->maxoffers ?? self::DEFAULT_MAX_OFFERS);
        if ($max <= 0) {
            $max = self::DEFAULT_MAX_OFFERS;
        }
        return \block_nit_offers\bar::rows($max, $fallbackurl, $linktext);
    }

    /**
     * Where a row links when the offer itself does not name a single course.
     *
     * @param stdClass $config the instance config
     * @return moodle_url|null null when the admin cleared the link on purpose
     */
    private function fallback_url(stdClass $config): ?moodle_url {
        $url = trim((string) ($config->ctaurl ?? ''));
        if ($url === '') {
            return new moodle_url('/local/nit_category/catalogue.php');
        }
        // An address that does not survive PARAM_URL is dropped rather than printed:
        // a bad link in a site-wide bar is worse than no link.
        $clean = clean_param($url, PARAM_URL);
        return $clean !== '' ? new moodle_url($clean) : null;
    }

    /**
     * The brand colour role tinting the bar.
     *
     * @param stdClass $config the instance config
     * @return string
     */
    private function tone(stdClass $config): string {
        $tone = (string) ($config->tone ?? 'accent');
        return in_array($tone, ['accent', 'primary', 'success', 'warning'], true) ? $tone : 'accent';
    }
}
