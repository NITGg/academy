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

namespace local_nit_flex\api;

use local_nit_flex\service\purchase_service;

/**
 * Public facade for buying and holding Flex packages. Money is integer minor units.
 *
 * In A1, {@see self::fulfil()} is the payment-success entry point (called by the core_payment
 * service_provider once a real gateway exists, and directly by CLI/tests meanwhile); {@see self::assign()}
 * is the admin offline path.
 *
 * @package    local_nit_flex
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @api
 */
final class purchase {
    /**
     * Fulfil a package purchase after payment success (US-PK-1-2).
     *
     * @param int $userid
     * @param int $packageid
     * @param string $method
     * @param string $reference
     * @return array
     */
    public static function fulfil(int $userid, int $packageid, string $method = 'online',
            string $reference = ''): array {
        return (new purchase_service())->fulfil($userid, $packageid, $method, $reference);
    }

    /**
     * Admin assigns a package to a student who paid offline (US-AD-4-1).
     *
     * @param int $adminid
     * @param int $studentid
     * @param int $packageid
     * @param int $amountminor
     * @param string $method
     * @param string $reference
     * @return array
     */
    public static function assign(int $adminid, int $studentid, int $packageid, int $amountminor = 0,
            string $method = 'offline', string $reference = ''): array {
        return (new purchase_service())->assign($adminid, $studentid, $packageid, $amountminor, $method, $reference);
    }

    /**
     * The student's active package summary, or null.
     *
     * @param int $userid
     * @return array|null
     */
    public static function active(int $userid): ?array {
        return (new purchase_service())->active($userid);
    }

    /**
     * The student's packages, active first (US-PK-2-1).
     *
     * @param int $userid
     * @return array
     */
    public static function my_packages(int $userid): array {
        return (new purchase_service())->my_packages($userid);
    }

    /**
     * The student's payment history (US-PK-2-1).
     *
     * @param int $userid
     * @return array
     */
    public static function payment_history(int $userid): array {
        return (new purchase_service())->payment_history($userid);
    }

    /**
     * Admin unassigns (cancels) a purchase.
     *
     * @param int $purchaseid
     * @param bool $refund
     * @param int $adminid
     * @return void
     */
    public static function unassign(int $purchaseid, bool $refund, int $adminid): void {
        (new purchase_service())->unassign($purchaseid, $refund, $adminid);
    }
}
