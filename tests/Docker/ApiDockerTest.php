<?php
declare(strict_types=1);

namespace Tests\Docker;

use mschandr\WeightedRandom\Api\ApiException;
use mschandr\WeightedRandom\Api\Application;
use mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Value\WeightedGroup;
use mschandr\WeightedRandom\Value\WeightedValue;
use mschandr\WeightedRandom\WeightedRandom;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[Group('docker')]
#[CoversClass(Application::class)]
#[UsesClass(ApiException::class)]
#[UsesClass(WeightedRandom::class)]
#[UsesClass(WeightedRandomGenerator::class)]
#[UsesClass(WeightedBagRandomGenerator::class)]
#[UsesClass(WeightedGroup::class)]
#[UsesClass(WeightedValue::class)]
final class ApiDockerTest extends TestCase
{
    private const IMAGE     = 'weighted-random-api:phpunit';
    private const CONTAINER = 'weighted-random-api-phpunit';
    private const HOST_PORT = 18766;

    private static bool $dockerAvailable = false;
    private static bool $imageBuildOk    = false;
    private static bool $containerReady  = false;

    public static function setUpBeforeClass(): void
    {
        exec('docker info 2>/dev/null', $_, $code);
        if ($code !== 0) {
            return;
        }
        self::$dockerAvailable = true;

        // Clean up any leftover from a previous run.
        exec('docker rm -f ' . escapeshellarg(self::CONTAINER) . ' 2>/dev/null');

        $root = dirname(__DIR__, 2);
        exec(
            'docker build -t ' . escapeshellarg(self::IMAGE) . ' ' . escapeshellarg($root) . ' 2>/dev/null',
            $_,
            $code
        );
        if ($code !== 0) {
            return;
        }
        self::$imageBuildOk = true;

        exec(sprintf(
            'docker run -d --name %s -p %d:8080 %s 2>/dev/null',
            escapeshellarg(self::CONTAINER),
            self::HOST_PORT,
            escapeshellarg(self::IMAGE)
        ), $_, $code);
        if ($code !== 0) {
            return;
        }

        // Poll up to 15 s for the container to accept connections.
        $url = sprintf('http://127.0.0.1:%d/health', self::HOST_PORT);
        for ($i = 0; $i < 30; $i++) {
            usleep(500_000);
            if (@file_get_contents($url) !== false) {
                self::$containerReady = true;
                break;
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        exec('docker rm -f ' . escapeshellarg(self::CONTAINER) . ' 2>/dev/null');
    }

    protected function setUp(): void
    {
        if (!self::$dockerAvailable) {
            $this->markTestSkipped('Docker daemon is not available.');
        }
        if (!self::$imageBuildOk) {
            $this->markTestSkipped('Docker image build failed.');
        }
        if (!self::$containerReady) {
            $this->markTestSkipped('Docker container failed to become ready.');
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed>|string|null $body
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

        $url     = sprintf('http://127.0.0.1:%d%s', self::HOST_PORT, $path);
        $raw     = file_get_contents($url, false, stream_context_create($opts));
        $headers = $http_response_header ?? [];

        preg_match('#HTTP/\S+ (\d+)#', $headers[0] ?? '', $m);

        return [(int)($m[1] ?? 0), json_decode((string)$raw, true), $headers];
    }

    // -------------------------------------------------------------------------
    // Docker-specific: image & container properties
    // -------------------------------------------------------------------------

    public function testImageWasBuiltSuccessfully(): void
    {
        exec('docker image inspect ' . escapeshellarg(self::IMAGE) . ' 2>/dev/null', $_, $code);
        $this->assertSame(0, $code, 'Image ' . self::IMAGE . ' does not exist.');
    }

    public function testContainerRunsAsUnprivilegedUser(): void
    {
        exec('docker exec ' . escapeshellarg(self::CONTAINER) . ' whoami 2>/dev/null', $output, $code);
        $this->assertSame(0, $code);
        $this->assertSame('app', trim($output[0] ?? ''));
    }

    public function testHealthCheckCommandPassesInsideContainer(): void
    {
        // Execute the same command the Dockerfile HEALTHCHECK uses.
        $cmd = 'docker exec ' . escapeshellarg(self::CONTAINER)
            . ' php -r \'$c=@file_get_contents("http://127.0.0.1:8080/health");exit($c!==false?0:1);\''
            . ' 2>/dev/null';

        exec($cmd, $_, $code);
        $this->assertSame(0, $code, 'HEALTHCHECK command returned non-zero exit code.');
    }

    public function testPortEnvVarOverride(): void
    {
        $name     = self::CONTAINER . '-port';
        $hostPort = self::HOST_PORT + 1;

        exec('docker rm -f ' . escapeshellarg($name) . ' 2>/dev/null');
        exec(sprintf(
            'docker run -d --name %s -p %d:9090 -e PORT=9090 %s 2>/dev/null',
            escapeshellarg($name),
            $hostPort,
            escapeshellarg(self::IMAGE)
        ), $_, $code);

        $this->assertSame(0, $code, 'Failed to start container with custom PORT.');

        try {
            $url      = sprintf('http://127.0.0.1:%d/health', $hostPort);
            $response = false;
            for ($i = 0; $i < 20; $i++) {
                usleep(500_000);
                $response = @file_get_contents($url);
                if ($response !== false) {
                    break;
                }
            }

            $this->assertNotFalse($response, 'Container did not respond on custom PORT 9090.');
            $payload = json_decode((string)$response, true);
            $this->assertSame('ok', $payload['status'] ?? null);
        } finally {
            exec('docker rm -f ' . escapeshellarg($name) . ' 2>/dev/null');
        }
    }

    public function testProductionAutoloaderHasNoDevDependencies(): void
    {
        // phpunit is a dev dependency — it must not be present in the image.
        exec(
            'docker exec ' . escapeshellarg(self::CONTAINER)
            . ' php -r "exit(class_exists(\'PHPUnit\\\\Framework\\\\TestCase\')?1:0);" 2>/dev/null',
            $_,
            $code
        );
        $this->assertSame(0, $code, 'PHPUnit (a dev dependency) was found inside the production image.');
    }

    // -------------------------------------------------------------------------
    // API smoke tests (confirm the full HTTP stack works inside Docker)
    // -------------------------------------------------------------------------

    public function testHealthEndpointReturns200(): void
    {
        [$status, $payload] = $this->request('GET', '/health');

        $this->assertSame(200, $status);
        $this->assertSame('ok', $payload['status']);
        $this->assertSame('weighted-random-api', $payload['service']);
    }

    public function testUnknownRouteReturns404(): void
    {
        [$status, $payload] = $this->request('GET', '/nope');

        $this->assertSame(404, $status);
        $this->assertArrayHasKey('error', $payload);
    }

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

    public function testGenerateEndpointReturns200(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/generate', [
            'values' => ['apple' => 3, 'banana' => 1],
            'count'  => 10,
        ]);

        $this->assertSame(200, $status);
        $this->assertSame(10, $payload['count']);
        foreach ($payload['results'] as $value) {
            $this->assertContains($value, ['apple', 'banana']);
        }
    }

    public function testValidationErrorReturns422(): void
    {
        [$status, $payload] = $this->request('POST', '/v1/generate', []);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testResponseHasJsonContentTypeHeader(): void
    {
        [, , $headers] = $this->request('GET', '/health');

        $found = false;
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'application/json') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Response is missing Content-Type: application/json header.');
    }
}
