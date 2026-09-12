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
 * The one financial-report panel, dropped into five pages.
 *
 * The whole point of this class is that the markup is written once. The "Reports" tab on
 * manage_courses, manage_subscriptions, manage_coupons and manage_offers, and every tab of the
 * master report, are the same HTML skeleton filled by the same JavaScript from the same
 * endpoint — so "make them similar to each other" is not a style that has to be maintained in
 * four places, it is the same file.
 *
 * The skeleton is deliberately empty: filters, totals, breakdown and rows are all painted by
 * report.js from JSON, which is what keeps the four pages from drifting apart the first time
 * one of them is edited.
 *
 * @package    local_nit_finance
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_nit_finance\output;

use local_nit_finance\report\revenue;

defined('MOODLE_INTERNAL') || die();

/**
 * Renders the shared financial report panel.
 */
class report_panel {

    /** @var bool Whether the stylesheet has already gone out on this request. */
    private static $cssdone = false;

    /**
     * The label every one of these tabs carries.
     *
     * @return string
     */
    public static function tab_label(): string {
        return get_string('rep_tab', 'local_nit_finance');
    }

    /**
     * Does the current user get to see money at all?
     *
     * The report reads across payments, coupons, offers and plans, so it is gated on the
     * finance capability rather than on whichever page it happens to be embedded in.
     *
     * @return bool
     */
    public static function allowed(): bool {
        return has_capability('local/nit_finance:manage', \context_system::instance());
    }

    /**
     * Emit the panel.
     *
     * @param string $scope the scope this page reports on; see revenue::scopes()
     * @param array $options 'scopes' => string[] to render an internal scope strip,
     *                       'intro' => string shown above the filters
     * @return string HTML
     */
    public static function render(string $scope, array $options = []): string {
        global $PAGE;

        $scope = revenue::scope($scope);

        if (!self::allowed()) {
            return \html_writer::div(get_string('rep_noaccess', 'local_nit_finance'), 'alert alert-warning');
        }

        // Footer, not head: every page that embeds this panel calls render() from inside its
        // body, by which point the head has already been printed and an $inhead request would
        // be queued into a section that is never emitted again. The footer also puts the script
        // after the NITFR_BOOT blob below, which is the order it needs.
        //
        // ?v= is the plugin version, because a browser holds the old file until the URL changes:
        // bump version.php whenever report.js does, or the fix ships and nobody sees it.
        $PAGE->requires->js(new \moodle_url('/local/nit_finance/report.js',
            ['v' => get_config('local_nit_finance', 'version')]));

        $out = self::css();
        $out .= self::skeleton($scope, $options);
        $out .= \html_writer::script('window.NITFR_BOOT = ' . json_encode(self::config($scope, $options)) . ';');

        return $out;
    }

    /**
     * The client's configuration: endpoint, scope, filter options and every string it prints.
     *
     * @param string $scope
     * @param array $options
     * @return array
     */
    private static function config(string $scope, array $options): array {
        $scopes = [];
        foreach ((array) ($options['scopes'] ?? []) as $s) {
            $s = revenue::scope($s);
            $scopes[] = ['key' => $s, 'label' => get_string('rep_scope_' . $s, 'local_nit_finance')];
        }

        // Only the scopes this panel can actually reach. On the four embedded tabs that is one,
        // so opening a coupon page no longer runs the course-list query it will never use.
        $reachable = $scopes ? array_column($scopes, 'key') : [$scope];

        $opts = [];
        $intros = [];
        foreach ($reachable as $s) {
            $list = revenue::options($s);
            if ($list) {
                $opts[$s] = $list;
            }
            $intros[$s] = self::intro($s);
        }

        return [
            'endpoint' => (new \moodle_url('/local/nit_finance/report_ajax.php'))->out(false),
            'sesskey'  => sesskey(),
            'scope'    => $scope,
            'scopes'   => $scopes,
            'options'  => $opts,
            'intros'   => $intros,
            'perpage'  => revenue::PER_PAGE,
            'str'      => self::strings(),
        ];
    }

