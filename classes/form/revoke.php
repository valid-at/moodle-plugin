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

/**
 * Revoking a certificate with a reason.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class revoke extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;

        foreach (['id', 'issue'] as $name) {
            $mform->addElement('hidden', $name);
            $mform->setType($name, PARAM_INT);
        }
        $mform->addElement('hidden', 'action', 'revoke');
        $mform->setType('action', PARAM_ALPHA);

        $mform->addElement('static', 'who', get_string('certificate', 'local_validat'), s($this->_customdata['who'] ?? ''));
        $mform->addElement('textarea', 'reason', get_string('revokereason', 'local_validat'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('reason', PARAM_TEXT);
        $mform->addRule('reason', null, 'required', null, 'client');
        $mform->addHelpButton('reason', 'revokereason', 'local_validat');

        $this->add_action_buttons(true, get_string('revoke', 'local_validat'));
    }

    /**
     * The reason must have 3 to 300 characters.
     *
     * @param array $data Submitted data
     * @param array $files Submitted files
     * @return array Errors per field
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $length = \core_text::strlen(trim($data['reason'] ?? ''));
        if ($length < 3 || $length > 300) {
            $errors['reason'] = get_string('error_reason', 'local_validat');
        }
        return $errors;
    }
}
