<?php
declare(strict_types=1);

namespace Tests\Api;

use mschandr\WeightedRandom\Api\ApiException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiException::class)]
final class ApiExceptionTest extends TestCase
{
    public function testDefaultStatusCode(): void
    {
        $e = new ApiException('something went wrong');

        $this->assertSame('something went wrong', $e->getMessage());
        $this->assertSame(400, $e->getStatusCode());
    }

    public function testCustomStatusCode(): void
    {
        $e = new ApiException('not found', 404);

        $this->assertSame(404, $e->getStatusCode());
    }

    public function testIsRuntimeException(): void
    {
        $e = new ApiException('error');

        $this->assertInstanceOf(\RuntimeException::class, $e);
    }
}
