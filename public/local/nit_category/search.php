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
 * One file answers the same question three ways, which is the whole point of it being one
 * file — the drop-down under the navbar and the full results page can never disagree about
 * what "digital marketing" finds, because there is one engine ({@see site_search}) and one
 * set of result rows below:
 *
 *   (no parameter)  the results page: every group, every row, with the totals.
 *   fragment=1      the same groups, capped and stripped of page furniture — the panel the
 *                   navbar script drops under the search box while you type.
 *   action=logmiss  no output; records a term that found nothing (AC-4.22.4). The page
 *                   itself records a miss without being asked; this exists for the panel,
 *                   where a learner may read "nothing found" and never press Enter.
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

$context = context_system::instance();
$PAGE->set_url($baseurl, $search->query() !== '' ? ['q' => $search->query()] : []);
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title(get_string('searchtitle', 'local_nit_category'));
$PAGE->set_heading(get_string('searchtitle', 'local_nit_category'));

// The panel is a preview, so it shows a handful; the page shows a screenful. Neither
// lists everything: a one-word term can match hundreds of courses, and every row drawn
// costs a price lookup and a file-area query. What is left over is one link away, on the
// page built to sort and filter that many results.
$limit = $fragment ? 5 : 24;
$groups = $search->is_answerable() ? $search->groups($limit) : [];
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
                    'trailhtml' => '<span class="nitsearch-row__note">' . s($row['count'] === 1
                        ? get_string('onecourse', 'local_nit_category')
                        : get_string('coursesfound', 'local_nit_category', $row['count'])) . '</span>',
                ]);
                ?>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>

          <?php if ($group['more'] > 0): ?>
            <!-- The overflow goes to the page that can do something with it: the catalogue
                 for courses, the category grid for categories, both already carrying the
                 term and both with a filter panel. -->
            <a class="nitsearch__more" href="<?= s($group['url']) ?>"><?=
              s(get_string('searchmoreresults', 'local_nit_category', $group['more'])) ?></a>
          <?php endif; ?>
        </section>
        <?php
    }
};

