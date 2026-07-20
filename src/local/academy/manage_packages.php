<?php
// Admin UI to manage lesson (Flex) packages. Uses the local_academy HTTP API (api.php) from the
// browser, authenticated with a web-service token minted for the logged-in admin.

require('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/webservice/lib.php');
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/local/academy/lib.php'); // local_academy_string_map()

admin_externalpage_setup('local_academy_managepackages');
require_capability('local/academy:managepackages', context_system::instance());

global $DB, $OUTPUT, $CFG, $PAGE;

// Mint (or reuse) a token for the current admin on the built-in mobile service.
$service = $DB->get_record('external_services', array('shortname' => MOODLE_OFFICIAL_MOBILE_SERVICE), '*', MUST_EXIST);
$tokenobj = external_generate_token_for_current_user($service);
$token = $tokenobj->token;

$PAGE->set_title(get_string('managepackages', 'local_academy'));
$PAGE->set_heading(get_string('managepackages', 'local_academy'));

// Shared UI helpers (AcademyUI.paginate) — inhead so it is ready before the page's inline script runs.
$PAGE->requires->js(new moodle_url('/local/academy/ui.js'), true);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managepackages', 'local_academy'));

// Localised strings for this page — used in the server-rendered HTML below (via $STR['key']) and
// shipped to the browser as window.ACADEMY_STR for the JS (via the str()/strf() helpers).
$STR = local_academy_string_map(array(
    // Static HTML labels / headers.
    'pkg_new', 'ui_refresh', 'ui_loading', 'ui_active', 'ui_save', 'ui_cancel', 'ui_optional',
    'pkg_col_id', 'pkg_col_name', 'pkg_col_flexes', 'pkg_col_price', 'pkg_col_expdays',
    'pkg_col_status', 'pkg_col_actions', 'pkg_field_name', 'pkg_field_description',
    'pkg_field_name_en', 'pkg_field_name_ar', 'pkg_field_desc_en', 'pkg_field_desc_ar',
    'pkg_field_flexcount', 'pkg_field_price', 'pkg_field_expdays',
    'pkg_userpackages', 'pkg_userpackages_desc', 'pkg_col_user', 'pkg_col_package', 'pkg_col_flex',
    'pkg_col_pricepaid', 'pkg_col_expiresat', 'pkg_unassign_title', 'pkg_unassign_refund',
    'pkg_unassign',
    // Dynamic strings used only from JS.
    'ui_edit', 'ui_delete', 'ui_activate', 'ui_deactivate', 'ui_never',
    'pkg_none', 'pkg_edit_titled', 'pkg_confirm_delete', 'pkg_users_none',
    'pkg_unassign_confirm', 'pkg_unassign_paid',
    'msg_package_created', 'msg_package_updated', 'msg_package_activated',
    'msg_package_deactivated', 'msg_package_deleted', 'msg_package_unassigned',
    'err_requestfailed', 'err_sessionexpired', 'ui_pager_info',
    // Flex reversal (US-FN-1-5), moved here from the Financial Reports page.
    'wd_reversal_title', 'wd_reversal_help', 'wd_lesson_id', 'wd_reason', 'wd_return_flex',
    'wd_enter_lesson', 'wd_flex_returned', 'err_reasonrequired', 'ui_currency_egp',
    'ui_picker_lesson_ph', 'ui_picker_searching', 'ui_picker_none', 'ui_picker_hint',
    // Page tabs.
    'pkg_tab_packages', 'pkg_tab_assign', 'pkg_tab_settings', 'pkg_tab_reports',
    // "Assign package" tab — moved here from the standalone assign_package.php page.
    'ap_student_label', 'ap_student_help', 'ap_student_placeholder', 'ap_package_label',
    'ap_amount_label', 'ap_amount_placeholder', 'ap_method_label', 'ap_method_offline',
    'ap_method_bank', 'ap_method_wallet', 'ap_reference_label', 'ap_reference_placeholder',
    'ap_note_label', 'ap_submit', 'ap_pkg_option', 'ap_no_packages', 'ap_enter_student', 'ap_assigned',
    'ui_picker_student_ph',
    // "Package settings" tab — moved here from the standalone manage_settings.php page.
    'set_min_booking', 'set_cancel_deadline', 'set_update_deadline', 'set_start_allowed',
    'set_complete_allowed', 'set_absence_report', 'set_lesson_start_reminder', 'set_lesson_start_reminder_help',
    'set_expiry_reminder', 'set_expiry_reminder_help',
    'set_teacher_percent', 'set_platform_percent', 'set_percent_help', 'set_save', 'set_saved',
    'set_reminder_add', 'set_reminder_placeholder',
    // "Flex reports" tab — moved here from the standalone manage_reports.php page.
    'rp_tab_lessons', 'rp_tab_platform', 'rp_tab_packages', 'rp_tab_studentflex', 'rp_tab_useractivity',
    'rp_f_userid', 'rp_f_email', 'rp_ua_registered', 'rp_ua_lastlogin', 'rp_ua_status', 'rp_ua_roles',
    'rp_ua_subs', 'rp_ua_memberships', 'rp_ua_courses', 'rp_ua_actions', 'rp_ua_none', 'rp_enter_user',
    'rp_f_status', 'rp_f_teacherid', 'rp_f_studentid', 'rp_f_from', 'rp_f_to', 'rp_f_earnstatus',
    'rp_f_source', 'rp_f_studentid_req', 'rp_run', 'ui_export_csv',
    'rp_c_id', 'rp_c_student', 'rp_c_teacher', 'rp_c_subject', 'rp_c_status', 'rp_c_confirmed',
    'rp_c_flex', 'rp_c_lesson', 'rp_c_date', 'rp_c_flexvalue', 'rp_c_platpct', 'rp_c_platform',
    'rp_c_package', 'rp_c_source', 'rp_c_price', 'rp_c_rem', 'rp_c_resv', 'rp_c_used', 'rp_c_type',
    'rp_c_amount', 'rp_c_before', 'rp_c_after', 'rp_c_by', 'rp_c_reason', 'rp_c_timeline',
    'rp_s_available', 'rp_s_reserved', 'rp_s_consumed', 'rp_s_package', 'rp_s_expires',
    'rp_timeline_title', 'rp_close', 'rp_tl_num', 'rp_tl_action', 'rp_tl_by', 'rp_tl_role',
    'rp_tl_time', 'rp_tl_title_full', 'rp_tl_joinedroom', 'rp_tl_started', 'rp_tl_ended', 'rp_tl_none',
    'rp_no_data', 'rp_enter_student', 'rp_enter_student_run',
    'ui_picker_placeholder', 'ui_picker_teacher_ph',
    'rp_sum_total', 'rp_sum_completed', 'rp_sum_student_absent', 'rp_sum_teacher_absent',
    'rp_sum_attendance_rate', 'rp_sum_total_platform_earnings', 'rp_sum_total_teacher_earnings',
    'rp_sum_total_consumed_value', 'rp_sum_completed_lessons', 'rp_sum_total_purchases',
    'rp_sum_total_sales_amount', 'rp_sum_online_count', 'rp_sum_assigned_count',
    'rp_sum_total_flex_added', 'rp_sum_total_flex_consumed', 'rp_sum_total_flex_returned',
    'rp_sum_reversals',
    'rp_act_requested', 'rp_act_teacher_accepted', 'rp_act_teacher_rejected', 'rp_act_teacher_suggested',
    'rp_act_student_accepted', 'rp_act_student_rejected', 'rp_act_student_suggested', 'rp_act_started',
    'rp_act_teacher_joined', 'rp_act_student_joined', 'rp_act_completed', 'rp_act_student_absent_reported',
    'rp_act_teacher_absent_reported', 'rp_act_request_cancelled', 'rp_act_cancelled_by_student',
    'rp_act_cancelled_by_teacher', 'rp_act_time_update_requested', 'rp_act_time_update_accepted',
    'rp_act_time_update_rejected',
));

