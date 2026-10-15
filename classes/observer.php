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

namespace local_validat;

/**
 * Event observers.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A learner completed a course: queue the certificate.
     *
     * @param \core\event\course_completed $event Event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;
        $userid = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;
        if (!$userid || !$courseid) {
            return;
        }
        $timecompleted = $DB->get_field('course_completions', 'timecompleted', ['id' => $event->objectid]);
        issuer::queue($courseid, $userid, $timecompleted ? (int) $timecompleted : (int) $event->timecreated);
    }

    /**
     * A course was deleted: remove its settings and records. Certificates on valid.at stay valid.
     *
     * @param \core\event\course_deleted $event Event
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        global $DB;
        $DB->delete_records('local_validat_courses', ['courseid' => $event->objectid]);
        $DB->delete_records('local_validat_issues', ['courseid' => $event->objectid]);
    }

    /**
     * A user was deleted: remove the records. Certificates on valid.at stay valid.
     *
     * @param \core\event\user_deleted $event Event
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('local_validat_issues', ['userid' => $event->objectid]);
    }
}
