<?php declare(strict_types=1);

namespace Oasys\Tests;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Oasys\Kernel;
use Oasys\Middleware\MiddlewareAttribute;
use Oasys\Routing\ExceptionMapper;
use Oasys\Routing\HttpMethod;
use Oasys\Routing\NotFoundException;
use Oasys\Routing\RouteTarget;
use Oasys\Routing\Router;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use LogicException;
use ReflectionClass;
use Throwable;

final class KernelTest extends TestCase
{
  public function testResolveDispatchesMatchedRoute(): void
  {
    $router = new Router();
    $router->get('/hello', [HelloController::class, 'index']);

    $container = new SimpleContainer([
      HelloController::class => new HelloController(),
    ]);

    $exceptionMapper = new ExceptionMapper([ErrorController::class, 'handle']);
    $kernel          = new Kernel($container, $router, $exceptionMapper);

    $request  = new ServerRequest('GET', '/hello');
    $response = $kernel->resolve($request);

    self::assertSame(200, $response->getStatusCode());
    self::assertSame('hello', (string) $response->getBody());
  }

  public function testResolveUsesExceptionMapperWhenNoRouteMatched(): void
  {
    $router = new Router(); // no routes

    $container = new SimpleContainer([
      ErrorController::class => new ErrorController(),
    ]);

    $exceptionMapper = new ExceptionMapper([ErrorController::class, 'handle']);
    $kernel          = new Kernel($container, $router, $exceptionMapper);

    $request  = new ServerRequest('GET', '/missing');
    $response = $kernel->resolve($request);

    self::assertSame(500, $response->getStatusCode());
    self::assertSame('error', (string) $response->getBody());
  }

  public function testControllerExceptionIsMappedToErrorHandler(): void
  {
    $router = new Router();
    $router->get('/boom', [BoomController::class, 'boom']);

    $container = new SimpleContainer([
      BoomController::class  => new BoomController(),
      ErrorController::class => new ErrorController(),
    ]);

    $exceptionMapper = new ExceptionMapper([ErrorController::class, 'handle']);
    $exceptionMapper->register(DomainException::class, [ErrorController::class, 'domain']);

    $kernel = new Kernel($container, $router, $exceptionMapper);

    $request  = new ServerRequest('GET', '/boom');
    $response = $kernel->resolve($request);

    self::assertSame(500, $response->getStatusCode());
    self::assertSame('domain', (string) $response->getBody());
  }

  public function testDispatchDepthLimit(): void
  {
    // Exception mapper that always maps to the same failing route
    $router = new Router();
    $router->get('/loop', [LoopController::class, 'fail']);

    $container = new SimpleContainer([
      LoopController::class => new LoopController(),
    ]);

    $exceptionMapper = new ExceptionMapper([LoopController::class, 'fail']); // maps any exception to same failing handler
    $kernel          = new Kernel($container, $router, $exceptionMapper, dispatchDepth: 2);

    $request = new ServerRequest('GET', '/loop');

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Too many dispatch attempts.');

    $kernel->resolve($request);
  }

  public function testMiddlewaresFromAttributesAreApplied(): void
  {
    $router = new Router();
    $router->get('/mw', [MiddlewareController::class, 'index']);

    $container = new SimpleContainer([
      MiddlewareController::class => new MiddlewareController(),
    ]);

    $exceptionMapper = new ExceptionMapper([ErrorController::class, 'handle']);
    $kernel          = new Kernel($container, $router, $exceptionMapper);

    $request  = new ServerRequest('GET', '/mw');
    $response = $kernel->resolve($request);

    self::assertSame(200, $response->getStatusCode());
    self::assertSame('mw', (string) $response->getBody());
    self::assertSame('1', $response->getHeaderLine('X-Middleware-Count'));
  }
}

/**
 * Simple array-backed container for tests
 */
final class SimpleContainer implements ContainerInterface
{
  /** @var array<string, mixed> */
  private array $entries;

  /**
   * @param array<string, mixed> $entries
   */
  public function __construct(array $entries = [])
  {
    $this->entries = $entries;
  }

  public function get(string $id): mixed
  {
    if (! $this->has($id)) {
      throw new class(sprintf('No entry "%s".', $id)) extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {};
    }

    return $this->entries[$id];
  }

  public function has(string $id): bool
  {
    return array_key_exists($id, $this->entries);
  }
}

final class HelloController
{
  public function index(ServerRequestInterface $request): ResponseInterface
  {
    return new Response(200, [], 'hello');
  }
}

final class ErrorController
{
  public function handle(ServerRequestInterface $request): ResponseInterface
  {
    return new Response(500, [], 'error');
  }

  public function domain(ServerRequestInterface $request): ResponseInterface
  {
    return new Response(500, [], 'domain');
  }
}

final class DomainException extends \RuntimeException {}

final class BoomController
{
  public function boom(ServerRequestInterface $request): ResponseInterface
  {
    throw new DomainException('boom');
  }
}

final class LoopController
{
  public function fail(ServerRequestInterface $request): ResponseInterface
  {
    throw new \RuntimeException('fail');
  }
}

/**
 * Example of middleware via attributes
 */
#[MiddlewareAttribute(HeaderMiddleware::class)]
final class MiddlewareController
{
  public function index(ServerRequestInterface $request): ResponseInterface
  {
    return new Response(200, [], 'mw');
  }
}

final class HeaderMiddleware implements MiddlewareInterface
{
  public function process(ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): ResponseInterface
  {
    $response = $handler->handle($request);

    return $response->withHeader('X-Middleware-Count', '1');
  }
}
