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

namespace local_nit_flex\service;

use local_nit_core\base\service;
use local_nit_flex\entity\package;
use local_nit_flex\entity\package_purchase;
use local_nit_flex\entity\payment;
use local_nit_flex\entity\flex_tx;
use local_nit_flex\exception\flex_exception;

/**
 * Student-facing package logic: purchase (fulfilment), admin assignment, my packages,
 * payment history, and the money totals the platform wallet needs. Ported from the
 * reference purchase_manager. All money is integer minor units.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purchase_service extends service {
    /**
     * The student's current active package entity, or null. Active = status active, has Flex,
     * and not expired.
     *
     * @param int $userid
     * @return package_purchase|null
     */
    public function active_entity(int $userid): ?package_purchase {
        $rows = package_purchase::get_records(
            ['userid' => $userid, 'status' => package_purchase::STATUS_ACTIVE], 'timecreated', 'DESC');
        foreach ($rows as $p) {
            if ($this->effective_status($p) === package_purchase::STATUS_ACTIVE) {
                return $p;
            }
        }
        return null;
    }

    /**
     * The student's active package as a summary array, or null.
     *
     * @param int $userid
     * @return array|null
     */
    public function active(int $userid): ?array {
        $p = $this->active_entity($userid);
        return $p ? $this->summarise($p) : null;
    }

    /**
     * Fulfil a package purchase after payment success (US-PK-1-2). Creates the purchase, records the
     * payment, and grants the opening Flex balance — all in one transaction.
     *
     * @param int $userid
     * @param int $packageid
     * @param string $method payment method label
     * @param string $reference external payment reference
     * @return array purchase summary
     */
    public function fulfil(int $userid, int $packageid, string $method = 'online', string $reference = ''): array {
        global $DB;
        $package = $this->available_package_or_fail($packageid);
        if ($this->active_entity($userid)) {
            throw new flex_exception('err_alreadyhaspackage');
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $purchase = $this->create_purchase($userid, $package,
            (int) $package->get('price_minor'),
            $method === 'admin_assigned' ? 'admin_assigned' : 'online', $now);

        $this->record_payment($userid, $purchase, $package, (int) $package->get('price_minor'),
            $method, $reference);

        (new flex_service())->log_grant($userid, (int) $purchase->get('id'),
            (int) $purchase->get('remaining_flex'), $userid, flex_tx::TYPE_PURCHASE,
            'Package purchased: ' . $package->get('name'));

        $transaction->allow_commit();
        return $this->summarise($purchase);
    }

    /**
     * Admin assigns a package to a student who paid offline (US-AD-4-1).
     *
     * @param int $adminid
     * @param int $studentid
     * @param int $packageid
     * @param int $amountminor amount paid externally (defaults to package price when <= 0)
     * @param string $method
     * @param string $reference
     * @return array
     */
    public function assign(int $adminid, int $studentid, int $packageid, int $amountminor,
            string $method = 'offline', string $reference = ''): array {
        global $DB;
        if (!\core_user::get_user($studentid, '*', IGNORE_MISSING)) {
            throw new flex_exception('err_studentnotfound');
        }
        $package = $this->available_package_or_fail($packageid);
        if ($this->active_entity($studentid)) {
            throw new flex_exception('err_studenthaspackage');
        }
        $price = $amountminor > 0 ? $amountminor : (int) $package->get('price_minor');
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        $purchase = $this->create_purchase($studentid, $package, $price, 'admin_assigned', $now);
        $this->record_payment($studentid, $purchase, $package, $price,
            $method !== '' ? $method : 'offline', $reference);
        (new flex_service())->log_grant($studentid, (int) $purchase->get('id'),
            (int) $purchase->get('remaining_flex'), $adminid, flex_tx::TYPE_ASSIGN,
            'Package assigned by admin: ' . $package->get('name'));

        $transaction->allow_commit();
        return $this->summarise($purchase);
    }

    /**
     * The student's packages, active first.
     *
     * @param int $userid
     * @return array
     */
    public function my_packages(int $userid): array {
        $rows = package_purchase::get_records(['userid' => $userid], 'timecreated', 'DESC');
        $out = array_map([$this, 'summarise'], array_values($rows));
        usort($out, static function ($a, $b) {
            if (($a['status'] === 'active') !== ($b['status'] === 'active')) {
                return $a['status'] === 'active' ? -1 : 1;
            }
            return $b['timeactivated'] - $a['timeactivated'];
        });
        return $out;
    }

    /**
     * The student's payment history, most recent first.
     *
     * @param int $userid
     * @return array
     */
    public function payment_history(int $userid): array {
        $rows = payment::get_records(['userid' => $userid], 'timecreated', 'DESC');
        $out = [];
        foreach ($rows as $p) {
            $out[] = [
                'id'             => (int) $p->get('id'),
                'packageid'      => (int) $p->get('packageid'),
                'amount_minor'   => (int) $p->get('amount_minor'),
                'method'         => $p->get('method'),
                'reference'      => $p->get('reference'),
                'transaction_no' => $p->get('transaction_no'),
                'status'         => $p->get('status'),
                'timecreated'    => (int) $p->get('timecreated'),
            ];
        }
        return $out;
    }

    /**
     * Money totals the platform wallet needs (US-FN reporting): total successful payments and the
     * value of all unconsumed (remaining + reserved) Flex across every purchase. Minor units.
     *
     * @return array{payments_minor:int, undistributed_minor:int}
     */
    public function money_totals(): array {
        global $DB;
        $payments = (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(amount_minor),0) FROM {nit_payment} WHERE status = :st",
            ['st' => payment::STATUS_SUCCESS]);

        $undistributed = 0;
        $purchases = $DB->get_records_select('nit_package_purchase', 'flex_count > 0', [],
            '', 'id, price_paid_minor, flex_count, remaining_flex, reserved_flex');
        foreach ($purchases as $p) {
            $value = (int) round((int) $p->price_paid_minor / (int) $p->flex_count);
            $undistributed += ((int) $p->remaining_flex + (int) $p->reserved_flex) * $value;
        }
        return ['payments_minor' => $payments, 'undistributed_minor' => $undistributed];
    }

    /**
     * Admin unassigns (cancels) a purchase, optionally recording a refund.
     *
     * @param int $purchaseid
     * @param bool $refund
     * @param int $adminid
     * @return void
     */
    public function unassign(int $purchaseid, bool $refund, int $adminid): void {
        global $DB;
        $purchase = package_purchase::get_record(['id' => $purchaseid]);
        if (!$purchase) {
            throw new flex_exception('err_notfound');
        }
        $now = time();
        $transaction = $DB->start_delegated_transaction();

        (new flex_service())->log_revoke((int) $purchase->get('userid'), $purchaseid, $adminid,
            'Unassigned by admin: ' . $adminid);
        $purchase->set('status', package_purchase::STATUS_CANCELLED);
        $purchase->set('expires_at', $now);
        $purchase->update();

        if ($refund) {
            (new payment(0, (object) [
                'userid'         => (int) $purchase->get('userid'),
                'purchaseid'     => $purchaseid,
                'packageid'      => (int) $purchase->get('packageid'),
                'amount_minor'   => -1 * abs((int) $purchase->get('price_paid_minor')),
                'method'         => 'refund',
                'reference'      => 'Unassigned by admin: ' . $adminid,
                'transaction_no' => self::generate_txn(),
                'status'         => payment::STATUS_SUCCESS,
            ]))->create();
        }
        $transaction->allow_commit();
    }

    /**
     * Compute the real status of a purchase at read time.
     *
     * @param package_purchase $purchase
     * @return string
     */
    public function effective_status(package_purchase $purchase): string {
        if ($purchase->get('status') === package_purchase::STATUS_ACTIVE) {
            if ((int) $purchase->get('remaining_flex') <= 0) {
                return package_purchase::STATUS_FULLY_USED;
            }
            $expires = (int) $purchase->get('expires_at');
            if ($expires > 0 && time() > $expires) {
                return package_purchase::STATUS_EXPIRED;
            }
        }
        return $purchase->get('status');
    }

    /**
     * Create the purchase record (snapshot + opening balance).
     *
     * @param int $userid
     * @param package $package
     * @param int $priceminor
     * @param string $source
     * @param int $now
     * @return package_purchase
     */
    private function create_purchase(int $userid, package $package, int $priceminor, string $source,
            int $now): package_purchase {
        $expirationdays = (int) $package->get('expiration_days');
        $purchase = new package_purchase(0, (object) [
            'packageid'        => (int) $package->get('id'),
            'userid'           => $userid,
            'price_paid_minor' => $priceminor,
            'flex_count'       => (int) $package->get('flex_count'),
            'expiration_days'  => $expirationdays,
            'status'           => package_purchase::STATUS_ACTIVE,
            'source'           => $source,
            'remaining_flex'   => (int) $package->get('flex_count'),
            'expires_at'       => $expirationdays > 0 ? $now + ($expirationdays * DAYSECS) : 0,
            'timeactivated'    => $now,
        ]);
        $purchase->create();
        return $purchase;
    }

    /**
     * Record a successful payment for a purchase.
     *
     * @param int $userid
     * @param package_purchase $purchase
     * @param package $package
     * @param int $amountminor
     * @param string $method
     * @param string $reference
     * @return void
     */
    private function record_payment(int $userid, package_purchase $purchase, package $package,
            int $amountminor, string $method, string $reference): void {
        (new payment(0, (object) [
            'userid'         => $userid,
            'purchaseid'     => (int) $purchase->get('id'),
            'packageid'      => (int) $package->get('id'),
            'amount_minor'   => $amountminor,
            'method'         => $method,
            'reference'      => $reference !== '' ? $reference : null,
            'transaction_no' => self::generate_txn(),
            'status'         => payment::STATUS_SUCCESS,
        ]))->create();
    }

    /**
     * Load an active (purchasable) package or throw.
     *
     * @param int $packageid
     * @return package
     */
    private function available_package_or_fail(int $packageid): package {
        $package = package::get_record(['id' => $packageid]);
        if (!$package) {
            throw new flex_exception('err_notfound');
        }
        if ($package->get('status') !== package::STATUS_ACTIVE) {
            throw new flex_exception('err_packagenotavailable');
        }
        return $package;
    }

    /**
     * Shape a purchase entity as a summary array.
     *
     * @param package_purchase $p
     * @return array
     */
    private function summarise(package_purchase $p): array {
        $total = (int) $p->get('flex_count');
        return [
            'id'              => (int) $p->get('id'),
            'packageid'       => (int) $p->get('packageid'),
            'userid'          => (int) $p->get('userid'),
            'total_flex'      => $total,
            'remaining_flex'  => (int) $p->get('remaining_flex'),
            'reserved_flex'   => (int) $p->get('reserved_flex'),
            'consumed_flex'   => (int) $p->get('consumed_flex'),
            'used_flex'       => $total - (int) $p->get('remaining_flex'),
            'price_paid_minor' => (int) $p->get('price_paid_minor'),
            'status'          => $this->effective_status($p),
            'source'          => $p->get('source'),
            'timeactivated'   => (int) $p->get('timeactivated'),
            'expires_at'      => (int) $p->get('expires_at'),
        ];
    }

    /**
     * Generate a unique-ish transaction number.
     *
     * @return string
     */
    private static function generate_txn(): string {
        return 'TXN' . strtoupper(substr(md5(uniqid('', true)), 0, 14));
    }
}
