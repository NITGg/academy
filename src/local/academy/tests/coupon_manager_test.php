<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for coupon CRUD + rules (US-AD-7-1..7-3, US-US-CP-1-1/1-3).
 *
 * @covers \local_academy\coupon_manager
 */
final class coupon_manager_test extends \advanced_testcase {

    public function test_create_and_get_with_scope(): void {
        $this->resetAfterTest();
        $id = coupon_manager::create_coupon([
            'code' => 'WELCOME10', 'discount_type' => 'percent', 'discount_value' => 10,
            'max_discount' => 25, 'usage_type' => 'multiple', 'usage_limit' => 100,
            'active' => 1, 'items' => [
                ['item_type' => 'package', 'item_id' => 0],
                ['item_type' => 'subscription', 'item_id' => 0],
            ],
        ], 2);

        $c = coupon_manager::get_coupon($id);
        $this->assertSame('WELCOME10', $c['code']);
        $this->assertSame('percent', $c['discount_type']);
        $this->assertEquals(10.0, $c['discount_value']);
        $this->assertEquals(25.0, $c['max_discount']);
        $this->assertSame('active', $c['status']);
        $this->assertCount(2, $c['applies_to']);
    }

    public function test_unique_code_enforced(): void {
        $this->resetAfterTest();
        coupon_manager::create_coupon(['code' => 'DUP', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        $this->expectException(\moodle_exception::class);
        // Case-insensitive clash.
        coupon_manager::create_coupon(['code' => 'dup', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
    }

    public function test_percentage_over_100_rejected(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        coupon_manager::create_coupon(['code' => 'BAD', 'discount_type' => 'percent', 'discount_value' => 150,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
    }

    public function test_update_and_status_toggle(): void {
        $this->resetAfterTest();
        $id = coupon_manager::create_coupon(['code' => 'EDIT', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        coupon_manager::update_coupon($id, ['discount_value' => 15, 'code' => 'EDITED'], 2);
        $c = coupon_manager::get_coupon($id);
        $this->assertEquals(15.0, $c['discount_value']);
        $this->assertSame('EDITED', $c['code']);

        coupon_manager::deactivate_coupon($id, 2);
        $this->assertSame('inactive', coupon_manager::get_coupon($id)['status']);
        coupon_manager::activate_coupon($id, 2);
        $this->assertSame('active', coupon_manager::get_coupon($id)['status']);
    }

    public function test_delete_unused_then_blocked_when_used(): void {
        global $DB;
        $this->resetAfterTest();

        // Unused → deletable.
        $id1 = coupon_manager::create_coupon(['code' => 'GONE', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        coupon_manager::delete_coupon($id1);
        $this->assertFalse($DB->record_exists('academy_coupons', ['id' => $id1]));

        // Used → delete blocked (US-AD-7-3).
        $id2 = coupon_manager::create_coupon(['code' => 'KEEP', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        $DB->insert_record('academy_coupon_usages', (object) [
            'couponid' => $id2, 'userid' => 3, 'transactionid' => 1, 'item_type' => 'package', 'item_id' => 9,
            'original_amount' => 100, 'discount_amount' => 5, 'final_amount' => 95, 'timecreated' => time(),
        ]);
        $this->expectException(\moodle_exception::class);
        coupon_manager::delete_coupon($id2);
    }

    public function test_available_excludes_inactive_and_out_of_window(): void {
        $this->resetAfterTest();
        coupon_manager::create_coupon(['code' => 'LIVE', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        coupon_manager::create_coupon(['code' => 'OFF', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 0, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        coupon_manager::create_coupon(['code' => 'PAST', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'startdate' => 100, 'enddate' => 200,
            'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        $codes = array_map(function($c) { return $c['code']; }, coupon_manager::get_available_coupons());
        $this->assertContains('LIVE', $codes);
        $this->assertNotContains('OFF', $codes);
        $this->assertNotContains('PAST', $codes);
    }
}
