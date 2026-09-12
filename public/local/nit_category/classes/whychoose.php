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

namespace local_nit_category;

/**
 * The "Why thousands choose us" section of the category pages.
 *
 * The section sits right under the hero of every category page (index.php) and is
 * site-wide: one set of texts and one list of cards, shown on every category. The three
 * texts (two headings and a description) live in plugin config, the cards in
 * {local_nit_cat_whycard}, and a card's optional picture in the 'whycard' file area of the
 * system context, keyed by the card id. Everything the admin types is bilingual and is
 * stored the way the rest of the site stores bilingual values: one field holding an
 * {mlang en}…{mlang}{mlang ar}…{mlang} pair, resolved for display by text_util::ml().
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class whychoose {

    /** @var string the table holding the cards */
    const TABLE = 'local_nit_cat_whycard';

    /** @var string file area of a card's picture (system context, itemid = card id) */
    const FILEAREA = 'whycard';

    /** @var string[] the three section texts, in page order; also the config key suffixes */
    const TEXTS = ['header1', 'header2', 'description'];

    /** @var string[] the languages the editor exposes, in output order */
    const LANGS = ['en', 'ar'];

    // ── Bilingual values ─────────────────────────────────────────────────────────────────

    /**
     * Collapse per-language inputs into the stored {mlang} value.
     *
     * Both languages → an {mlang} pair; one language → that text on its own (the display
     * side shows a single-language value as is); none → ''.
     *
     * @param array $values lang code => text
     * @return string
     */
    public static function build(array $values): string {
        $parts = [];
        foreach (self::LANGS as $lang) {
            $val = trim((string) ($values[$lang] ?? ''));
            if ($val !== '') {
                $parts[$lang] = $val;
            }
        }
        if (count($parts) <= 1) {
            return (string) reset($parts);
        }
        $out = '';
        foreach ($parts as $lang => $val) {
            $out .= '{mlang ' . $lang . '}' . $val . '{mlang}';
        }
        return $out;
    }

    /**
     * Split a stored value back into per-language parts for the editor.
     *
     * A value with no {mlang} markup is treated as English, so a single-language entry
     * comes back into the field it was typed in.
     *
     * @param string|null $text the stored value
     * @return array lang code => text, always holding every LANGS key
     */
    public static function split(?string $text): array {
        $out = array_fill_keys(self::LANGS, '');
        $raw = trim((string) $text);
        if ($raw === '') {
            return $out;
        }
        $found = false;
        if (preg_match_all('/\{mlang\s+([^}]+)\}(.*?)\{mlang\}/is', $raw, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $code = strtolower(trim($m[1]));
                foreach (self::LANGS as $lang) {
                    if (strpos($code, $lang) === 0) {
                        $out[$lang] = trim($m[2]);
                        $found = true;
                    }
                }
            }
        }
        if (!$found) {
            $out['en'] = $raw;
        }
        return $out;
    }

    // ── Section texts ────────────────────────────────────────────────────────────────────

    /**
     * The stored (still bilingual) section texts.
     *
     * @return array header1 / header2 / description => stored {mlang} value ('' when unset)
     */
    public static function get_texts(): array {
        $out = [];
        foreach (self::TEXTS as $key) {
            $out[$key] = (string) get_config('local_nit_category', 'whychoose_' . $key);
        }
        return $out;
    }

    /**
     * The section texts resolved to the current language, ready to print (plain text).
     *
     * @return array header1 / header2 / description => text in the current language
     */
    public static function get_texts_resolved(): array {
        $out = [];
        foreach (self::get_texts() as $key => $raw) {
            $out[$key] = text_util::ml($raw);
        }
        return $out;
    }

    /**
     * Store the section texts from the editor's per-language fields.
     *
     * @param array $values 'header1_en', 'header1_ar', … => text
     * @return void
     */
    public static function save_texts(array $values): void {
        foreach (self::TEXTS as $key) {
            $perlang = [];
            foreach (self::LANGS as $lang) {
                $perlang[$lang] = (string) ($values[$key . '_' . $lang] ?? '');
            }
            set_config('whychoose_' . $key, self::build($perlang), 'local_nit_category');
        }
    }

    // ── Cards ────────────────────────────────────────────────────────────────────────────

    /**
     * Every card, in display order.
     *
     * @return \stdClass[] records keyed by id
     */
    public static function get_cards(): array {
        global $DB;
        return $DB->get_records(self::TABLE, null, 'sortorder ASC, id ASC');
    }

    /**
     * One card.
     *
     * @param int $id
     * @return \stdClass|null null when there is no such card
     */
    public static function get_card(int $id): ?\stdClass {
        global $DB;
        $rec = $DB->get_record(self::TABLE, ['id' => $id]);
        return $rec ?: null;
    }

    /**
     * Create or update a card from the editor's data.
     *
     * A new card goes to the end of the list. The picture is saved after the record so a
     * new card has an id to key its file area on.
     *
     * @param \stdClass $data form data: cardid, title_en/ar, body_en/ar, image_filemanager
     * @return int the card id
     */
    public static function save_card(\stdClass $data): int {
        global $DB;

        $now = time();
        $rec = new \stdClass();
        $rec->title = self::build(['en' => $data->title_en ?? '', 'ar' => $data->title_ar ?? '']);
        $rec->body  = self::build(['en' => $data->body_en ?? '', 'ar' => $data->body_ar ?? '']);
        $rec->timemodified = $now;

        $id = (int) ($data->cardid ?? 0);
        if ($id > 0 && $DB->record_exists(self::TABLE, ['id' => $id])) {
            $rec->id = $id;
            $DB->update_record(self::TABLE, $rec);
        } else {
            $max = (int) $DB->get_field_sql('SELECT MAX(sortorder) FROM {' . self::TABLE . '}');
            $rec->sortorder   = $max + 1;
            $rec->timecreated = $now;
            $id = (int) $DB->insert_record(self::TABLE, $rec);
        }

        if (isset($data->image_filemanager)) {
            file_save_draft_area_files(
                $data->image_filemanager,
                \context_system::instance()->id,
                'local_nit_category',
                self::FILEAREA,
                $id,
                self::image_options()
            );
        }
        return $id;
    }

    /**
     * Delete a card and its picture, then close the gap in the ordering.
     *
     * @param int $id
     * @return void
     */
    public static function delete_card(int $id): void {
        global $DB;
        if (!$DB->record_exists(self::TABLE, ['id' => $id])) {
            return;
        }
        get_file_storage()->delete_area_files(
            \context_system::instance()->id, 'local_nit_category', self::FILEAREA, $id);
        $DB->delete_records(self::TABLE, ['id' => $id]);
        self::renumber();
    }

    /**
     * Move a card one step up (-1) or down (+1) in the display order.
     *
     * @param int $id
     * @param int $direction -1 = up (earlier), +1 = down (later)
     * @return void
     */
    public static function move_card(int $id, int $direction): void {
        global $DB;
        self::renumber();
        $cards = array_values(self::get_cards());
        foreach ($cards as $i => $card) {
            if ((int) $card->id !== $id) {
                continue;
            }
            $j = $i + ($direction < 0 ? -1 : 1);
            if (!isset($cards[$j])) {
                return; // Already at that end.
            }
            $DB->set_field(self::TABLE, 'sortorder', $cards[$j]->sortorder, ['id' => $card->id]);
            $DB->set_field(self::TABLE, 'sortorder', $card->sortorder, ['id' => $cards[$j]->id]);
            return;
        }
    }

    /**
     * Make the sort orders a gap-free 1..n sequence in the current display order.
     *
     * @return void
     */
    protected static function renumber(): void {
        global $DB;
        $n = 0;
        foreach (self::get_cards() as $card) {
            $n++;
            if ((int) $card->sortorder !== $n) {
                $DB->set_field(self::TABLE, 'sortorder', $n, ['id' => $card->id]);
            }
        }
    }

    // ── Pictures ─────────────────────────────────────────────────────────────────────────

    /**
     * Filemanager / draft-area options for a card picture: one web image.
     *
     * @return array
     */
    public static function image_options(): array {
        global $CFG;
        return [
            'maxfiles'       => 1,
            'maxbytes'       => $CFG->maxbytes,
            'subdirs'        => 0,
            'accepted_types' => ['web_image'],
        ];
    }

    /**
     * The picture uploaded for a card, if any.
     *
     * @param int $cardid
     * @return \stored_file|null
     */
    public static function get_image_file(int $cardid): ?\stored_file {
        $files = get_file_storage()->get_area_files(
            \context_system::instance()->id, 'local_nit_category', self::FILEAREA, $cardid,
            'itemid, filepath, filename', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return $file;
            }
        }
        return null;
    }

    /**
     * URL of a card's picture, or '' when it has none.
     *
     * @param int $cardid
     * @return string
     */
    public static function get_image_url(int $cardid): string {
        $file = self::get_image_file($cardid);
        if (!$file) {
            return '';
        }
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(), $file->get_component(), $file->get_filearea(),
            $file->get_itemid(), $file->get_filepath(), $file->get_filename()
        )->out(false);
    }

    // ── What the page prints ─────────────────────────────────────────────────────────────

    /**
     * Everything index.php needs to draw the section, resolved to the current language.
     *
     * @return array|null null when there is nothing to show (no cards and no texts), else
     *                    ['texts' => [...], 'cards' => [['number','title','body','image'], …]]
     */
    public static function for_display(): ?array {
        $texts = self::get_texts_resolved();
        $cards = [];
        $n = 0;
        foreach (self::get_cards() as $card) {
            $n++;
            $cards[] = [
                'id'     => (int) $card->id,
                'number' => str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'title'  => text_util::ml($card->title),
                'body'   => text_util::ml($card->body),
                'image'  => self::get_image_url((int) $card->id),
            ];
        }
        if (!$cards && trim(implode('', $texts)) === '') {
            return null;
        }
        return ['texts' => $texts, 'cards' => $cards];
    }

    /**
     * The content the section ships with: the design's copy, so the section is live the
     * moment the plugin upgrades and the admin edits from something rather than nothing.
     * Only fills what is empty — never overwrites an admin's own texts or cards.
     *
     * @return void
     */
    public static function seed_defaults(): void {
        global $DB;

        $defaults = [
            'header1' => ['en' => 'Why thousands choose',
                          'ar' => 'لماذا يختار الآلاف'],
            'header2' => ['en' => 'EAAC — the training experts of the Middle East',
                          'ar' => 'إيـــاك خبراء التدريب في الشرق الاوسط'],
            'description' => [
                'en' => 'We do not just offer courses — we design career transformations through '
                    . 'structured learning, expert teaching, and employer-recognised certificates.',
                'ar' => 'نحن لا نقدم دورات فقط - بل نصمم تحولات مهنية من خلال التعلم المنظم، '
                    . 'والتدريس الخبير، والشهادات المعترف بها من أصحاب العمل',
            ],
        ];
        foreach ($defaults as $key => $perlang) {
            if ((string) get_config('local_nit_category', 'whychoose_' . $key) === '') {
                set_config('whychoose_' . $key, self::build($perlang), 'local_nit_category');
            }
        }

        if ($DB->count_records(self::TABLE) > 0) {
            return;
        }
        $cards = [
            [['en' => 'A personalised learning experience', 'ar' => 'تجربة تعلم مخصصة'],
             ['en' => 'Invest in your future with practical skills that raise your career prospects, '
                    . 'keep you competitive and open the door to new opportunities.',
              'ar' => 'استثمر في مستقبلك من خلال مهارات عملية تعزز فرصك المهنية، مما يجعلك تنافسياً '
                    . 'ويفتح أمامك أبواب فرص جديدة.']],
            [['en' => 'Skills that advance your career', 'ar' => 'مهارات تعزز مسيرتك المهنية'],
             ['en' => 'Dive into interactive, engaging learning with multimedia content, quizzes and '
                    . 'hands-on exercises for a dynamic learning experience.',
              'ar' => 'انغمس في تعلم تفاعلي وممتع مع محتوى وسائط متعددة، واختبارات، وتمارين عملية '
                    . 'لتجربة تعليمية ديناميكية.']],
            [['en' => 'Engaging multimedia content', 'ar' => 'محتوى وسائط متعددة جذاب'],
             ['en' => 'Enjoy the freedom to learn at your own pace, anytime and anywhere, and balance '
                    . 'your studies with a busy schedule.',
              'ar' => 'استمتع بحرية التعلم وفقًا لسرعتك الخاصة، في أي وقت وأي مكان، مما يتيح لك '
                    . 'موازنة التعليم مع جدولك المزدحم.']],
            [['en' => 'Learning built around you', 'ar' => 'تجربة تعلم مخصصة'],
             ['en' => 'Benefit from tailored courses that adapt to your learning style and preferences, '
                    . 'for a learning journey made for you.',
              'ar' => 'استفد من دورات مخصصة تتكيف مع أسلوب تعلمك وتفضيلاتك، مما يضمن رحلة تعليمية '
                    . 'مخصصة لك.']],
        ];
        $now = time();
        $order = 0;
        foreach ($cards as [$title, $body]) {
            $order++;
            $DB->insert_record(self::TABLE, (object) [
                'title'        => self::build($title),
                'body'         => self::build($body),
                'sortorder'    => $order,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }
}
