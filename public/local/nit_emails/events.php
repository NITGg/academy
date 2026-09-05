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
 * One row per message this site sends, two columns: does it go out as an email, and does it
 * show on the notification bell. Two tabs: the academy's own messages lead, Moodle's stock
 * events sit behind the second tab. Both are inside one form with one Save.
 *
 * A cell is a tick only where there is really something to switch. Where an event has no
 * such channel at all the cell is empty — see {@see \local_nit_emails\event_registry} for
 * the three kinds of row and why an empty cell is not the same statement as an unticked box.
 *
 * @package    local_nit_emails
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_nit_emails\channels;
use local_nit_emails\event_registry;

admin_externalpage_setup('local_nit_emails_events');
require_capability('local/nit_emails:manage', context_system::instance());

$pageurl = new moodle_url('/local/nit_emails/events.php');
$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('events', 'local_nit_emails'));
$PAGE->set_heading(get_string('events', 'local_nit_emails'));

$available = channels::available();

// ─────────────────────────────────────────────────────────────────────────────────────────
// Save.
//
// The rows to write are the registry's, walked in the same order they were drawn — the
// posted data is only ever read by the field name a row owns, never trusted as a list of
// what to change. A channel the page could not offer (its processor is switched off
// site-wide) is left out entirely rather than being read as "unticked".
// ─────────────────────────────────────────────────────────────────────────────────────────
if (data_submitted() && confirm_sesskey()) {
    $changed = 0;

    foreach (event_registry::all_rows() as $row) {
        $posted = [];
        foreach (array_keys($available) as $channel) {
            $posted[$channel] = (bool) optional_param($channel . '_' . $row['id'], 0, PARAM_BOOL);
        }
        if (event_registry::apply($row, $posted)) {
            $changed++;
        }
    }

    if ($changed) {
        // Message settings are read through the plugin config cache, and the plugin manager
        // caches the provider list beside them; core's own settings page resets this after
        // every write and skipping it leaves the next page load reading the old answer.
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

$sections = event_registry::sections();

// Filtering by typing beats scrolling forty rows. Progressive enhancement: with scripting
// off the box stays hidden and every row is already on the page.
$PAGE->requires->js_amd_inline(<<<'JS'
require([], function() {
    var form = document.getElementById('nitev-form');
    if (!form) {
        return;
    }

    var linkFor = function(paneid) {
        return form.querySelector('.nitev-tablink[data-nitev-for="' + paneid + '"]');
    };

    // Come back to the tab that was being worked on. Saving redirects, so without this an
    // administrator who ticks something among Moodle's forty events is returned to the
    // academy tab and has to find their place again.
    var STORE = 'nitev-tab';
    form.querySelectorAll('.nitev-tablink').forEach(function(link) {
        link.addEventListener('shown.bs.tab', function() {
            try {
                window.sessionStorage.setItem(STORE, link.dataset.nitevFor);
            } catch (e) {
                return;
            }
        });
    });
    try {
        var wanted = linkFor(window.sessionStorage.getItem(STORE) || '');
        if (wanted && !wanted.classList.contains('active')) {
            wanted.click();
        }
    } catch (e) {
        // A browser with storage switched off simply always opens on the first tab.
    }

    var box = document.getElementById('nitev-filter');
    if (!box) {
        return;
    }
    box.closest('.nitev-filterwrap').hidden = false;

    box.addEventListener('input', function() {
        var needle = box.value.trim().toLowerCase();
        var counts = {};

        form.querySelectorAll('.nitev-pane').forEach(function(pane) {
            var shown = 0;

            pane.querySelectorAll('.nitev-block').forEach(function(block) {
                var hits = 0;
                block.querySelectorAll('tr[data-nitev-text]').forEach(function(row) {
                    var hit = needle === '' || row.dataset.nitevText.indexOf(needle) !== -1;
                    row.hidden = !hit;
                    hits += hit ? 1 : 0;
                });
                block.hidden = hits === 0;
                shown += hits;
            });
            counts[pane.id] = shown;

            // The other tab is hidden, so its matches have to be counted on its label or
            // they are invisible - which is what makes a search look like it found nothing.
            var link = linkFor(pane.id);
            var badge = link && link.querySelector('.nitev-count');
            if (badge) {
                badge.textContent = shown;
                badge.hidden = needle === '';
            }
        });

        if (needle === '') {
            return;
        }

        // And when every match is on the other tab, go there rather than leaving the reader
        // looking at an empty table.
        var active = form.querySelector('.nitev-pane.active');
        if (!active || counts[active.id]) {
            return;
        }
        var target = Object.keys(counts).find(function(id) {
            return counts[id] > 0;
        });
        if (target && linkFor(target)) {
            linkFor(target).click();
        }
    });
});
JS);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('events', 'local_nit_emails'));
echo html_writer::div(get_string('events_intro', 'local_nit_emails'), 'alert alert-info');

// A channel whose processor is switched off site-wide delivers nothing whatever this page
// says, so say so rather than showing ticks that do nothing.
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

/**
 * One cell: a live tick, a locked tick, or nothing at all.
 *
 * @param array $row the registry row
 * @param string $channel
 * @param array $available the processors that can actually deliver
 * @return string HTML
 */
$rendercell = function (array $row, string $channel, array $available): string {
    $cell = $row['channels'][$channel];

    if ($cell['mode'] === event_registry::MODE_NA) {
        // Deliberately not an unticked box: "this event has no bell" and "the bell is
        // switched off for this event" are different statements and must not look alike.
        return html_writer::tag('span', '—', [
            'class' => 'text-muted',
            'title' => get_string('events_notapplicable', 'local_nit_emails'),
            'aria-label' => get_string('events_notapplicable', 'local_nit_emails'),
        ]);
    }

    $id = $channel . '_' . $row['id'];
    $attributes = [
        'type' => 'checkbox',
        'name' => $id,
        'id' => $id,
        'value' => 1,
        'class' => 'form-check-input',
        'aria-label' => get_string('events_sendvia', 'local_nit_emails', [
            'event' => $row['label'],
            'channel' => get_string('channel_' . $channel, 'local_nit_emails'),
        ]),
    ];
    if (!empty($cell['on'])) {
        $attributes['checked'] = 'checked';
    }
    if ($cell['mode'] === event_registry::MODE_ALWAYS || !isset($available[$channel])) {
        $attributes['disabled'] = 'disabled';
    }

    $out = html_writer::empty_tag('input', $attributes);

    if ($cell['mode'] === event_registry::MODE_ALWAYS) {
        $out .= html_writer::div(get_string('events_alwayssent', 'local_nit_emails'), 'small text-muted');
    } else if (!empty($cell['locked']) && !empty($cell['on'])) {
        // A state this page cannot set but core's own screen can — shown rather than
        // hidden, and saving here leaves it alone.
        $out .= html_writer::div(get_string('events_forced', 'local_nit_emails'), 'small text-muted');
    }

    return $out;
};

/**
 * A table of rows, optionally under sub-headings by plugin.
 *
 * @param array[] $blocks each: label ('' for no heading), rows[]
 * @param callable $rendercell
 * @param array $available
 * @return void prints
 */
$rendertable = function (array $blocks) use ($rendercell, $available): void {
    echo html_writer::start_div('table-responsive');
    echo html_writer::start_tag('table', ['class' => 'table generaltable']);

    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('events_event', 'local_nit_emails'), ['scope' => 'col']);
    foreach (channels::channels() as $channel) {
        echo html_writer::tag('th', get_string('channel_' . $channel, 'local_nit_emails'),
            ['scope' => 'col', 'class' => 'text-center', 'style' => 'width: 11rem;']);
    }
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');

    foreach ($blocks as $block) {
        // One tbody per block, so the filter can fold a whole plugin away at once.
        echo html_writer::start_tag('tbody', ['class' => 'nitev-block']);

        if ($block['label'] !== '') {
            echo html_writer::tag('tr',
                html_writer::tag('th', s($block['label']),
                    ['colspan' => count(channels::channels()) + 1, 'scope' => 'colgroup']),
                ['class' => 'table-active']);
        }

        foreach ($block['rows'] as $row) {
            // What the filter matches on: the wording, the technical key and the plugin, so
            // a row can be found by whichever of the three the reader happens to know.
            $haystack = core_text::strtolower($row['label'] . ' ' . $row['sub'] . ' ' . $block['label']);

            echo html_writer::start_tag('tr', ['data-nitev-text' => $haystack]);

            $label = html_writer::div(s($row['label']));
            if ($row['sub'] !== '') {
                $label .= html_writer::tag('small', s($row['sub']), ['class' => 'text-muted']);
            }
            if (!empty($row['link'])) {
                $label .= ' ' . html_writer::link($row['link'], s($row['linktext']), ['class' => 'small']);
            }
            echo html_writer::tag('td', $label);

            foreach (channels::channels() as $channel) {
                echo html_writer::tag('td', $rendercell($row, $channel, $available), ['class' => 'text-center']);
            }

            echo html_writer::end_tag('tr');
        }

        echo html_writer::end_tag('tbody');
    }

    echo html_writer::end_tag('table');
    echo html_writer::end_div();
};

