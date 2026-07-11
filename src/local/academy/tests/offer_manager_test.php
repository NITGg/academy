<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for offer CRUD + rules (US-AD-8-1..8-3, US-US-OF-1-1/1-3).
 *
 * @covers \local_academy\offer_manager
 */
final class offer_manager_test extends \advanced_testcase {

    public function test_create_and_get_with_scope(): void {
        $this->resetAfterTest();
        $id = offer_manager::create_offer([
            'name' => 'Summer sale', 'discount_type' => 'percent', 'discount_value' => 20,
            'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]],
        ], 2);

        $o = offer_manager::get_offer($id);
        $this->assertSame('Summer sale', $o['name']);
        $this->assertSame('percent', $o['discount_type']);
        $this->assertEquals(20.0, $o['discount_value']);
        $this->assertSame('active', $o['status']);
        $this->assertCount(1, $o['applies_to']);
        $this->assertSame('course', $o['applies_to'][0]['item_type']);
    }

    public function test_name_required(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        offer_manager::create_offer(['name' => '  ', 'discount_type' => 'percent', 'discount_value' => 10,
            'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);
    }

    public function test_update_and_status_toggle(): void {
        $this->resetAfterTest();
        $id = offer_manager::create_offer(['name' => 'X', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);

        offer_manager::update_offer($id, ['name' => 'Y', 'discount_value' => 15], 2);
        $o = offer_manager::get_offer($id);
        $this->assertSame('Y', $o['name']);
        $this->assertEquals(15.0, $o['discount_value']);

        offer_manager::deactivate_offer($id, 2);
        $this->assertSame('inactive', offer_manager::get_offer($id)['status']);
    }

    public function test_delete_blocked_when_used(): void {
        global $DB;
        $this->resetAfterTest();
        $id = offer_manager::create_offer(['name' => 'Used', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);
        $DB->insert_record('academy_offer_usages', (object) [
            'offerid' => $id, 'userid' => 3, 'transactionid' => 1, 'item_type' => 'course', 'item_id' => 9,
            'original_amount' => 100, 'discount_amount' => 5, 'final_amount' => 95, 'timecreated' => time(),
        ]);
        $this->expectException(\moodle_exception::class);
        offer_manager::delete_offer($id);
    }

    public function test_available_excludes_inactive(): void {
        $this->resetAfterTest();
        offer_manager::create_offer(['name' => 'On', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 1, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);
        offer_manager::create_offer(['name' => 'Off', 'discount_type' => 'fixed', 'discount_value' => 5,
            'active' => 0, 'items' => [['item_type' => 'course', 'item_id' => 0]]], 2);

        $names = array_map(function($o) { return $o['name']; }, offer_manager::get_available_offers());
        $this->assertContains('On', $names);
        $this->assertNotContains('Off', $names);
    }
}