// Flex reversal, offline package assignment, the platform settings and the Flex reports are all
// platform-money actions, so they are gated on manageplatform — a user with only managepackages can
// reach this page but must not see those sections (the API would reject them anyway; hiding them
// avoids offering actions that cannot succeed).
$canplatform = has_capability('local/academy:manageplatform', context_system::instance());

// Pass config + localised strings to JS. The assign/settings/reports blocks below were merged in
// from their old standalone pages and keep reading their original config globals, so alias them.
echo html_writer::script('window.ACADEMY_PKG = ' . json_encode(array(
    'endpoint' => $CFG->wwwroot . '/local/academy/api.php',
    'export'   => $CFG->wwwroot . '/local/academy/export.php',
    'token'    => $token,
    'lang'     => optional_param('lang', current_language(), PARAM_LANG),
)) . ';');
echo html_writer::script('window.ACADEMY_AP = window.ACADEMY_SET = window.ACADEMY_RP = window.ACADEMY_PKG;');
echo html_writer::script('window.ACADEMY_STR = ' . json_encode($STR) . ';');
echo html_writer::script('window.ACADEMY_CAN_REVERSE_FLEX = ' . ($canplatform ? 'true' : 'false') . ';');

// Page markup.
?>
<div id="academy-pkg-app">
    <div id="pkg-tabs">
        <button data-tab="packages" class="active"><?php echo $STR['pkg_tab_packages']; ?></button>
        <?php if ($canplatform) { ?>
        <button data-tab="assign"><?php echo $STR['pkg_tab_assign']; ?></button>
        <button data-tab="settings"><?php echo $STR['pkg_tab_settings']; ?></button>
        <button data-tab="reports"><?php echo $STR['pkg_tab_reports']; ?></button>
        <?php } ?>
    </div>

    <div class="pkg-pane active" data-pane="packages">
    <div class="mb-3">
        <button id="pkg-new" class="btn btn-primary"><?php echo $STR['pkg_new']; ?></button>
        <button id="pkg-refresh" class="btn btn-secondary"><?php echo $STR['ui_refresh']; ?></button>
    </div>

    <div id="pkg-message" class="alert" style="display:none"></div>

    <table class="table table-striped" id="pkg-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_id']; ?></th><th><?php echo $STR['pkg_col_name']; ?></th>
                <th><?php echo $STR['pkg_col_flexes']; ?></th><th><?php echo $STR['pkg_col_price']; ?></th>
                <th><?php echo $STR['pkg_col_expdays']; ?></th><th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="pkg-table-pager" class="acad-pager"></div>

    <!-- Create / edit form (hidden by default) -->
    <div id="pkg-form-card" class="card" style="display:none; max-width:560px;">
        <div class="card-body">
            <h4 id="pkg-form-title" class="card-title"><?php echo $STR['pkg_new']; ?></h4>
            <input type="hidden" id="f-id">
            <div class="form-group">
                <label for="f-name-en"><?php echo $STR['pkg_field_name_en']; ?></label>
                <input type="text" class="form-control" id="f-name-en" dir="ltr">
            </div>
            <div class="form-group">
                <label for="f-name-ar"><?php echo $STR['pkg_field_name_ar']; ?></label>
                <input type="text" class="form-control" id="f-name-ar" dir="rtl">
            </div>
            <div class="form-group">
                <label for="f-desc-en"><?php echo $STR['pkg_field_desc_en']; ?></label>
                <textarea class="form-control" id="f-desc-en" rows="2" dir="ltr"></textarea>
            </div>
            <div class="form-group">
                <label for="f-desc-ar"><?php echo $STR['pkg_field_desc_ar']; ?></label>
                <textarea class="form-control" id="f-desc-ar" rows="2" dir="rtl"></textarea>
            </div>
            <div class="form-group">
                <label for="f-flex"><?php echo $STR['pkg_field_flexcount']; ?></label>
                <input type="number" class="form-control" id="f-flex" min="1">
            </div>
            <div class="form-group">
                <label for="f-price"><?php echo $STR['pkg_field_price']; ?></label>
                <input type="number" class="form-control" id="f-price" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label for="f-exp"><?php echo $STR['pkg_field_expdays']; ?></label>
                <input type="number" class="form-control" id="f-exp" min="0" value="0">
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="f-active" checked>
                <label class="form-check-label" for="f-active"><?php echo $STR['ui_active']; ?></label>
            </div>
            <button id="pkg-save" class="btn btn-primary"><?php echo $STR['ui_save']; ?></button>
            <button id="pkg-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
        </div>
    </div>

    <!-- ── User Packages ── -->
    <h4 class="mt-4"><?php echo $STR['pkg_userpackages']; ?></h4>
    <p class="text-muted"><?php echo $STR['pkg_userpackages_desc']; ?></p>
    <button id="refresh-users" class="btn btn-secondary mb-2"><?php echo $STR['ui_refresh']; ?></button>
    <table class="table table-striped" id="users-table">
        <thead>
            <tr>
                <th><?php echo $STR['pkg_col_user']; ?></th>
                <th><?php echo $STR['pkg_col_package']; ?></th>
                <th><?php echo $STR['pkg_col_flex']; ?></th>
                <th><?php echo $STR['pkg_col_pricepaid']; ?></th>
                <th><?php echo $STR['pkg_col_status']; ?></th>
                <th><?php echo $STR['pkg_col_expiresat']; ?></th>
                <th><?php echo $STR['pkg_col_actions']; ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7"><?php echo $STR['ui_loading']; ?></td></tr></tbody>
    </table>
    <div id="users-table-pager" class="acad-pager"></div>

    <!-- ── Flex reversal (US-FN-1-5) — returns one consumed Flex and reverses the earning ── -->
    <?php if ($canplatform) { ?>
    <div class="pkg-reversal">
        <h5><?php echo $STR['wd_reversal_title']; ?></h5>
        <p class="text-muted" style="font-size:.88rem"><?php echo $STR['wd_reversal_help']; ?></p>
        <div class="form-group">
            <label for="pkg-rev-lesson"><?php echo $STR['wd_lesson_id']; ?></label>
            <div id="pkg-rev-lesson"></div>
        </div>
        <div class="form-group">
            <label for="pkg-rev-reason"><?php echo $STR['wd_reason']; ?></label>
            <input class="form-control" id="pkg-rev-reason">
        </div>
        <button id="pkg-rev-btn" class="btn btn-warning"><?php echo $STR['wd_return_flex']; ?></button>
    </div>
    <?php } ?>
    </div><!-- /pane packages -->

    <?php if ($canplatform) { ?>
    <!-- ── Assign package to a student after an offline payment (US-AD-4-1) ── -->
    <div class="pkg-pane" data-pane="assign">
      <div id="ap-app">
        <div id="ap-msg" class="alert" style="display:none"></div>
        <div class="card"><div class="card-body">
          <div class="form-group"><label for="ap-student"><?php echo $STR['ap_student_label']; ?></label>
            <div id="ap-student"></div>
            <small class="text-muted"><?php echo $STR['ap_student_help']; ?></small></div>
          <div class="form-group"><label for="ap-package"><?php echo $STR['ap_package_label']; ?></label>
            <select class="form-control" id="ap-package"></select></div>
          <div class="form-group"><label for="ap-amount"><?php echo $STR['ap_amount_label']; ?></label>
            <input class="form-control" id="ap-amount" type="number" min="0" step="0.01" placeholder="<?php echo s($STR['ap_amount_placeholder']); ?>">
          </div>
          <div class="form-group"><label for="ap-method"><?php echo $STR['ap_method_label']; ?></label>
            <select class="form-control" id="ap-method">
              <option value="offline"><?php echo $STR['ap_method_offline']; ?></option>
              <option value="bank"><?php echo $STR['ap_method_bank']; ?></option>
              <option value="wallet"><?php echo $STR['ap_method_wallet']; ?></option>
            </select></div>
          <div class="form-group"><label for="ap-reference"><?php echo $STR['ap_reference_label']; ?></label>
            <input class="form-control" id="ap-reference" placeholder="<?php echo s($STR['ap_reference_placeholder']); ?>"></div>
          <div class="form-group"><label for="ap-note"><?php echo $STR['ap_note_label']; ?></label>
            <input class="form-control" id="ap-note"></div>
          <button id="ap-submit" class="btn btn-primary"><?php echo $STR['ap_submit']; ?></button>
        </div></div>
      </div>
    </div><!-- /pane assign -->

    <!-- ── Package settings (US-AD-2-1) — the lesson/package half of the old Admin settings page ── -->
    <div class="pkg-pane" data-pane="settings">
      <div id="set-msg" class="alert" style="display:none"></div>
      <div class="card" style="max-width:560px;">
        <div class="card-body">
          <div class="form-group"><label><?php echo $STR['set_min_booking']; ?></label><input class="form-control" id="s-min_booking_minutes" type="number" min="0"></div>
          <div class="form-group"><label><?php echo $STR['set_cancel_deadline']; ?></label><input class="form-control" id="s-cancel_deadline_minutes" type="number" min="0"></div>
          <div class="form-group"><label><?php echo $STR['set_update_deadline']; ?></label><input class="form-control" id="s-update_deadline_minutes" type="number" min="0"></div>
          <div class="form-group"><label><?php echo $STR['set_start_allowed']; ?></label><input class="form-control" id="s-start_allowed_minutes" type="number" min="0"></div>
          <div class="form-group"><label><?php echo $STR['set_complete_allowed']; ?></label><input class="form-control" id="s-complete_allowed_minutes" type="number" min="0"></div>
          <div class="form-group"><label><?php echo $STR['set_absence_report']; ?></label><input class="form-control" id="s-absence_report_minutes" type="number" min="0"></div>
          <div class="form-group">
            <label><?php echo $STR['set_lesson_start_reminder']; ?></label>
            <div id="reminder-tags" style="margin-bottom:0.5rem; display:flex; gap:0.5rem; flex-wrap:wrap;"></div>
            <div class="input-group" style="max-width:200px; margin-bottom:0.25rem;">
              <input class="form-control" id="reminder-add-input" type="number" min="1" placeholder="<?php echo s($STR['set_reminder_placeholder']); ?>">
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" id="reminder-add-btn"><?php echo $STR['set_reminder_add']; ?></button>
              </div>
            </div>
            <input type="hidden" id="s-lesson_start_reminder_minutes">
            <small class="text-muted"><?php echo $STR['set_lesson_start_reminder_help']; ?></small>
          </div>
          <div class="form-group"><label><?php echo $STR['set_expiry_reminder']; ?></label><input class="form-control" id="s-expiry_reminder_days" type="number" min="0"><small class="text-muted"><?php echo $STR['set_expiry_reminder_help']; ?></small></div>
          <div class="form-group"><label><?php echo $STR['set_teacher_percent']; ?></label><input class="form-control" id="s-teacher_percent" type="number" min="0" max="100"></div>
          <div class="form-group"><label><?php echo $STR['set_platform_percent']; ?></label><input class="form-control" id="s-platform_percent" type="number" min="0" max="100"></div>
          <small class="text-muted"><?php echo $STR['set_percent_help']; ?></small>
          <br><br>
          <button id="set-save" class="btn btn-primary"><?php echo $STR['set_save']; ?></button>
        </div>
      </div>
    </div><!-- /pane settings -->

    <!-- ── Flex platform reports (US-AD-3-1..3-4) with CSV export ── -->
    <div class="pkg-pane" data-pane="reports">
      <div id="rp-app">
        <div id="rp-msg" class="alert" style="display:none"></div>
        <div id="rp-tabs">
          <button data-tab="lessons" class="active"><?php echo $STR['rp_tab_lessons']; ?></button>
          <button data-tab="platform_earnings"><?php echo $STR['rp_tab_platform']; ?></button>
          <button data-tab="packages"><?php echo $STR['rp_tab_packages']; ?></button>
          <button data-tab="student_flex"><?php echo $STR['rp_tab_studentflex']; ?></button>
          <button data-tab="user_activity"><?php echo $STR['rp_tab_useractivity']; ?></button>
        </div>
        <div id="rp-filters"></div>
        <div id="rp-summary"></div>
        <div id="rp-generic"><div style="overflow-x:auto"><table class="rp-table"><thead id="rp-head"></thead><tbody id="rp-body"></tbody></table></div><div id="rp-body-pager" class="acad-pager"></div></div>
        <div id="rp-useractivity" style="display:none"></div>
        <div id="rp-timeline" style="display:none;margin-top:1rem;border:1px solid #dee2e6;border-radius:.5rem;padding:.75rem">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
            <strong id="rp-tl-title"><?php echo $STR['rp_timeline_title']; ?></strong>
            <button id="rp-tl-close" class="btn btn-sm btn-outline-secondary"><?php echo $STR['rp_close']; ?></button>
          </div>
          <div id="rp-tl-meta" style="margin-bottom:.6rem;display:flex;gap:.5rem;flex-wrap:wrap"></div>
          <table class="rp-table"><thead><tr><th><?php echo $STR['rp_tl_num']; ?></th><th><?php echo $STR['rp_tl_action']; ?></th><th><?php echo $STR['rp_tl_by']; ?></th><th><?php echo $STR['rp_tl_role']; ?></th><th><?php echo $STR['rp_tl_time']; ?></th></tr></thead><tbody id="rp-tl-body"></tbody></table>
        </div>
      </div>
    </div><!-- /pane reports -->
    <?php } ?>

    <!-- ── Unassign confirmation modal ── -->
    <div id="unassign-modal-backdrop" class="academy-modal-backdrop" style="display:none;">
        <div class="academy-modal">
            <h5 class="academy-modal-title"><?php echo $STR['pkg_unassign_title']; ?></h5>
            <p id="unassign-modal-text"></p>
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="unassign-refund-checkbox">
                <label class="form-check-label" for="unassign-refund-checkbox">
                    <?php echo $STR['pkg_unassign_refund']; ?> <span class="text-muted"><?php echo $STR['ui_optional']; ?></span>
                </label>
            </div>
            <div class="academy-modal-actions">
                <button id="unassign-modal-cancel" class="btn btn-link"><?php echo $STR['ui_cancel']; ?></button>
                <button id="unassign-modal-confirm" class="btn btn-danger"><?php echo $STR['pkg_unassign']; ?></button>
            </div>
        </div>
    </div>
