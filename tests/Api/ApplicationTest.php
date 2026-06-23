<?php
declare(strict_types=1);

namespace Tests\Api;

use mschandr\WeightedRandom\Api\Application;
use mschandr\WeightedRandom\Api\ApiException;
use mschandr\WeightedRandom\WeightedRandom;
use mschandr\WeightedRandom\Generator\WeightedRandomGenerator;
use mschandr\WeightedRandom\Generator\WeightedBagRandomGenerator;
use mschandr\WeightedRandom\Value\WeightedGroup;
use mschandr\WeightedRandom\Value\WeightedValue;
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
final class ApplicationTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        $this->app = new Application();
    }

    public function testHealthReturnsOk(): void
    {
        [$status, $payload] = $this->app->handle('GET', '/health');

        $this->assertSame(200, $status);
        $this->assertSame('ok', $payload['status']);
    }

    public function testRootAlsoReturnsHealth(): void
    {
        [$status, $payload] = $this->app->handle('GET', '/');

        $this->assertSame(200, $status);
        $this->assertSame('ok', $payload['status']);
    }

    public function testUnknownRouteReturns404(): void
    {
        [$status, $payload] = $this->app->handle('GET', '/nope');

        $this->assertSame(404, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateWithValuesMap(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['apple' => 3, 'banana' => 1],
            'count'  => 5,
        ]);

        $this->assertSame(200, $status);
        $this->assertSame('float', $payload['generator']);
        $this->assertSame(5, $payload['count']);
        $this->assertCount(5, $payload['results']);
        foreach ($payload['results'] as $value) {
            $this->assertContains($value, ['apple', 'banana']);
        }
    }

    public function testGenerateDefaultsToSingleResult(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['x' => 1],
        ]);

        $this->assertSame(200, $status);
        $this->assertSame(1, $payload['count']);
        $this->assertSame(['x'], $payload['results']);
    }

    public function testGenerateWithItemsPreservesType(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'items' => [
                ['value' => 42, 'weight' => 1],
            ],
            'count' => 3,
        ]);

        $this->assertSame(200, $status);
        $this->assertSame([42, 42, 42], $payload['results']);
    }

    public function testGenerateBagDistributesExactly(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'generator' => 'bag',
            'values'    => ['rare' => 1, 'common' => 9],
            'count'     => 10,
        ]);

        $this->assertSame(200, $status);
        $counts = array_count_values($payload['results']);
        $this->assertSame(1, $counts['rare']);
        $this->assertSame(9, $counts['common']);
    }

    public function testGenerateUniqueHasNoDuplicates(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['a' => 1, 'b' => 1, 'c' => 1],
            'count'  => 3,
            'unique' => true,
        ]);

        $this->assertSame(200, $status);
        $this->assertSame($payload['results'], array_unique($payload['results']));
        $this->assertCount(3, $payload['results']);
    }

    public function testGenerateWithGroups(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'groups' => [
                ['members' => ['gold', 'silver'], 'weight' => 5],
            ],
            'count' => 4,
        ]);

        $this->assertSame(200, $status);
        foreach ($payload['results'] as $value) {
            $this->assertContains($value, ['gold', 'silver']);
        }
    }

    public function testGenerateWithNoValuesIsRejected(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', []);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateRejectsUnknownGenerator(): void
    {
        [$status] = $this->app->handle('POST', '/v1/generate', [
            'generator' => 'quantum',
            'values'    => ['a' => 1],
        ]);

        $this->assertSame(422, $status);
    }

    public function testGenerateRejectsNonPositiveCount(): void
    {
        [$status] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['a' => 1],
            'count'  => 0,
        ]);

        $this->assertSame(422, $status);
    }

    public function testGenerateRejectsNegativeWeight(): void
    {
        [$status] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['a' => -1],
        ]);

        $this->assertSame(422, $status);
    }

    public function testDistributionReturnsProbabilitiesAndStats(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/distribution', [
            'values' => [1 => 1, 2 => 2, 3 => 1],
        ]);

        $this->assertSame(200, $status);
        $this->assertSame(3, $payload['totalValues']);
        $this->assertEqualsWithDelta(2.0, $payload['expectedValue'], 1e-9);
        $this->assertGreaterThan(0.0, $payload['entropy']);

        $total = array_sum(array_column($payload['distribution'], 'probability'));
        $this->assertEqualsWithDelta(1.0, $total, 1e-9);
    }

    public function testDistributionWithNonNumericValuesHasNullStats(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/distribution', [
            'values' => ['apple' => 1, 'banana' => 1],
        ]);

        $this->assertSame(200, $status);
        $this->assertNull($payload['expectedValue']);
        $this->assertNull($payload['variance']);
        $this->assertNull($payload['standardDeviation']);
    }

    public function testOpenapiDocumentIsServed(): void
    {
        [$status, $payload] = $this->app->handle('GET', '/v1/openapi.json');

        $this->assertSame(200, $status);
        $this->assertSame('3.1.0', $payload['openapi']);
        $this->assertArrayHasKey('/v1/generate', $payload['paths']);
    }

    public function testGenerateRejectsCountExceedingMaximum(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['a' => 1],
            'count'  => 100_001,
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateRejectsNonArrayValues(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'values' => 'not-an-array',
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateRejectsNonArrayItems(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'items' => 'not-an-array',
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateRejectsMalformedItem(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'items' => [['bad' => true]],
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateRejectsNonNumericItemWeight(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'items' => [['value' => 'x', 'weight' => 'abc']],
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateRejectsMalformedGroup(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'groups' => [['bad' => true]],
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateRejectsGroupWithEmptyMembers(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'groups' => [['members' => [], 'weight' => 1]],
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }

    public function testGenerateAcceptsIntegerValuedFloatCount(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['a' => 1],
            'count'  => 2.0,
        ]);

        $this->assertSame(200, $status);
        $this->assertSame(2, $payload['count']);
    }

    public function testGenerateRejectsNonIntegerCount(): void
    {
        [$status, $payload] = $this->app->handle('POST', '/v1/generate', [
            'values' => ['a' => 1],
            'count'  => 1.5,
        ]);

        $this->assertSame(422, $status);
        $this->assertArrayHasKey('error', $payload);
    }
}
