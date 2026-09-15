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
 * What the header search box finds (SRS 4.22).
 *
 * The endpoint behind the header search box. One engine ({@see site_search}) answers
 * three requests:
 *
 *   fragment=1      the result groups, capped and stripped of page furniture — the panel
 *                   the navbar script drops under the search box while you type.
 *   action=logmiss  no output; records a term that found nothing (AC-4.22.4). This exists
 *                   for the panel, where a learner may read "nothing found" and never
 *                   press Enter.
 *   (no parameter)  Enter in the box, or the panel's "see all" link: a redirect to the
 *                   catalogue carrying the same term. There is no results page of its own —
 *                   the catalogue already lists what the term finds and has the filter
 *                   panel to do something with it, so a page between the two was one
 *                   click for nothing.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');
require_once($CFG->libdir . '/filelib.php');

use local_nit_category\pricing;
use local_nit_category\search_log;
use local_nit_category\site_search;

// Same rule as the catalogue and the category grid: a site that requires login to browse
// requires it here too. Course visibility is checked by the engine either way.
if (!empty($CFG->forcelogin)) {
    require_login();
}

$fragment = optional_param('fragment', 0, PARAM_BOOL);
$action   = optional_param('action', '', PARAM_ALPHA);

$search = site_search::from_request();
$baseurl = new moodle_url('/local/nit_category/search.php');

