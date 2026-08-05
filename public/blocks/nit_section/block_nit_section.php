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
 * NIT Section block — rich HTML with per-instance layout controls.
 *
 * Renders author-supplied HTML and stamps layout classes (width, alignment,
 * spacing, chrome) onto the block wrapper so the theme's front-page regions
 * can flow blocks side-by-side. No dependency on any third-party theme.
 *
 * @package    block_nit_section
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Block class for block_nit_section.
 */
class block_nit_section extends block_base {

    /** @var string[] Allowed width keys mapped to wrapper classes. */
    private const WIDTHS = ['full', 'half', 'third', 'twothirds', 'quarter'];

    /** @var string[] Allowed alignment keys. */
    private const ALIGNS = ['stretch', 'start', 'center', 'end'];

    /** @var string[] Allowed CSS length units for spacing. */
    private const UNITS = ['px', 'rem', 'em', '%', 'vh', 'vw'];

    public function init() {
        $this->title = get_string('pluginname', 'block_nit_section');
    }

    public function has_config() {
        return false;
    }

    public function applicable_formats() {
        // Usable anywhere, but designed for the site-index full-width regions.
        return ['all' => true];
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function specialization() {
        if (!empty($this->config->showtitle) && !empty($this->config->title)) {
            $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        } else {
            $this->title = get_string('newsectionblock', 'block_nit_section');
        }
    }

    /**
     * Hide the block title unless the author opted to show one. The block's
     * action menu (move, configure, delete) is rendered independently of the
     * title (see moodleblock.class.php), so it stays reachable while editing
     * even with the title hidden.
     *
     * @return bool
     */
    public function hide_header() {
        return empty($this->config->showtitle);
    }

    public function get_content() {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if ($this->content !== null) {
            return $this->content;
        }

        $filteropt = new stdClass();
        $filteropt->overflowdiv = true;
        if ($this->content_is_trusted()) {
            $filteropt->noclean = true;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (isset($this->config->text)) {
            $text = file_rewrite_pluginfile_urls(
                $this->config->text,
                'pluginfile.php',
                $this->context->id,
                'block_nit_section',
                'content',
                null
            );
            $format = $this->config->format ?? FORMAT_HTML;
            $this->content->text = format_text($text, $format, $filteropt);
        } else {
            $this->content->text = '';
        }

        return $this->content;
    }

    /**
     * Stamp layout classes onto the block's outer wrapper.
     *
     * The theme's front-page SCSS reads these to size and position the block
     * within a flex region.
     *
     * @return array
     */
    public function html_attributes() {
        $attributes = parent::html_attributes();

        $width = $this->config->width ?? 'full';
        $align = $this->config->align ?? 'stretch';
        $chrome = !empty($this->config->plain) ? 'plain' : 'card';

        $classes = ['block_nit_section'];
        $classes[] = 'nit-w-' . (in_array($width, self::WIDTHS, true) ? $width : 'full');
        $classes[] = 'nit-align-' . (in_array($align, self::ALIGNS, true) ? $align : 'stretch');
        $classes[] = 'nit-chrome-' . $chrome;
        if (!empty($this->config->attachprev)) {
            $classes[] = 'nit-attach-prev';
        }
        $attributes['class'] = trim(($attributes['class'] ?? '') . ' ' . implode(' ', $classes));

        // Spacing: author-entered numbers in the chosen unit become inline
        // styles. Values are cast to float and the unit is whitelisted, so this
        // is injection-safe. !important beats Bootstrap's mb-3 utility.
        $unit = in_array($this->config->spacingunit ?? 'px', self::UNITS, true)
            ? $this->config->spacingunit : 'px';
        $styles = [];
        $attach = !empty($this->config->attachprev);
        $top = $this->config->margintop ?? '';
        $bottom = $this->config->marginbottom ?? '';
        if ($attach) {
            // Attaching to the block above always wins over any top value.
            $styles[] = 'margin-top:0 !important';
        } else if ($top !== '' && is_numeric($top)) {
            $styles[] = 'margin-top:' . (float) $top . $unit . ' !important';
        }
        if ($bottom !== '' && is_numeric($bottom)) {
            $styles[] = 'margin-bottom:' . (float) $bottom . $unit . ' !important';
        }
        if ($styles) {
            $existing = isset($attributes['style']) ? rtrim($attributes['style'], ';') . ';' : '';
            $attributes['style'] = $existing . implode(';', $styles) . ';';
        }

        return $attributes;
    }

    /**
     * Persist config, moving any embedded draft files into the block filearea.
     *
     * @param stdClass $data
     * @param bool $nolongerused
     */
    public function instance_config_save($data, $nolongerused = false) {
        $config = clone($data);
        if (isset($data->text) && is_array($data->text)) {
            $config->text = file_save_draft_area_files(
                $data->text['itemid'],
                $this->context->id,
                'block_nit_section',
                'content',
                0,
                ['subdirs' => true],
                $data->text['text']
            );
            $config->format = $data->text['format'];
        }
        parent::instance_config_save($config, $nolongerused);
    }

    public function instance_delete() {
        $fs = get_file_storage();
        $fs->delete_area_files($this->context->id, 'block_nit_section');
        return true;
    }

    /**
     * Copy embedded files when the block instance is duplicated.
     *
     * @param int $fromid
     * @return bool
     */
    public function instance_copy($fromid) {
        $fromcontext = context_block::instance($fromid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($fromcontext->id, 'block_nit_section', 'content', 0, 'id ASC', false);
        foreach ($files as $file) {
            $fs->create_file_from_storedfile(['contextid' => $this->context->id], $file);
        }
        return true;
    }

    /**
     * Rich HTML (including scripts) is only trusted outside personal/user pages.
     *
     * @return bool
     */
    public function content_is_trusted() {
        if (!$context = context::instance_by_id($this->instance->parentcontextid, IGNORE_MISSING)) {
            return false;
        }
        // No JS on public personal pages — same stance as the core HTML block.
        return $context->contextlevel != CONTEXT_USER;
    }
}
