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
 * Paint the site's Brand Colors groups in the 2026-10 Figma palette — and undo it.
 *
 * The Figma file (8svWT7P3B6GDFjpTWwlaQI) moved every page onto a navy ground
 * (#00102A dark / #F9FBFF light) and gave each academy its own colour. Typing
 * that into the gallery editor is ~14 groups × ~20 cards, so this does it in
 * one pass, writing exactly the rows the editor's Save writes
 * (`brandcolour_<group>_<role>` and the two category→group maps) — nothing a
 * later edit on gallery.php cannot change back by hand.
 *
 *   * Groups 4/5 (the site pair) keep their navbar and secondary choices; only
 *     the grounds, inks, borders, status colours and the main-button blue move.
 *   * Groups 6–17 (one light/dark pair per academy) are rebuilt whole from
 *     theme_nit_brand_hue_templates() — the same templates that seeded them —
 *     re-hued with a ramp cut from the academy's Figma colour, then given the
 *     Figma neutrals. So a hover, an outline button or a footer link follows
 *     the new colour instead of keeping the old hue's.
 *   * Each academy is pointed at the pair whose name is closest to its new
 *     colour (Engineering → Emerald, Languages → Amber, …); the group names stay
 *     as they are.
 *
 * Label colour on a filled button is white as in Figma, except where white on
 * that fill is under 3:1 (Logistics' sky blue, Languages' orange): those get the
 * page ink. Flip `onprimary` on gallery.php if the design wins.
 *
 * Every --apply first writes a backup of every row it may touch; --restore puts
 * a backup back exactly (rows the backup did not have are removed, so a role
 * that was on its default before is on its default again).
 *
 * Usage:
 *   php theme/nit/cli/apply_figma_palette.php                 # dry run: what would change
 *   php theme/nit/cli/apply_figma_palette.php --apply         # back up, then apply
 *   php theme/nit/cli/apply_figma_palette.php --restore=FILE  # put a backup back
 *   php theme/nit/cli/apply_figma_palette.php --restore=- < brand_backup.json
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
    'apply'       => false,
    'restore'     => '',
    'hero'        => false,
    'hero-remove' => false,
    'help'        => false,
], ['a' => 'apply', 'r' => 'restore', 'h' => 'help']);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}

