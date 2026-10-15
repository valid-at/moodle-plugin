<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_validat\local;

/**
 * Error answer of the valid.at API, or no answer at all.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \Exception {
    /** @var int HTTP status, 0 when valid.at could not be reached */
    public $status;

    /** @var array Reasons per field, as sent by valid.at */
    public $fields;

    /**
     * Constructor.
     *
     * @param int $status HTTP status, 0 when valid.at could not be reached
     * @param string $message Message from valid.at
     * @param array $fields Reasons per field
     */
    public function __construct(int $status, string $message, array $fields = []) {
        parent::__construct($message);
        $this->status = $status;
        $this->fields = $fields;
    }

    /**
     * Whether trying again later can help: no connection, too many requests or an error on the server.
     *
     * @return bool
     */
    public function is_temporary(): bool {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }

    /**
     * Status, message and the reasons per field, for logs and the course page.
     *
     * @return string
     */
    public function describe(): string {
        $text = implode(': ', array_filter([$this->status ? 'HTTP ' . $this->status : '', $this->getMessage()], 'strlen'));
        foreach ($this->fields as $field => $reasons) {
            $text .= ' ' . $field . ': ' . implode(' ', array_map('strval', (array) $reasons));
        }
        return $text;
    }
}
