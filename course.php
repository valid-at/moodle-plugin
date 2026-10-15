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

/**
 * Certificates of a course: settings, list, revoking and issuing for past completions.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/completionlib.php');

use core\output\notification;
use local_validat\issuer;
use local_validat\local\api_exception;

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 50;

$course = get_course($id);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/validat:view', $context);
$canmanage = has_capability('local/validat:manage', $context);

$url = new moodle_url('/local/validat/course.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('coursenav', 'local_validat'));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));

if ($canmanage && $action === 'retry') {
    require_sesskey();
    $queued = issuer::retry(required_param('issue', PARAM_INT), $course->id);
    redirect(
        $url,
        get_string($queued ? 'retry_queued' : 'retry_notpossible', 'local_validat'),
        null,
        $queued ? notification::NOTIFY_SUCCESS : notification::NOTIFY_WARNING
    );
}

if ($canmanage && $action === 'backfill') {
    require_sesskey();
    $count = issuer::queue_existing($course->id);
    redirect($url, get_string('backfill_queued', 'local_validat', $count), null, notification::NOTIFY_SUCCESS);
}

if ($canmanage && $action === 'revoke') {
    $conditions = ['id' => required_param('issue', PARAM_INT), 'courseid' => $course->id, 'status' => issuer::STATUS_ISSUED];
    $issue = $DB->get_record('local_validat_issues', $conditions, '*', MUST_EXIST);
    $learner = core_user::get_user($issue->userid);
    $who = ($learner ? fullname($learner) : '') . ', ' . get_string('code', 'local_validat') . ' ' .
        issuer::format_code($issue->code);
    $form = new \local_validat\form\revoke(new moodle_url('/local/validat/course.php'), ['who' => $who]);
    if ($form->is_cancelled()) {
        redirect($url);
    }
    $error = null;
    if ($data = $form->get_data()) {
        try {
            issuer::revoke((int) $issue->id, $course->id, trim($data->reason));
            redirect($url, get_string('revoked', 'local_validat'), null, notification::NOTIFY_SUCCESS);
        } catch (api_exception $e) {
            $error = issuer::error_text($e);
        }
    }
    $form->set_data(['id' => $course->id, 'issue' => $issue->id, 'action' => 'revoke']);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('revoketitle', 'local_validat'));
    echo html_writer::tag('p', get_string('revoke_intro', 'local_validat'));
    if ($error !== null) {
        echo $OUTPUT->notification(get_string('revoke_failed', 'local_validat', s($error)), notification::NOTIFY_ERROR);
    }
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

$settings = issuer::settings($course);
$form = null;
if ($canmanage) {
    $form = new \local_validat\form\course_settings($url);
    if ($data = $form->get_data()) {
        issuer::save_settings($course->id, $data);
        redirect($url, get_string('changessaved'), null, notification::NOTIFY_SUCCESS);
    }
    $form->set_data([
        'id' => $course->id,
        'enabled' => $settings->enabled,
        'title' => $settings->title,
        'validmonths' => $settings->validmonths,
        'note' => $settings->note,
        'sendemail' => $settings->sendemail,
        'notify' => $settings->notify,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursenav', 'local_validat'));
echo html_writer::tag('p', get_string('course_intro', 'local_validat'));

if (trim((string) get_config('local_validat', 'apikey')) === '') {
    $message = get_string('warning_nokey', 'local_validat');
    if (has_capability('moodle/site:config', context_system::instance())) {
        $message .= ' ' . html_writer::link(
            new moodle_url('/admin/settings.php', ['section' => 'local_validat']),
            get_string('settings', 'local_validat')
        );
    }
    echo $OUTPUT->notification($message, notification::NOTIFY_WARNING);
}
$completion = new completion_info($course);
if (!$completion->is_enabled() || !$completion->has_criteria()) {
    echo $OUTPUT->notification(get_string('warning_completion', 'local_validat') . ' ' .
        html_writer::link(
            new moodle_url('/course/completion.php', ['id' => $course->id]),
            get_string('warning_completion_link', 'local_validat')
        ), notification::NOTIFY_WARNING);
}

if ($form) {
    $form->display();
} else {
    echo html_writer::tag('p', $settings->enabled
        ? get_string('summary_on', 'local_validat', s($settings->title))
        : get_string('summary_off', 'local_validat'));
}

if ($canmanage && $settings->enabled && ($missing = issuer::count_missing($course->id))) {
    echo $OUTPUT->box_start('generalbox');
    echo html_writer::tag('p', get_string('backfill_info', 'local_validat', $missing));
    $button = new single_button(new moodle_url($url, ['action' => 'backfill']), get_string('backfill', 'local_validat'), 'post');
    $button->add_confirm_action(get_string('backfill_confirm', 'local_validat', $missing));
    echo $OUTPUT->render($button);
    echo $OUTPUT->box_end();
}

$total = $DB->count_records('local_validat_issues', ['courseid' => $course->id]);
echo $OUTPUT->heading(get_string('issues', 'local_validat', $total), 3);
if (!$total) {
    echo html_writer::tag('p', get_string('issues_none', 'local_validat'));
    echo $OUTPUT->footer();
    exit;
}

$issues = $DB->get_records(
    'local_validat_issues',
    ['courseid' => $course->id],
    'timecompleted DESC, id DESC',
    '*',
    $page * $perpage,
    $perpage
);
$users = $DB->get_records_list('user', 'id', array_unique(array_column($issues, 'userid')));
$dateformat = get_string('strftimedate', 'langconfig');
$newwindow = html_writer::span(' (' . get_string('opensinnewwindow') . ')', 'sr-only visually-hidden');

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    get_string('learner', 'local_validat'),
    get_string('completed', 'local_validat'),
    get_string('status', 'local_validat'),
    get_string('code', 'local_validat'),
];
if ($canmanage) {
    $table->head[] = get_string('actions');
}
foreach ($issues as $issue) {
    $learner = $users[$issue->userid] ?? null;
    $name = $learner
        ? html_writer::link(
            new moodle_url('/user/view.php', ['id' => $issue->userid, 'course' => $course->id]),
            s(fullname($learner))
        )
        : '';

    $status = html_writer::tag('strong', get_string('status_' . $issue->status, 'local_validat'));
    if ($issue->status === issuer::STATUS_ISSUED) {
        if ($issue->validuntil) {
            [$year, $month, $day] = array_map('intval', explode('-', $issue->validuntil));
            $status .= html_writer::div(get_string(
                'validuntil',
                'local_validat',
                userdate(make_timestamp($year, $month, $day, 12), $dateformat)
            ), 'small');
        } else {
            $status .= html_writer::div(get_string('validity_none', 'local_validat'), 'small');
        }
    } else if ($issue->status === issuer::STATUS_REVOKED && $issue->revokereason) {
        $status .= html_writer::div(s($issue->revokereason), 'small');
    } else if ($issue->lasterror) {
        $status .= html_writer::div(s($issue->lasterror), 'small text-danger');
    }

    $code = '';
    if ($issue->code && $issue->verifyurl) {
        $code = html_writer::link(
            $issue->verifyurl,
            issuer::format_code($issue->code) . $newwindow,
            ['target' => '_blank', 'rel' => 'noopener noreferrer', 'class' => 'text-nowrap']
        );
    }

    $row = [$name, userdate($issue->timecompleted, $dateformat), $status, $code];
    if ($canmanage) {
        $actions = '';
        if ($issue->status === issuer::STATUS_ISSUED) {
            $actions = html_writer::link(
                new moodle_url($url, ['action' => 'revoke', 'issue' => $issue->id]),
                get_string('revoke', 'local_validat')
            );
        } else if ($issue->status === issuer::STATUS_FAILED || $issue->status === issuer::STATUS_PENDING) {
            $actions = html_writer::link(
                new moodle_url($url, ['action' => 'retry', 'issue' => $issue->id, 'sesskey' => sesskey()]),
                get_string('retry', 'local_validat')
            );
        }
        $row[] = $actions;
    }
    $table->data[] = $row;
}
echo html_writer::table($table);
echo $OUTPUT->paging_bar($total, $page, $perpage, $url);
echo $OUTPUT->footer();
