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
 * Install a TrueType font so certificates can be drawn in it.
 *
 * Certificates are PDFs, and a PDF can only draw glyphs the embedded font
 * actually contains. Of the thirty-one fonts Moodle ships, exactly two carry
 * Arabic - freeserif and freemono - so an Arabic certificate either uses one of
 * those or comes out as a row of empty boxes. Neither is a design anybody chose;
 * they are what is left after the other twenty-nine are ruled out.
 *
 * This script adds a real one. There is no screen for it in Moodle: TCPDF cannot
 * read a .ttf directly, it needs the font pre-compiled into its own .php/.z pair,
 * and nothing in the admin interface performs that conversion.
 *
 * WHERE THE FILES GO
 *
 * Not into lib/tcpdf/fonts. That is core, it is replaced wholesale on every
 * Moodle upgrade, and a font installed there disappears with the first point
 * release - silently, because the certificate goes on rendering, just in boxes
 * again. Moodle's supported alternative is PDF_CUSTOM_FONT_PATH, which defaults
 * to moodledata/fonts (see lib/pdflib.php), lives outside the code tree and
 * survives upgrades untouched.
 *
 * That path has one condition, and it is all-or-nothing: tcpdf_init_k_font_path()
 * switches to the custom directory only if the standard families are present
 * there too, otherwise every add-on asking for Helvetica would break. So the
 * first run mirrors the bundled fonts across before adding anything. Mirroring is
 * a copy, never a move - lib/tcpdf/fonts is left exactly as Moodle shipped it.
 *
 * NAMING
 *
 * TCPDF derives the font key from the filename and reads the style out of it:
 * Cairo-Regular.ttf becomes 'cairo', Cairo-Bold.ttf becomes 'cairob',
 * Cairo-BoldItalic.ttf becomes 'cairobi'. That convention is what makes four
 * files one family with three styles in the certificate designer instead of four
 * unrelated entries, and it is the naming Google Fonts already uses. Install all
 * four; an element set to bold in a family whose bold was never installed falls
 * back to the regular face.
 *
 *   php local/academy/cli/install_pdf_font.php --list
 *   php local/academy/cli/install_pdf_font.php --file=/tmp/Cairo-Regular.ttf
 *   php local/academy/cli/install_pdf_font.php --dir=/tmp/cairo
 *   php local/academy/cli/install_pdf_font.php --file=/tmp/X.ttf --name=cairob
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/pdflib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'file'    => '',      // One .ttf/.otf to install.
        'dir'     => '',      // A directory of them - the usual Google Fonts download.
        'name'    => '',      // Force the font key instead of deriving it from the filename.
        'list'    => false,   // Show what is installed and which fonts can draw Arabic.
        'dry-run' => false,   // Say what would happen, write nothing.
        'help'    => false,
    ],
    ['h' => 'help']
);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help'] || (!$options['list'] && $options['file'] === '' && $options['dir'] === '')) {
    cli_writeln("Install a TrueType font for certificate PDFs.\n");
    cli_writeln("Moodle bundles only two fonts that can draw Arabic (Free Serif, Free Mono).");
    cli_writeln("This installs a real one into moodledata/fonts, where it survives upgrades.\n");
    cli_writeln("Options:");
    cli_writeln("  --file=PATH   A .ttf or .otf file to install.");
    cli_writeln("  --dir=PATH    Install every .ttf/.otf in this directory.");
    cli_writeln("  --name=KEY    Force the font key (default: derived from the filename).");
    cli_writeln("  --list        List installed fonts and whether each can draw Arabic.");
    cli_writeln("  --dry-run     Report without writing.");
    cli_writeln("  -h, --help    This help.\n");
    cli_writeln("Name the files the way Google Fonts does and the styles group themselves:");
    cli_writeln("  Cairo-Regular.ttf -> cairo      Cairo-Bold.ttf       -> cairob");
    cli_writeln("  Cairo-Italic.ttf  -> cairoi     Cairo-BoldItalic.ttf -> cairobi\n");
    cli_writeln("Examples:");
    cli_writeln("  php local/academy/cli/install_pdf_font.php --dir=/tmp/cairo");
    cli_writeln("  php local/academy/cli/install_pdf_font.php --file=/tmp/Cairo-Bold.ttf");
    cli_writeln("  php local/academy/cli/install_pdf_font.php --list");
    exit(0);
}

$dryrun = (bool) $options['dry-run'];
$coredir = $CFG->dirroot . '/lib/tcpdf/fonts/';
$customdir = rtrim($CFG->dataroot . '/fonts', '/\\') . '/';

// Coverage is decided by \local_academy\pdf_fonts, the same class the certificate
// consults while it renders - so what this script reports and what a learner's
// PDF actually does can never drift apart. It takes an explicit directory because
// a font written during this run lands somewhere TCPDF will only start reading on
// the next request.

// ----------------------------------------------------------------- list mode.

