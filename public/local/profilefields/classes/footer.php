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
 * Bilingual by construction, not by markup. Every piece of prose is stored once per
 * language - `footeraddress_en`, `footeraddress_ar` - the way theme_nit already
 * stores the account-screen quote. An administrator types Arabic in the Arabic box
 * and English in the English box; nobody has to know what {mlang} is, and nothing
 * silently renders as literal markup when the filter is off. Missing translations
 * fall back: the interface language, then English, then whichever language was
 * actually filled in, so a half-translated field still shows something.
 *
 * All values are plain site config - no table. The footer is a fixed handful of
 * fields and a table would buy nothing but a schema to migrate. The two link
 * columns are the one exception in shape: they are a list, so they are stored as
 * JSON, one object per link ({url, en, ar}).
 *
 * @package    local_profilefields
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class footer {

    /** @var string Config component. */
    const COMPONENT = 'local_profilefields';

    /** @var int How many link rows each column offers. */
    const MAXLINKS = 8;

    /**
     * The languages the footer is written in, in fallback order.
     *
     * Mirrors theme_nit_auth_text_langs() - the account-screen quote is stored the
     * same way, and a site that adds a third language should grow both at once.
     *
     * @return string[] language codes
     */
    public static function langs(): array {
        return ['en', 'ar'];
    }

    /**
     * The fields that are prose, and therefore exist once per language.
     *
     * A phone number and an email address are not prose - they are the same
     * string in both languages - so they are deliberately not in here.
     *
     * @return string[] config suffixes, without the `footer` prefix
     */
    public static function translatable(): array {
        return ['contactheading', 'address', 'hours', 'col2heading', 'col3heading', 'copyright'];
    }

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
     * The link columns, and the shipped set of links in each.
     *
     * Between them these are the six static pages of AC-4.21.1, so the footer is
     * complete the day those pages exist and nobody has to remember to come back
     * here and add them.
     *
     * @return array<string, array<int, array{url: string, en: string, ar: string}>>
     */
    public static function defaultlinks(): array {
        return [
            'col2' => [
                ['url' => '/local/nit_core/page.php?page=about', 'en' => 'About us', 'ar' => 'معلومات عنا'],
                ['url' => '/course/', 'en' => 'Courses', 'ar' => 'الدورات'],
                ['url' => '/local/nit_core/page.php?page=contact', 'en' => 'Contact us', 'ar' => 'اتصل بنا'],
            ],
            'col3' => [
                ['url' => '/local/nit_core/page.php?page=terms', 'en' => 'Terms and conditions', 'ar' => 'الشروط والأحكام'],
                ['url' => '/local/nit_core/page.php?page=privacy', 'en' => 'Privacy policy', 'ar' => 'سياسة الخصوصية'],
                ['url' => '/local/nit_core/page.php?page=refund', 'en' => 'Refund policy', 'ar' => 'سياسة الاسترداد'],
            ],
        ];
    }

    /**
     * The shipped defaults - what the footer says before anyone edits it.
     *
     * Keyed by the full config name, so per-language fields appear here with their
     * language suffix exactly as they are stored.
     *
     * @return array<string, string> config suffix => default value
     */
    public static function defaults(): array {
        return [
            'enabled'            => '1',
            'contactheading_en'  => 'Contact information',
            'contactheading_ar'  => 'معلومات الاتصال',
            'col2heading_en'     => 'Explore',
            'col2heading_ar'     => 'استكشاف',
            'col3heading_en'     => 'Useful links',
            'col3heading_ar'     => 'روابط مفيدة',
            'copyrightyear'      => date('Y'),
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
     * One language's raw value of a translatable field, exactly as stored.
     *
     * For display use text(); this is what the edit form puts in its boxes.
     *
     * @param string $name config suffix, without the `footer` prefix or language
     * @param string $lang language code
     * @return string
     */
    public static function raw(string $name, string $lang): string {
        return self::get($name . '_' . $lang);
    }

    /**
     * A translatable field resolved to the language being displayed.
     *
     * Order: the interface language, then English, then whatever was filled in.
     * A field an administrator only wrote in one language therefore shows that
     * one everywhere, rather than going blank for half the site's visitors.
     *
     * @param string $name config suffix, without the `footer` prefix or language
     * @return string
     */
    public static function text(string $name): string {
        $order = array_unique(array_merge([current_language()], ['en'], self::langs()));

        foreach ($order as $lang) {
            if (!in_array($lang, self::langs(), true)) {
                continue;
            }
            $value = trim(self::raw($name, $lang));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * The stored links of one column, as records.
     *
     * @param string $col 'col2' or 'col3'
     * @return array<int, array{url: string, en: string, ar: string}>
     */
    public static function links(string $col): array {
        $stored = get_config(self::COMPONENT, 'footer' . $col . 'links');

        // Never saved: ship the defaults. Saved as an empty list: respect that -
        // an administrator who deleted every row wants an empty column, not the
        // defaults back on the next page load.
        if ($stored === false || $stored === null || $stored === '') {
            return self::defaultlinks()[$col] ?? [];
        }

        $rows = json_decode($stored, true);
        if (!is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['url'])) {
                continue;
            }
            $link = ['url' => (string) $row['url']];
            foreach (self::langs() as $lang) {
                $link[$lang] = isset($row[$lang]) ? (string) $row[$lang] : '';
            }
            $clean[] = $link;
        }

        return $clean;
    }

    /**
     * One link's label in the language being displayed.
     *
     * Same fallback ladder as text(), so a link labelled only in English still
     * has a label on the Arabic site.
     *
     * @param array $link a record from links()
     * @return string
     */
    protected static function link_label(array $link): string {
        $order = array_unique(array_merge([current_language()], ['en'], self::langs()));

        foreach ($order as $lang) {
            if (!empty($link[$lang])) {
                return (string) $link[$lang];
            }
        }

        return '';
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
     * theme decides how a column looks; this decides what is in one. Every string
     * is already resolved to the language being displayed.
     *
     * @return array{
     *     enabled: bool,
     *     contact: array{heading: string, rows: array<int, array{icon: string, text: string, url: string}>},
     *     columns: array<int, array{heading: string, links: array<int, array{label: string, url: string}>}>,
     *     social: array<int, array{network: string, icon: string, url: string}>,
     *     copyright: string
     * }
     */
    public static function config(): array {
        $rows = [];
        foreach (self::contactrows() as $name => $icon) {
            $text = in_array($name, self::translatable(), true)
                ? self::text($name)
                : trim(self::get($name));
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
            $links = [];
            foreach (self::links($col) as $link) {
                $label = self::link_label($link);
                if ($label === '') {
                    continue;
                }
                $links[] = ['label' => $label, 'url' => $link['url']];
            }

            $heading = self::text($col . 'heading');
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
            'contact'   => ['heading' => self::text('contactheading'), 'rows' => $rows],
            'columns'   => $columns,
            'social'    => $social,
            'copyright' => self::copyright(),
        ];
    }

    /**
     * The copyright line, with the year the administrator set substituted in.
     *
     * Kept as a sentence with a {year} placeholder rather than two fields glued
     * together, because the sentence reads differently in Arabic and the year does
     * not sit in the same place.
     *
     * @return string
     */
    public static function copyright(): string {
        $year = trim(self::get('copyrightyear'));
        $text = self::text('copyright');

        if ($text === '') {
            $text = get_string('footercopyrightdefault', 'local_profilefields');
        }

        return str_replace('{year}', $year, $text);
    }

    /**
     * Save the footer tab from the current POST.
     *
     * Returns what it could not keep rather than dropping it in silence: a link
     * row typed without a destination, or with one Moodle will not accept, used
     * to vanish on save with no explanation, which reads as "the field does not
     * work". The caller shows these as a warning.
     *
     * @return string[] human-readable problems, empty when everything was kept
     */
    public static function save(): array {
        $problems = [];

        set_config('footerenabled', optional_param('footerenabled', 0, PARAM_BOOL) ? '1' : '0', self::COMPONENT);

        // Prose, one box per language.
        foreach (self::translatable() as $name) {
            foreach (self::langs() as $lang) {
                set_config('footer' . $name . '_' . $lang,
                    optional_param('footer' . $name . '_' . $lang, '', PARAM_TEXT), self::COMPONENT);
            }
        }

        // The same in both languages.
        foreach (['phone', 'email'] as $name) {
            set_config('footer' . $name, optional_param('footer' . $name, '', PARAM_TEXT), self::COMPONENT);
        }

        foreach (['col2', 'col3'] as $col) {
            $rows = [];

            for ($i = 0; $i < self::MAXLINKS; $i++) {
                $rawurl = trim(optional_param('footer' . $col . 'url_' . $i, '', PARAM_RAW_TRIMMED));

                $labels = [];
                foreach (self::langs() as $lang) {
                    $labels[$lang] = trim(optional_param(
                        'footer' . $col . 'label_' . $i . '_' . $lang, '', PARAM_TEXT));
                }
                $haslabel = implode('', $labels) !== '';

                if ($rawurl === '' && !$haslabel) {
                    // An untouched spare row.
                    continue;
                }

                if ($rawurl === '') {
                    // Text typed with nowhere to send anyone: worth saying so,
                    // because the row would otherwise just be gone next time.
                    $problems[] = get_string('footerlinknourl', 'local_profilefields', $i + 1);
                    continue;
                }

                $url = clean_param($rawurl, PARAM_URL);
                if ($url === '') {
                    $problems[] = get_string('footerlinkbadurl', 'local_profilefields',
                        (object) ['row' => $i + 1, 'url' => s($rawurl)]);
                    continue;
                }
                if (!$haslabel) {
                    $problems[] = get_string('footerlinknolabel', 'local_profilefields', $i + 1);
                    continue;
                }

                $rows[] = ['url' => $url] + $labels;
            }

            set_config('footer' . $col . 'links',
                json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::COMPONENT);
        }

        // A year, or a range like "2020-2026". Anything else is not a year.
        $year = trim(optional_param('footercopyrightyear', '', PARAM_TEXT));
        if (!preg_match('/^\d{4}(\s*[-\x{2013}]\s*\d{4})?$/u', $year)) {
            $year = date('Y');
        }
        set_config('footercopyrightyear', $year, self::COMPONENT);

        foreach (array_keys(self::networks()) as $network) {
            $raw = trim(optional_param('footersocial' . $network, '', PARAM_RAW_TRIMMED));
            $url = clean_param($raw, PARAM_URL);
            if ($raw !== '' && $url === '') {
                $problems[] = get_string('footersocialbadurl', 'local_profilefields',
                    (object) ['network' => ucfirst($network), 'url' => s($raw)]);
            }
            set_config('footersocial' . $network, $url, self::COMPONENT);
        }

        return $problems;
    }

    /**
     * Carry a footer configured under the first, single-box shape into the new one.
     *
     * The first version stored one value per field, into which an administrator was
     * expected to type {mlang} markup, and each link column as `Label|url` lines.
     * Run once from db/upgrade.php: split anything that carries {mlang} spans into
     * the matching per-language boxes, put anything else in both, and decode the
     * link lines into rows. Only ever writes a key that has not been written yet,
     * so running it twice cannot overwrite an edit made in between.
     *
     * @return void
     */
    public static function migrate_single_language_values(): void {
        foreach (self::translatable() as $name) {
            $old = get_config(self::COMPONENT, 'footer' . $name);
            if ($old === false || $old === null || trim((string) $old) === '') {
                continue;
            }

            foreach (self::langs() as $lang) {
                if (get_config(self::COMPONENT, 'footer' . $name . '_' . $lang) !== false) {
                    continue;
                }
                set_config('footer' . $name . '_' . $lang, self::split_mlang((string) $old, $lang), self::COMPONENT);
            }

            unset_config('footer' . $name, self::COMPONENT);
        }

        foreach (['col2', 'col3'] as $col) {
            $old = (string) get_config(self::COMPONENT, 'footer' . $col . 'links');
            if ($old === '' || $old[0] === '[') {
                // Never set, or already JSON.
                continue;
            }

            $rows = [];
            foreach (preg_split('/\R/', $old) as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '|') === false) {
                    continue;
                }
                [$label, $url] = array_map('trim', explode('|', $line, 2));
                $url = clean_param($url, PARAM_URL);
                if ($label === '' || $url === '') {
                    continue;
                }
                $row = ['url' => $url];
                foreach (self::langs() as $lang) {
                    $row[$lang] = self::split_mlang($label, $lang);
                }
                $rows[] = $row;
            }

            set_config('footer' . $col . 'links',
                json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::COMPONENT);
        }

        // The footer logo is now read from the site logo, the way the navbar
        // reads it, so a URL typed into the old field has nowhere to go.
        unset_config('footerlogourl', self::COMPONENT);
    }

    /**
     * One language out of a string that may carry {mlang} spans.
     *
     * A string with no spans is the same in every language, so it comes back
     * whole; a string with spans gives up just the requested language, and the
     * whole string if that language is not among them - which is still better
     * than an empty box.
     *
     * @param string $text the stored value
     * @param string $lang language code
     * @return string
     */
    protected static function split_mlang(string $text, string $lang): string {
        if (stripos($text, '{mlang') === false) {
            return $text;
        }

        if (preg_match('/\{mlang\s+' . preg_quote($lang, '/') . '\s*\}(.*?)\{mlang\s*\}/is', $text, $m)) {
            return trim($m[1]);
        }

        // Spans, but not this language: fall back to the first one written.
        if (preg_match('/\{mlang[^}]*\}(.*?)\{mlang\s*\}/is', $text, $m)) {
            return trim($m[1]);
        }

        return $text;
    }
}