    /**
     * Every string report.js prints, resolved server-side so the panel is localised
     * the same way the page around it is.
     *
     * @return array
     */
    public static function strings(): array {
        $keys = [
            'rep_tab', 'rep_intro', 'rep_loading', 'rep_none', 'rep_error', 'rep_truncated',
            'rep_apply', 'rep_reset', 'rep_export', 'rep_search', 'rep_search_ph',
            'rep_from', 'rep_to', 'rep_state', 'rep_currency', 'rep_narrow', 'rep_all',
            'rep_state_paid', 'rep_state_completed', 'rep_state_refunded', 'rep_state_lost',
            'rep_state_all',
            'rep_kpi_gross', 'rep_kpi_gross_help', 'rep_kpi_discount', 'rep_kpi_discount_help',
            'rep_kpi_net', 'rep_kpi_net_help', 'rep_kpi_refunded', 'rep_kpi_refunded_help',
            'rep_kpi_earned', 'rep_kpi_earned_help', 'rep_kpi_orders', 'rep_kpi_orders_help',
            'rep_kpi_lost', 'rep_kpi_lost_help',
            'rep_col_share', 'rep_col_orders', 'rep_col_learners', 'rep_col_gross',
            'rep_col_discount', 'rep_col_net', 'rep_col_lost', 'rep_col_refunded', 'rep_col_earned',
            'rep_col_date', 'rep_col_order', 'rep_col_user', 'rep_col_item', 'rep_col_source',
            'rep_col_detail', 'rep_col_status', 'rep_col_total',
            'rep_orders_heading', 'rep_pager', 'rep_prev', 'rep_next', 'rep_clearsource',
        ];

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = get_string($key, 'local_nit_finance');
        }
        return $out;
    }

    /**
     * The one line under the heading that says what this scope is about.
     *
     * @param string $scope
     * @return string
     */
    private static function intro(string $scope): string {
        $key = 'rep_intro_' . $scope;
        return get_string_manager()->string_exists($key, 'local_nit_finance')
            ? get_string($key, 'local_nit_finance')
            : get_string('rep_intro', 'local_nit_finance');
    }

    /**
     * The empty skeleton report.js fills in.
     *
     * @param string $scope
     * @param array $options
     * @return string
     */
    private static function skeleton(string $scope, array $options): string {
        $intro = (string) ($options['intro'] ?? self::intro($scope));

        $html = '<div class="nitfr" data-nitfr-scope="' . s($scope) . '">';
        $html .= '<div class="nitfr-msg alert alert-danger" data-nitfr="msg" hidden></div>';
        $html .= '<nav class="nitfr-scopes" data-nitfr="scopes" hidden></nav>';
        $html .= '<p class="nitfr-intro" data-nitfr="intro">' . $intro . '</p>';
        $html .= '<form class="nitfr-filters" data-nitfr="filters" onsubmit="return false"></form>';
        $html .= '<div class="nitfr-drill" data-nitfr="drill" hidden></div>';
        $html .= '<div class="nitfr-kpis" data-nitfr="kpis"></div>';
        $html .= '<h4 class="nitfr-h" data-nitfr="breakdownheading"></h4>';
        $html .= '<div class="nitfr-wrap"><table class="table table-sm nitfr-table" data-nitfr="breakdown">';
        $html .= '<thead></thead><tbody><tr><td>' . get_string('rep_loading', 'local_nit_finance') . '</td></tr></tbody>';
        $html .= '<tfoot></tfoot></table></div>';
        $html .= '<h4 class="nitfr-h">' . get_string('rep_orders_heading', 'local_nit_finance') . '</h4>';
        $html .= '<div class="nitfr-wrap"><table class="table table-sm nitfr-table" data-nitfr="rows">';
        $html .= '<thead></thead><tbody><tr><td>' . get_string('rep_loading', 'local_nit_finance') . '</td></tr></tbody>';
        $html .= '</table></div>';
        $html .= '<div class="nitfr-pager" data-nitfr="pager"></div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * The panel's stylesheet, emitted once per request.
     *
     * Inline rather than a styles.css, for the same reason the sibling admin pages inline
     * theirs: a plugin stylesheet only reaches the browser after a theme cache purge, and a
     * report that is half-styled until someone remembers to purge is worse than no report.
     * Every colour is a theme_nit brand token, so the panel follows light, dark and every
     * category palette without knowing which one it is in.
     *
     * @return string
     */
    private static function css(): string {
        if (self::$cssdone) {
            return '';
        }
        self::$cssdone = true;

        return <<<'CSS'
<style>
.nitfr { --nitfr-gap: .75rem; }
/* The scope strip and the drill-down note are toggled with the `hidden` attribute, and both
   carry display:flex below. A class rule beats the UA stylesheet's [hidden]{display:none},
   so without this they show as empty bars on every page that never reveals them — which is
   all four embedded ones, where there is no scope strip at all. */
.nitfr [hidden] { display: none !important; }
.nitfr-intro { color: var(--nit-brand-textsecondary); max-width: 80ch; margin-bottom: 1rem; }

/* Scope strip: only the master report shows one, but it is styled here so that page and the
   four embedded panels cannot drift apart. */
.nitfr-scopes { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:1rem; }
.nitfr-scopes button { border:1px solid var(--nit-brand-borderprimary); background:var(--nit-brand-surface);
    color:var(--nit-brand-textprimary); border-radius:999px; padding:.3rem .9rem; cursor:pointer;
    font-size:.9rem; }
.nitfr-scopes button:hover { border-color:var(--nit-brand-primary); }
.nitfr-scopes button.is-active { background:var(--nit-brand-primary);
    border-color:var(--nit-brand-primary); color:var(--nit-brand-hovertext, var(--nit-brand-textprimary)); }

/* Filters */
.nitfr-filters { display:flex; flex-wrap:wrap; gap:var(--nitfr-gap); align-items:flex-end; margin-bottom:1rem; }
.nitfr-field { display:flex; flex-direction:column; gap:.2rem; }
.nitfr-field label { font-size:.8rem; margin:0; color:var(--nit-brand-textsecondary); }
.nitfr-field .form-control, .nitfr-field .form-select, .nitfr-field select, .nitfr-field input {
    min-width:10rem; }
.nitfr-field--grow { flex:1 1 14rem; }
.nitfr-field--grow input { min-width:100%; }
.nitfr-actions { display:flex; gap:.4rem; align-items:flex-end; margin-inline-start:auto; }

/* Totals. Six cards in one row on a desktop, wrapping to two on a phone. */
.nitfr-kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(9.5rem, 1fr));
    gap:var(--nitfr-gap); margin-bottom:1.5rem; }
