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
 * Every category, as a filterable grid of cards.
 *
 * This is where the home page's category strip goes when you ask it for more. The card is
 * the one from theme_nit's home_categories_block, rebuilt in markup rather than cloned by
 * script, and the filter panel is the catalogue's — applied to what each category holds, so
 * ticking "Advanced" removes the categories with no Advanced course and rewrites the count
 * on the ones that stay (see {@see \local_nit_category\category_browser}).
 *
 * AC-4.8.1 asks that the count update without a full page reload, and it does: the results
 * region re-renders itself over fetch(). The page is still a plain GET form underneath, so
 * every state has its own address, the Back button works and the whole thing degrades to
 * "press Apply" with scripting off. That is also why the request is served twice from this
 * one file — `fragment=1` returns just the region the script swaps in, from exactly the
 * same code that rendered it the first time, which is the only way the two can never drift
 * apart.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');
require_once($CFG->libdir . '/filelib.php');

use local_nit_category\catalogue;
use local_nit_category\category_browser;
use local_nit_category\text_util;

// Same rule as the catalogue: a site that requires login to browse requires it here too.
if (!empty($CFG->forcelogin)) {
    require_login();
}

$subs     = optional_param('subs', 0, PARAM_BOOL);
$sort     = optional_param('sort', category_browser::SORT_COURSES, PARAM_ALPHA);
$fragment = optional_param('fragment', 0, PARAM_BOOL);
if (!array_key_exists($sort, category_browser::sort_options())) {
    $sort = category_browser::SORT_COURSES;
}

$baseurl = new moodle_url('/local/nit_category/categories.php');
$context = context_system::instance();

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth');
$PAGE->set_title(get_string('categories'));
$PAGE->set_heading(get_string('allcategories', 'local_nit_category'));

// One catalogue over the whole site supplies both the filter definitions and the courses
// the cards are counted from. Reusing it is the point: the panel here and the panel on
// catalogue.php are the same panel, reading the same URL parameters.
$catalogue = new catalogue(0);
$catalogue->read_request();

$active = $catalogue->active();
$filters = $catalogue->filters();
$facets = $catalogue->facets();

$browser = new category_browser($catalogue, (bool) $subs, $sort);
$rows = $browser->rows();

$hascheckout = local_nit_category_require_checkout();

/**
 * Build a link to this page with some parameters changed.
 *
 * Not a moodle_url, for the same reason the catalogue's is not: the ticked filter values
 * are repeated query parameters, which moodle_url cannot express.
 *
 * @param array $overrides values to set; null removes a parameter
 * @return string
 */