// ─────────────────────────────────────────────────────────────────────────────────────────
// action=logmiss — the panel reporting that a term it showed found nothing.
//
// Sesskey-guarded so the log cannot be stuffed from outside a session, and the term is
// re-checked here rather than trusted: the endpoint records a miss only if the search
// really does miss, which keeps a forged request from inventing demand for a course.
// ─────────────────────────────────────────────────────────────────────────────────────────
if ($action === 'logmiss') {
    require_sesskey();
    if ($search->is_answerable() && !$search->has_results()) {
        search_log::record_miss($search->query());
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Frame-Options: DENY');
    http_response_code(204);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────────────
// No parameter — the form submitted, or the panel's "see all" link followed.
//
// AC-4.22.4: a term that found nothing is recorded before handing over, because the
// catalogue does not know it is answering a search. The panel usually reports the miss
// first (see search.js).
// ─────────────────────────────────────────────────────────────────────────────────────────
if (!$fragment) {
    if ($search->is_answerable() && !$search->has_results()) {
        search_log::record_miss($search->query());
    }
    redirect($search->catalogue_url());
}

$context = context_system::instance();
$PAGE->set_url($baseurl, $search->query() !== '' ? ['q' => $search->query()] : []);
$PAGE->set_context($context);

// The panel is a preview, so it shows a handful of each; whatever is left over is one
// link away in the catalogue.
$groups = $search->is_answerable() ? $search->groups(5, 5) : [];
$total = $search->is_answerable() ? $search->total() : 0;

$countrynotice = pricing::country_notice();

/**
 * The price a result row prints: the discounted pair where an offer is live, the plain
 * price otherwise, "Free" where the course is not sold, and nothing at all where a price
 * exists but no rule resolves to an amount for this viewer — saying nothing beats claiming
 * "Free" about a course somebody would be charged for.
 *
 * @param array $info from pricing::info()
 * @return string HTML
 */
$pricetag = function (array $info) use ($countrynotice): string {
    if (!empty($info['countryrequired'])) {
        return $countrynotice ? '<span class="nitsearch-row__note">' . s($countrynotice['short']) . '</span>' : '';
    }
    if ($info['enrolled'] || $info['purchased']) {
        return '<span class="nitcat-badge nitcat-badge--ok">&#10003; '
            . s(get_string($info['enrolled'] ? 'enrolled' : 'purchased', 'local_nit_category')) . '</span>';
    }
    if (!empty($info['covered'])) {
        return '<span class="nitcat-badge nitcat-badge--sub">&#9733; '
            . s(get_string('insubscription', 'local_nit_category')) . '</span>';
    }
    if ($info['offerlabel'] !== '' && $info['offerfinal'] > 0) {
        return '<span class="nitcat-price-was">' . s(pricing::money($info['price'], $info['currency'])) . '</span>'
            . '<span class="nitcat-price-now">' . s(pricing::money($info['offerfinal'], $info['currency'])) . '</span>';
    }
    if ($info['price'] > 0) {
        return '<span class="nitcat-price-now">' . s(pricing::money($info['price'], $info['currency'])) . '</span>';
    }
    if (!$info['haspricing']) {
        return '<span class="nitcat-card__free">' . s(get_string('free', 'local_nit_category')) . '</span>';
    }
    return '';
};

/**
 * One result row, whichever group it belongs to.
 *
 * Both kinds of result are the same shape — a picture, a line of context, a title and a
 * trailing note — because they sit in one list under two headings, and a reader scanning
 * that list should not have to learn two layouts. The differences are in what fills the
 * slots, not in the slots.
 *
 * @param array $item picture, url, title, context line, trailing HTML
 * @return void prints
 */
$renderrow = function (array $item): void {
    $picture = (string) ($item['picture'] ?? '');
    ?>
    <a class="nitsearch-row" href="<?= s($item['url']) ?>">
      <span class="nitsearch-row__media"<?php
          if ($picture !== '') {
              echo ' style="background-image: url(&quot;' . s($picture) . '&quot;);"';
          }
          ?>>
        <?php if ($picture === ''): ?>
          <span class="nitsearch-row__glyph" aria-hidden="true"><?= s($item['glyph'] ?? '🎓') ?></span>
        <?php endif; ?>
      </span>
      <span class="nitsearch-row__body">
        <?php if (!empty($item['context'])): ?>
          <span class="nitsearch-row__context"><?= s($item['context']) ?></span>
        <?php endif; ?>
        <span class="nitsearch-row__title"><?= $item['titlehtml'] ?></span>
        <?php if (!empty($item['chips'])): ?>
          <span class="nitsearch-row__chips">
            <?php foreach ($item['chips'] as $chip): ?>
              <span class="nitsearch-row__chip"><?= s($chip) ?></span>
            <?php endforeach; ?>
          </span>
        <?php endif; ?>
      </span>
      <?php if (!empty($item['trailhtml'])): ?>
        <span class="nitsearch-row__trail"><?= $item['trailhtml'] ?></span>
      <?php endif; ?>
    </a>
    <?php
};

/**
 * The groups, each under its own heading with its own count (AC-4.22.3).
 *
 * A group with nothing in it is not drawn — "Categories (0)" is noise, and the total line
 * above already says what was and was not found.
 *
 * @return void prints
 */
$rendergroups = function () use ($groups, $search, $renderrow, $pricetag): void {
    foreach ($groups as $group) {
        if ($group['count'] === 0) {
            continue;
        }
        ?>
        <section class="nitsearch__group" aria-labelledby="nitsearch-h-<?= s($group['key']) ?>">
          <h2 class="nitsearch__grouptitle" id="nitsearch-h-<?= s($group['key']) ?>">
            <span><?= s($group['label']) ?></span>
            <span class="nitsearch__count"><?= (int) $group['count'] ?></span>
          </h2>

          <div class="nitsearch__rows">
            <?php foreach ($group['rows'] as $row): ?>
              <?php if ($group['key'] === site_search::GROUP_COURSES): ?>
                <?php
                $item = $search->present_course($row);
                $renderrow([
                    'url'       => $item['url'],
                    'picture'   => $item['image'],
                    'glyph'     => '📘',
                    'context'   => $item['catname'],
                    'titlehtml' => $item['namehtml'],
                    'chips'     => $item['chips'],
                    'trailhtml' => $pricetag($item['pricing']),
                ]);
                ?>
              <?php else: ?>
                <?php
                $renderrow([
                    'url'       => $row['url'],
                    'picture'   => $row['image'] !== '' ? $row['image'] : $row['icon'],
                    'glyph'     => $row['emoji'] !== '' ? $row['emoji'] : '📂',
                    'context'   => $row['parentname'],
                    'titlehtml' => s($row['name']),
                    'chips'     => [],
                    // A subject area with nothing in it yet is still an answer to "is there
                    // a category called X?", so it is listed — but it says so plainly
                    // rather than printing "0 courses".
                    'trailhtml' => '<span class="nitsearch-row__note">' . s(match (true) {
                        $row['count'] === 0 => get_string('nocoursesyet', 'local_nit_category'),
                        $row['count'] === 1 => get_string('onecourse', 'local_nit_category'),
                        default => get_string('coursesfound', 'local_nit_category', $row['count']),
                    }) . '</span>',
                ]);
                ?>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <?php if ($group['more'] > 0 && $group['url'] !== ''): ?>
            <!-- The overflow goes to the catalogue, which already carries the term and has
                 the filter panel to do something with that many courses. -->
            <a class="nitsearch__more" href="<?= s($group['url']) ?>"><?=
              s(get_string('searchmoreresults', 'local_nit_category', $group['more'])) ?></a>
          <?php endif; ?>
        </section>
        <?php
    }
};

// ─────────────────────────────────────────────────────────────────────────────────────────
// fragment=1 — the panel under the navbar box. Groups and rows, no page furniture.
//
// data-nitsearch-total is what the navbar script watches: a zero there is what makes it
// report the miss back to action=logmiss a moment later, once the typing has settled.
// ─────────────────────────────────────────────────────────────────────────────────────────
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
?>
<div class="nitsearch nitsearch--panel" dir="auto" data-nitsearch-total="<?= (int) $total ?>">
  <?php if (!$search->is_answerable()): ?>
    <p class="nitsearch__hint"><?= s(get_string('searchhint', 'local_nit_category')) ?></p>
  <?php elseif ($total === 0): ?>
    <p class="nitsearch__hint"><?= s(get_string('searchnothing', 'local_nit_category',
      $search->query())) ?></p>
  <?php else: ?>
    <?php $rendergroups(); ?>
    <!-- Straight to the catalogue: it carries the term and has the filters. -->
    <a class="nitsearch__all" href="<?= s($search->catalogue_url()) ?>"><?=
      s($total === 1
        ? get_string('searchseeallone', 'local_nit_category')
        : get_string('searchseeall', 'local_nit_category', $total)) ?></a>
  <?php endif; ?>
</div>
