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
 * Per-instance settings for block_nit_offers.
 *
 * @package    block_nit_offers
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * The offers bar's configuration form.
 */
class block_nit_offers_edit_form extends block_edit_form {

    /**
     * Add the bar's own settings.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // What the bar says.
        $mform->addElement('select', 'config_source', get_string('source', 'block_nit_offers'), [
            'auto'   => get_string('source_auto', 'block_nit_offers'),
            'custom' => get_string('source_custom', 'block_nit_offers'),
        ]);
        $mform->addHelpButton('config_source', 'source', 'block_nit_offers');
        $mform->setDefault('config_source', 'auto');

        $mform->addElement('textarea', 'config_customhtml', get_string('customhtml', 'block_nit_offers'),
            ['rows' => 3, 'style' => 'width: 100%;']);
        $mform->setType('config_customhtml', PARAM_RAW);
        $mform->addHelpButton('config_customhtml', 'customhtml', 'block_nit_offers');
        $mform->hideIf('config_customhtml', 'config_source', 'neq', 'custom');

        $mform->addElement('text', 'config_maxoffers', get_string('maxoffers', 'block_nit_offers'), ['size' => 4]);
        $mform->setType('config_maxoffers', PARAM_INT);
        $mform->setDefault('config_maxoffers', block_nit_offers::DEFAULT_MAX_OFFERS);
        $mform->addHelpButton('config_maxoffers', 'maxoffers', 'block_nit_offers');
        $mform->hideIf('config_maxoffers', 'config_source', 'neq', 'auto');

        // Where it sends people.
        $mform->addElement('text', 'config_ctalabel', get_string('ctalabel', 'block_nit_offers'), ['size' => 30]);
        $mform->setType('config_ctalabel', PARAM_TEXT);
        $mform->addHelpButton('config_ctalabel', 'ctalabel', 'block_nit_offers');

        // PARAM_RAW_TRIMMED, not PARAM_URL: an address the form silently blanks is a
        // setting that vanishes without saying why. The block validates it instead and
        // drops only what is genuinely unusable.
        $mform->addElement('text', 'config_ctaurl', get_string('ctaurl', 'block_nit_offers'), ['size' => 50]);
        $mform->setType('config_ctaurl', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('config_ctaurl', 'ctaurl', 'block_nit_offers');

        // Behaviour.
        $mform->addElement('advcheckbox', 'config_rotate', get_string('rotate', 'block_nit_offers'));
        $mform->addHelpButton('config_rotate', 'rotate', 'block_nit_offers');
        $mform->setDefault('config_rotate', 1);
        $mform->hideIf('config_rotate', 'config_source', 'neq', 'auto');

        $mform->addElement('advcheckbox', 'config_dismissible', get_string('dismissible', 'block_nit_offers'));
        $mform->addHelpButton('config_dismissible', 'dismissible', 'block_nit_offers');
        $mform->setDefault('config_dismissible', 0);

        $mform->addElement('advcheckbox', 'config_hidewhenempty', get_string('hidewhenempty', 'block_nit_offers'));
        $mform->addHelpButton('config_hidewhenempty', 'hidewhenempty', 'block_nit_offers');
        $mform->setDefault('config_hidewhenempty', 1);

        // Appearance.
        $mform->addElement('header', 'appearanceheader', get_string('appearance', 'block_nit_offers'));

        $mform->addElement('select', 'config_tone', get_string('tone', 'block_nit_offers'), [
            'accent'  => get_string('tone_accent', 'block_nit_offers'),
            'primary' => get_string('tone_primary', 'block_nit_offers'),
            'success' => get_string('tone_success', 'block_nit_offers'),
            'warning' => get_string('tone_warning', 'block_nit_offers'),
        ]);
        $mform->addHelpButton('config_tone', 'tone', 'block_nit_offers');
        $mform->setDefault('config_tone', 'accent');

        $mform->addElement('advcheckbox', 'config_showtitle', get_string('showtitle', 'block_nit_offers'));
        $mform->addHelpButton('config_showtitle', 'showtitle', 'block_nit_offers');
        $mform->setDefault('config_showtitle', 0);

        $mform->addElement('text', 'config_blocktitle', get_string('blocktitle', 'block_nit_offers'), ['size' => 30]);
        $mform->setType('config_blocktitle', PARAM_TEXT);
        $mform->hideIf('config_blocktitle', 'config_showtitle', 'eq', 0);
    }
}