// ─────────────────────────────────────────────────────────────────────────────────────────
// fragment=1 — the panel under the navbar box. Same groups, same rows, no page furniture.
//
// data-nitsearch-total is what the navbar script watches: a zero there is what makes it
// report the miss back to action=logmiss a moment later, once the typing has settled.
// ─────────────────────────────────────────────────────────────────────────────────────────
if ($fragment) {
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
        <a class="nitsearch__all" href="<?= s($search->url()) ?>"><?=
          s($total === 1
            ? get_string('searchseeallone', 'local_nit_category')
            : get_string('searchseeall', 'local_nit_category', $total)) ?></a>
      <?php endif; ?>
    </div>
    <?php
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────────────────
// The full page.
//
// AC-4.22.4: a term that reached this page and found nothing is what EAAC wants to see in
// the report, so it is recorded here — deliberately after the results are known, and only
// for a term that could have matched something.
// ─────────────────────────────────────────────────────────────────────────────────────────
if ($search->is_answerable() && $total === 0) {
    search_log::record_miss($search->query());
}

// The eight local colour slots map to brand roles by job, exactly as the catalogue and the
// category grid do, so all three are one design under one palette and re-skin together.
$stylevars =
    '--cbg1: var(--nit-brand-background); '
  . '--cbg2: var(--nit-brand-surface); '
  . '--cbg3: color-mix(in srgb, var(--nit-brand-surface) 88%, var(--nit-brand-textprimary)); '
  . '--cbg4: var(--nit-brand-primary); '
  . '--ctext1: var(--nit-brand-textprimary); '
  . '--ctext2: var(--nit-brand-textsecondary); '
  . '--ctext3: var(--nit-brand-accenttext); '
  . '--caccent: var(--nit-brand-accent); '
  . '--ctext4: var(--nit-brand-textprimary); '
  . '--cborder: var(--nit-brand-borderprimary); '
  . '--csuccess: var(--nit-brand-success); ';

echo $OUTPUT->header();
?>

<div dir="auto" class="nitcat nitsearch" style="<?= $stylevars ?>">
 <div class="nitsearch__wrap">
  <header class="nitcat__head">
    <nav class="nitcat__crumbs" aria-label="<?= s(get_string('breadcrumb', 'local_nit_category')) ?>">
      <a href="<?= s((new moodle_url('/'))->out()) ?>"><?= s(get_string('home')) ?></a>
      <span aria-hidden="true">›</span>
      <span aria-current="page"><?= s(get_string('searchtitle', 'local_nit_category')) ?></span>
    </nav>

    <h1 class="nitcat__title"><?= s(get_string('searchtitle', 'local_nit_category')) ?></h1>

    <form method="get" action="<?= s($baseurl->out_omit_querystring()) ?>" class="nitcat__search" role="search">
      <label class="sr-only visually-hidden" for="nitsearch-q"><?=
        s(get_string('searchplaceholder', 'local_nit_category')) ?></label>
      <input type="search" id="nitsearch-q" name="q" value="<?= s($search->query()) ?>"
             placeholder="<?= s(get_string('searchplaceholder', 'local_nit_category')) ?>" autofocus>
      <button type="submit" class="btn btn-primary fw-bold"><?= s(get_string('search')) ?></button>
    </form>

    <?php if ($search->query() === ''): ?>
      <p class="nitcat__intro"><?= s(get_string('searchhint', 'local_nit_category')) ?></p>
    <?php elseif (!$search->is_answerable()): ?>
      <p class="nitcat__intro"><?= s(get_string('searchtooshort', 'local_nit_category',
        site_search::MIN_LENGTH)) ?></p>
    <?php else: ?>
      <p class="nitcat__total" role="status">
        <?= s($total === 1
          ? get_string('searchoneresult', 'local_nit_category', $search->query())
          : get_string('searchresults', 'local_nit_category',
              ['count' => $total, 'query' => $search->query()])) ?>
      </p>
    <?php endif; ?>
  </header>

  <div class="nitsearch__body">
    <?php if ($search->is_answerable() && $total === 0): ?>
      <div class="nitcat__empty">
        <div class="nitcat__emptyicon" aria-hidden="true">🔍</div>
        <p class="nitcat__emptytitle"><?= s(get_string('searchnothing', 'local_nit_category',
          $search->query())) ?></p>
        <p class="nitcat__emptyhint"><?= s(get_string('searchnothinghint', 'local_nit_category')) ?></p>
        <div class="nitsearch__emptylinks">
          <a class="btn btn-outline-primary fw-bold" href="<?=
            s((new moodle_url('/local/nit_category/catalogue.php'))->out()) ?>"><?=
            s(get_string('catalogue', 'local_nit_category')) ?></a>
          <a class="btn btn-outline-primary fw-bold" href="<?=
            s((new moodle_url('/local/nit_category/categories.php'))->out()) ?>"><?=
            s(get_string('allcategories', 'local_nit_category')) ?></a>
        </div>
      </div>
    <?php else: ?>
      <?php $rendergroups(); ?>

      <?php if ($total > 0): ?>
        <!-- The results are a list, on purpose: what to do with a result is a decision the
             catalogue is built for. Rather than grow a second filter panel here, the two
             pages that already have one are offered the same term. -->
        <div class="nitsearch__refine">
          <span><?= s(get_string('searchrefine', 'local_nit_category')) ?></span>
          <a class="btn btn-outline-primary fw-bold" href="<?= s($search->catalogue_url()) ?>"><?=
            s(get_string('searchrefinecourses', 'local_nit_category')) ?></a>
          <a class="btn btn-outline-primary fw-bold" href="<?= s($search->categories_url()) ?>"><?=
            s(get_string('searchrefinecategories', 'local_nit_category')) ?></a>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
 </div>
</div>

<?php
echo $OUTPUT->footer();