if ($options['list']) {
    $active = TCPDF_FONTS::_getfontpath();

    cli_writeln('Active font directory : ' . $active);
    cli_writeln('Custom font directory : ' . $customdir
        . (is_dir($customdir) ? '' : '   (does not exist yet)'));
    cli_writeln('');

    $definitions = glob($active . '*.php') ?: [];
    sort($definitions);

    $arabic = [];
    foreach ($definitions as $definition) {
        $key = basename($definition, '.php');
        if (\local_academy\pdf_fonts::covers_arabic($key, $active)) {
            $arabic[] = $key;
        }
    }

    cli_writeln('Fonts installed : ' . count($definitions));
    cli_writeln('Can draw Arabic : ' . (count($arabic) ? implode(', ', $arabic) : 'NONE'));
    cli_writeln('');
    cli_writeln('Anything not on that line prints Arabic as empty boxes.');
    exit(0);
}

// -------------------------------------------------------- collect the sources.

$sources = [];

if ($options['file'] !== '') {
    $sources[] = $options['file'];
}

if ($options['dir'] !== '') {
    $dir = rtrim($options['dir'], '/\\');

    if (!is_dir($dir)) {
        cli_error('Not a directory: ' . $dir);
    }

    foreach (['ttf', 'TTF', 'otf', 'OTF'] as $extension) {
        foreach (glob($dir . '/*.' . $extension) ?: [] as $found) {
            $sources[] = $found;
        }
    }

    if (empty($sources)) {
        cli_error('No .ttf or .otf files in ' . $dir);
    }
}

if (count($sources) > 1 && $options['name'] !== '') {
    cli_error('--name names a single font; use it with --file, not --dir.');
}

foreach ($sources as $source) {
    if (!is_readable($source)) {
        cli_error('Cannot read font file: ' . $source);
    }
}

sort($sources);

// ---------------------------------------------- make moodledata/fonts usable.

if (!is_dir($customdir)) {
    cli_writeln(($dryrun ? 'Would create ' : 'Creating ') . $customdir);
    if (!$dryrun && !make_writable_directory($customdir, false)) {
        cli_error('Could not create ' . $customdir);
    }
}

// Moodle ignores the custom directory entirely unless the standard families are
// in it, so bring across everything bundled that is not there yet. Existing files
// are never overwritten - a font already sitting here is the site's, not ours.
$mirrored = 0;

foreach (glob($coredir . '*') ?: [] as $bundled) {
    if (!is_file($bundled)) {
        continue;
    }

    $target = $customdir . basename($bundled);
    if (file_exists($target)) {
        continue;
    }

    if (!$dryrun && !@copy($bundled, $target)) {
        cli_error('Could not copy ' . basename($bundled) . ' into ' . $customdir);
    }

    $mirrored++;
}

if ($mirrored) {
    cli_writeln(($dryrun ? 'Would mirror ' : 'Mirrored ') . $mirrored
        . ' bundled font file(s) into ' . $customdir);
    cli_writeln('(copied, not moved - lib/tcpdf/fonts is untouched)');
    cli_writeln('');
}

// ------------------------------------------------------------- install each.

$installed = [];

foreach ($sources as $source) {
    $label = basename($source);

    // TCPDF names the font after the file, so forcing a key means handing it the
    // filename it should have had. Staged as a copy rather than renamed: the
    // source belongs to the administrator and this script does not alter it.
    $convert = $source;

    if ($options['name'] !== '') {
        $forced = preg_replace('/[^a-z0-9_]/', '', core_text::strtolower($options['name']));

        if ($forced === '') {
            cli_error('--name must contain at least one letter or digit.');
        }

        $staged = make_request_directory() . '/' . $forced . '.' . pathinfo($source, PATHINFO_EXTENSION);

        if (!$dryrun) {
            if (!@copy($source, $staged)) {
                cli_error('Could not stage ' . $source);
            }
            $convert = $staged;
        }
    }

    if ($dryrun) {
        cli_writeln('Would install ' . $label);
        continue;
    }

    // TrueTypeUnicode is what makes the font subset-embedded and addressable by
    // codepoint - the prerequisite for any non-Latin script.
    $key = TCPDF_FONTS::addTTFfont($convert, 'TrueTypeUnicode', '', 32, $customdir);

    if ($key === false) {
        cli_writeln('FAILED    ' . $label . '   (TCPDF could not read this font)');
        continue;
    }

    $arabic = \local_academy\pdf_fonts::covers_arabic($key, $customdir);
    $installed[$key] = $arabic;

    cli_writeln('installed ' . str_pad($key, 16) . $label . '   Arabic: ' . ($arabic ? 'yes' : 'NO'));
}

cli_writeln('');

if ($dryrun) {
    cli_writeln('Dry run - nothing was written.');
    exit(0);
}

if (empty($installed)) {
    cli_error('No fonts were installed.');
}

// ------------------------------------------------------------------- report.

$noarabic = array_keys(array_filter($installed, function ($arabic) {
    return !$arabic;
}));

if ($noarabic) {
    cli_writeln('Note: ' . implode(', ', $noarabic) . ' contain(s) no Arabic glyphs.');
    cli_writeln('Fine for Latin text, but Arabic in them still prints as boxes.');
    cli_writeln('');
}

cli_writeln('Next:');
cli_writeln('  1. php admin/cli/purge_caches.php');
cli_writeln('  2. Certificate designer -> edit each element -> Font -> '
    . implode(' / ', array_keys($installed)));
cli_writeln('');
cli_writeln('Verify with: php local/academy/cli/install_pdf_font.php --list');

exit(0);
