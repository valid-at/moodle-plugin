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
 * Tests the connection to valid.at with the saved API key.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_validat\local\api_client;
use local_validat\local\api_exception;

require_login();
$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/validat/check.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('check', 'local_validat'));
$PAGE->set_heading(get_string('check', 'local_validat'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('check', 'local_validat'));

try {
    $issuer = api_client::from_config()->issuer();
    echo $OUTPUT->notification(get_string('check_ok', 'local_validat'), \core\output\notification::NOTIFY_SUCCESS);
    $quota = $issuer['kontingent'] ?? [];
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->data = [
        [get_string('check_issuer', 'local_validat'), s($issuer['aussteller'] ?? '')],
        [get_string('check_verified', 'local_validat'),
            !empty($issuer['identitaetGeprueft']) ? get_string('yes') : get_string('check_notverified', 'local_validat')],
        [get_string('check_plan', 'local_validat'), s($issuer['paket'] ?? '')],
        [get_string('check_quota', 'local_validat'), get_string('check_quota_value', 'local_validat', (object) [
            'used' => (int) ($quota['bisherImJahr'] ?? 0),
            'limit' => isset($quota['proJahr']) ? (int) $quota['proJahr'] : get_string('check_quota_agreed', 'local_validat'),
        ])],
        [get_string('check_key', 'local_validat'), s($issuer['schluessel'] ?? '')],
    ];
    echo html_writer::table($table);
} catch (api_exception $e) {
    echo $OUTPUT->notification(
        get_string('check_failed', 'local_validat', s(\local_validat\issuer::error_text($e))),
        \core\output\notification::NOTIFY_ERROR
    );
}

echo html_writer::link(
    new moodle_url('/admin/settings.php', ['section' => 'local_validat']),
    get_string('settings', 'local_validat'),
    ['class' => 'btn btn-secondary']
);
echo $OUTPUT->footer();
