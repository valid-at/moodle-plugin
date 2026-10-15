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

use local_validat\local\api_client;
use local_validat\local\api_exception;
use local_validat\local\http_response;
use local_validat\local\payload;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_http.php');

/**
 * Tests for the request and the API client.
 *
 * @package    local_validat
 * @copyright  2026 dawox GmbH
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_validat\local\payload
 * @covers     \local_validat\local\api_client
 * @covers     \local_validat\local\api_exception
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\local_validat\local\payload::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_validat\local\api_client::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_validat\local\api_exception::class)]
final class payload_test extends \basic_testcase {
    /** @var int 2026-03-01 23:30 UTC, already 2 March in Vienna */
    const COMPLETED = 1772407800;

    /**
     * All fields end up in the request, with the date of issue in Vienna time.
     */
    public function test_build_with_all_fields(): void {
        $data = payload::build(
            "  Maria \n Muster ",
            'maria@example.com',
            'Ersthelfer im Betrieb',
            self::COMPLETED,
            'Europe/Vienna',
            24,
            'Moodle EH-26',
            "8 Unterrichtseinheiten\nPraxisteil",
            true
        );
        $this->assertSame([
            'inhaber' => 'Maria Muster',
            'nachweis' => 'Ersthelfer im Betrieb',
            'ausgestelltAm' => '2026-03-02',
            'pdf' => true,
            'benachrichtigen' => true,
            'inhaberEmail' => 'maria@example.com',
            'gueltigMonate' => 24,
            'referenz' => 'Moodle EH-26',
            'zusatztext' => "8 Unterrichtseinheiten\nPraxisteil",
        ], $data);
    }

    /**
     * Optional fields are left out when empty.
     */
    public function test_build_without_email_and_expiry(): void {
        $data = payload::build('Maria Muster', null, 'Hygieneschulung', self::COMPLETED, 'UTC', 0, '', '', true);
        $this->assertSame('2026-03-01', $data['ausgestelltAm']);
        $this->assertFalse($data['benachrichtigen']);
        $this->assertArrayNotHasKey('inhaberEmail', $data);
        $this->assertArrayNotHasKey('gueltigMonate', $data);
        $this->assertArrayNotHasKey('referenz', $data);
        $this->assertArrayNotHasKey('zusatztext', $data);
    }

    /**
     * Text is shortened to the limits of the API without breaking characters.
     */
    public function test_cut_respects_limits_of_the_api(): void {
        $this->assertSame(60, \core_text::strlen(payload::cut(str_repeat('ä', 80), payload::MAX_REFERENCE)));
        $this->assertSame('Kurz', payload::cut(' Kurz ', 10));
        // An unknown time zone falls back to Vienna.
        $this->assertSame('2026-03-02', payload::date(self::COMPLETED, 'Not/AZone'));
    }

    /**
     * The key is unique per completion and never longer than 100 characters.
     */
    public function test_idempotency_key(): void {
        $key = payload::idempotency_key('site', 12, 345, self::COMPLETED);
        $this->assertSame('moodle-' . substr(sha1('site'), 0, 12) . '-12-345-' . self::COMPLETED, $key);
        $longest = payload::idempotency_key(str_repeat('x', 500), PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX);
        $this->assertLessThanOrEqual(100, strlen($longest));
    }

    /**
     * The client sends JSON with the API key and the idempotency key.
     */
    public function test_client_sends_json_with_key(): void {
        $http = new fake_http([new http_response(201, '{"code":"K7M2X9QD"}')]);
        $client = new api_client('https://valid.at/', ' vat_test ', $http);
        $this->assertSame(['code' => 'K7M2X9QD'], $client->issue(['inhaber' => 'Jörg Müller'], 'moodle-1'));
        $request = $http->requests[0];
        $this->assertSame('POST', $request->method);
        $this->assertSame('https://valid.at/api/v1/aussteller/nachweise', $request->url);
        $this->assertContains('Authorization: Bearer vat_test', $request->headers);
        $this->assertContains('Idempotency-Key: moodle-1', $request->headers);
        $this->assertContains('Content-Type: application/json', $request->headers);
        $this->assertSame('{"inhaber":"Jörg Müller"}', $request->body);
    }

    /**
     * Errors from valid.at are refused or retried depending on the status.
     */
    public function test_client_errors(): void {
        $http = new fake_http([
            new http_response(400, '{"fehler":"Bitte die Felder prüfen.","felder":{"inhaber":["Bitte den Namen angeben."]}}'),
            new http_response(503, 'Service Unavailable'),
            new http_response(0, '', 'Could not resolve host'),
        ]);
        $client = new api_client('https://valid.at', 'vat_test', $http);
        try {
            $client->issue([], 'a');
            $this->fail('400 erwartet');
        } catch (api_exception $e) {
            $this->assertFalse($e->is_temporary());
            $this->assertSame('HTTP 400: Bitte die Felder prüfen. inhaber: Bitte den Namen angeben.', $e->describe());
        }
        foreach (['HTTP 503', 'Could not resolve host'] as $expected) {
            try {
                $client->issuer();
                $this->fail('Fehler erwartet');
            } catch (api_exception $e) {
                $this->assertTrue($e->is_temporary());
                $this->assertSame($expected, $e->describe());
            }
        }
    }
}
