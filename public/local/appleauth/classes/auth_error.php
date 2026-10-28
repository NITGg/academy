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

namespace local_appleauth;

/**
 * A sign-in failure the endpoint reports as {"error": code} with an HTTP status.
 *
 * @package    local_appleauth
 * @copyright  2026 NIT Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class auth_error extends \Exception {

    /** @var string machine-readable error code sent to the app */
    public string $errorcode;

    /** @var int HTTP status */
    public int $status;

    /** @var array extra keys merged into the JSON body */
    public array $extra;

    /**
     * Constructor.
     *
     * @param string $errorcode machine-readable error code
     * @param int $status HTTP status
     * @param array $extra extra keys merged into the JSON body
     */
    public function __construct(string $errorcode, int $status = 400, array $extra = []) {
        parent::__construct($errorcode);
        $this->errorcode = $errorcode;
        $this->status = $status;
        $this->extra = $extra;
    }
}