$linkto = function (array $overrides) use ($baseurl, $active, $sort, $subs, $filters): string {
    $query = [];
    if (isset($active['q'])) {
        $query['q'] = $active['q'];
    }
    if (!empty($active['free'])) {
        $query['free'] = 1;
    }
    foreach (['pricemin', 'pricemax'] as $key) {
        if (isset($active[$key])) {
            $query[$key] = $active[$key];
        }
    }
    foreach ($filters as $shortname => $filter) {
        if (!isset($active[$shortname])) {
            continue;
        }
        if ($filter['kind'] === catalogue::KIND_OPTIONS) {
            $query['f_' . $shortname] = $active[$shortname];
        } else if ($filter['kind'] === catalogue::KIND_BOOL) {
            $query['f_' . $shortname] = 1;
        } else {
            foreach (['min', 'max'] as $edge) {
                if (isset($active[$shortname][$edge])) {
                    $query[$edge . '_' . $shortname] = $active[$shortname][$edge];
                }
            }
        }
    }
    $query['sort'] = $sort;
    if ($subs) {
        $query['subs'] = 1;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $string = http_build_query($query, '', '&');
    return $baseurl->out_omit_querystring() . ($string !== '' ? '?' . $string : '');
};

/**
 * A link that removes one value from the current filters — what an active-filter chip does.
 *
 * @param string $param the query parameter
 * @param string|null $value the single value to drop, or null to drop the parameter
 * @return string
 */
$removelink = function (string $param, ?string $value = null) use ($linkto, $active): string {
    if ($value === null) {
        return $linkto([$param => null]);
    }
    $shortname = preg_replace('/^f_/', '', $param);
    $kept = array_values(array_filter($active[$shortname] ?? [], static fn($v) => (string) $v !== $value));
    return $linkto([$param => $kept ?: null]);
};

// "Clear all" built from the filter definitions rather than written out, so a custom field
// added tomorrow is cleared by it without anybody remembering to come back here.
$clearparams = ['q' => null, 'free' => null, 'pricemin' => null, 'pricemax' => null];
foreach ($filters as $shortname => $filter) {
    if ($filter['kind'] === catalogue::KIND_RANGE) {
        $clearparams['min_' . $shortname] = null;
        $clearparams['max_' . $shortname] = null;
    } else {
        $clearparams['f_' . $shortname] = null;
    }
}
$clearall = $linkto($clearparams);

/**
 * "1 category" / "9 categories" — a count with the right noun.
 *
 * @param int $count
 * @return string
 */
$categorycount = function (int $count): string {
    return $count === 1
        ? get_string('onecategory', 'local_nit_category')
        : get_string('categoriesfound', 'local_nit_category', $count);
};

/**
 * The results region: the filter panel, the toolbar, the active-filter chips and the grid.
 *
 * Everything whose content depends on a filter lives in here, and nothing else does — which
 * is what lets the script replace it wholesale and lets the search box outside keep the
 * caret the reader is typing into.
 *
 * @return void
 */
$renderregion = function () use ($rows, $browser, $active, $facets, $filters, $sort, $subs,
        $clearall, $linkto, $removelink, $categorycount, $hascheckout) {
    ?>
    <aside class="nitcat__side">
      <div class="nitcat__sidehead">
        <h2 class="nitcat__sidetitle"><?= s(get_string('filters', 'local_nit_category')) ?></h2>
        <?php if (!empty($active)): ?>
          <a class="nitcat__clear" href="<?= s($clearall) ?>"><?= s(get_string('clearall', 'local_nit_category')) ?></a>
        <?php endif; ?>
      </div>

      <!-- Depth first, because it changes what the other filters are counting. -->
      <fieldset class="nitcat__group nitcat__group--flat">
        <legend class="visually-hidden"><?= s(get_string('categorydepth', 'local_nit_category')) ?></legend>
        <label class="nitcat__opt">
          <input type="checkbox" name="subs" value="1" <?= $subs ? 'checked' : '' ?>>
          <span class="nitcat__optlabel"><?= s(get_string('includesubcategories', 'local_nit_category')) ?></span>
        </label>
      </fieldset>

      <?php foreach ($facets as $facet): ?>
        <?php if ($facet['kind'] === catalogue::KIND_OPTIONS): ?>
          <fieldset class="nitcat__group" data-nitcat-group>
            <legend><?= s($facet['name']) ?></legend>
            <div class="nitcat__opts">
              <?php foreach ($facet['values'] as $i => $option): ?>
                <label class="nitcat__opt<?= $i >= catalogue::OPTIONS_VISIBLE ? ' is-extra' : '' ?>">
                  <input type="checkbox" name="f_<?= s($facet['shortname']) ?>[]" value="<?= s($option['key']) ?>"
                         <?= $option['selected'] ? 'checked' : '' ?>>
                  <span class="nitcat__optlabel"><?= s($option['label']) ?></span>
                  <span class="nitcat__optcount"><?= (int) $option['count'] ?></span>
                </label>
              <?php endforeach; ?>
            </div>
            <?php if (count($facet['values']) > catalogue::OPTIONS_VISIBLE): ?>
              <button type="button" class="nitcat__more" data-nitcat-more
                      data-less="<?= s(get_string('showfewer', 'local_nit_category')) ?>"><?=
                s(get_string('showall', 'local_nit_category', count($facet['values']))) ?></button>
            <?php endif; ?>
          </fieldset>

        <?php elseif ($facet['kind'] === catalogue::KIND_BOOL): ?>
          <fieldset class="nitcat__group nitcat__group--flat">
            <legend class="visually-hidden"><?= s($facet['name']) ?></legend>
            <label class="nitcat__opt">
              <input type="checkbox" name="f_<?= s($facet['shortname']) ?>" value="1"
                     <?= !empty($facet['selected']) ? 'checked' : '' ?>>
              <span class="nitcat__optlabel"><?= s($facet['name']) ?></span>
              <span class="nitcat__optcount"><?= (int) $facet['count'] ?></span>
            </label>
          </fieldset>

        <?php else: // A numeric range — duration, and anything else an admin models as a number. ?>
          <fieldset class="nitcat__group">
            <legend><?= s($facet['name']) ?></legend>
            <div class="nitcat__range">
              <label>
                <span class="nitcat__rangelab"><?= s(get_string('from', 'local_nit_category')) ?></span>
                <input type="number" inputmode="decimal" name="min_<?= s($facet['shortname']) ?>"
                       value="<?= $facet['min'] !== null ? s(text_util::number($facet['min'])) : '' ?>"
                       placeholder="<?= s(text_util::number($facet['bound_min'])) ?>">
              </label>
              <label>
                <span class="nitcat__rangelab"><?= s(get_string('to', 'local_nit_category')) ?></span>
                <input type="number" inputmode="decimal" name="max_<?= s($facet['shortname']) ?>"
                       value="<?= $facet['max'] !== null ? s(text_util::number($facet['max'])) : '' ?>"
                       placeholder="<?= s(text_util::number($facet['bound_max'])) ?>">
              </label>
            </div>
          </fieldset>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if ($hascheckout): ?>
        <fieldset class="nitcat__group">
          <legend><?= s(get_string('price', 'local_nit_category')) ?></legend>
          <label class="nitcat__opt">
            <input type="checkbox" name="free" value="1" <?= !empty($active['free']) ? 'checked' : '' ?>>
            <span class="nitcat__optlabel"><?= s(get_string('freeonly', 'local_nit_category')) ?></span>
          </label>
          <div class="nitcat__range">
            <label>
              <span class="nitcat__rangelab"><?= s(get_string('from', 'local_nit_category')) ?></span>
              <input type="number" inputmode="decimal" min="0" name="pricemin"
                     value="<?= isset($active['pricemin']) ? s(text_util::number($active['pricemin'])) : '' ?>">
            </label>
            <label>
              <span class="nitcat__rangelab"><?= s(get_string('to', 'local_nit_category')) ?></span>
              <input type="number" inputmode="decimal" min="0" name="pricemax"
                     value="<?= isset($active['pricemax']) ? s(text_util::number($active['pricemax'])) : '' ?>">
            </label>
          </div>
        </fieldset>
      <?php endif; ?>

      <!-- With scripting off this is the only way to apply a tick, so it is a real button.
           The script hides it and re-renders on change instead. -->
      <button type="submit" class="btn btn-primary fw-bold nitcat__apply" data-nitcat-apply>
        <?= s(get_string('applyfilters', 'local_nit_category')) ?>
      </button>
    </aside>

    <main class="nitcat__main">

      <div class="nitcat__toolbar">
        <!-- aria-live: with the reload gone, this number changing is the only signal that
             a tick did anything, so a screen reader has to be told about it. -->
        <div class="nitcat__count" aria-live="polite" data-nitcats-count>
          <strong><?= s($categorycount(count($rows))) ?></strong>
          <?php if (count($rows) !== $browser->total()): ?>
            <span class="nitcat__countof"><?= s(get_string('ofcategories', 'local_nit_category',
              $browser->total())) ?></span>
          <?php endif; ?>
        </div>
        <label class="nitcat__sort">
          <span><?= s(get_string('sortby', 'local_nit_category')) ?></span>
          <select name="sort">
            <?php foreach (category_browser::sort_options() as $key => $label): ?>
              <option value="<?= s($key) ?>" <?= $key === $sort ? 'selected' : '' ?>><?= s($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <?php if (!empty($active)): ?>
        <div class="nitcat__chips">
          <?php if (isset($active['q'])): ?>
            <a class="nitcat__chip" href="<?= s($removelink('q')) ?>">
              <span>“<?= s($active['q']) ?>”</span><span aria-hidden="true">×</span>
            </a>
          <?php endif; ?>
          <?php foreach ($facets as $facet): ?>
            <?php if ($facet['kind'] === catalogue::KIND_OPTIONS): ?>
              <?php foreach ($facet['values'] as $option): ?>
                <?php if ($option['selected']): ?>
                  <a class="nitcat__chip" href="<?= s($removelink('f_' . $facet['shortname'], $option['key'])) ?>">
                    <span><?= s($option['label']) ?></span><span aria-hidden="true">×</span>
                  </a>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php elseif ($facet['kind'] === catalogue::KIND_BOOL && !empty($facet['selected'])): ?>
              <a class="nitcat__chip" href="<?= s($removelink('f_' . $facet['shortname'])) ?>">
                <span><?= s($facet['name']) ?></span><span aria-hidden="true">×</span>
              </a>
            <?php elseif ($facet['kind'] === catalogue::KIND_RANGE && ($facet['min'] !== null || $facet['max'] !== null)): ?>
              <a class="nitcat__chip" href="<?= s($linkto([
                    'min_' . $facet['shortname'] => null, 'max_' . $facet['shortname'] => null])) ?>">
                <span><?= s($facet['name']) ?>: <?= s(text_util::number($facet['min'] ?? $facet['bound_min'])) ?>–<?=
                    s(text_util::number($facet['max'] ?? $facet['bound_max'])) ?></span><span aria-hidden="true">×</span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (!empty($active['free'])): ?>
            <a class="nitcat__chip" href="<?= s($removelink('free')) ?>">
              <span><?= s(get_string('freeonly', 'local_nit_category')) ?></span><span aria-hidden="true">×</span>
            </a>
          <?php endif; ?>
          <?php if (isset($active['pricemin']) || isset($active['pricemax'])): ?>
            <a class="nitcat__chip" href="<?= s($linkto(['pricemin' => null, 'pricemax' => null])) ?>">
              <span><?= s(get_string('price', 'local_nit_category')) ?>: <?=
                  s(text_util::number($active['pricemin'] ?? 0)) ?>–<?=
                  s(isset($active['pricemax']) ? text_util::number($active['pricemax']) : '∞') ?></span><span aria-hidden="true">×</span>
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (empty($rows)): ?>
        <div class="nitcat__empty">
          <div class="nitcat__emptyicon" aria-hidden="true">🔍</div>
          <p class="nitcat__emptytitle"><?= s(get_string('nocategories', 'local_nit_category')) ?></p>
          <p class="nitcat__emptyhint"><?= s($subs
            ? get_string('nocategorieshint', 'local_nit_category')
            : get_string('nocategorieshintsubs', 'local_nit_category')) ?></p>
          <?php if (!$subs): ?>
            <a class="btn btn-outline-primary fw-bold" href="<?= s($linkto(['subs' => 1])) ?>"><?=
              s(get_string('includesubcategories', 'local_nit_category')) ?></a>
          <?php endif; ?>
          <a class="btn btn-outline-primary fw-bold" href="<?= s($clearall) ?>"><?=
            s(get_string('clearall', 'local_nit_category')) ?></a>
        </div>
      <?php else: ?>
        <!-- The card is theme_nit's home_categories_block card, element for element: the
             374:216 picture panel, the title, the course count, and the 44px "More" footer
             with the arrow that flips itself in Arabic. The block has to build its cards by
             cloning a hidden template, because block_nit_section prints its HTML verbatim
             with no template engine behind it; this page has a server, so the cards are
             simply written out — which is also how a search engine and a screen reader get
             to read them.

             The picture is a background rather than an <img> so the fixed ratio crops it
             the same way the block's does, and so an emoji can take the same slot. It is
             decorative either way: the card's accessible name is its heading. -->
        <div class="nitcats-grid">
          <?php foreach ($rows as $row): ?>
            <?php $picture = $row['image'] !== '' ? $row['image'] : $row['icon']; ?>
            <a class="nitcats-card" href="<?= s($row['url']) ?>">
              <div class="nitcats-card__media"<?php
                  if ($picture !== '') {
                      echo ' style="background-image: url(&quot;' . s($picture) . '&quot;);"';
                  }
                  ?>>
                <?php if ($picture === ''): ?>
                  <span class="nitcats-card__emoji" aria-hidden="true"><?=
                    s($row['emoji'] !== '' ? $row['emoji'] : '🎓') ?></span>
                <?php endif; ?>
              </div>
              <div class="nitcats-card__body">
                <?php if ($row['parentname'] !== ''): ?>
                  <p class="nitcats-card__parent"><?= s($row['parentname']) ?></p>
                <?php endif; ?>
                <h3 class="nitcats-card__title"><?= s($row['name']) ?></h3>
                <p class="nitcats-card__count">
                  <?= s($row['count'] === 1
                    ? get_string('onecourse', 'local_nit_category')
                    : get_string('coursesfound', 'local_nit_category', $row['count'])) ?>
                </p>
                <?php if ($row['description'] !== ''): ?>
                  <p class="nitcats-card__desc"><?= s($row['description']) ?></p>
                <?php endif; ?>
              </div>
              <div class="nitcats-card__cta">
                <span><?= s(get_string('viewmore', 'local_nit_category')) ?></span>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"
                     aria-hidden="true" focusable="false">
                  <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"></path>
                </svg>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
    <?php
};

