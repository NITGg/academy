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
 * Which brand group is light, which is dark — and is anything wearing the wrong one.
 *
 * Two questions this answers, both of which used to need somebody to open the
 * Brand Colors tab and squint at five palettes:
 *
 *   1. What scheme is each group actually authored for? Measured from the
 *      group's own Background against its Text primary, which is also what the
 *      design-system API publishes as `brandcolors.groups[].scheme` and what the
 *      "Category styles" selector now filters on.
 *   2. Is any display mode pointed at a group authored for the OTHER scheme?
 *      That is a dark screen for a visitor who asked for the light one, on the
 *      web and in the app alike — and it is invisible on the settings form,
 *      where every group is just a name in a list.
 *
 * `--fix` repoints each mismatch at the site's own group for that mode (the
 * "Site styles" pair), which is the palette those pages would have shown if
 * nobody had assigned anything. It never invents a group and never touches an
 * assignment that already matches.
 *
 * Usage:
 *   php theme/nit/cli/scheme_diagnose.php
 *   php theme/nit/cli/scheme_diagnose.php --fix
 *
 * @package    theme_nit
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/theme/nit/lib.php');

list($options, $unrecognised) = cli_get_params([
    'fix'  => false,
    'help' => false,
], ['f' => 'fix', 'h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Report — and optionally repair — brand groups worn by the wrong display mode.

  --fix    repoint every mismatched assignment at the site's group for that mode
  --help   this text
");
    exit(0);
}

$fix = !empty($options['fix']);
$grouplabels = theme_nit_brand_groups();
$modes = array_keys(theme_nit_modes());

// ---------------------------------------------------------------------------
// 1. The groups, and what each one is authored as.
// ---------------------------------------------------------------------------
cli_heading('Brand groups');
foreach ($grouplabels as $gkey => $glabel) {
    cli_writeln(sprintf(
        '  %-3s %-32s scheme=%-5s  background=%s  text=%s',
        $gkey,
        $glabel,
        theme_nit_brand_group_scheme($gkey),
        theme_nit_brandcolour($gkey . '_background'),
        theme_nit_brandcolour($gkey . '_textprimary')
    ));
}

foreach ($modes as $mode) {
    $for = theme_nit_groups_for_scheme($mode);
    cli_writeln(sprintf('  usable for %-5s mode: %s', $mode, $for ? implode(', ', $for) : '(none!)'));
}

// ---------------------------------------------------------------------------
// 2. Site styles — the pair published as brandcolors.schemes.
// ---------------------------------------------------------------------------
cli_heading('Site styles (published as brandcolors.schemes)');
$sitegroups = theme_nit_mode_groups();
$sitechanged = false;
foreach ($sitegroups as $mode => $gkey) {
    $scheme = theme_nit_brand_group_scheme($gkey);
    $ok = ($scheme === $mode);
    cli_writeln(sprintf('  %-5s mode -> %-3s (%s, scheme=%s) %s',
        $mode, $gkey, $grouplabels[$gkey] ?? '?', $scheme, $ok ? 'OK' : '** MISMATCH **'));
    if (!$ok && $fix) {
        // Nothing to fall back to here — the site pair IS the fallback — so
        // take the first group authored for the mode, in group order.
        $candidates = theme_nit_groups_for_scheme($mode);
        if ($candidates) {
            $sitegroups[$mode] = reset($candidates);
            $sitechanged = true;
            cli_writeln(sprintf('        fixed -> %s', $sitegroups[$mode]));
        } else {
            cli_writeln('        cannot fix: no group is authored for this mode.');
        }
    }
}
if ($sitechanged) {
    set_config('nit_mode_groups', json_encode($sitegroups), 'theme_nit');
}

// ---------------------------------------------------------------------------
// 3. Category styles — one assignment per main category per mode.
// ---------------------------------------------------------------------------
cli_heading('Category styles');
$maps = [];
foreach ($modes as $mode) {
    $maps[$mode] = theme_nit_category_group_map($mode);
}

$mismatches = 0;
$changed = [];
foreach (core_course_category::top()->get_children() as $cat) {
    $line = sprintf('  %-5d %-34s', $cat->id, core_text::substr($cat->get_formatted_name(), 0, 34));
    foreach ($modes as $mode) {
        $gkey = $maps[$mode][$cat->id] ?? '';
        if ($gkey === '') {
            $line .= sprintf('%s: site default (%s)   ', $mode, $sitegroups[$mode] ?? '?');
            continue;
        }
        $scheme = theme_nit_brand_group_scheme($gkey);
        $ok = ($scheme === $mode);
        $line .= sprintf('%s: %-3s %s   ', $mode, $gkey, $ok ? 'OK' : '** ' . $scheme . ' PALETTE **');
        if ($ok) {
            continue;
        }
        $mismatches++;
        if ($fix && !empty($sitegroups[$mode]) && theme_nit_brand_group_scheme($sitegroups[$mode]) === $mode) {
            $maps[$mode][$cat->id] = $sitegroups[$mode];
            $changed[$mode] = true;
            $line .= '-> ' . $sitegroups[$mode] . '   ';
        }
    }
    cli_writeln(rtrim($line));
}

foreach ($changed as $mode => $unused) {
    set_config(theme_nit_category_groups_config($mode), json_encode($maps[$mode]), 'theme_nit');
}

cli_writeln('');
if (!$mismatches) {
    cli_writeln('No category is wearing the wrong scheme.');
} else if ($fix) {
    cli_writeln($mismatches . ' mismatch(es) repointed. Purge caches, then re-read /theme/nit/design_system.php.');
} else {
    cli_writeln($mismatches . ' mismatch(es). Re-run with --fix, or set them on the "Category styles" tab.');
}
exit(0);
