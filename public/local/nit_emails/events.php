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
 * Site administration › Plugins › Local plugins › Event notifications.
 *
 * One row per event the site can notify about, two ticks per row: does it send an email,
 * and does it show a notification. The ticks are the values {@see \message_send()} reads —
 * see {@see \local_nit_emails\channels} for which ones and why there is no second store.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_emails\channels;

admin_externalpage_setup('local_nit_emails_events');
require_capability('local/nit_emails:manage', context_system::instance());

$pageurl = new moodle_url('/local/nit_emails/events.php');
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('events', 'local_nit_emails'));
$PAGE->set_heading(get_string('events', 'local_nit_emails'));

$available = channels::available();
$groups = channels::groups();

// ─────────────────────────────────────────────────────────────────────────────────────────
// Save.
//
// The posted values are never trusted as a list: the events to write are the events this
// site has, walked in the same order they were drawn, and each tick is read by the field
// name that row owns. A checkbox that is off posts nothing, which is exactly what an
// unticked box means here — but only for a channel the page was able to offer at all, so a
// greyed-out column is left out of $wanted rather than being read as "switched off".
// ─────────────────────────────────────────────────────────────────────────────────────────
if (data_submitted() && confirm_sesskey()) {
    $changed = 0;

    foreach ($groups as $group) {
        foreach ($group['rows'] as $row) {
            $wanted = [];
            foreach (array_keys($available) as $channel) {
                $wanted[$channel] = (bool) optional_param($channel . '_' . $row['field'], 0, PARAM_BOOL);
            }
            if ($wanted && channels::apply($row['component'], $row['name'], $wanted)) {
                $changed++;
            }
        }
    }

    if ($changed) {
        // The stored values are read through the plugin config cache and the plugin manager
        // caches the provider list beside them; core's own settings page resets this after
        // every write, and skipping it here would leave the next page load reading the old
        // answer out of the cache.
        \core_plugin_manager::reset_caches();
    }

    redirect($pageurl,
        $changed ? get_string('eventssaved', 'local_nit_emails', $changed)
                 : get_string('eventsnochange', 'local_nit_emails'),
        null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ─────────────────────────────────────────────────────────────────────────────────────────
// Output.
// ─────────────────────────────────────────────────────────────────────────────────────────

// Filtering a sixty-row table by typing beats scrolling it. Progressive enhancement: with
// scripting off the box simply does nothing and every row is already on the page.
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var box = document.getElementById('nitev-filter');
    if (!box) {
        return;
    }
    box.closest('.nitev-filterwrap').hidden = false;
    box.addEventListener('input', function() {
        var needle = box.value.trim().toLowerCase();
        document.querySelectorAll('#nitev-table tbody').forEach(function(group) {
            var shown = 0;
            group.querySelectorAll('tr[data-nitev-text]').forEach(function(row) {
                var hit = needle === '' || row.dataset.nitevText.indexOf(needle) !== -1;
                row.hidden = !hit;
                shown += hit ? 1 : 0;
            });
            group.hidden = shown === 0;
        });
    });
});
JS);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('events', 'local_nit_emails'));
echo html_writer::div(get_string('events_intro', 'local_nit_emails'), 'alert alert-info');

// A channel whose processor is switched off site-wide cannot deliver whatever this page
// says, so say so instead of showing ticks that do nothing.
foreach (channels::channels() as $channel) {
    if (!isset($available[$channel])) {
        echo html_writer::div(
            get_string('events_channeloff', 'local_nit_emails', [
                'channel' => get_string('channel_' . $channel, 'local_nit_emails'),
                'url' => (new moodle_url('/admin/message.php'))->out(),
            ]),
            'alert alert-warning');
    }
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false), 'id' => 'nitev-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('nitev-filterwrap mb-3', ['hidden' => 'hidden']);
echo html_writer::label(get_string('events_filter', 'local_nit_emails'), 'nitev-filter', true,
    ['class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'id' => 'nitev-filter',
    'class' => 'form-control',
    'style' => 'max-width: 28rem;',
    'autocomplete' => 'off',
    'placeholder' => get_string('events_filter_placeholder', 'local_nit_emails'),
]);
echo html_writer::end_div();

echo html_writer::start_div('table-responsive');
echo html_writer::start_tag('table', ['class' => 'table generaltable', 'id' => 'nitev-table']);

echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('events_event', 'local_nit_emails'), ['scope' => 'col']);
foreach (channels::channels() as $channel) {
    echo html_writer::tag('th', get_string('channel_' . $channel, 'local_nit_emails'),
        ['scope' => 'col', 'class' => 'text-center', 'style' => 'width: 12rem;']);
}
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

foreach ($groups as $group) {
    // One tbody per plugin, so the filter can hide a whole group once none of its rows match.
    echo html_writer::start_tag('tbody');

    echo html_writer::tag('tr',
        html_writer::tag('th', s($group['label']),
            ['colspan' => count(channels::channels()) + 1, 'scope' => 'colgroup']),
        ['class' => 'table-active']);

    foreach ($group['rows'] as $row) {
        // What the filter box matches on: the event's own wording plus the component_name
        // key, so an administrator can find a row by either.
        $haystack = core_text::strtolower($row['label'] . ' ' . $row['key'] . ' ' . $group['label']);

        echo html_writer::start_tag('tr', ['data-nitev-text' => $haystack]);

        $labelcell = html_writer::div(s($row['label']));
        $labelcell .= html_writer::tag('small', s($row['key']), ['class' => 'text-muted']);
        if ($row['disabled']) {
            $labelcell .= ' ' . html_writer::tag('span',
                get_string('events_providerdisabled', 'local_nit_emails'),
                ['class' => 'badge bg-secondary']);
        }
        echo html_writer::tag('td', $labelcell);

        foreach (channels::channels() as $channel) {
            $id = $channel . '_' . $row['field'];
            $state = $row['states'][$channel];

            $attributes = [
                'type' => 'checkbox',
                'name' => $id,
                'id' => $id,
                'value' => 1,
                'class' => 'form-check-input',
                // The row already names the event; the box needs its own name for a screen
                // reader reading the column out of context.
                'aria-label' => get_string('events_sendvia', 'local_nit_emails', [
                    'event' => $row['label'],
                    'channel' => get_string('channel_' . $channel, 'local_nit_emails'),
                ]),
            ];
            if ($state['on']) {
                $attributes['checked'] = 'checked';
            }
            if (!isset($available[$channel])) {
                $attributes['disabled'] = 'disabled';
            }

            $cell = html_writer::empty_tag('input', $attributes);
            if ($state['on'] && $state['locked']) {
                // Not a state this page can set, but one core's own page can — so it is
                // shown rather than hidden, and saving here leaves it alone.
                $cell .= html_writer::div(get_string('events_forced', 'local_nit_emails'),
                    'small text-muted');
            }
            echo html_writer::tag('td', $cell, ['class' => 'text-center']);
        }

        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
}

echo html_writer::end_tag('table');
echo html_writer::end_div();

echo html_writer::div(
    html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-primary',
        'value' => get_string('savechanges'),
    ]),
    'mb-3');
echo html_writer::end_tag('form');

echo html_writer::tag('p',
    get_string('events_seealso', 'local_nit_emails', (new moodle_url('/admin/message.php'))->out()),
    ['class' => 'text-muted']);

echo $OUTPUT->footer();
