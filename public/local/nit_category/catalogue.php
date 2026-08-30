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
 * The course catalogue: every course the visitor may see, with filters built from the
 * course custom fields that actually exist.
 *
 * The whole page is one GET form. Ticking a box, typing a search or changing the sort
 * submits it and the server answers with a filtered page — so the catalogue works with
 * scripting off, every result has its own shareable address, and the browser Back button
 * does what it should. The JavaScript only removes the need to press "Apply".
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
use local_nit_category\pricing;
use local_nit_category\text_util;

// Respect the site's forced-login policy: if the site requires login to browse, gate the
// catalogue too. Course visibility checks below apply either way.
if (!empty($CFG->forcelogin)) {
    require_login();
}

$rootid  = optional_param('id', 0, PARAM_INT);            // Browse inside one category, 0 = whole site.
$sort    = optional_param('sort', 'popular', PARAM_ALPHA);
$page    = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', catalogue::DEFAULT_PERPAGE, PARAM_INT);
if (!in_array($perpage, catalogue::PERPAGE_OPTIONS, true)) {
    $perpage = catalogue::DEFAULT_PERPAGE;
}
if (!array_key_exists($sort, catalogue::sort_options())) {
    $sort = 'popular';
}

$root = null;
if ($rootid) {
    $root = core_course_category::get($rootid, MUST_EXIST);
    $context = $root->get_context();
} else {
    $context = context_system::instance();
}

$baseurl = new moodle_url('/local/nit_category/catalogue.php', $rootid ? ['id' => $rootid] : []);
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('nit_fullwidth');

$heading = $root ? text_util::plain($root->get_formatted_name()) : get_string('catalogue', 'local_nit_category');
$PAGE->set_title($heading);
$PAGE->set_heading($heading);

// Build the result set.
$catalogue = new catalogue($rootid);
$catalogue->read_request();
$active = $catalogue->active();
$matches = catalogue::sort($catalogue->matches(), $sort);

$found = count($matches);
$pagecount = max(1, (int) ceil($found / $perpage));
if ($page >= $pagecount) {
    $page = $pagecount - 1;   // A stale page number lands on the last real page, not on nothing.
}
$visible = array_slice($matches, $page * $perpage, $perpage);

$filters = $catalogue->filters();
$facets = $catalogue->facets();
$categoryfacet = $catalogue->category_facet();

$hascheckout = local_nit_category_require_checkout();
$countrynotice = pricing::country_notice();

/**
 * Build a link to this page with some parameters changed.
 *
 * Array parameters (the ticked filter values) are why this is not a moodle_url: the query
 * string has to carry repeated names, which moodle_url does not model.
 *
 * @param array $overrides values to set; null removes a parameter
 * @return string
 */