</div>
<style>
    /* Page-level tabs: Packages / Assign package / Package settings / Flex reports. */
    #pkg-tabs { display:flex; gap:.25rem; border-bottom:1px solid #dee2e6; margin-bottom:1rem; flex-wrap:wrap; }
    #pkg-tabs button { border:none; background:none; padding:.5rem .9rem; border-bottom:3px solid transparent; cursor:pointer; }
    #pkg-tabs button.active { border-bottom-color:#0f6cbf; font-weight:600; color:#0f6cbf; }
    .pkg-pane { display:none; }
    .pkg-pane.active { display:block; }

    /* "Assign package" tab. */
    #ap-app { max-width:560px; }
    #ap-app .form-group { margin-bottom:.75rem; }

    /* "Flex reports" tab (inner tabs, filter bar, summary chips, result table). */
    #rp-app { max-width:1040px; }
    #rp-tabs { display:flex; gap:.25rem; border-bottom:1px solid #dee2e6; margin-bottom:1rem; flex-wrap:wrap; }
    #rp-tabs button { border:none; background:none; padding:.5rem .9rem; border-bottom:3px solid transparent; cursor:pointer; }
    #rp-tabs button.active { border-bottom-color:#0f6cbf; font-weight:600; color:#0f6cbf; }
    #rp-filters { display:flex; gap:.5rem; align-items:flex-end; flex-wrap:wrap; margin-bottom:.75rem; }
    #rp-filters .fg { display:flex; flex-direction:column; font-size:.82rem; color:#6c757d; }
    #rp-filters .form-control { max-width:180px; }
    #rp-filters .acad-picker { max-width:260px; }
    #rp-filters .acad-picker__input { max-width:100%; }
    #rp-summary { display:flex; gap:.6rem; flex-wrap:wrap; margin-bottom:.75rem; }
    .rp-chip { border:1px solid #dee2e6; border-radius:.5rem; padding:.4rem .7rem; font-size:.85rem; }
    .rp-chip b { display:block; font-size:1.1rem; }
    table.rp-table { width:100%; border-collapse:collapse; }
    table.rp-table th, table.rp-table td { border-bottom:1px solid #eee; padding:.4rem .5rem; text-align:left; font-size:.86rem; }
    .rp-badge { display:inline-block; padding:.05rem .45rem; border-radius:1rem; font-size:.76rem; font-weight:600; background:#eef; }

    /* Flex reversal (US-FN-1-5) — warning tint marks it as a corrective, money-moving action. */
    .pkg-reversal {
        margin-top: 2rem;
        border: 1px solid #ffe082;
        background: #fff8e1;
        border-radius: 0.5rem;
        padding: 1rem;
        max-width: 560px;
    }
    .pkg-reversal .form-group { margin-bottom: 0.6rem; }
    .academy-modal-backdrop {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1050;
    }
    .academy-modal {
        background: #fff;
        border-radius: 10px;
        padding: 1.5rem;
        max-width: 440px;
        width: 90%;
        box-shadow: 0 12px 30px rgba(0,0,0,0.25);
    }
    .academy-modal-title {
        margin-bottom: 0.75rem;
        font-weight: 600;
    }
    .academy-modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.5rem;
        margin-top: 1.25rem;
    }
</style>
<?php

echo html_writer::script(<<<'JS'
(function () {
    var CFG = window.ACADEMY_PKG;
    var STR = window.ACADEMY_STR || {};

    function $(id) { return document.getElementById(id); }

    // ── Shared client-side pagination (AcademyUI.paginate from ui.js) ──
    var PAGE_SIZE = 10;
    var pkgPager = null, usersPager = null;
    function pagerLabels() { return { info: str('ui_pager_info') }; }

    // Localised string lookup; falls back to the key so a missing string is visible, not blank.
    function str(k) { return (k in STR) ? STR[k] : k; }
    // Like str() but fills Moodle {$a} / {$a->name} placeholders from a params object.
    function strf(k, params) {
        var s = str(k);
        if (params == null) { return s; }
        if (typeof params !== 'object') { return s.replace(/\{\$a\}/g, params); }
        return s.replace(/\{\$a->(\w+)\}/g, function (m, name) {
            return (name in params) ? params[name] : m;
        });
    }

    function msg(text, type) {
        var el = $('pkg-message');
        el.textContent = text;
        el.className = 'alert alert-' + (type || 'info');
        el.style.display = 'block';
        if (type === 'success') { setTimeout(function () { el.style.display = 'none'; }, 3000); }
    }

    // Call the package API. Resolves with `data`, rejects with an Error(message).
    function api(func, params, method) {
        params = params || {};
        method = method || 'GET';
        var data = new URLSearchParams({ function: func, token: CFG.token });
        if (CFG.lang) { data.append('alang', CFG.lang); }
        Object.keys(params).forEach(function (k) { data.append(k, params[k]); });
        var opts, url = CFG.endpoint;
        if (method === 'POST') {
            opts = { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: data.toString() };
        } else {
            url = CFG.endpoint + '?' + data.toString();
            opts = {};
        }
        return fetch(url, opts)
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var json;
                try { json = JSON.parse(text); }
                catch (e) { throw new Error(str('err_sessionexpired')); }
                if (json.status !== 'success') { throw new Error(json.error || str('err_requestfailed')); }
                return json.data;
            });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        });
    }

    // ── Multilang helpers ──────────────────────────────────────────────────────
    // The DB keeps a SINGLE name/description field. To hold two languages in it we use the site's
    // active "Multi-Language Content (v2)" filter syntax: {mlang en}…{mlang}{mlang ar}…{mlang}.
    // These helpers let the admin edit two clean boxes (EN / AR) that map to that one field.

    // Pull the {en, ar} values out of a stored multilang string. Understands both the v2 {mlang}
    // syntax and the legacy core <span class="multilang"> syntax (so older entries still load).
    // A plain value (no markup) is treated as the English text.
    function parseMultilang(value) {
        var out = { en: '', ar: '' };
        var raw = String(value == null ? '' : value);
        var m, found = false;

        // v2 syntax: {mlang en}…{mlang}
        var re2 = /\{\s*mlang\s+([a-zA-Z0-9_-]+)\s*\}([\s\S]*?)\{\s*mlang\s*\}/g;
        while ((m = re2.exec(raw)) !== null) {
            found = true;
            var code2 = m[1].toLowerCase();
            if (code2.indexOf('ar') === 0) { out.ar = m[2].trim(); }
            else if (code2.indexOf('en') === 0) { out.en = m[2].trim(); }
        }
        if (found) { return out; }

        // Legacy core syntax: <span lang="xx" class="multilang">…</span>
        var re1 = /<span[^>]*\blang\s*=\s*"([a-zA-Z0-9_-]+)"[^>]*>([\s\S]*?)<\/span>/g;
        while ((m = re1.exec(raw)) !== null) {
            found = true;
            var code1 = m[1].toLowerCase();
            if (code1.indexOf('ar') === 0) { out.ar = m[2].trim(); }
            else if (code1.indexOf('en') === 0) { out.en = m[2].trim(); }
        }
        if (!found) { out.en = raw; } // plain text → English box
        return out;
    }

    // Combine the two boxes back into one field value. Two langs → {mlang} blocks; a single lang →
    // plain text (so it still shows even if the filter is off); both empty → ''.
    function buildMultilang(en, ar) {
        en = String(en == null ? '' : en).trim();
        ar = String(ar == null ? '' : ar).trim();
        if (en && ar) {
            return '{mlang en}' + en + '{mlang}{mlang ar}' + ar + '{mlang}';
        }
        return en || ar;
    }

    // Readable label for the admin table: "English / Arabic" (whichever are present).
    function displayName(value) {
        var v = parseMultilang(value);
        return [v.en, v.ar].filter(function (x) { return x; }).join(' / ') || value || '';
    }

    // Render one page of package rows (keeps tr._pkg for the click-delegation handler).
    function renderPkgRows(items) {
        var tbody = $('pkg-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function (p) {
            var tr = document.createElement('tr');
            var toggle = p.status === 'active'
                ? '<button class="btn btn-sm btn-warning" data-act="deactivate" data-id="' + p.id + '">' + esc(str('ui_deactivate')) + '</button>'
                : '<button class="btn btn-sm btn-success" data-act="activate" data-id="' + p.id + '">' + esc(str('ui_activate')) + '</button>';
            tr.innerHTML =
                '<td>' + esc(p.id) + '</td>' +
                '<td>' + esc(displayName(p.name)) + '</td>' +
                '<td>' + esc(p.flex_count) + '</td>' +
                '<td>' + esc(p.price) + '</td>' +
                '<td>' + esc(p.expiration_days) + '</td>' +
                '<td>' + esc(p.status) + '</td>' +
                '<td>' +
                    '<button class="btn btn-sm btn-secondary" data-act="edit" data-id="' + p.id + '">' + esc(str('ui_edit')) + '</button> ' +
                    toggle + ' ' +
                    '<button class="btn btn-sm btn-danger" data-act="delete" data-id="' + p.id + '">' + esc(str('ui_delete')) + '</button>' +
                '</td>';
            tr._pkg = p;
            tbody.appendChild(tr);
        });
    }

    function loadPackages() {
        var tbody = $('pkg-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_packages').then(function (rows) {
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7">' + esc(str('pkg_none')) + '</td></tr>';
                $('pkg-table-pager').innerHTML = '';
                return;
            }
            if (pkgPager) {
                pkgPager.setRows(rows);
            } else {
                pkgPager = AcademyUI.paginate({
                    rows: rows, pageSize: PAGE_SIZE, pagerEl: $('pkg-table-pager'),
                    labels: pagerLabels(), render: renderPkgRows
                });
            }
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    function showForm(pkg) {
        $('pkg-form-title').textContent = pkg ? strf('pkg_edit_titled', pkg.id) : str('pkg_new');
        var nm = parseMultilang(pkg ? pkg.name : '');
        var ds = parseMultilang(pkg ? (pkg.description || '') : '');
        $('f-id').value          = pkg ? pkg.id : '';
        $('f-name-en').value     = nm.en;
        $('f-name-ar').value     = nm.ar;
        $('f-desc-en').value     = ds.en;
        $('f-desc-ar').value     = ds.ar;
        $('f-flex').value        = pkg ? pkg.flex_count : '';
        $('f-price').value       = pkg ? pkg.price : '';
        $('f-exp').value         = pkg ? pkg.expiration_days : 0;
        $('f-active').checked    = pkg ? (pkg.status === 'active') : true;
        $('pkg-form-card').style.display = 'block';
    }
    function hideForm() { $('pkg-form-card').style.display = 'none'; }

    function save() {
        var id = $('f-id').value;
        var params = {
            name: buildMultilang($('f-name-en').value, $('f-name-ar').value),
            description: buildMultilang($('f-desc-en').value, $('f-desc-ar').value),
            flex_count: $('f-flex').value,
            price: $('f-price').value,
            expiration_days: $('f-exp').value || 0
        };
        var p;
        if (id) {
            params.id = id;
            params.status = $('f-active').checked ? 'active' : 'inactive';
            p = api('update_package', params);
        } else {
            params.active = $('f-active').checked ? 1 : 0;
            p = api('create_package', params);
        }
        p.then(function () {
            msg(id ? str('msg_package_updated') : str('msg_package_created'), 'success');
            hideForm();
            loadPackages();
        }).catch(function (e) { msg(e.message, 'danger'); });
    }

    // Table action buttons (event delegation).
    $('pkg-table').addEventListener('click', function (ev) {
        var btn = ev.target.closest('button[data-act]');
        if (!btn) { return; }
        var id = btn.getAttribute('data-id');
        var act = btn.getAttribute('data-act');
        var row = btn.closest('tr');

        if (act === 'edit') { showForm(row._pkg); return; }
        if (act === 'activate') {
            api('activate_package', { id: id }).then(function () { msg(str('msg_package_activated'), 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'deactivate') {
            api('deactivate_package', { id: id }).then(function () { msg(str('msg_package_deactivated'), 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        } else if (act === 'delete') {
            if (!confirm(str('pkg_confirm_delete'))) { return; }
            api('delete_package', { id: id }).then(function () { msg(str('msg_package_deleted'), 'success'); loadPackages(); }).catch(function (e) { msg(e.message, 'danger'); });
        }
    });

    $('pkg-new').addEventListener('click', function () { showForm(null); });
    $('pkg-refresh').addEventListener('click', loadPackages);
    $('pkg-save').addEventListener('click', save);
    $('pkg-cancel').addEventListener('click', hideForm);

    // ── User Packages ──
    // Render one page of user-package rows (keeps tr._row for the unassign handler).
    function renderUserRows(items) {
        var tbody = $('users-table').querySelector('tbody');
        tbody.innerHTML = '';
        items.forEach(function(r) {
            var tr = document.createElement('tr');
            var toggle = '';
            if (r.status === 'active') {
                toggle = '<button class="btn btn-sm btn-danger btn-unassign" data-id="' + r.id + '">' + esc(str('pkg_unassign')) + '</button>';
            }
            var expires = r.expires_at > 0 ? new Date(r.expires_at * 1000).toLocaleString() : str('ui_never');
            tr.innerHTML =
                '<td>' + esc(r.user_fullname) + ' <br><small class="text-muted">' + esc(r.user_email) + '</small></td>' +
                '<td>' + esc(r.name) + '</td>' +
                '<td>' + esc(r.remaining_flex) + ' / ' + esc(r.total_flex) + '</td>' +
                '<td>' + esc(r.price_paid) + '</td>' +
                '<td>' + esc(r.status) + '</td>' +
                '<td>' + expires + '</td>' +
                '<td>' + toggle + '</td>';
            tr._row = r;
            tbody.appendChild(tr);
        });
    }

    function loadUsers() {
        var tbody = $('users-table').querySelector('tbody');
        tbody.innerHTML = '<tr><td colspan="7">' + esc(str('ui_loading')) + '</td></tr>';
        api('get_all_user_packages').then(function(rows) {
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="7">' + esc(str('pkg_users_none')) + '</td></tr>';
                $('users-table-pager').innerHTML = '';
                return;
            }
            if (usersPager) {
                usersPager.setRows(rows);
            } else {
                usersPager = AcademyUI.paginate({
                    rows: rows, pageSize: PAGE_SIZE, pagerEl: $('users-table-pager'),
                    labels: pagerLabels(), render: renderUserRows
                });
            }
        }).catch(function(e) { msg(e.message, 'danger'); });
    }

    // ── Unassign confirmation modal ──
    var pendingUnassign = null;

    function openUnassignModal(row) {
        pendingUnassign = row;
        var priceText = row.price_paid ? strf('pkg_unassign_paid', esc(row.price_paid)) : '';
        $('unassign-modal-text').innerHTML = strf('pkg_unassign_confirm', {
            name: esc(row.name),
            user: esc(row.user_fullname),
            price: priceText
        });
        $('unassign-refund-checkbox').checked = false;
        $('unassign-modal-backdrop').style.display = 'flex';
    }

    function closeUnassignModal() {
        pendingUnassign = null;
        $('unassign-modal-backdrop').style.display = 'none';
    }

    $('users-table').addEventListener('click', function(ev) {
        var btn = ev.target.closest('.btn-unassign');
        if (!btn) return;
        openUnassignModal(btn.closest('tr')._row);
    });

    $('unassign-modal-cancel').addEventListener('click', closeUnassignModal);
    $('unassign-modal-backdrop').addEventListener('click', function(ev) {
        if (ev.target === this) { closeUnassignModal(); }
    });
    document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape' && $('unassign-modal-backdrop').style.display !== 'none') { closeUnassignModal(); }
    });
    $('unassign-modal-confirm').addEventListener('click', function() {
        var row = pendingUnassign;
        if (!row) { return; }
        var refund = $('unassign-refund-checkbox').checked;
        api('unassign_package', {
            purchaseid: row.id,
            refund: refund ? 1 : 0
        }, 'POST').then(function() {
            msg(str('msg_package_unassigned'), 'success');
            closeUnassignModal();
            loadUsers();
        }).catch(function(e) { msg(e.message, 'danger'); });
    });

    $('refresh-users').addEventListener('click', loadUsers);

    // ── Flex reversal (US-FN-1-5) ──────────────────────────────────────────────
    // Returns one consumed Flex to the student and reverses the teacher/platform earning. Rendered
    // only for admins holding manageplatform, so the whole block is guarded on that flag.
    if (window.ACADEMY_CAN_REVERSE_FLEX) {
        var lessonPicker = AcademyUI.picker({
            mount: $('pkg-rev-lesson'),
            placeholder: str('ui_picker_lesson_ph'),
            labels: {
                searching: str('ui_picker_searching'),
                none: str('ui_picker_none'),
                hint: str('ui_picker_hint')
            },
            search: function (q) { return api('list_reversible_lessons', { query: q }); },
            primary: function (l) { return '#' + l.id + ' — ' + (l.subject || ''); },
            secondary: function (l) {
                return [
                    l.student_name,
                    l.teacher_name,
                    l.lesson_time ? new Date(l.lesson_time * 1000).toLocaleString() : '',
                    Number(l.flex_value || 0).toFixed(2) + ' ' + str('ui_currency_egp')
                ].filter(function (x) { return x; }).join(' • ');
            }
        });

        $('pkg-rev-btn').addEventListener('click', function () {
            var lessonid = lessonPicker.value();
            var reason = $('pkg-rev-reason').value;
            if (!lessonid) { msg(str('wd_enter_lesson'), 'danger'); return; }
            if (!reason.trim()) { msg(str('err_reasonrequired'), 'danger'); return; }
            api('reverse_flex', { lessonid: lessonid, reason: reason }, 'POST').then(function () {
                msg(str('wd_flex_returned'), 'success');
                lessonPicker.clear();
                $('pkg-rev-reason').value = '';
                loadUsers(); // The student's remaining/consumed Flex counts just changed.
            }).catch(function (e) { msg(e.message, 'danger'); });
        });
    }

    loadPackages();
    loadUsers();
})();
JS
);

// ── Page-level tab switching ───────────────────────────────────────────────────
echo html_writer::script(<<<'JS'
(function () {
    var tabs = document.querySelectorAll('#pkg-tabs button');
    Array.prototype.forEach.call(tabs, function (b) {
        b.onclick = function () {
            Array.prototype.forEach.call(tabs, function (x) { x.classList.remove('active'); });
            b.classList.add('active');
            var tab = b.getAttribute('data-tab');
            Array.prototype.forEach.call(document.querySelectorAll('.pkg-pane'), function (p) {
                p.classList.toggle('active', p.getAttribute('data-pane') === tab);
            });
        };
    });
})();
JS
);

if ($canplatform) {

// ── "Assign package" tab (was assign_package.php) ──────────────────────────────
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_AP;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('ap-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function apiGet(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign(base,p||{}))).then(parse);}
  function apiPost(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}var b=new URLSearchParams(Object.assign(base,p));return fetch(CFG.endpoint,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:b.toString()}).then(parse);}

  // Searchable student picker (replaces the old numeric user-id input).
  var studentPicker = AcademyUI.userPicker({
    mount: $('ap-student'),
    placeholder: str('ui_picker_student_ph'),
    labels: { searching: str('ui_picker_searching'), none: str('ui_picker_none'), hint: str('ui_picker_hint') },
    search: function (q) { return apiGet('search_users', { query: q, role: 'any' }); }
  });

  // Populate active packages.
  apiGet('get_packages',{status:'active'}).then(function(rows){
    var sel=$('ap-package');
    rows.forEach(function(p){var o=document.createElement('option');o.value=p.id;o.textContent=strf('ap_pkg_option',{name:p.name,flex:p.flex_count,price:p.price});sel.appendChild(o);});
    if(!rows.length){msg(str('ap_no_packages'),'danger');}
  }).catch(function(e){msg(e.message,'danger');});

  $('ap-submit').onclick=function(){
    var sid=studentPicker.value();
    if(!sid){msg(str('ap_enter_student'),'danger');return;}
    apiPost('assign_package',{studentid:sid,packageid:$('ap-package').value,amount:$('ap-amount').value||'0',
      method:$('ap-method').value,reference:$('ap-reference').value,note:$('ap-note').value})
      .then(function(d){msg(strf('ap_assigned',{name:d.package_name,flex:d.flex_balance,student:d.student_name}),'success');
        $('ap-amount').value='';$('ap-reference').value='';$('ap-note').value='';})
      .catch(function(e){msg(e.message,'danger');});
  };
})();
JS
);

