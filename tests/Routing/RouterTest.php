<?php declare(strict_types=1);

namespace Oasys\Tests\Routing;

use Nyholm\Psr7\ServerRequest;
use Oasys\Routing\HttpMethod;
use Oasys\Routing\RouteTarget;
use Oasys\Routing\Router;
use Oasys\Routing\Attributes\Get;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use LogicException;
use ReflectionAttribute;

final class RouterTest extends TestCase
{
  public function testRegistersAndMatchesSimpleRoute(): void
  {
    $router = new Router();

    $router->get('/hello', [DummyController::class, 'hello']);

    $request  = new ServerRequest('GET', '/hello');
    $target   = $router->match($request);

    self::assertInstanceOf(RouteTarget::class, $target);
    self::assertSame(DummyController::class, $target->controllerClass);
    self::assertSame('hello', $target->action);
  }

  public function testReturnsNullForUnknownHttpMethod(): void
  {
    $router = new Router();

    $router->get('/hello', [DummyController::class, 'hello']);

    $request = new ServerRequest('FOO', '/hello');

    self::assertNull($router->match($request));
  }

  public function testReturnsNullWhenNoRouteMatches(): void
  {
    $router = new Router();

    $router->get('/hello', [DummyController::class, 'hello']);

    $request = new ServerRequest('GET', '/world');

    self::assertNull($router->match($request));
  }

  public function testAppliesAliasesToPatterns(): void
  {
    $router = new Router([
      'id' => '\d+',
    ]);

    $router->get('/user/{id}', [DummyController::class, 'hello']);

    $match  = $router->match(new ServerRequest('GET', '/user/123'));
    $miss   = $router->match(new ServerRequest('GET', '/user/abc'));

    self::assertInstanceOf(RouteTarget::class, $match);
    self::assertNull($miss);
  }

  public function testRegisterRejectsMalformedHandlerArray(): void
  {
    $router = new Router();

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Invalid route handler format');

    /** @phpstan-ignore-next-line intentionally wrong handler */
    $router->get('/hello', ['OnlyClass']);
  }

  public function testRegisterRejectsNonExistingHandlerClass(): void
  {
    $router = new Router();

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('class does not exist');

    $router->get('/hello', ['App\\MissingController', 'index']);
  }

  public function testRegisterRejectsNonExistingHandlerMethod(): void
  {
    $router = new Router();

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('method does not exist');

    $router->get('/hello', [DummyController::class, 'missing']);
  }

  public function testRegisterRejectsNonPublicHandlerMethod(): void
  {
    $router = new Router();

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('method is not public');

    $router->get('/hello', [DummyController::class, 'hidden']);
  }

  public function testRegisterRejectsInvalidPattern(): void
  {
    $router = new Router([
      'bad' => '[unclosed', // invalid regex fragment
    ]);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Invalid route pattern');

    $router->get('/foo/{bad}', [DummyController::class, 'hello']);
  }

  public function testBindRegistersRoutesFromAttributes(): void
  {
    $router = new Router();

    $router->bind(AttributeController::class);

    $requestFoo = new ServerRequest('GET', '/foo');
    $requestBar = new ServerRequest('GET', '/bar');

    $foo = $router->match($requestFoo);
    $bar = $router->match($requestBar);

    self::assertInstanceOf(RouteTarget::class, $foo);
    self::assertSame(AttributeController::class, $foo->controllerClass);
    self::assertSame('foo', $foo->action);

    self::assertInstanceOf(RouteTarget::class, $bar);
    self::assertSame(AttributeController::class, $bar->controllerClass);
    self::assertSame('bar', $bar->action);
  }
}

/**
 * Dummy controller for routing tests
 */
final class DummyController
{
  public function hello(): ResponseInterface
  {
    // Not actually used by Router tests (only RouteTarget), so we can cheat.
    return new \Nyholm\Psr7\Response();
  }

  private function hidden(): void {}
}

/**
 * Controller with route attributes
 */
final class AttributeController
{
  #[Get('/foo')]
  public function foo(): ResponseInterface
  {
    return new \Nyholm\Psr7\Response();
  }

  #[Get('/bar')]
  public function bar(): ResponseInterface
  {
    return new \Nyholm\Psr7\Response();
  }
}