.nitfr-kpi { border:1px solid var(--nit-brand-borderprimary); border-radius:10px; padding:.7rem .9rem;
    background:var(--nit-brand-surface); color:var(--nit-brand-textprimary); }
.nitfr-kpi__label { display:block; font-size:.82rem; color:var(--nit-brand-textsecondary); }
.nitfr-kpi__value { display:block; font-size:1.4rem; font-weight:700; line-height:1.3;
    margin-top:.15rem; word-break:break-word; }
.nitfr-kpi__cur { font-size:.75em; font-weight:600; color:var(--nit-brand-textsecondary);
    margin-inline-start:.25rem; }
.nitfr-kpi__help { display:block; font-size:.72rem; color:var(--nit-brand-textsecondary); margin-top:.2rem; }
.nitfr-kpi--net { border-color:color-mix(in srgb, var(--nit-brand-primary) 55%, transparent);
    background:color-mix(in srgb, var(--nit-brand-primary) 10%, var(--nit-brand-surface)); }
.nitfr-kpi--refund .nitfr-kpi__value { color:var(--nit-brand-danger, #c0392b); }
.nitfr-kpi--lost .nitfr-kpi__value { color:var(--nit-brand-textsecondary); }
.nitfr-kpi--earned { border-color:color-mix(in srgb, var(--nit-brand-success, #2e7d32) 55%, transparent);
    background:color-mix(in srgb, var(--nit-brand-success, #2e7d32) 10%, var(--nit-brand-surface)); }

/* Tables */
.nitfr-h { margin:1.5rem 0 .5rem; font-size:1.05rem; }
.nitfr-wrap { overflow-x:auto; }
.nitfr-table { width:100%; margin-bottom:0; }
.nitfr-table th { white-space:nowrap; font-size:.82rem; color:var(--nit-brand-textsecondary);
    font-weight:600; }
.nitfr-table td { vertical-align:middle; font-size:.9rem; }
.nitfr-num { text-align:end; white-space:nowrap; font-variant-numeric:tabular-nums; }
/* Every amount is wrapped in <bdi dir="ltr"> so a leading minus stays attached to its number
   in Arabic; this keeps that isolation even where a stylesheet resets bdi. */
.nitfr bdi { unicode-bidi:isolate; }
.nitfr-table tfoot td { font-weight:700; border-top:2px solid var(--nit-brand-borderprimary); }
.nitfr-clickable { cursor:pointer; }
.nitfr-clickable:hover { background:color-mix(in srgb, var(--nit-brand-primary) 8%, transparent); }
.nitfr-muted { color:var(--nit-brand-textsecondary); font-size:.85em; }
/* An order that never paid: still listed, because "we nearly sold 40 of these" is a real
   answer, but dimmed so it can never be misread as income. */
.nitfr-row--lost td { color:var(--nit-brand-textsecondary); }
.nitfr-lost { color:var(--nit-brand-textsecondary); }
.nitfr-sub { display:block; color:var(--nit-brand-textsecondary); font-size:.8em; }

/* A share bar in the breakdown, so the biggest source is visible without reading numbers. */
.nitfr-bar { display:block; height:.35rem; border-radius:999px; min-width:2px;
    background:var(--nit-brand-primary); }
.nitfr-bar__track { display:block; width:4.5rem; height:.35rem; border-radius:999px;
    background:color-mix(in srgb, var(--nit-brand-textsecondary) 22%, transparent); }

/* Badges: what kind of money this row is. */
.nitfr-badge { display:inline-block; padding:.15rem .5rem; border-radius:999px; font-size:.78rem;
    white-space:nowrap; border:1px solid var(--nit-brand-borderprimary);
    background:var(--nit-brand-surface); color:var(--nit-brand-textprimary); }
.nitfr-badge--coupon { border-color:color-mix(in srgb, var(--nit-brand-info) 50%, transparent);
    background:color-mix(in srgb, var(--nit-brand-info) 14%, var(--nit-brand-surface)); }
.nitfr-badge--offer { border-color:color-mix(in srgb, var(--nit-brand-warning, #b26a00) 50%, transparent);
    background:color-mix(in srgb, var(--nit-brand-warning, #b26a00) 14%, var(--nit-brand-surface)); }
.nitfr-badge--full { border-color:color-mix(in srgb, var(--nit-brand-primary) 45%, transparent);
    background:color-mix(in srgb, var(--nit-brand-primary) 12%, var(--nit-brand-surface)); }
.nitfr-badge--refund { border-color:color-mix(in srgb, var(--nit-brand-danger, #c0392b) 50%, transparent);
    background:color-mix(in srgb, var(--nit-brand-danger, #c0392b) 12%, var(--nit-brand-surface)); }

/* Drill-down note: which card the table is currently filtered to. */
.nitfr-drill { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin-bottom:1rem;
    padding:.5rem .75rem; border-radius:8px;
    border:1px solid color-mix(in srgb, var(--nit-brand-primary) 40%, transparent);
    background:color-mix(in srgb, var(--nit-brand-primary) 10%, var(--nit-brand-surface));
    color:var(--nit-brand-textprimary); }

/* Pager, matching the sibling admin pages' .acad-pager exactly. */
.nitfr-pager { display:flex; flex-wrap:wrap; align-items:center; gap:.35rem; margin:1rem 0; }
.nitfr-pager__info { margin-inline-end:auto; color:var(--nit-brand-textsecondary); font-size:.9rem; }
.nitfr-pager button { border:1px solid var(--nit-brand-borderprimary); background:var(--nit-brand-surface);
    color:var(--nit-brand-textprimary); border-radius:6px; padding:.25rem .6rem; cursor:pointer; }
.nitfr-pager button.is-active { background:var(--nit-brand-primary);
    border-color:var(--nit-brand-primary); color:var(--nit-brand-hovertext, var(--nit-brand-textprimary)); }
.nitfr-pager button:disabled { opacity:.5; cursor:default; }

.nitfr-busy { opacity:.55; transition:opacity .15s; }
</style>
CSS;
    }
}
