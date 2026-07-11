<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the coupon/offer discount engine (US-AD-7-*, US-AD-8-*, US-US-CP-*, US-US-OF-*).
 *
 * @covers \local_academy\discount_manager
 */
final class discount_manager_test extends \advanced_testcase {

    /** Insert a package with a price and return its id. */
    private function make_package(float $price): int {
        global $DB;
        return (int) $DB->insert_record('academy_packages', (object) [
            'name' => 'Pkg', 'description' => '', 'flex_count' => 10, 'price' => $price,
            'expiration_days' => 0, 'status' => 'active', 'timecreated' => time(),
            'timemodified' => time(), 'usermodified' => 2,
        ]);
    }

    public function test_discount_amount_percent_fixed_and_caps(): void {
        // Percentage.
        $this->assertEquals(20.0, discount_manager::discount_amount('percent', 10, null, 200));
        // Fixed.
        $this->assertEquals(50.0, discount_manager::discount_amount('fixed', 50, null, 200));
        // Capped by max_discount.
        $this->assertEquals(30.0, discount_manager::discount_amount('percent', 50, 30, 200));
        // Never exceeds the base price.
        $this->assertEquals(200.0, discount_manager::discount_amount('fixed', 1000, null, 200));
    }

    public function test_scope_matches_all_and_specific(): void {
        $all = [(object) ['item_type' => 'package', 'item_id' => 0]];
        $this->assertTrue(discount_manager::scope_matches($all, 'package', 7));
        $this->assertFalse(discount_manager::scope_matches($all, 'course', 7));

        $specific = [(object) ['item_type' => 'package', 'item_id' => 7]];
        $this->assertTrue(discount_manager::scope_matches($specific, 'package', 7));
        $this->assertFalse(discount_manager::scope_matches($specific, 'package', 8));
    }