// ── "Package settings" tab (was the Package settings tab of manage_settings.php) ──
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_SET;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  // Only the package/lesson half of the settings — the subscription keys live on
  // manage_subscriptions.php. update_lesson_settings only writes the fields it is sent.
  var KEYS = ['min_booking_minutes','cancel_deadline_minutes','update_deadline_minutes',
    'start_allowed_minutes','complete_allowed_minutes','absence_report_minutes',
    'lesson_start_reminder_minutes','expiry_reminder_days','teacher_percent','platform_percent'];
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('set-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display='block';if(k==='success'){setTimeout(function(){e.style.display='none';},3000);}}
  function api(func,params){var qs=new URLSearchParams({function:func,token:CFG.token});if(CFG.lang){qs.append('alang',CFG.lang);}Object.keys(params||{}).forEach(function(k){qs.append(k,params[k]);});
    return fetch(CFG.endpoint+'?'+qs.toString()).then(function(r){return r.text();}).then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}

  function renderReminders() {
    var remTags = $('reminder-tags');
    var remInput = $('s-lesson_start_reminder_minutes');
    if (!remTags || !remInput) return;
    remTags.innerHTML = '';
    var vals = (remInput.value || '').split(',').map(function(s){return s.trim();}).filter(function(s){return s !== '';});
    vals.forEach(function(val, idx) {
      var tag = document.createElement('span');
      tag.className = 'badge badge-primary';
      tag.style.cssText = 'font-size:0.9rem; padding:0.4rem 0.6rem; display:inline-flex; align-items:center; gap:0.4rem;';
      tag.innerHTML = val + ' min <span style="cursor:pointer; font-weight:bold;" data-idx="'+idx+'">&times;</span>';
      remTags.appendChild(tag);
    });
  }

  $('reminder-tags').addEventListener('click', function(e) {
    if (e.target.tagName === 'SPAN' && e.target.hasAttribute('data-idx')) {
      var idx = parseInt(e.target.getAttribute('data-idx'), 10);
      var remInput = $('s-lesson_start_reminder_minutes');
      var vals = (remInput.value || '').split(',').map(function(s){return s.trim();}).filter(function(s){return s !== '';});
      vals.splice(idx, 1);
      remInput.value = vals.join(',');
      renderReminders();
    }
  });

  $('reminder-add-btn').addEventListener('click', function() {
    var remAddInput = $('reminder-add-input');
    var remInput = $('s-lesson_start_reminder_minutes');
    var val = remAddInput.value.trim();
    if (val && parseInt(val, 10) > 0) {
      var vals = (remInput.value || '').split(',').map(function(s){return s.trim();}).filter(function(s){return s !== '';});
      if (vals.indexOf(val) === -1) {
        vals.push(val);
        vals.sort(function(a,b){return parseInt(b,10) - parseInt(a,10);});
        remInput.value = vals.join(',');
        renderReminders();
      }
      remAddInput.value = '';
    }
  });

  function setVal(k, v){ var el=$('s-'+k); if(el){ el.value = v; } }
  function getVal(k){ var el=$('s-'+k); return el ? el.value : ''; }

  function load(){api('get_lesson_settings',{}).then(function(d){KEYS.forEach(function(k){setVal(k, d[k]);});renderReminders();}).catch(function(e){msg(e.message,'danger');});}
  function save(){var p={};KEYS.forEach(function(k){p[k]=getVal(k);});api('update_lesson_settings',p).then(function(d){KEYS.forEach(function(k){setVal(k, d[k]);});renderReminders();msg(str('set_saved'),'success');}).catch(function(e){msg(e.message,'danger');});}
  $('set-save').addEventListener('click',save);
  load();
})();
JS
);