// ─────────────────────────────────────────────────────────────────────────────────────────
// The two groups, as tabs.
//
// Both panes stay in the form whichever tab is on top — Bootstrap hides the other with
// `display: none`, which leaves its checkboxes in the submitted data. That is what lets the
// one Save button below go on writing every row in one go: the save loop walks
// event_registry::all_rows(), and a row that had gone missing from the POST would be read as
// "unticked" and switched off. Client-side tabs rather than a `?tab=` page load for exactly
// that reason.
// ─────────────────────────────────────────────────────────────────────────────────────────
$paneid = static fn(array $section): string => 'nitev-pane-' . $section['key'];

echo html_writer::start_tag('ul', ['class' => 'nav nav-tabs mb-3', 'role' => 'tablist']);
foreach ($sections as $index => $section) {
    // The academy's own messages are the reason anybody opens this page, so they lead.
    $isacademy = $index === 0;

    echo html_writer::tag('li',
        html_writer::link('#' . $paneid($section),
            s($section['label']) .
            // How many rows the filter is matching in the tab you are not looking at.
            // Empty and hidden until something is typed.
            html_writer::tag('span', '', ['class' => 'nitev-count badge bg-secondary ms-2', 'hidden' => 'hidden']),
            [
                'class' => 'nav-link nitev-tablink' . ($isacademy ? ' active' : ''),
                'id' => 'nitev-tab-' . $section['key'],
                'data-bs-toggle' => 'tab',
                'data-nitev-for' => $paneid($section),
                'role' => 'tab',
                'aria-controls' => $paneid($section),
                'aria-selected' => $isacademy ? 'true' : 'false',
            ]),
        ['class' => 'nav-item', 'role' => 'presentation']);
}
echo html_writer::end_tag('ul');

echo html_writer::start_div('tab-content');
foreach ($sections as $index => $section) {
    $isacademy = $index === 0;

    echo html_writer::start_div('tab-pane nitev-pane' . ($isacademy ? ' active' : ''), [
        'id' => $paneid($section),
        'role' => 'tabpanel',
        'aria-labelledby' => 'nitev-tab-' . $section['key'],
    ]);

    echo html_writer::tag('p', $section['intro'], ['class' => 'text-muted']);

    $blocks = $section['blocks'];
    if (!empty($section['rows'])) {
        $blocks = [['label' => '', 'rows' => $section['rows']]];
    }
    $rendertable($blocks);

    echo html_writer::end_div();
}
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
