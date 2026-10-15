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

namespace local_validat\task;

/**
 * Sends one course completion to valid.at. Throws on temporary problems, so
 * Moodle tries again later with increasing delay.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_certificate extends \core\task\adhoc_task {
    /**
     * Name of the task.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_issue', 'local_validat');
    }

    /**
     * Runs the task.
     */
    public function execute() {
        $data = $this->get_custom_data();
        if (empty($data->issueid)) {
            return;
        }
        \local_validat\issuer::issue((int) $data->issueid);
    }
}