// ── "Flex reports" tab (was manage_reports.php) ────────────────────────────────
echo html_writer::script(<<<'JS'
(function () {
  var CFG = window.ACADEMY_RP;
  var STR = window.ACADEMY_STR || {};
  function str(k){return (k in STR)?STR[k]:k;}
  function strf(k,params){var s=str(k);if(params==null){return s;}if(typeof params!=='object'){return s.replace(/\{\$a\}/g,params);}return s.replace(/\{\$a->(\w+)\}/g,function(m,name){return (name in params)?params[name]:m;});}
  function $(id){return document.getElementById(id);}
  function msg(t,k){var e=$('rp-msg');e.textContent=t;e.className='alert alert-'+(k||'info');e.style.display=t?'block':'none';}
  function parse(r){return r.text().then(function(t){var j;try{j=JSON.parse(t);}catch(e){throw new Error(str('err_sessionexpired'));}if(j.status!=='success'){throw new Error(j.error||str('err_requestfailed'));}return j.data;});}
  function money(n){return Number(n||0).toFixed(2);}
  function fmt(ts){return ts?new Date(ts*1000).toLocaleString():'—';}
  function esc(s){return (s==null?'':String(s)).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function apiGet(fn,p){var base={function:fn,token:CFG.token};if(CFG.lang){base.alang=CFG.lang;}return fetch(CFG.endpoint+'?'+new URLSearchParams(Object.assign(base,p||{}))).then(parse);}

  var current='lessons';
  // Filter tuples: [name, label, inputType, pickerRole?]. When pickerRole is present the field is
  // rendered as a searchable user picker (AcademyUI.userPicker) instead of a raw numeric-id input.
  var FILTERS={
    lessons:[['status',str('rp_f_status'),'text'],['teacherid',str('rp_f_teacherid'),'number','teacher'],['studentid',str('rp_f_studentid'),'number','student'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']],
    platform_earnings:[['status',str('rp_f_earnstatus'),'text'],['teacherid',str('rp_f_teacherid'),'number','teacher'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']],
    packages:[['source',str('rp_f_source'),'text'],['studentid',str('rp_f_studentid'),'number','student'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']],
    student_flex:[['studentid',str('rp_f_studentid_req'),'number','student']],
    user_activity:[['userid',str('rp_f_userid'),'number','user'],['email',str('rp_f_email'),'text'],['from',str('rp_f_from'),'number'],['to',str('rp_f_to'),'number']]
  };
  var PICKERS={}; // name -> AcademyUI.userPicker instance for the current tab's filters
  function pickerPlaceholder(role){return role==='teacher'?str('ui_picker_teacher_ph'):(role==='student'?str('ui_picker_student_ph'):str('ui_picker_placeholder'));}
  function pickerApiRole(role){return role==='teacher'?'teacher':'any';}
  var FN={lessons:'report_lessons',platform_earnings:'report_platform_earnings',packages:'report_packages',student_flex:'report_student_flex',user_activity:'report_user_activity'};
  var EXPORTTYPE={lessons:'lessons',platform_earnings:'platform_earnings',packages:'packages',student_flex:'student_flex'};

  function readFilters(){
    var p={};(FILTERS[current]||[]).forEach(function(f){
      var v = PICKERS[f[0]] ? PICKERS[f[0]].value() : $('f-'+f[0]).value;
      if(v!==''){p[f[0]]=v;}
    });return p;
  }
  function renderFilters(){
    var box=$('rp-filters');box.innerHTML='';PICKERS={};
    (FILTERS[current]||[]).forEach(function(f){
      var fg=document.createElement('div');fg.className='fg';
      fg.innerHTML='<label>'+f[1]+'</label>';
      var role=f[3];
      if(role){
        // Searchable user picker instead of a numeric-id text field.
        var mount=document.createElement('div');fg.appendChild(mount);box.appendChild(fg);
        PICKERS[f[0]]=AcademyUI.userPicker({
          mount:mount,placeholder:pickerPlaceholder(role),
          labels:{searching:str('ui_picker_searching'),none:str('ui_picker_none'),hint:str('ui_picker_hint')},
          search:function(q){return apiGet('search_users',{query:q,role:pickerApiRole(role)});},
          onChange:function(){updateExportLink();}
        });
        return;
      }
      var inp=document.createElement('input');inp.className='form-control';inp.id='f-'+f[0];inp.type=f[2];
      fg.appendChild(inp);box.appendChild(fg);
    });
    var run=document.createElement('button');run.className='btn btn-primary';run.textContent=str('rp_run');run.onclick=load;box.appendChild(run);
    if(EXPORTTYPE[current]){
      var exp=document.createElement('a');exp.className='btn btn-outline-secondary';exp.textContent=str('ui_export_csv');exp.id='rp-export';exp.target='_blank';box.appendChild(exp);
    }
  }
  function updateExportLink(){
    if(!EXPORTTYPE[current]||!$('rp-export')){return;}
    var p=Object.assign({type:EXPORTTYPE[current],token:CFG.token},readFilters());
    if(CFG.lang){p.alang=CFG.lang;}
    $('rp-export').href=CFG.export+'?'+new URLSearchParams(p).toString();
  }

  var COLS={
    lessons:[['id',str('rp_c_id')],['student_name',str('rp_c_student')],['teacher_name',str('rp_c_teacher')],['subject',str('rp_c_subject')],['status',str('rp_c_status')],['confirmed_time',str('rp_c_confirmed'),fmt],['flex_state',str('rp_c_flex')]],
    platform_earnings:[['lessonid',str('rp_c_lesson')],['teacher_name',str('rp_c_teacher')],['student_name',str('rp_c_student')],['lesson_time',str('rp_c_date'),fmt],['flex_value',str('rp_c_flexvalue'),money],['platform_percent',str('rp_c_platpct')],['platform_amount',str('rp_c_platform'),money],['status',str('rp_c_status')]],
    packages:[['id',str('rp_c_id')],['student_name',str('rp_c_student')],['package_name',str('rp_c_package')],['source',str('rp_c_source')],['price_paid',str('rp_c_price'),money],['flex_count',str('rp_c_flex')],['remaining_flex',str('rp_c_rem')],['reserved_flex',str('rp_c_resv')],['consumed_flex',str('rp_c_used')],['status',str('rp_c_status')]],
    student_flex:[['timecreated',str('rp_c_date'),fmt],['type',str('rp_c_type')],['amount',str('rp_c_amount')],['balance_before',str('rp_c_before')],['balance_after',str('rp_c_after')],['package',str('rp_c_package')],['lessonid',str('rp_c_lesson')],['performed_by',str('rp_c_by')],['reason',str('rp_c_reason')]]
  };

  function renderSummary(d){
    var box=$('rp-summary');box.innerHTML='';
    var s=d.summary; if(!s && d.balance){ // student_flex
      var b=d.balance;
      box.innerHTML='<div class="rp-chip">'+esc(str('rp_s_available'))+'<b>'+b.available_flex+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_reserved'))+'<b>'+b.reserved_flex+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_consumed'))+'<b>'+b.consumed_flex+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_package'))+'<b>'+esc(b.active_package||'—')+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_s_expires'))+'<b>'+fmt(b.expires_at)+'</b></div>';
      return;
    }
    if(!s){return;}
    Object.keys(s).forEach(function(k){
      if(k==='by_status'){return;}
      var v=s[k]; if(typeof v==='number' && String(k).indexOf('amount')>=0 || String(k).indexOf('earnings')>=0 || String(k).indexOf('value')>=0){v=money(v);}
      // Localised chip label (rp_sum_<field>), falling back to the humanised field name.
      var label=str('rp_sum_'+k); if(label==='rp_sum_'+k){label=k.replace(/_/g,' ');}
      box.innerHTML+='<div class="rp-chip">'+esc(label)+'<b>'+esc(v)+'</b></div>';
    });
  }

  function rowsOf(d){
    if(d.rows){return d.rows;}
    if(d.history){return d.history;}
    return [];
  }
  // Pretty labels for the audit-trail action keys (resolved from lang pack: rp_act_<action>).
  function actionLabel(a){var k='rp_act_'+a;return str(k)!==k?str(k):a;}

  function render(d){
    renderSummary(d);
    $('rp-timeline').style.display='none';
    var cols=COLS[current];
    var tl=(current==='lessons'); // lessons rows get an action-timeline button
    $('rp-head').innerHTML='<tr>'+cols.map(function(c){return '<th>'+esc(c[1])+'</th>';}).join('')+(tl?'<th>'+esc(str('rp_c_timeline'))+'</th>':'')+'</tr>';
    var rows=rowsOf(d);
    var pg=$('rp-body-pager');
    if(!rows.length){$('rp-body').innerHTML='<tr><td colspan="'+(cols.length+(tl?1:0))+'" class="text-muted">'+esc(str('rp_no_data'))+'</td></tr>';if(pg){pg.innerHTML='';}return;}
    function rowHtml(r){
      var tds=cols.map(function(c){var v=r[c[0]];if(c[2]){v=c[2](v);}return '<td>'+esc(v)+'</td>';}).join('');
      if(tl){tds+='<td><button type="button" class="btn btn-sm btn-outline-secondary rp-tl" data-id="'+r.id+'">'+esc(str('rp_c_timeline'))+'</button></td>';}
      return '<tr>'+tds+'</tr>';
    }
    AcademyUI.paginate({rows:rows,pageSize:15,pagerEl:pg,labels:{info:str('ui_pager_info')},
      render:function(items){$('rp-body').innerHTML=items.map(rowHtml).join('');}});
  }

  function showTimeline(id){
    apiGet('report_lesson_events',{lessonid:id}).then(function(d){
      var l=d.lesson||{};
      $('rp-tl-title').textContent=strf('rp_tl_title_full',{id:id,subject:l.subject||'',student:l.student_name||'',teacher:l.teacher_name||''});
      $('rp-tl-meta').innerHTML=
        '<div class="rp-chip">'+esc(str('rp_tl_joinedroom'))+'<b>'+fmt(l.teacher_joined_at)+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_tl_started'))+'<b>'+fmt(l.actual_start)+'</b></div>'+
        '<div class="rp-chip">'+esc(str('rp_tl_ended'))+'<b>'+fmt(l.actual_end)+'</b></div>';
      var ev=d.events||[];
      $('rp-tl-body').innerHTML=ev.length?ev.map(function(e,i){
        return '<tr><td>'+(i+1)+'</td><td>'+esc(actionLabel(e.action))+'</td><td>'+esc(e.actor_name||'—')+'</td><td>'+esc(e.role)+'</td><td>'+(e.time?fmt(e.time):'—')+'</td></tr>';
      }).join(''):'<tr><td colspan="5" class="text-muted">'+esc(str('rp_tl_none'))+'</td></tr>';
      $('rp-timeline').style.display='block';
      $('rp-timeline').scrollIntoView({behavior:'smooth',block:'nearest'});
    }).catch(function(e){msg(e.message,'danger');});
  }
  $('rp-tl-close').onclick=function(){$('rp-timeline').style.display='none';};
  $('rp-body').addEventListener('click',function(e){
    var b=e.target.closest&&e.target.closest('.rp-tl');
    if(b){showTimeline(b.getAttribute('data-id'));}
  });

  // US-B2B-1-9: custom panel for the nested user-activity payload (profile + subs + memberships + courses + actions).
  function renderUserActivity(d){
    var u=d.user||{};
    function tbl(title, head, rows){
      var h='<h5 class="mt-3">'+esc(title)+'</h5>';
      if(!rows.length){return h+'<p class="text-muted">'+esc(str('rp_ua_none'))+'</p>';}
      h+='<div style="overflow-x:auto"><table class="rp-table"><thead><tr>'+head.map(function(c){return '<th>'+esc(c)+'</th>';}).join('')+'</tr></thead><tbody>';
      h+=rows.map(function(r){return '<tr>'+r.map(function(c){return '<td>'+esc(c)+'</td>';}).join('')+'</tr>';}).join('');
      return h+'</tbody></table></div>';
    }
    var html='<div class="rp-summary" style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.5rem 0">'+
      '<div class="rp-chip">'+esc(u.name||'')+'<b>'+esc(u.email||'')+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_registered'))+'<b>'+fmt(u.registered)+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_lastlogin'))+'<b>'+fmt(u.last_login)+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_status'))+'<b>'+esc(u.account_status)+'</b></div>'+
      '<div class="rp-chip">'+esc(str('rp_ua_roles'))+'<b>'+esc((u.roles||[]).join(', ')||'—')+'</b></div>'+
    '</div>';
    html+=tbl(str('rp_ua_subs'),[str('rp_c_package'),str('rp_c_type'),str('rp_c_status'),str('rp_s_expires')],
      (d.subscriptions||[]).map(function(s){return [s.name,s.type+(s.seats?(' ('+s.seats+')'):''),s.status,fmt(s.expires_at)];}));
    html+=tbl(str('rp_ua_memberships'),[str('rp_c_package'),str('rp_c_status'),str('rp_c_date')],
      (d.memberships||[]).map(function(m){return [m.subscription,m.status,fmt(m.timecreated)];}));
    html+=tbl(str('rp_ua_courses'),[str('rp_c_id'),str('rp_c_package')],
      (d.courses||[]).map(function(c){return [c.id,c.fullname];}));
    html+=tbl(str('rp_ua_actions'),[str('rp_c_date'),str('rp_c_type'),str('rp_c_amount'),str('rp_c_status')],
      (d.actions||[]).map(function(a){return [fmt(a.timecreated),a.detail,money(a.amount),a.status];}));
    $('rp-useractivity').innerHTML=html;
  }

  function load(){
    msg('');
    if(current==='student_flex'){
      var sid=PICKERS['studentid']?PICKERS['studentid'].value():'';
      if(!sid){msg(str('rp_enter_student'),'info');$('rp-body').innerHTML='';$('rp-head').innerHTML='';$('rp-summary').innerHTML='';return;}
    }
    if(current==='user_activity'){
      var uid=PICKERS['userid']?PICKERS['userid'].value():'', em=$('f-email').value;
      if(!uid && !em){msg(str('rp_enter_user'),'info');$('rp-useractivity').innerHTML='';return;}
      apiGet(FN[current],readFilters()).then(renderUserActivity).catch(function(e){msg(e.message,'danger');});
      return;
    }
    updateExportLink();
    apiGet(FN[current],readFilters()).then(render).catch(function(e){msg(e.message,'danger');});
  }

  function togglePanels(){
    var ua=(current==='user_activity');
    $('rp-generic').style.display=ua?'none':'block';
    $('rp-summary').style.display=ua?'none':'flex';
    $('rp-useractivity').style.display=ua?'block':'none';
  }

  Array.prototype.forEach.call(document.querySelectorAll('#rp-tabs button'),function(b){
    b.onclick=function(){
      Array.prototype.forEach.call(document.querySelectorAll('#rp-tabs button'),function(x){x.classList.remove('active');});
      b.classList.add('active');current=b.getAttribute('data-tab');
      renderFilters();updateExportLink();togglePanels();
      if(current==='student_flex'){$('rp-body').innerHTML='';$('rp-head').innerHTML='';$('rp-summary').innerHTML='';msg(str('rp_enter_student_run'),'info');}
      else if(current==='user_activity'){$('rp-useractivity').innerHTML='';msg(str('rp_enter_user'),'info');}
      else{load();}
    };
  });

  renderFilters();togglePanels();load();
})();
JS
);

}

echo $OUTPUT->footer();
