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
 * Client for the issuer API of valid.at (/api/v1/aussteller).
 *
 * Field names follow the API, which uses German names. The full description
 * is published at https://valid.at/schnittstelle and as OpenAPI 3.1 under
 * /api/v1/openapi.json.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    /** @var string Address of valid.at without trailing slash */
    private $baseurl;

    /** @var string API key starting with vat_ */
    private $apikey;

    /** @var http Transport */
    private $http;

    /**
     * Constructor.
     *
     * @param string $baseurl Address of valid.at, for example https://valid.at
     * @param string $apikey API key starting with vat_
     * @param http $http Transport
     */
    public function __construct(string $baseurl, string $apikey, http $http) {
        $this->baseurl = rtrim(trim($baseurl), '/');
        $this->apikey = trim($apikey);
        $this->http = $http;
    }

    /**
     * Client with the settings of the site.
     *
     * @return self
     * @throws api_exception When no API key is set
     */
    public static function from_config(): self {
        $baseurl = (string) get_config('local_validat', 'apiurl');
        $apikey = (string) get_config('local_validat', 'apikey');
        if (trim($apikey) === '') {
            throw new api_exception(401, get_string('error_nokey', 'local_validat'));
        }
        return new self($baseurl !== '' ? $baseurl : 'https://valid.at', $apikey, new moodle_http());
    }

    /**
     * Issuer, plan and quota of the key. Good for testing the connection.
     *
     * @return array
     */
    public function issuer(): array {
        return $this->request('GET', '/api/v1/aussteller');
    }

    /**
     * Issues a certificate. A repeated request with the same key returns the certificate issued before.
     *
     * @param array $data Fields of the API, see payload::build()
     * @param string $idempotencykey Unique per course completion, at most 100 characters
     * @return array Certificate with code, pruefseite and inhaberLink
     */
    public function issue(array $data, string $idempotencykey): array {
        return $this->request('POST', '/api/v1/aussteller/nachweise', $data, ['Idempotency-Key: ' . $idempotencykey]);
    }

    /**
     * Current state of a certificate.
     *
     * @param string $code Code of the certificate
     * @return array
     */
    public function get(string $code): array {
        return $this->request('GET', '/api/v1/aussteller/nachweise/' . rawurlencode($code));
    }

    /**
     * Revokes a certificate. The reason appears on the verification page.
     *
     * @param string $code Code of the certificate
     * @param string $reason 3 to 300 characters
     * @return array
     */
    public function revoke(string $code, string $reason): array {
        return $this->request('POST', '/api/v1/aussteller/nachweise/' . rawurlencode($code) . '/widerruf', ['grund' => $reason]);
    }

    /**
     * Sends a request and decodes the answer.
     *
     * @param string $method GET or POST
     * @param string $path Path below the base address
     * @param array|null $body Sent as JSON
     * @param string[] $headers Additional headers
     * @return array
     * @throws api_exception On any answer other than 2xx with JSON
     */
    private function request(string $method, string $path, ?array $body = null, array $headers = []): array {
        $headers[] = 'Authorization: Bearer ' . $this->apikey;
        $headers[] = 'Accept: application/json';
        $json = null;
        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }
        $response = $this->http->request($method, $this->baseurl . $path, $headers, $json);
        if ($response->status === 0) {
            throw new api_exception(0, $response->error);
        }
        $data = json_decode($response->body, true);
        if ($response->status >= 200 && $response->status < 300 && is_array($data)) {
            return $data;
        }
        $message = is_array($data) && isset($data['fehler']) && is_string($data['fehler']) ? $data['fehler'] : '';
        $fields = is_array($data) && isset($data['felder']) && is_array($data['felder']) ? $data['felder'] : [];
        throw new api_exception($response->status, $message, $fields);
    }
}
