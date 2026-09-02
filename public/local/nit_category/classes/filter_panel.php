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

namespace local_nit_category;

defined('MOODLE_INTERNAL') || die();

/**
 * The filter panel, drawn once and used by both catalogue pages.
 *
 * SRS §4.8 names six filters and an order for them: category, level, price range,
 * language, duration, certificate. That order is the design's, not the database's, so it
 * is written out here rather than derived from whatever order the custom fields happen to
 * sit in — {@see catalogue::filter_roles()} gives each facet the job it does, and this
 * asks for facets by job.
 *
 * It lives in one file because it used to live in two, and the two drifted. The catalogue
 * shows all six; the categories page shows five, because a category filter on the page
 * that lists categories is the page arguing with itself, and gets a subcategory toggle in
 * its place.
 *
 * @package    local_nit_category
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_panel {

    /**
     * Draw the panel.
     *
     * @param catalogue $catalogue the engine holding the facets and what is ticked
     * @param string $clearall address with every filter removed
     * @param array $options 'categoryfacet' => array[] to show a Category group,
     *                       'subs' => bool|null to show the subcategory toggle in its place
     * @return void echoes
     */
    public static function render(catalogue $catalogue, string $clearall, array $options = []): void {
        $active = $catalogue->active();
        $facets = $catalogue->facets();
        $categoryfacet = $options['categoryfacet'] ?? [];
        $subs = $options['subs'] ?? null;
        ?>
        <aside class="nitcat__side">
          <div class="nitcat__sidehead">
            <h2 class="nitcat__sidetitle"><?= s(get_string('filters', 'local_nit_category')) ?></h2>
            <?php if (!empty($active)): ?>
              <a class="nitcat__clear" href="<?= s($clearall) ?>"><?=
                s(get_string('clearall', 'local_nit_category')) ?></a>
            <?php endif; ?>
          </div>

          <?php if (!empty($categoryfacet)): ?>
            <?php self::options_group(get_string('filtercategory', 'local_nit_category'),
                'cat[]', $categoryfacet); ?>
          <?php endif; ?>

          <?php if ($subs !== null): ?>
            <!-- The categories page's stand-in for the Category group: the question there
                 is not "which category" but "how deep". -->
            <fieldset class="nitcat__group">
              <legend class="nitcat__grouptitle"><?=
                s(get_string('categorydepth', 'local_nit_category')) ?></legend>
              <div class="nitcat__opts">
                <label class="nitcat__opt">
                  <input type="checkbox" name="subs" value="1" <?= $subs ? 'checked' : '' ?>>
                  <span class="nitcat__optlabel"><?=
                    s(get_string('includesubcategories', 'local_nit_category')) ?></span>
                </label>
              </div>
            </fieldset>
          <?php endif; ?>

          <?php
          // Level, then price, then language, duration and certificate — the design's
          // order. Each is drawn only if the site actually has the field behind it.
          $level = catalogue::facet_by_role($facets, 'level');
          if ($level) {
              self::options_group($level['name'], 'f_' . $level['shortname'], $level['values']);
          }

          self::price_group($catalogue, $active, (bool) ($options['hascheckout'] ?? true));

          foreach (['language', 'duration'] as $role) {
              $facet = catalogue::facet_by_role($facets, $role);
              if ($facet) {
                  self::options_group($facet['name'], 'f_' . $facet['shortname'], $facet['values']);
              }
          }

          $certificate = catalogue::facet_by_role($facets, 'certificate');
          if ($certificate) {
              ?>
              <fieldset class="nitcat__group nitcat__group--flat">
                <legend class="visually-hidden"><?= s($certificate['name']) ?></legend>
                <div class="nitcat__opts">
                  <label class="nitcat__opt">
                    <input type="checkbox" name="f_<?= s($certificate['shortname']) ?>" value="1"
                           <?= !empty($certificate['selected']) ? 'checked' : '' ?>>
                    <span class="nitcat__optlabel"><?= s($certificate['name']) ?></span>
                    <span class="nitcat__optcount"><?= (int) $certificate['count'] ?></span>
                  </label>
                </div>
              </fieldset>
              <?php
          }
          ?>

          <!-- With scripting off this is the only way to apply a tick, so it is a real
               button. The page scripts hide it and re-filter on change instead. -->
          <button type="submit" class="btn btn-primary fw-bold nitcat__apply" data-nitcat-apply>
            <?= s(get_string('applyfilters', 'local_nit_category')) ?>
          </button>
        </aside>
        <?php
    }

    /**
     * One checkbox group, with a "Show all" fold once the list gets long.
     *
     * @param string $title
     * @param string $name the form field name, with [] where several may be ticked
     * @param array[] $values each ['key', 'label', 'count', 'selected']
     * @return void
     */
    private static function options_group(string $title, string $name, array $values): void {
        if (empty($values)) {
            return;
        }
        $foldable = count($values) > catalogue::OPTIONS_VISIBLE;
        ?>
        <fieldset class="nitcat__group" data-nitcat-group>
          <legend class="nitcat__grouptitle"><?= s($title) ?></legend>
          <div class="nitcat__opts">
            <?php foreach ($values as $i => $option): ?>
              <label class="nitcat__opt<?= $foldable && $i >= catalogue::OPTIONS_VISIBLE ? ' is-extra' : '' ?>">
                <input type="checkbox" name="<?= s($name) ?>" value="<?= s($option['key']) ?>"
                       <?= !empty($option['selected']) ? 'checked' : '' ?>>
                <span class="nitcat__optlabel"><?= s($option['label']) ?></span>
                <?php if (isset($option['count'])): ?>
                  <span class="nitcat__optcount"><?= (int) $option['count'] ?></span>
                <?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($foldable): ?>
            <button type="button" class="nitcat__more" data-nitcat-more
                    data-less="<?= s(get_string('showfewer', 'local_nit_category')) ?>"><?=
              s(get_string('showall', 'local_nit_category', count($values))) ?></button>
          <?php endif; ?>
        </fieldset>
        <?php
    }

    /**
     * The price range: one track with two handles, and the two ends written underneath.
     *
     * The numbers the form actually submits are the two inputs at the bottom, kept out of
     * sight. The sliders carry no name of their own on purpose — a range input always has
     * a value, so naming them would put `pricemin=0&pricemax=5000` into every address
     * whether or not anybody touched the control, and "clear all" would never look clear.
     * pricerange.js copies a moved handle into the input beside it, and empties that input
     * again when the handle returns to its end.
     *
     * @param catalogue $catalogue
     * @param array $active
     * @param bool $hascheckout whether this site sells anything at all
     * @return void
     */
    private static function price_group(catalogue $catalogue, array $active, bool $hascheckout): void {
        if (!$hascheckout) {
            return;
        }
        $bounds = $catalogue->price_bounds();
        if ($bounds['max'] <= 0) {
            return;     // Nothing in view has a price; a slider from zero to zero is furniture.
        }

        $currency = $bounds['currency'] !== ''
            ? $bounds['currency']
            : get_string('defaultcurrency', 'local_nit_category');
        $low = $active['pricemin'] ?? $bounds['min'];
        $high = $active['pricemax'] ?? $bounds['max'];
        $step = $bounds['max'] > 1000 ? 50 : 10;
        ?>
        <fieldset class="nitcat__group">
          <legend class="nitcat__grouptitle"><?= s(get_string('filterprice', 'local_nit_category')) ?></legend>

          <div class="nitcat__price" data-nitcat-price
               data-min="<?= s((string) $bounds['min']) ?>"
               data-max="<?= s((string) $bounds['max']) ?>"
               data-currency="<?= s($currency) ?>">
            <div class="nitcat__pricetrack" aria-hidden="true">
              <span class="nitcat__pricefill" data-nitcat-price-fill></span>
            </div>
            <input type="range" class="nitcat__pricehandle" data-nitcat-price-low
                   min="<?= s((string) $bounds['min']) ?>" max="<?= s((string) $bounds['max']) ?>"
                   step="<?= (int) $step ?>" value="<?= s((string) $low) ?>"
                   aria-label="<?= s(get_string('pricefrom', 'local_nit_category')) ?>">
            <input type="range" class="nitcat__pricehandle" data-nitcat-price-high
                   min="<?= s((string) $bounds['min']) ?>" max="<?= s((string) $bounds['max']) ?>"
                   step="<?= (int) $step ?>" value="<?= s((string) $high) ?>"
                   aria-label="<?= s(get_string('priceto', 'local_nit_category')) ?>">

            <div class="nitcat__priceends">
              <span data-nitcat-price-lowlabel><?= s(pricing::money((float) $low, $currency)) ?></span>
              <span data-nitcat-price-highlabel><?= s(pricing::money((float) $high, $currency)) ?></span>
            </div>

            <!-- What actually travels in the query string. Empty means "not set", which is
                 why these are text-free rather than defaulted to the slider's ends. -->
            <input type="number" name="pricemin" hidden data-nitcat-price-input
                   value="<?= isset($active['pricemin']) ? s(text_util::number($active['pricemin'])) : '' ?>">
            <input type="number" name="pricemax" hidden data-nitcat-price-input
                   value="<?= isset($active['pricemax']) ? s(text_util::number($active['pricemax'])) : '' ?>">
          </div>

          <label class="nitcat__opt nitcat__opt--free">
            <input type="checkbox" name="free" value="1" <?= !empty($active['free']) ? 'checked' : '' ?>>
            <span class="nitcat__optlabel"><?= s(get_string('freeonly', 'local_nit_category')) ?></span>
          </label>
        </fieldset>
        <?php
    }
}
