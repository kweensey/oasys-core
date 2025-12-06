<?php declare(strict_types=1);

namespace Oasys\Tests\Routing;

use LogicException;
use Nyholm\Psr7\Response;
use Oasys\Routing\ExceptionMapper;
use Oasys\Routing\RouteTarget;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ExceptionMapperTest extends TestCase
{
  public function testConstructorRegistersFallback(): void
  {
    $mapper = new ExceptionMapper([ErrorController::class, 'generic']);

    $target = $mapper->match(new RuntimeException('test'));

    self::assertInstanceOf(RouteTarget::class, $target);
    self::assertSame(ErrorController::class, $target->controllerClass);
    self::assertSame('generic', $target->action);
  }

  public function testRegisterRejectsMalformedHandler(): void
  {
    $mapper = new ExceptionMapper([ErrorController::class, 'generic']);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Invalid route handler format');

    /** @phpstan-ignore-next-line intentionally wrong handler */
    $mapper->register(RuntimeException::class, ['OnlyClass']);
  }

  public function testRegisterRejectsNonExistingClass(): void
  {
    $mapper = new ExceptionMapper([ErrorController::class, 'generic']);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('class does not exist');

    $mapper->register(RuntimeException::class, ['App\\MissingController', 'handle']);
  }

  public function testRegisterRejectsNonExistingMethod(): void
  {
    $mapper = new ExceptionMapper([ErrorController::class, 'generic']);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('method does not exist');

    $mapper->register(RuntimeException::class, [ErrorController::class, 'missing']);
  }

  public function testRegisterRejectsNonPublicMethod(): void
  {
    $mapper = new ExceptionMapper([ErrorController::class, 'generic']);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('method is not public');

    $mapper->register(RuntimeException::class, [ErrorController::class, 'hidden']);
  }

  public function testMatchesExactExceptionClass(): void
  {
    $mapper = new ExceptionMapper([ErrorController::class, 'generic']);
    $mapper->register(RuntimeException::class, [ErrorController::class, 'runtime']);

    $target = $mapper->match(new RuntimeException('test'));

    self::assertSame(ErrorController::class, $target->controllerClass);
    self::assertSame('runtime', $target->action);
  }

  public function testFallsBackToThrowableWhenNoSpecificMatch(): void
  {
    $mapper = new ExceptionMapper([ErrorController::class, 'generic']);

    $target = $mapper->match(new class extends \Exception {});

    self::assertSame(ErrorController::class, $target->controllerClass);
    self::assertSame('generic', $target->action);
  }
}

final class ErrorController
{
  public function generic(): Response
  {
    return new Response(500, [], 'generic');
  }

  public function runtime(): Response
  {
    return new Response(500, [], 'runtime');
  }

  private function hidden(): void {}
}
