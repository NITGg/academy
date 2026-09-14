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
 * Point the charts core draws with Chart.js at the Brand Colors palette.
 *
 * A chart is a canvas, so no stylesheet reaches it. Core colours it twice, and
 * neither pass knows the brand: `core/chart_base` hands each series a colour
 * from a fixed ten-entry list (`COLORSET`, or `$CFG->chart_colorset` when an
 * admin set one), and Chart.js paints the legend, the axis ticks and the grid
 * in its own greys (#666 text over a 10% black grid), tuned for a white page.
 *
 * This module runs once, before the first chart on the page is built (the theme
 * renderer queues it from `render_chart()`, ahead of core's own chart script),
 * and rewires both passes from the live `--nit-brand-*` custom properties on
 * `<html>` — so whichever group the light/dark switch or a category style has
 * put there is the one the chart follows, exactly as the CSS around it does.
 *
 * Series take the vivid roles in a fixed order — Primary first, so a one-series
 * chart is simply the brand — and skip Secondary, which is a surface tone that
 * would vanish against the page. An explicit `$CFG->chart_colorset` still wins:
 * this replaces core's built-in default, not an admin's choice.
 *
 * @module     theme_nit/chart_brand
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Base from 'core/chart_base';
import Chart from 'core/chartjs';

/** @type {Boolean} Set after the first run; every later call is a no-op. */
let done = false;

/**
 * A brand role, as the page currently resolves it.
 *
 * @param {String} name the part after `--nit-brand-`
 * @return {String} a CSS colour, or '' when the theme did not publish the role
 */
const role = (name) => getComputedStyle(document.documentElement).getPropertyValue('--nit-brand-' + name).trim();

/**
 * Apply the brand to Chart.js and to core's series palette.
 */
export const init = () => {
    if (done) {
        return;
    }
    done = true;

    // Series palette: the roles a data mark can wear. Core reads the list with
    // the series' 1-based position (chart_base.addSeries() pushes first, then
    // indexes by `length`), so the FIRST series wears the SECOND entry: Primary
    // sits second on purpose, and entry 0 is what the seventh series wraps to.
    const series = ['accent', 'primary', 'info', 'success', 'warning', 'error'].map(role);
    if (series.every(Boolean)) {
        Base.prototype.COLORSET = series;
    }

    // Text, rules and the hover tooltip.
    const ink = role('textprimary');
    const rule = role('borderprimary');
    const surface = role('surface');
    if (ink) {
        Chart.defaults.color = ink;
    }
    if (rule) {
        Chart.defaults.borderColor = rule;
    }
    if (surface && ink) {
        const tooltip = Chart.defaults.plugins.tooltip;
        tooltip.backgroundColor = surface;
        tooltip.titleColor = ink;
        tooltip.bodyColor = ink;
        tooltip.footerColor = ink;
        tooltip.borderColor = rule || ink;
        tooltip.borderWidth = 1;
    }
};
