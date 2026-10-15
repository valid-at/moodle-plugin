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
 * Builds the request for a certificate from a course completion.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payload {
    /** @var int Maximum length of the name in the API */
    public const MAX_NAME = 120;
    /** @var int Maximum length of the kind of certificate */
    public const MAX_TITLE = 200;
    /** @var int Maximum length of the reference */
    public const MAX_REFERENCE = 60;
    /** @var int Maximum length of the additional text on the PDF */
    public const MAX_NOTE = 600;

    /**
     * Fields for POST /api/v1/aussteller/nachweise.
     *
     * @param string $fullname Full name of the learner
     * @param string|null $email Email of the learner, null to leave it out
     * @param string $title Kind of certificate
     * @param int $timecompleted Time of the course completion
     * @param string $timezone Time zone for the date of issue, for example Europe/Vienna
     * @param int $validmonths Validity in months, 0 for no expiry
     * @param string $reference Internal reference, printed small on the PDF
     * @param string $note Additional text on the PDF
     * @param bool $notify Whether valid.at sends the learner an email
     * @return array
     */
    public static function build(
        string $fullname,
        ?string $email,
        string $title,
        int $timecompleted,
        string $timezone,
        int $validmonths,
        string $reference,
        string $note,
        bool $notify
    ): array {
        $data = [
            'inhaber' => self::cut($fullname, self::MAX_NAME),
            'nachweis' => self::cut($title, self::MAX_TITLE),
            'ausgestelltAm' => self::date($timecompleted, $timezone),
            'pdf' => true,
            'benachrichtigen' => $notify && $email !== null,
        ];
        if ($email !== null && trim($email) !== '') {
            $data['inhaberEmail'] = trim($email);
        }
        if ($validmonths > 0) {
            $data['gueltigMonate'] = $validmonths;
        }
        if (trim($reference) !== '') {
            $data['referenz'] = self::cut($reference, self::MAX_REFERENCE);
        }
        if (trim($note) !== '') {
            $data['zusatztext'] = self::cut($note, self::MAX_NOTE, false);
        }
        return $data;
    }

    /**
     * Key against duplicates: the same completion never leads to a second certificate,
     * even after a lost answer or when several Moodle sites share one issuer.
     *
     * @param string $siteidentifier Identifier of the Moodle site
     * @param int $courseid Course
     * @param int $userid Learner
     * @param int $timecompleted Time of the course completion
     * @return string At most 100 characters
     */
    public static function idempotency_key(string $siteidentifier, int $courseid, int $userid, int $timecompleted): string {
        return 'moodle-' . substr(sha1($siteidentifier), 0, 12) . '-' . $courseid . '-' . $userid . '-' . $timecompleted;
    }

    /**
     * Calendar date of a time in the given time zone.
     *
     * @param int $time Unix time
     * @param string $timezone Time zone
     * @return string YYYY-MM-DD
     */
    public static function date(int $time, string $timezone): string {
        try {
            $zone = new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $zone = new \DateTimeZone('Europe/Vienna');
        }
        return (new \DateTimeImmutable('@' . $time))->setTimezone($zone)->format('Y-m-d');
    }

    /**
     * Trims and shortens text to the limit of the API without breaking characters.
     *
     * @param string $text Text
     * @param int $max Maximum number of characters
     * @param bool $singleline Whether line breaks and repeated spaces become one space
     * @return string
     */
    public static function cut(string $text, int $max, bool $singleline = true): string {
        $text = trim($singleline ? (preg_replace('/\s+/u', ' ', $text) ?? $text) : $text);
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $max - 1, 'UTF-8')) . '…';
    }
}