$linkto = function (array $overrides) use ($baseurl, $active, $sort, $perpage, $filters, $rootid): string {
    $query = [];
    if ($rootid) {
        $query['id'] = $rootid;
    }
    if (isset($active['q'])) {
        $query['q'] = $active['q'];
    }
    if (isset($active['cat'])) {
        $query['cat'] = $active['cat'];
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
    $query['perpage'] = $perpage;

    foreach ($overrides as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    // A changed filter always returns to the first page: page 4 of the old result set is
    // meaningless against the new one.
    if (!array_key_exists('page', $overrides)) {
        unset($query['page']);
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
$removelink = function (string $param, ?string $value = null) use ($linkto, $active, $filters): string {
    if ($value === null) {
        return $linkto([$param => null]);
    }
    // Rebuild the parameter without this one value.
    $shortname = preg_replace('/^f_/', '', $param);
    if ($param === 'cat') {
        $kept = array_values(array_filter($active['cat'] ?? [], static fn($v) => (string) $v !== $value));
    } else {
        $kept = array_values(array_filter($active[$shortname] ?? [], static fn($v) => (string) $v !== $value));
    }
    return $linkto([$param => $kept ?: null]);
};

/**
 * "1 course" / "7 courses" — a count with the right noun.
 *
 * @param int $count
 * @param string $manykey the string to use for every count but one
 * @return string
 */
$coursecount = function (int $count, string $manykey): string {
    return $count === 1
        ? get_string('onecourse', 'local_nit_category')
        : get_string($manykey, 'local_nit_category', $count);
};

// "Clear all": every filter parameter set to null, ranges included. Built from the filter
// definitions rather than written out, so a newly added custom field is cleared by it too.
$clearparams = ['q' => null, 'cat' => null, 'free' => null, 'pricemin' => null, 'pricemax' => null];
foreach ($filters as $shortname => $filter) {
    if ($filter['kind'] === catalogue::KIND_RANGE) {
        $clearparams['min_' . $shortname] = null;
        $clearparams['max_' . $shortname] = null;
    } else {
        $clearparams['f_' . $shortname] = null;
    }
}
$clearall = $linkto($clearparams);

// The 8 local colour slots map to brand roles by job, exactly as the category page does, so
// the two pages are the same design under one palette and re-skin together.
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

$brandgroupclass = '';
$themenitlib = $CFG->dirroot . '/theme/nit/lib.php';
if (file_exists($themenitlib)) {
    require_once($themenitlib);
}
if ($rootid && function_exists('theme_nit_category_brand_group')) {
    $brandgroupclass = theme_nit_brand_group_class(theme_nit_category_brand_group($rootid));
}

/**
 * The price tags a card prints. With a live offer that is the original struck through, the
 * discounted amount and the "-40%" pill; otherwise the plain price; and nothing at all when
 * the course is priced but no rule resolves to an amount — saying nothing beats claiming "Free".
 *
 * @param array $info from pricing::info()
 * @return string
 */
$pricetags = function (array $info) use ($countrynotice): string {
    if (!empty($info['countryrequired']) && $countrynotice) {
        return '<span class="nitcat-price-note">' . s($countrynotice['short']) . '</span>';
    }
    if ($info['offerlabel'] !== '' && $info['offerfinal'] > 0) {
        return '<span class="nitcat-price-was">' . s(pricing::money($info['price'], $info['currency'])) . '</span>'
            . '<span class="nitcat-price-now">' . s(pricing::money($info['offerfinal'], $info['currency'])) . '</span>'
            . '<span class="nitcat-price-off">' . s($info['offerlabel']) . '</span>';
    }
    if ($info['price'] > 0) {
        return '<span class="nitcat-price-now">' . s(pricing::money($info['price'], $info['currency'])) . '</span>';
    }
    return '';
};

echo $OUTPUT->header();
?>

<div dir="auto" class="nitcat<?= $brandgroupclass !== '' ? ' ' . $brandgroupclass : '' ?>" style="<?= $stylevars ?>">
<form method="get" action="<?= s($baseurl->out_omit_querystring()) ?>" class="nitcat__form" data-nitcat-form>
  <?php if ($rootid): ?>
    <input type="hidden" name="id" value="<?= (int) $rootid ?>">
  <?php endif; ?>
  <input type="hidden" name="perpage" value="<?= (int) $perpage ?>">

  <!-- ── Page head: where you are, what this is, and the search box ────────────── -->
  <header class="nitcat__head">
    <nav class="nitcat__crumbs" aria-label="<?= s(get_string('breadcrumb', 'local_nit_category')) ?>">
      <a href="<?= s((new moodle_url('/'))->out()) ?>"><?= s(get_string('home')) ?></a>
      <span aria-hidden="true">›</span>
      <a href="<?= s((new moodle_url('/local/nit_category/catalogue.php'))->out()) ?>"><?= s(get_string('catalogue', 'local_nit_category')) ?></a>
      <?php if ($root): ?>
        <span aria-hidden="true">›</span>
        <span aria-current="page"><?= $root->get_formatted_name() ?></span>
      <?php endif; ?>
    </nav>

    <h1 class="nitcat__title"><?= s($heading) ?></h1>
    <?php if ($root && trim(strip_tags($root->description)) !== ''): ?>
      <div class="nitcat__intro"><?= format_text($root->description, $root->descriptionformat, ['context' => $context]) ?></div>
    <?php endif; ?>
    <p class="nitcat__total"><?= s($coursecount($catalogue->total(), 'coursesinscope')) ?></p>

    <div class="nitcat__search">
      <label class="sr-only visually-hidden" for="nitcat-q"><?= s(get_string('searchcourses', 'local_nit_category')) ?></label>
      <input type="search" id="nitcat-q" name="q" value="<?= s($active['q'] ?? '') ?>"
             placeholder="<?= s(get_string('searchcourses', 'local_nit_category')) ?>">
      <button type="submit" class="btn btn-primary fw-bold"><?= s(get_string('search')) ?></button>
    </div>
  </header>

  <div class="nitcat__body">

    <!-- ── Filters ───────────────────────────────────────────────────────────────
         Built from the site's course custom fields, not from a hard-coded list: a
         group appears here only because some course in scope actually carries that
         field, which is what keeps the panel honest when courses are described
         differently from one another. -->
    <aside class="nitcat__side">
      <div class="nitcat__sidehead">
        <h2 class="nitcat__sidetitle"><?= s(get_string('filters', 'local_nit_category')) ?></h2>
        <?php if (!empty($active)): ?>
          <a class="nitcat__clear" href="<?= s($clearall) ?>"><?= s(get_string('clearall', 'local_nit_category')) ?></a>
        <?php endif; ?>
      </div>

      <?php if (!empty($categoryfacet)): ?>
        <fieldset class="nitcat__group" data-nitcat-group>
          <legend><?= s(get_string('category')) ?></legend>
          <div class="nitcat__opts">
            <?php foreach ($categoryfacet as $i => $option): ?>
              <label class="nitcat__opt<?= $i >= catalogue::OPTIONS_VISIBLE ? ' is-extra' : '' ?>">
                <input type="checkbox" name="cat[]" value="<?= s($option['key']) ?>"
                       <?= $option['selected'] ? 'checked' : '' ?>>
                <span class="nitcat__optlabel"><?= s($option['label']) ?></span>
                <span class="nitcat__optcount"><?= (int) $option['count'] ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if (count($categoryfacet) > catalogue::OPTIONS_VISIBLE): ?>
            <button type="button" class="nitcat__more" data-nitcat-more
                    data-less="<?= s(get_string('showfewer', 'local_nit_category')) ?>"><?=
              s(get_string('showall', 'local_nit_category', count($categoryfacet))) ?></button>
          <?php endif; ?>
        </fieldset>
      <?php endif; ?>

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

        <?php else: // A numeric range. ?>
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

      <!-- Without scripting this is the only way to apply a tick, so it is a real button
           and not a decoration; the script hides it and submits on change instead. -->
      <button type="submit" class="btn btn-primary fw-bold nitcat__apply" data-nitcat-apply>
        <?= s(get_string('applyfilters', 'local_nit_category')) ?>
      </button>
    </aside>

    <!-- ── Results ───────────────────────────────────────────────────────────────-->
    <main class="nitcat__main">

      <div class="nitcat__toolbar">
        <div class="nitcat__count">
          <strong><?= s($coursecount($found, 'coursesfound')) ?></strong>
        </div>
        <label class="nitcat__sort">
          <span><?= s(get_string('sortby', 'local_nit_category')) ?></span>
          <select name="sort" data-nitcat-submit>
            <?php foreach (catalogue::sort_options() as $key => $label): ?>
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
          <?php foreach ($categoryfacet as $option): ?>
            <?php if ($option['selected']): ?>
              <a class="nitcat__chip" href="<?= s($removelink('cat', $option['key'])) ?>">
                <span><?= s($option['label']) ?></span><span aria-hidden="true">×</span>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
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

      <?php if (empty($visible)): ?>
        <div class="nitcat__empty">
          <div class="nitcat__emptyicon" aria-hidden="true">🔍</div>
          <p class="nitcat__emptytitle"><?= s(get_string('nomatches', 'local_nit_category')) ?></p>
          <p class="nitcat__emptyhint"><?= s(get_string('nomatcheshint', 'local_nit_category')) ?></p>
          <a class="btn btn-outline-primary fw-bold" href="<?= s($clearall) ?>"><?=
            s(get_string('clearall', 'local_nit_category')) ?></a>
        </div>
      <?php else: ?>
        <div class="nitcat__grid">
          <?php foreach ($visible as $row): ?>
            <?php
            $course = $row['course'];
            $info = pricing::info($row['id']);
            $coursename = $course->get_formatted_name();
            $coursecontext = context_course::instance($row['id']);
            $detailsurl = (new moodle_url('/course/view.php', ['id' => $row['id']]))->out();
            $enrolurl = (new moodle_url('/local/nit_subscriptions/enrol.php',
                ['courseid' => $row['id'], 'sesskey' => sesskey()]))->out(false);

            // Thumbnail: the course's own overview file, or the tinted placeholder tile the
            // grid uses so a course without a picture still keeps the card's proportions.
            $imageurl = '';
            foreach (get_file_storage()->get_area_files($coursecontext->id, 'course', 'overviewfiles', 0,
                    'sortorder DESC, id DESC', false) as $file) {
                $imageurl = moodle_url::make_pluginfile_url($coursecontext->id, 'course', 'overviewfiles', null,
                    $file->get_filepath(), $file->get_filename())->out(false);
                break;
            }

            // Card chips and meta come from the same field values the filters were built
            // from, so what a card advertises is exactly what can be filtered on.
            ['chips' => $chips, 'meta' => $meta] = $catalogue->card_labels($row);
            ?>
            <article class="nitcat-card">
              <a class="nitcat-card__media" href="<?= $detailsurl ?>" tabindex="-1" aria-hidden="true">
                <?php if ($imageurl !== ''): ?>
                  <img src="<?= s($imageurl) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="nitcat-card__noimage"><?= s(shorten_text(text_util::plain($coursename), 40)) ?></span>
                <?php endif; ?>
              </a>

              <div class="nitcat-card__body">
                <?php if ($row['catname'] !== ''): ?>
                  <div class="nitcat-card__cat"><?= s($row['catname']) ?></div>
                <?php endif; ?>

                <h3 class="nitcat-card__title">
                  <a href="<?= $detailsurl ?>"><?= $coursename ?></a>
                </h3>

                <?php if (!empty($chips)): ?>
                  <div class="nitcat-card__chips">
                    <?php foreach ($chips as $chip): ?>
                      <span class="nitcat-card__chip"><?= s($chip) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($meta)): ?>
                  <div class="nitcat-card__meta"><?= s(implode(' · ', $meta)) ?></div>
                <?php endif; ?>

                <div class="nitcat-card__foot">
                  <!-- A fixed-height status/price row keeps the buttons on the same line in
                       every card: a free course simply leaves it empty. -->
                  <div class="nitcat-card__status">
                    <?php if ($info['enrolled']): ?>
                      <span class="nitcat-badge nitcat-badge--ok">✓ <?= s(get_string('enrolled', 'local_nit_category')) ?></span>
                      <?= $pricetags($info) ?>
                    <?php elseif ($info['purchased']): ?>
                      <span class="nitcat-badge nitcat-badge--ok">✓ <?= s(get_string('purchased', 'local_nit_category')) ?></span>
                      <?= $pricetags($info) ?>
                    <?php elseif ($info['covered']): ?>
                      <span class="nitcat-badge nitcat-badge--sub">★ <?= s(get_string('insubscription', 'local_nit_category')) ?></span>
                      <?= $pricetags($info) ?>
                    <?php elseif ($info['haspricing']): ?>
                      <?= $pricetags($info) ?>
                    <?php else: ?>
                      <span class="nitcat-card__free"><?= s(get_string('free', 'local_nit_category')) ?></span>
                    <?php endif; ?>
                  </div>

                  <div class="d-grid gap-2">
                    <?php if ($info['enrolled'] || $info['purchased']): ?>
                      <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= s(get_string('coursedetails', 'local_nit_category')) ?></a>
                    <?php elseif ($info['covered']): ?>
                      <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= s(get_string('enrol', 'local_nit_category')) ?></a>
                      <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= s(get_string('coursedetails', 'local_nit_category')) ?></a>
                    <?php elseif (!empty($info['countryrequired']) && $countrynotice): ?>
                      <?php // Buying is refused server-side without a profile country, so the card
                            // offers the fix instead of a Buy button that can only fail. ?>
                      <a href="<?= s($countrynotice['url']) ?>" class="btn btn-primary fw-bold"><?= s($countrynotice['action']) ?></a>
                      <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= s(get_string('coursedetails', 'local_nit_category')) ?></a>
                    <?php elseif ($info['haspricing'] && $hascheckout): ?>
                      <button type="button" class="btn btn-primary fw-bold" data-nit-buy-course
                        data-courseid="<?= (int) $row['id'] ?>" data-name="<?= s($coursename) ?>"
                        data-price="<?= s((string) $info['price']) ?>" data-currency="<?= s($info['currency']) ?>"><?=
                        s(get_string('buynow', 'local_nit_category')) ?></button>
                      <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= s(get_string('coursedetails', 'local_nit_category')) ?></a>
                    <?php else: ?>
                      <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= s(get_string('enrol', 'local_nit_category')) ?></a>
                      <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= s(get_string('coursedetails', 'local_nit_category')) ?></a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($pagecount > 1): ?>
          <nav class="nitcat__pager" aria-label="<?= s(get_string('pagination', 'local_nit_category')) ?>">
            <?php if ($page > 0): ?>
              <a class="nitcat__page" href="<?= s($linkto(['page' => $page - 1])) ?>" rel="prev" aria-label="<?= s(get_string('previous')) ?>">‹</a>
            <?php endif; ?>
            <?php for ($p = 0; $p < $pagecount; $p++): ?>
              <?php if ($p === $page): ?>
                <span class="nitcat__page is-current" aria-current="page"><?= $p + 1 ?></span>
              <?php else: ?>
                <a class="nitcat__page" href="<?= s($linkto(['page' => $p])) ?>"><?= $p + 1 ?></a>
              <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $pagecount - 1): ?>
              <a class="nitcat__page" href="<?= s($linkto(['page' => $page + 1])) ?>" rel="next" aria-label="<?= s(get_string('next')) ?>">›</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>

        <div class="nitcat__perpage">
          <?= s(get_string('perpage', 'local_nit_category')) ?>
          <?php foreach (catalogue::PERPAGE_OPTIONS as $option): ?>
            <?php if ($option === $perpage): ?>
              <strong><?= $option ?></strong>
            <?php else: ?>
              <a href="<?= s($linkto(['perpage' => $option])) ?>"><?= $option ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</form>
</div>

<?php
$PAGE->requires->js(new moodle_url('/local/nit_category/catalogue.js'));
local_nit_category_checkout_footer();

echo $OUTPUT->footer();
