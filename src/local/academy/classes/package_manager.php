<?php
namespace local_academy;

defined('MOODLE_INTERNAL') || die();

/**
 * CRUD + rules for lesson (Flex) packages.
 *
 * Covers user stories US-AD-1-1 .. US-AD-1-4 (see docs/specs/admin/).
 */
class package_manager {

    /** Allowed package statuses. */
    const STATUS_ACTIVE   = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Create a package (US-AD-1-1).
     *
     * @param array $data name, description, flex_count, price, expiration_days, active
     * @param int $userid admin performing the action
     * @return int new package id
     */
    public static function create_package(array $data, $userid) {
        global $DB;

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            throw new \moodle_exception('err_namerequired', 'local_academy');
        }
        $flexcount = (int)($data['flex_count'] ?? 0);
        if ($flexcount <= 0) {
            throw new \moodle_exception('err_flexpositive', 'local_academy');
        }
        $price = (float)($data['price'] ?? 0);
        if ($price < 0) {
            throw new \moodle_exception('err_pricenegative', 'local_academy');
        }
        $expiration = (int)($data['expiration_days'] ?? 0);
        if ($expiration < 0) {
            throw new \moodle_exception('err_expnegative', 'local_academy');
        }

        $now = time();
        $record = new \stdClass();
        $record->name            = $name;
        $record->description     = $data['description'] ?? '';
        $record->flex_count      = $flexcount;
        $record->price           = $price;
        $record->expiration_days = $expiration;
        $record->status          = !empty($data['active']) ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
        $record->timecreated     = $now;
        $record->timemodified    = $now;
        $record->usermodified    = $userid;

        return $DB->insert_record('academy_packages', $record);
    }

    /**
     * Update a package (US-AD-1-2). Only the provided fields are changed; changes apply to
     * future purchases only (existing purchases keep their snapshot terms).
     *
     * @param int $id
     * @param array $data subset of: name, description, flex_count, price, expiration_days, status
     * @param int $userid admin performing the action
     */
    public static function update_package($id, array $data, $userid) {
        global $DB;

        $package = self::get_package($id); // throws if missing

        $update = new \stdClass();
        $update->id = $package->id;

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new \moodle_exception('err_nameempty', 'local_academy');
            }
            $update->name = $name;
        }
        if (array_key_exists('description', $data)) {
            $update->description = $data['description'];
        }
        if (array_key_exists('flex_count', $data)) {
            $flexcount = (int)$data['flex_count'];
            if ($flexcount <= 0) {
                throw new \moodle_exception('err_flexpositive', 'local_academy');
            }
            $update->flex_count = $flexcount;
        }
        if (array_key_exists('price', $data)) {
            $price = (float)$data['price'];
            if ($price < 0) {
                throw new \moodle_exception('err_pricenegative', 'local_academy');
            }
            $update->price = $price;
        }
        if (array_key_exists('expiration_days', $data)) {
            $expiration = (int)$data['expiration_days'];
            if ($expiration < 0) {
                throw new \moodle_exception('err_expnegative', 'local_academy');
            }
            $update->expiration_days = $expiration;
        }
        if (array_key_exists('status', $data)) {
            $update->status = self::normalize_status($data['status']);
        }

        $update->timemodified = time();
        $update->usermodified = $userid;

        $DB->update_record('academy_packages', $update);
    }

    /**
     * Deactivate a package (US-AD-1-3). Existing purchases are unaffected.
     */
    public static function deactivate_package($id, $userid) {
        self::set_status($id, self::STATUS_INACTIVE, $userid);
    }

    /**
     * Reactivate a package (US-AD-1-3 note: admin can activate again later).
     */
    public static function activate_package($id, $userid) {
        self::set_status($id, self::STATUS_ACTIVE, $userid);
    }

    /**
     * Delete a package (US-AD-1-4). Allowed only if it was never purchased.
     */
    public static function delete_package($id) {
        global $DB;

        self::get_package($id); // throws if missing

        if (self::has_purchases($id)) {
            throw new \moodle_exception('err_haspurchases', 'local_academy');
        }

        $DB->delete_records('academy_packages', array('id' => $id));
    }

    /**
     * Whether any student has ever purchased/been assigned this package.
     */
    public static function has_purchases($packageid) {
        global $DB;
        return $DB->record_exists('academy_package_purchases', array('packageid' => $packageid));
    }

    /**
     * Fetch a single package or throw.
     */
    public static function get_package($id) {
        global $DB;
        $package = $DB->get_record('academy_packages', array('id' => $id));
        if (!$package) {
            throw new \moodle_exception('err_notfound', 'local_academy');
        }
        return $package;
    }

    /**
     * List packages, optionally filtered by status.
     *
     * @param string $status '' for all, or 'active' | 'inactive'
     * @return array
     */
    public static function get_packages($status = '') {
        global $DB;
        $conditions = array();
        if ($status !== '') {
            $conditions['status'] = self::normalize_status($status);
        }
        return array_values($DB->get_records('academy_packages', $conditions, 'timecreated DESC'));
    }

    /**
     * Set a package status (shared by activate/deactivate).
     */
    private static function set_status($id, $status, $userid) {
        global $DB;
        self::get_package($id); // throws if missing
        $update = new \stdClass();
        $update->id           = $id;
        $update->status       = $status;
        $update->timemodified = time();
        $update->usermodified = $userid;
        $DB->update_record('academy_packages', $update);
    }

    /**
     * Validate/normalize a status value.
     */
    private static function normalize_status($status) {
        $status = strtolower(trim((string)$status));
        if (!in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE), true)) {
            throw new \moodle_exception('err_status', 'local_academy');
        }
        return $status;
    }
}
