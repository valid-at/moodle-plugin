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
 * HTTP requests through the curl class of Moodle, so proxy settings and the
 * list of blocked hosts of the site apply.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_http implements http {
    /**
     * Sends one request.
     *
     * @param string $method GET or POST
     * @param string $url Full URL
     * @param string[] $headers Lines like "Name: value"
     * @param string|null $body Request body
     * @return http_response
     */
    public function request(string $method, string $url, array $headers, ?string $body): http_response {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader($headers);
        $options = ['CURLOPT_TIMEOUT' => 30, 'CURLOPT_CONNECTTIMEOUT' => 10];
        if ($method === 'POST') {
            $result = $curl->post($url, $body ?? '', $options);
        } else {
            $result = $curl->get($url, [], $options);
        }
        if ($curl->get_errno() || $curl->error) {
            return new http_response(0, '', (string) $curl->error);
        }
        $info = $curl->get_info();
        return new http_response((int) ($info['http_code'] ?? 0), (string) $result);
    }
}