// ─────────────────────────────────────────────────────────────────────────────────────────
// The script's re-render asks for this and nothing else. Same closure, same request
// handling, same output — only the page furniture is left off.
// ─────────────────────────────────────────────────────────────────────────────────────────
if ($fragment) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    $renderregion();
    exit;
}

// The eight local colour slots map to brand roles by job, exactly as the catalogue and the
// category page do, so all three are one design under one palette and re-skin together.
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

<div dir="auto" class="nitcat" style="<?= $stylevars ?>">
<form method="get" action="<?= s($baseurl->out_omit_querystring()) ?>" class="nitcat__form" data-nitcats-form>

  <header class="nitcat__head">
    <nav class="nitcat__crumbs" aria-label="<?= s(get_string('breadcrumb', 'local_nit_category')) ?>">
      <a href="<?= s((new moodle_url('/'))->out()) ?>"><?= s(get_string('home')) ?></a>
      <span aria-hidden="true">›</span>
      <span aria-current="page"><?= s(get_string('allcategories', 'local_nit_category')) ?></span>
    </nav>

    <h1 class="nitcat__title"><?= s(get_string('allcategories', 'local_nit_category')) ?></h1>
    <p class="nitcat__intro"><?= s(get_string('allcategoriesintro', 'local_nit_category')) ?></p>

    <div class="nitcat__search">
      <label class="sr-only visually-hidden" for="nitcats-q"><?= s(get_string('searchcategories', 'local_nit_category')) ?></label>
      <input type="search" id="nitcats-q" name="q" value="<?= s($active['q'] ?? '') ?>"
             placeholder="<?= s(get_string('searchcategories', 'local_nit_category')) ?>">
      <button type="submit" class="btn btn-primary fw-bold"><?= s(get_string('search')) ?></button>
    </div>
  </header>

  <div class="nitcat__body" data-nitcats-region>
    <?php $renderregion(); ?>
  </div>
</form>
</div>

<?php
$PAGE->requires->js(new moodle_url('/local/nit_category/categories.js'));

echo $OUTPUT->footer();