    public function test_resolve_offer_only(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        offer_manager::create_offer([
            'name' => 'Ten off', 'discount_type' => 'percent', 'discount_value' => 10,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]],
        ], 2);

        $r = discount_manager::resolve('package', $pkgid, 3);
        $this->assertEquals(200.0, $r['original']);
        $this->assertEquals(20.0, $r['offer_discount']);
        $this->assertEquals(0.0, $r['coupon_discount']);
        $this->assertEquals(180.0, $r['final']);
    }

    public function test_resolve_offers_stack(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        offer_manager::create_offer(['name' => 'A', 'discount_type' => 'percent', 'discount_value' => 10,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        offer_manager::create_offer(['name' => 'B', 'discount_type' => 'fixed', 'discount_value' => 60,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => $pkgid]]], 2);

        $r = discount_manager::resolve('package', $pkgid, 3);
        $this->assertEquals(80.0, $r['offer_discount']); // offers STACK: 20 (10% of 200) + 60
        $this->assertEquals(120.0, $r['final']);
        $this->assertCount(2, $r['offers']);
    }

    public function test_resolve_two_percent_offers_sum(): void {
        // The reported scenario: two percentage offers should combine (30% + 30% = 60% off).
        $this->resetAfterTest();
        $pkgid = $this->make_package(1000);
        offer_manager::create_offer(['name' => 'Thirty A', 'discount_type' => 'percent', 'discount_value' => 30,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        offer_manager::create_offer(['name' => 'Thirty B', 'discount_type' => 'percent', 'discount_value' => 30,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        $r = discount_manager::resolve('package', $pkgid, 3);
        $this->assertEquals(600.0, $r['offer_discount']); // 300 + 300
        $this->assertEquals(400.0, $r['final']);

        $summary = discount_manager::offer_summary('package', $pkgid, 1000);
        $this->assertEquals(600.0, $summary['discount']);
        $this->assertEquals('-60%', $summary['label']);
    }

    public function test_stacked_offers_clamp_to_base(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(100);
        offer_manager::create_offer(['name' => 'Seventy', 'discount_type' => 'percent', 'discount_value' => 70,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        offer_manager::create_offer(['name' => 'Sixty', 'discount_type' => 'percent', 'discount_value' => 60,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        $r = discount_manager::resolve('package', $pkgid, 3);
        $this->assertEquals(100.0, $r['offer_discount']); // 70 + 60 = 130 clamped to 100
        $this->assertEquals(0.0, $r['final']);
    }

    public function test_record_usage_records_every_stacked_offer(): void {
        global $DB;
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        offer_manager::create_offer(['name' => 'A', 'discount_type' => 'percent', 'discount_value' => 10,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        offer_manager::create_offer(['name' => 'B', 'discount_type' => 'percent', 'discount_value' => 20,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        $r = discount_manager::resolve('package', $pkgid, 3);
        discount_manager::record_usage($r, 3, 888, 'package', $pkgid);
        $this->assertEquals(2, $DB->count_records('academy_offer_usages', ['transactionid' => 888]));
    }

    public function test_resolve_coupon_stacks_on_offer(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        offer_manager::create_offer(['name' => 'Ten', 'discount_type' => 'percent', 'discount_value' => 10,
            'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        coupon_manager::create_coupon(['code' => 'SAVE50', 'discount_type' => 'fixed', 'discount_value' => 50,
            'usage_type' => 'multiple', 'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => $pkgid]]], 2);

        $r = discount_manager::resolve('package', $pkgid, 3, 'SAVE50');
        $this->assertEquals(20.0, $r['offer_discount']);   // 10% of 200
        $this->assertEquals(50.0, $r['coupon_discount']);  // fixed 50 on the 180 running total
        $this->assertEquals(130.0, $r['final']);
        $this->assertEquals(70.0, $r['discount']);
    }

    public function test_resolve_final_never_below_zero(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        coupon_manager::create_coupon(['code' => 'HUGE', 'discount_type' => 'fixed', 'discount_value' => 1000,
            'usage_type' => 'multiple', 'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        $r = discount_manager::resolve('package', $pkgid, 3, 'HUGE');
        $this->assertEquals(0.0, $r['final']);
    }

    public function test_coupon_case_insensitive_and_scope_mismatch(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        coupon_manager::create_coupon(['code' => 'MixedCase', 'discount_type' => 'percent', 'discount_value' => 10,
            'usage_type' => 'multiple', 'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => $pkgid]]], 2);

        // Lower-case code still resolves.
        $r = discount_manager::resolve('package', $pkgid, 3, 'mixedcase');
        $this->assertEquals(20.0, $r['coupon_discount']);

        // Same coupon does not apply to a subscription (scope mismatch → throws).
        $this->expectException(\moodle_exception::class);
        discount_manager::resolve('subscription', $pkgid, 3, 'mixedcase');
    }

    public function test_coupon_expired_window_throws(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        coupon_manager::create_coupon(['code' => 'OLD', 'discount_type' => 'percent', 'discount_value' => 10,
            'usage_type' => 'multiple', 'active' => 1, 'startdate' => 100, 'enddate' => 200,
            'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        $this->expectException(\moodle_exception::class);
        discount_manager::resolve('package', $pkgid, 3, 'OLD'); // now >> enddate
    }

    public function test_once_coupon_usage_limit(): void {
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        $cid = coupon_manager::create_coupon(['code' => 'ONCE', 'discount_type' => 'fixed', 'discount_value' => 10,
            'usage_type' => 'once', 'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);

        // First resolve + record is fine.
        $r = discount_manager::resolve('package', $pkgid, 3, 'ONCE');
        discount_manager::record_usage($r, 3, 555, 'package', $pkgid);

        // Second attempt is rejected (one-time coupon already used).
        $this->expectException(\moodle_exception::class);
        discount_manager::resolve('package', $pkgid, 4, 'ONCE');
    }

    public function test_record_usage_idempotent_per_transaction(): void {
        global $DB;
        $this->resetAfterTest();
        $pkgid = $this->make_package(200);
        $cid = coupon_manager::create_coupon(['code' => 'MULTI', 'discount_type' => 'fixed', 'discount_value' => 10,
            'usage_type' => 'multiple', 'active' => 1, 'items' => [['item_type' => 'package', 'item_id' => 0]]], 2);
        $r = discount_manager::resolve('package', $pkgid, 3, 'MULTI');

        discount_manager::record_usage($r, 3, 777, 'package', $pkgid);
        discount_manager::record_usage($r, 3, 777, 'package', $pkgid); // same transaction — must not double

        $this->assertEquals(1, $DB->count_records('academy_coupon_usages', ['couponid' => $cid, 'transactionid' => 777]));
    }
}
