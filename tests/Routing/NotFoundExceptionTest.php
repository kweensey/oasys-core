<?php declare(strict_types=1);

namespace Oasys\Tests\Routing;

use Oasys\Routing\NotFoundException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class NotFoundExceptionTest extends TestCase
{
  public function testDefaults(): void
  {
    $exception = new NotFoundException();

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Route not found.', $exception->getMessage());
    self::assertSame(404, $exception->getCode());
    self::assertNull($exception->getPrevious());
  }

  public function testCustomValues(): void
  {
    $previous  = new RuntimeException('Previous');
    $exception = new NotFoundException('Custom', 418, $previous);

    self::assertSame('Custom', $exception->getMessage());
    self::assertSame(418, $exception->getCode());
    self::assertSame($previous, $exception->getPrevious());
  }
}