if ($options['help']) {
    cli_writeln("Apply the Figma palette to the Brand Colors groups, or restore a backup.

  (no option)      dry run — list every colour that would change
  --apply          write a backup, then apply the palette
  --restore=FILE   put a backup back; FILE '-' reads it from standard input
  --hero           give ONLY the category hero buttons the Figma colours
                   (one row, nit_hero_buttons; the groups are not touched)
  --hero-remove    drop that row: hero buttons follow their group again
  --help           this text
");
    exit(0);
}

// ---------------------------------------------------------------------------
// The rows this script owns — the same selection a backup and a restore use.
// ---------------------------------------------------------------------------
$where = "plugin = ? AND (" . $DB->sql_like('name', '?') . " OR " . $DB->sql_like('name', '?') . " OR name = ?)";
$whereparams = ['theme_nit', 'brandcolour_%', 'nit_category_groups%', 'nit_mode_groups'];

/**
 * Every row this script may write, as the backup file stores them.
 *
 * @return array<int, array{name: string, value: string}>
 */
function figma_palette_snapshot(): array {
    global $DB, $where, $whereparams;
    $rows = $DB->get_records_select('config_plugins', $where, $whereparams, 'name', 'name, value');
    return array_values(array_map(fn($r) => ['name' => $r->name, 'value' => $r->value], $rows));
}

// ---------------------------------------------------------------------------
// --restore
// ---------------------------------------------------------------------------
if ($options['restore'] !== '') {
    $file = $options['restore'];
    $json = ($file === '-') ? stream_get_contents(STDIN) : @file_get_contents($file);
    if ($json === false) {
        cli_error("Cannot read {$file}");
    }
    $rows = json_decode($json, true);
    if (!is_array($rows) || !$rows) {
        cli_error('The backup is empty or not JSON — nothing was changed.');
    }
    foreach ($rows as $row) {
        if (!isset($row['name'], $row['value']) || !is_string($row['name'])) {
            cli_error('The backup has a row without name/value — nothing was changed.');
        }
    }
    $transaction = $DB->start_delegated_transaction();
    $DB->delete_records_select('config_plugins', $where, $whereparams);
    foreach ($rows as $row) {
        set_config($row['name'], $row['value'], 'theme_nit');
    }
    $transaction->allow_commit();
    purge_all_caches();
    cli_writeln('Restored ' . count($rows) . ' settings. Caches purged.');
    exit(0);
}

// ---------------------------------------------------------------------------
// The Figma palette.
// ---------------------------------------------------------------------------

/** Mix two #rrggbb colours: $amount of $with into $base. */
function figma_palette_mix(string $base, string $with, float $amount): string {
    $out = '#';
    for ($i = 1; $i <= 5; $i += 2) {
        $a = hexdec(substr($base, $i, 2));
        $b = hexdec(substr($with, $i, 2));
        $out .= sprintf('%02x', (int) round($a + ($b - $a) * $amount));
    }
    return $out;
}

/** WCAG contrast ratio of two colours. */
function figma_palette_contrast(string $a, string $b): float {
    $la = theme_nit_hex_luminance($a);
    $lb = theme_nit_hex_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** The ramp theme_nit_brand_hue_templates() asks for, cut from one colour. */
function figma_palette_rungs(string $base): array {
    $base = strtolower($base);
    return [
        'A200' => figma_palette_mix($base, '#ffffff', 0.70),
        'A300' => figma_palette_mix($base, '#ffffff', 0.50),
        'A400' => figma_palette_mix($base, '#ffffff', 0.30),
        'A600' => $base,
        'A700' => figma_palette_mix($base, '#000000', 0.20),
        'A800' => figma_palette_mix($base, '#000000', 0.40),
        'H85'  => figma_palette_mix($base, '#ffffff', 0.15),
        'H90'  => figma_palette_mix($base, '#ffffff', 0.10),
        'V'    => $base,
        'Vh'   => figma_palette_mix($base, '#000000', 0.08),
        'Vd'   => figma_palette_mix($base, '#000000', 0.30),
    ];
}

// The page ink a dark label uses on a bright fill.
$ink = '#00102a';

// Figma neutrals, per scheme. Grounds, inks, borders, status.
$neutrals = [
    'dark' => [
        'background'          => '#00102a',
        'background2'         => '#091540',
        'surface'             => '#0a1a33',   // #FFFFFF at 4% over #00102A.
        'navbarbackground1'   => '#00102a',
        'navbarbackground2'   => '#00102a',
        'footerbackground1'   => '#00102a',
        'footerbackground2'   => '#091540',
        'textprimary'         => '#ffffff',
        'textsecondary'       => '#94a3bb',
        'borderprimary'       => '#334155',
        'hoverbackground'     => '#091540',
        'hoverbackgroundsecondary' => '#0a1a33',
        'checkbg'             => '#0a1a33',
        'btnoutlineprimarybg' => '#00102a',
        'btnoutlinebg'        => '#00102a',
        'success'             => '#10b981',
        'error'               => '#e30727',
    ],
    'light' => [
        'background'          => '#f9fbff',
        'navbarbackground2'   => '#f9fbff',
        'btnoutlineprimarybg' => '#f9fbff',
        'btnoutlinebg'        => '#f9fbff',
        'textprimary'         => '#000000',
        'textsecondary'       => '#94a3bb',
        'success'             => '#10b981',
        'error'               => '#e30727',
    ],
];

// Academy colours from the Figma category frames, and the pair each goes to.
// Category ids are the production ones; a missing id is reported and skipped.
$academies = [
    ['category' => 15, 'name' => 'Engineering & Industry', 'colour' => '#3e8541', 'light' => 'g6',  'dark' => 'g7'],
    ['category' => 19, 'name' => 'Languages',              'colour' => '#ed772d', 'light' => 'g8',  'dark' => 'g9'],
    ['category' => 16, 'name' => 'Accounting & Finance',   'colour' => '#757575', 'light' => 'g10', 'dark' => 'g11'],
    ['category' => 14, 'name' => 'Logistics',              'colour' => '#00a8e8', 'light' => 'g12', 'dark' => 'g13'],
    ['category' => 18, 'name' => 'Hospital Management',    'colour' => '#e63946', 'light' => 'g14', 'dark' => 'g15'],
    ['category' => 17, 'name' => 'Law',                    'colour' => '#ad881f', 'light' => 'g16', 'dark' => 'g17'],
];

// The site pair: main button blue, accent red (Figma home frames).
$sitemain = '#277ee8';
$siteaccent = '#e30727';

$knownroles = theme_nit_brand_roles();
$templates = theme_nit_brand_hue_templates();

/** @var array<string, string> config name => new value */
$target = [];

// Groups 4/5: neutrals + the main button only.
foreach (['g4' => 'light', 'g5' => 'dark'] as $gkey => $scheme) {
    $roles = $neutrals[$scheme] + [
        'primary'               => $sitemain,
        'btnprimaryborder'      => $sitemain,
        'btnprimaryhoverbg'     => figma_palette_mix($sitemain, '#000000', 0.12),
        'btnprimaryhoverborder' => figma_palette_mix($sitemain, '#000000', 0.12),
        'accent'                => $siteaccent,
    ];
    foreach ($roles as $role => $value) {
        $target['brandcolour_' . $gkey . '_' . $role] = $value;
    }
}

// Groups 6–17: whole palette from the hue template, then the Figma neutrals.
foreach ($academies as $a) {
    foreach (['light', 'dark'] as $scheme) {
        $roles = theme_nit_brand_rehue($templates[$scheme], figma_palette_rungs($a['colour']));
        $roles = $neutrals[$scheme] + $roles;
        // Label on a filled button: white as in Figma, unless white is under 3:1
        // on that fill (the sky blue, the orange) — then the page ink.
        $label = figma_palette_contrast($roles['primary'], '#ffffff') >= 3.0 ? '#ffffff' : $ink;
        foreach (['onprimary', 'btnprimaryhovertext', 'btnoutlineprimaryhovertext', 'checkcheckedmark'] as $role) {
            $roles[$role] = $label;
        }
        if ($scheme === 'dark') {
            $roles['btnoutlinehovertext'] = $label;
        }
        foreach ($roles as $role => $value) {
            $target['brandcolour_' . $a[$scheme] . '_' . $role] = $value;
        }
    }
}

// Drop anything the theme no longer knows as a role.
foreach (array_keys($target) as $name) {
    $role = preg_replace('/^brandcolour_g\d+_/', '', $name);
    if (!array_key_exists($role, $knownroles)) {
        unset($target[$name]);
    }
}

// --hero / --hero-remove: only the category hero's three buttons (read by
// local/nit_category/index.php), never the groups themselves.
if ($options['hero-remove']) {
    unset_config('nit_hero_buttons', 'theme_nit');
    cli_writeln('Hero buttons follow their group again.');
    exit(0);
}
if ($options['hero']) {
    $buttonroles = ['primary', 'onprimary', 'btnprimaryborder', 'btnprimaryhoverbg', 'btnprimaryhovertext',
        'btnprimaryhoverborder', 'btnoutlineprimarytext', 'btnoutlineprimaryborder', 'btnoutlineprimaryhoverbg',
        'btnoutlineprimaryhovertext', 'btnoutlineprimaryhoverborder'];
    $hero = [];
    foreach ($academies as $a) {
        foreach (['light', 'dark'] as $scheme) {
            foreach ($buttonroles as $role) {
                $name = 'brandcolour_' . $a[$scheme] . '_' . $role;
                if (isset($target[$name])) {
                    $hero[$a[$scheme]][$role] = $target[$name];
                }
            }
        }
    }
    set_config('nit_hero_buttons', json_encode($hero), 'theme_nit');
    cli_writeln('Hero buttons set for ' . count($hero) . ' groups: ' . implode(', ', array_keys($hero)));
    exit(0);
}

// Category → group maps, merged into what is there.
$catwarnings = [];
foreach (['light' => 'nit_category_groups', 'dark' => 'nit_category_groups_dark'] as $mode => $config) {
    $raw = get_config('theme_nit', $config);
    $map = ($raw && is_string($raw)) ? (json_decode($raw, true) ?: []) : [];
    foreach ($academies as $a) {
        $cat = $DB->get_record('course_categories', ['id' => $a['category']], 'id, name, parent');
        if (!$cat || (int) $cat->parent !== 0) {
            $catwarnings[$a['category']] = "  ! category {$a['category']} ({$a['name']}) is not a main category here — its group mapping was skipped";
            continue;
        }
        $map[(string) $a['category']] = $a[$mode];
    }
    if (!$map && $raw === false) {
        continue;
    }
    ksort($map);
    $target[$config] = json_encode($map);
}

// ---------------------------------------------------------------------------
// Report (and apply).
// ---------------------------------------------------------------------------
$changes = [];
foreach ($target as $name => $value) {
    $current = get_config('theme_nit', $name);
    if ((string) $current !== (string) $value) {
        $changes[$name] = [$current === false ? '(default)' : $current, $value];
    }
}

cli_heading($options['apply'] ? 'Applying the Figma palette' : 'Dry run — nothing is written');
foreach ($changes as $name => [$old, $new]) {
    cli_writeln(sprintf('  %-48s %s -> %s', $name, $old, $new));
}
foreach ($catwarnings as $line) {
    cli_writeln($line);
}
cli_writeln(count($changes) . ' settings ' . ($options['apply'] ? 'to change.' : 'would change.'));

if (!$options['apply']) {
    cli_writeln('Run again with --apply to write them (a backup is taken first).');
    exit(0);
}
if (!$changes) {
    cli_writeln('Nothing to do.');
    exit(0);
}

$backupdir = make_writable_directory($CFG->dataroot . '/theme_nit_backups');
$backupfile = $backupdir . '/brand_backup_' . date('Ymd-His') . '.json';
if (file_put_contents($backupfile, json_encode(figma_palette_snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    cli_error("Could not write the backup to {$backupfile} — nothing was changed.");
}
cli_writeln("Backup written: {$backupfile}");

$transaction = $DB->start_delegated_transaction();
foreach ($changes as $name => [, $new]) {
    set_config($name, $new, 'theme_nit');
}
$transaction->allow_commit();
theme_reset_all_caches();

cli_writeln('Done. To undo:');
cli_writeln("  php {$CFG->dirroot}/theme/nit/cli/apply_figma_palette.php --restore={$backupfile}");
