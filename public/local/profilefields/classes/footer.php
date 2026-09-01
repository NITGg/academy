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

defined('MOODLE_INTERNAL') || die();

/**
 * What the site footer says (AC-4.7.13) - the content half of the band.
 *
 * The footer itself is drawn by theme_nit (theme/nit/templates/theme_nit/site_footer),
 * because it is presentation; everything an administrator can change about it lives
 * here, because it is content. The theme reads this through config() and holds no
 * settings of its own, so there is one place to edit the footer and one place that
 * knows what a footer link is.
 *
 * All values are plain site config under this plugin - no table. The footer is a
 * fixed handful of fields, and a table would buy nothing but a schema to migrate.
 * Text is stored raw and escaped at render; multilang ({mlang} spans) therefore
 * works in every field, because the theme runs them through format_string().
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class footer {

    /** @var string Config component. */
    const COMPONENT = 'local_profilefields';

    /**
     * The social networks the footer can show, in the order they are drawn.
     *
     * The icon class is fixed per network rather than typed by the administrator:
     * the value goes straight into a class attribute, so letting it be edited
     * would be an injection point for no benefit. FontAwesome 6 brands ship with
     * Moodle (lib/fonts/fa-brands-400.woff2), so no asset is needed.
     *
     * @return array<string, string> config suffix => FontAwesome class
     */
    public static function networks(): array {
        return [
            'facebook'  => 'fa-brands fa-facebook-f',
            'instagram' => 'fa-brands fa-instagram',
            'linkedin'  => 'fa-brands fa-linkedin-in',
            'twitter'   => 'fa-brands fa-x-twitter',
            'youtube'   => 'fa-brands fa-youtube',
            'tiktok'    => 'fa-brands fa-tiktok',
            'whatsapp'  => 'fa-brands fa-whatsapp',
            'telegram'  => 'fa-brands fa-telegram',
        ];
    }

    /**
     * The contact rows, in the order they are drawn, with their icons.
     *
     * @return array<string, string> config suffix => FontAwesome class
     */
    public static function contactrows(): array {
        return [
            'address' => 'fa-solid fa-location-dot',
            'phone'   => 'fa-solid fa-phone',
            'hours'   => 'fa-solid fa-clock',
            'email'   => 'fa-solid fa-envelope',
        ];
    }

    /**
     * The shipped defaults - what the footer says before anyone edits it.
     *
     * The two link columns are seeded with the six static pages of AC-4.21.1, so
     * the footer is complete the day those pages exist and nobody has to remember
     * to come back here and add them.
     *
     * @return array<string, string> config suffix => default value
     */
    public static function defaults(): array {
        return [
            'enabled'         => '1',
            'contactheading'  => 'Contact information',
            'address'         => '',
            'phone'           => '',
            'hours'           => '',
            'email'           => '',
            'col2heading'     => 'Explore',
            'col2links'       => "About us|/local/nit_core/page.php?page=about\n"
                . "Courses|/course/\n"
                . "Contact us|/local/nit_core/page.php?page=contact",
            'col3heading'     => 'Useful links',
            'col3links'       => "Terms and conditions|/local/nit_core/page.php?page=terms\n"
                . "Privacy policy|/local/nit_core/page.php?page=privacy\n"
                . "Refund policy|/local/nit_core/page.php?page=refund",
            'logourl'         => '',
            'copyright'       => '',
            'copyrightyear'   => date('Y'),
        ];
    }

    /**
     * One footer setting, falling back to its shipped default.
     *
     * @param string $name config suffix, without the `footer` prefix
     * @return string
     */
    public static function get(string $name): string {
        $value = get_config(self::COMPONENT, 'footer' . $name);
        if ($value === false || $value === null) {
            return (string) (self::defaults()[$name] ?? '');
        }
        return (string) $value;
    }

    /**
     * Whether the footer is drawn at all.
     *
     * @return bool
     */
    public static function enabled(): bool {
        return self::get('enabled') === '1';
    }

    /**
     * The whole footer, as data - the contract theme_nit renders.
     *
     * Deliberately presentation-free: no HTML, no classes, no template names. The
     * theme decides how a column looks; this decides what is in one.
     *
     * @return array{
     *     enabled: bool,
     *     logourl: string,
     *     contact: array{heading: string, rows: array<int, array{icon: string, text: string, url: string}>},
     *     columns: array<int, array{heading: string, links: array<int, array{label: string, url: string}>}>,
     *     social: array<int, array{network: string, icon: string, url: string}>,
     *     copyright: string
     * }
     */
    public static function config(): array {
        $rows = [];
        foreach (self::contactrows() as $name => $icon) {
            $text = trim(self::get($name));
            if ($text === '') {
                continue;
            }
            // The phone and email rows are worth being clickable on a phone; an
            // address and an opening-hours line are not links to anything.
            $url = '';
            if ($name === 'email' && validate_email($text)) {
                $url = 'mailto:' . $text;
            } else if ($name === 'phone') {
                $url = 'tel:' . preg_replace('/[^\d+]/', '', $text);
            }
            $rows[] = ['icon' => $icon, 'text' => $text, 'url' => $url];
        }

        $columns = [];
        foreach (['col2', 'col3'] as $col) {
            $links = self::parse_links(self::get($col . 'links'));
            $heading = trim(self::get($col . 'heading'));
            if ($heading === '' && empty($links)) {
                continue;
            }
            $columns[] = ['heading' => $heading, 'links' => $links];
        }

        $social = [];
        foreach (self::networks() as $network => $icon) {
            $url = trim(self::get('social' . $network));
            if ($url === '') {
                continue;
            }
            $social[] = ['network' => $network, 'icon' => $icon, 'url' => $url];
        }

        return [
            'enabled'   => self::enabled(),
            'logourl'   => trim(self::get('logourl')),
            'contact'   => ['heading' => trim(self::get('contactheading')), 'rows' => $rows],
            'columns'   => $columns,
            'social'    => $social,
            'copyright' => self::copyright(),
        ];
    }

    /**
     * The copyright line, with the year the administrator set substituted in.
     *
     * Kept as a template string with a {year} placeholder rather than two fields
     * glued together, because the sentence reads differently in Arabic and the
     * year does not sit in the same place.
     *
     * @return string
     */
    public static function copyright(): string {
        $year = trim(self::get('copyrightyear'));
        $text = trim(self::get('copyright'));

        if ($text === '') {
            $text = get_string('footercopyrightdefault', 'local_profilefields');
        }

        return str_replace('{year}', $year, $text);
    }

    /**
     * Parse a "Label|url" per line textarea into link records.
     *
     * Lines without a pipe are treated as label-only and skipped - a link with no
     * destination is a dead end, and silently dropping it is better than drawing
     * something unclickable in the footer of every page.
     *
     * @param string $raw the stored textarea
     * @return array<int, array{label: string, url: string}>
     */
    public static function parse_links(string $raw): array {
        $links = [];

        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '|') === false) {
                continue;
            }
            [$label, $url] = array_map('trim', explode('|', $line, 2));
            $url = clean_param($url, PARAM_URL);
            if ($label === '' || $url === '') {
                continue;
            }
            $links[] = ['label' => $label, 'url' => $url];
        }

        return $links;
    }

    /**
     * Save the footer tab from the current POST.
     *
     * @return void
     */
    public static function save(): void {
        set_config('footerenabled', optional_param('footerenabled', 0, PARAM_BOOL) ? '1' : '0', self::COMPONENT);

        // Free text: stored raw so {mlang} spans survive, escaped at render.
        foreach (['contactheading', 'address', 'phone', 'hours', 'email',
                  'col2heading', 'col3heading', 'copyright'] as $name) {
            set_config('footer' . $name, optional_param('footer' . $name, '', PARAM_TEXT), self::COMPONENT);
        }

        // "Label|url" blocks: re-serialised from the parsed form, so a malformed
        // or unsafe line is dropped once here rather than on every page view.
        foreach (['col2links', 'col3links'] as $name) {
            $lines = [];
            foreach (self::parse_links(optional_param('footer' . $name, '', PARAM_RAW)) as $link) {
                $lines[] = $link['label'] . '|' . $link['url'];
            }
            set_config('footer' . $name, implode("\n", $lines), self::COMPONENT);
        }

        set_config('footerlogourl', optional_param('footerlogourl', '', PARAM_URL), self::COMPONENT);

        // A year, or a range like "2020-2026". Anything else is not a year.
        $year = trim(optional_param('footercopyrightyear', '', PARAM_TEXT));
        if (!preg_match('/^\d{4}(\s*[-\x{2013}]\s*\d{4})?$/u', $year)) {
            $year = date('Y');
        }
        set_config('footercopyrightyear', $year, self::COMPONENT);

        foreach (array_keys(self::networks()) as $network) {
            set_config('footersocial' . $network,
                optional_param('footersocial' . $network, '', PARAM_URL), self::COMPONENT);
        }
    }
}
