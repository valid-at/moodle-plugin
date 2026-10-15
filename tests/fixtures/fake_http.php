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

use local_validat\local\http;
use local_validat\local\http_response;

/**
 * Transport for tests: records requests and returns prepared answers.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_http implements http {
    /** @var \stdClass[] Sent requests with method, url, headers and body */
    public $requests = [];

    /** @var http_response[] Answers in order */
    private $responses;

    /**
     * Constructor.
     *
     * @param http_response[] $responses Answers in order
     */
    public function __construct(array $responses) {
        $this->responses = $responses;
    }

    /**
     * Records the request and returns the next answer.
     *
     * @param string $method Method
     * @param string $url URL
     * @param string[] $headers Headers
     * @param string|null $body Body
     * @return http_response
     */
    public function request(string $method, string $url, array $headers, ?string $body): http_response {
        $this->requests[] = (object) ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        return array_shift($this->responses) ?? new http_response(500, '{"fehler":"Keine Antwort vorbereitet."}');
    }
}
