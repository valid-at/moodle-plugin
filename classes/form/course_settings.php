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

namespace local_validat\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_validat\local\payload;

/**
 * Settings of a course: whether and which certificate it issues.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_settings extends \moodleform {
    /** @var int[] Offered validity in months */
    public const VALIDITY = [6, 12, 24, 36, 48, 60];

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('enabled', 'local_validat'),
            get_string('enabled_label', 'local_validat')
        );

        $mform->addElement(
            'text',
            'title',
            get_string('title', 'local_validat'),
            ['size' => 60, 'maxlength' => payload::MAX_TITLE]
        );
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addHelpButton('title', 'title', 'local_validat');

        $options = [0 => get_string('validity_none', 'local_validat')];
        foreach (self::VALIDITY as $months) {
            $options[$months] = get_string('validity_months', 'local_validat', $months);
        }
        $mform->addElement('select', 'validmonths', get_string('validity', 'local_validat'), $options);
        $mform->addHelpButton('validmonths', 'validity', 'local_validat');

        $mform->addElement('textarea', 'note', get_string('note', 'local_validat'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('note', PARAM_TEXT);
        $mform->addHelpButton('note', 'note', 'local_validat');

        $mform->addElement(
            'advcheckbox',
            'sendemail',
            get_string('sendemail', 'local_validat'),
            get_string('sendemail_label', 'local_validat')
        );
        $mform->addElement(
            'advcheckbox',
            'notify',
            get_string('notify', 'local_validat'),
            get_string('notify_label', 'local_validat')
        );
        $mform->disabledIf('notify', 'sendemail', 'notchecked');

        $this->add_action_buttons(false, get_string('savechanges'));
    }

    /**
     * Checks the limits of valid.at.
     *
     * @param array $data Submitted data
     * @param array $files Submitted files
     * @return array Errors per field
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $title = trim($data['title'] ?? '');
        if (\core_text::strlen($title) < 2) {
            $errors['title'] = get_string('error_title', 'local_validat');
        } else if (\core_text::strlen($title) > payload::MAX_TITLE) {
            $errors['title'] = get_string('maximumchars', '', payload::MAX_TITLE);
        }
        if (\core_text::strlen(trim($data['note'] ?? '')) > payload::MAX_NOTE) {
            $errors['note'] = get_string('maximumchars', '', payload::MAX_NOTE);
        }
        $months = (int) ($data['validmonths'] ?? 0);
        if ($months !== 0 && !in_array($months, self::VALIDITY, true)) {
            $errors['validmonths'] = get_string('error_validity', 'local_validat');
        }
        return $errors;
    }
}
