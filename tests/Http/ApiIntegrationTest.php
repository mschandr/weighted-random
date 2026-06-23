<?php
declare(strict_types=1);

namespace Tests\Http;

use mschandr\WeightedRandom\Api\ApiException;
use mschandr\WeightedRandom\Api\Application;
use mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Value\WeightedGroup;
use mschandr\WeightedRandom\Value\WeightedValue;
use mschandr\WeightedRandom\WeightedRandom;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Application::class)]
#[UsesClass(ApiException::class)]
#[UsesClass(WeightedRandom::class)]
#[UsesClass(WeightedRandomGenerator::class)]
#[UsesClass(WeightedBagRandomGenerator::class)]
#[UsesClass(WeightedGroup::class)]
#[UsesClass(WeightedValue::class)]
final class ApiIntegrationTest extends TestCase
{
    private const HOST = '127.0.0.1';
    private const PORT = 18765;

    /** @var resource|null */
    private static mixed $serverProcess = null;
    private static bool $serverReady    = false;

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);

        self::$serverProcess = proc_open(
            sprintf('php -S %s:%d -t public public/index.php', self::HOST, self::PORT),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );

        if (!is_resource(self::$serverProcess)) {
            return;
        }

        // Poll up to 2 s (20 × 100 ms) for the server to accept connections.
        $url = sprintf('http://%s:%d/health', self::HOST, self::PORT);
        for ($i = 0; $i < 20; $i++) {
            usleep(100_000);
            if (@file_get_contents($url) !== false) {
                self::$serverReady = true;
                break;
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    protected function setUp(): void
    {
        if (!self::$serverReady) {
            $this->markTestSkipped(sprintf(
                'Built-in PHP server could not be started on %s:%d.',
                self::HOST,
                self::PORT
            ));
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed>|string|null $body  Pass an array to JSON-encode,
     *                                                a raw string to send verbatim,
     *                                                or null for no body.
     * @return array{0:int,1:mixed,2:string[]}
     */
    private function request(string $method, string $path, array|string|null $body = null): array
    {
        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => 'Content-Type: application/json',
                'ignore_errors' => true,
            ],
        ];

        if ($body !== null) {
            $opts['http']['content'] = is_array($body) ? json_encode($body) : $body;
        }

        $url      = sprintf('http://%s:%d%s', self::HOST, self::PORT, $path);
        $raw      = file_get_contents($url, false, stream_context_create($opts));
        $headers  = $http_response_header ?? [];

        preg_match('#HTTP/\S+ (\d+)#', $headers[0] ?? '', $m);
        $status  = isset($m[1]) ? (int)$m[1] : 0;
        $payload = json_decode((string)$raw, true);

        return [$status, $payload, $headers];
    }

    // -------------------------------------------------------------------------
    // Health / routing
    // -------------------------------------------------------------------------

    public function testHealthEndpointReturns200(): void
    {
        [$status, $payload] = $this->request('GET', '/health');

        $this->assertSame(200, $status);
        $this->assertSame('ok', $payload['status']);
    }

    public function testRootRedirectsToHealth(): void
    {
        [$status, $payload] = $this->request('GET', '/');

        $this->assertSame(200, $status);
        $this->assertSame('ok', $payload['status']);
    }

    public function testUnknownRouteReturns404(): void
    {
        [$status, $payload] = $this->request('GET', '/nope');

        $this->assertSame(404, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    // -------------------------------------------------------------------------
    // Content-Type header
    // -------------------------------------------------------------------------

    public function testResponseAlwaysHasJsonContentType(): void
    {
        [, , $headers] = $this->request('GET', '/health');

        $contentType = '';
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $contentType = $h;
                break;
            }
        }

        $this->assertStringContainsStringIgnoringCase('application/json', $contentType);
    }

    // -------------------------------------------------------------------------
    // JSON parsing layer (index.php) — not reachable from unit tests
    // -------------------------------------------------------------------------

    public function testMalformedJsonBodyReturns400(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/generate', '{not valid json');

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testNonObjectJsonBodyReturns400(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/generate', '"just a string"');

        $this->assertSame(400, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    // -------------------------------------------------------------------------
    // Core generate endpoint
    // -------------------------------------------------------------------------

    public function testGenerateWithValuesReturns200(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/generate', [
            'values' => ['apple' => 3, 'banana' => 1],
            'count'  => 5,
        ]);

        $this->assertSame(200, $status);
        $this->assertSame(5, $payload['count']);
        $this->assertCount(5, $payload['results']);
        foreach ($payload['results'] as $value) {
            $this->assertContains($value, ['apple', 'banana']);
        }
    }

    public function testGenerateBagYieldsExactRatios(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/generate', [
            'generator' => 'bag',
            'values'    => ['rare' => 1, 'common' => 9],
            'count'     => 10,
        ]);

        $this->assertSame(200, $status);
        $counts = array_count_values($payload['results']);
        $this->assertSame(1, $counts['rare']);
        $this->assertSame(9, $counts['common']);
    }

    public function testGenerateValidationErrorReturns422(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/generate', []);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    // -------------------------------------------------------------------------
    // Distribution endpoint
    // -------------------------------------------------------------------------

    public function testDistributionReturns200WithStats(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/distribution', [
            'values' => ['1' => 1, '2' => 2, '3' => 1],
        ]);

        $this->assertSame(200, $status);
        $this->assertSame(3, $payload['totalValues']);
        $this->assertEqualsWithDelta(1.0,
            array_sum(array_column($payload['distribution'], 'probability')), 1e-9);
    }

    // -------------------------------------------------------------------------
    // OpenAPI endpoint
    // -------------------------------------------------------------------------

    public function testOpenapiEndpointReturns200(): void
    {
        [$status, $payload] = $this->request('GET', '/v1/openapi.json');

        $this->assertSame(200, $status);
        $this->assertSame('3.1.0', $payload['openapi']);
        $this->assertArrayHasKey('/v1/generate', $payload['paths']);
    }
}
