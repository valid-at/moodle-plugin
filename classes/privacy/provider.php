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

namespace local_validat\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider.
 *
 * Moodle stores which certificate belongs to which course completion. The
 * certificate itself is held by valid.at; deleting data in Moodle does not
 * delete it there, because certificates are revoked, not deleted.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Data stored and sent.
     *
     * @param collection $collection Collection to add to
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_validat_issues', [
            'userid' => 'privacy:metadata:issues:userid',
            'courseid' => 'privacy:metadata:issues:courseid',
            'timecompleted' => 'privacy:metadata:issues:timecompleted',
            'status' => 'privacy:metadata:issues:status',
            'code' => 'privacy:metadata:issues:code',
            'verifyurl' => 'privacy:metadata:issues:verifyurl',
            'holderurl' => 'privacy:metadata:issues:holderurl',
            'validuntil' => 'privacy:metadata:issues:validuntil',
            'revokereason' => 'privacy:metadata:issues:revokereason',
        ], 'privacy:metadata:issues');

        $collection->add_external_location_link('validat', [
            'fullname' => 'privacy:metadata:validat:fullname',
            'email' => 'privacy:metadata:validat:email',
            'title' => 'privacy:metadata:validat:title',
            'timecompleted' => 'privacy:metadata:validat:timecompleted',
            'course' => 'privacy:metadata:validat:course',
        ], 'privacy:metadata:validat');

        return $collection;
    }

    /**
     * Course contexts with records of the user.
     *
     * @param int $userid User
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {local_validat_issues} i ON i.courseid = ctx.instanceid AND ctx.contextlevel = :courselevel
              WHERE i.userid = :userid",
            ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]
        );
        return $contextlist;
    }

    /**
     * Users with records in a course.
     *
     * @param userlist $userlist List to add to
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql(
            'userid',
            "SELECT userid FROM {local_validat_issues} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Exports the records of the user.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $records = $DB->get_records(
                'local_validat_issues',
                ['courseid' => $context->instanceid, 'userid' => $userid],
                'timecompleted ASC'
            );
            if (!$records) {
                continue;
            }
            $certificates = [];
            foreach ($records as $record) {
                $certificates[] = (object) [
                    'timecompleted' => transform::datetime($record->timecompleted),
                    'status' => get_string('status_' . $record->status, 'local_validat'),
                    'code' => $record->code,
                    'verifyurl' => $record->verifyurl,
                    'holderurl' => $record->holderurl,
                    'validuntil' => $record->validuntil,
                    'revokereason' => $record->revokereason,
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_validat')],
                (object) ['certificates' => $certificates]
            );
        }
    }

    /**
     * Deletes all records of a course.
     *
     * @param \context $context Context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_course) {
            $DB->delete_records('local_validat_issues', ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Deletes the records of one user.
     *
     * @param approved_contextlist $contextlist Approved contexts
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records('local_validat_issues', ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Deletes the records of several users in one course.
     *
     * @param approved_userlist $userlist Approved users
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course || !$userlist->get_userids()) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('local_validat_issues', "courseid = :courseid AND userid $insql", $params);
    }
}
