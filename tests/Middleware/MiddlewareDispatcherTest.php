<?php declare(strict_types=1);

namespace Oasys\Tests\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Oasys\Middleware\MiddlewareDispatcher;
use Oasys\Middleware\MiddlewareHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class MiddlewareDispatcherTest extends TestCase
{
  public function testNoMiddlewaresCallsCore(): void
  {
    $core = new class implements RequestHandlerInterface {
      public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
      {
        return new Response(200, [], 'core');
      }
    };

    $dispatcher = new MiddlewareDispatcher([], $core);
    $response   = $dispatcher->handle(new ServerRequest('GET', '/'));

    self::assertSame('core', (string) $response->getBody());
  }

  public function testSingleMiddlewareWrapsCore(): void
  {
    $calls = [];

    $middleware = new class($calls) implements MiddlewareInterface {
      private array $calls;

      public function __construct(array &$calls)
      {
        $this->calls = &$calls;
      }

      public function process(\Psr\Http\Message\ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
      {
        $this->calls[] = 'before';
        $response      = $handler->handle($request);
        $this->calls[] = 'after';

        return $response;
      }
    };

    $core = new class implements RequestHandlerInterface {
      public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
      {
        return new Response(200, [], 'core');
      }
    };

    $dispatcher = new MiddlewareDispatcher([$middleware], $core);
    $response   = $dispatcher->handle(new ServerRequest('GET', '/'));

    self::assertSame('core', (string) $response->getBody());
    self::assertSame(['before', 'after'], $middlewareReflection = (new \ReflectionClass($middleware))->getProperty('calls')->getValue($middleware));
  }

  public function testThrowsForInvalidMiddlewareType(): void
  {
    $core = new class implements RequestHandlerInterface {
      public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
      {
        return new Response();
      }
    };

    $dispatcher = new MiddlewareDispatcher(['not-a-middleware'], $core);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Invalid middleware type');

    $dispatcher->handle(new ServerRequest('GET', '/'));
  }

  public function testMultipleMiddlewaresOrder(): void
  {
    $log = [];

    $mw1 = new LoggingMiddleware($log, 'mw1');
    $mw2 = new LoggingMiddleware($log, 'mw2');

    $core = new class($log) implements RequestHandlerInterface {
      private array $log;

      public function __construct(array &$log)
      {
        $this->log = &$log;
      }

      public function handle(\Psr\Http\Message\ServerRequestInterface $request): ResponseInterface
      {
        $this->log[] = 'core';
        return new Response(200, [], 'core');
      }
    };

    $dispatcher = new MiddlewareDispatcher([$mw1, $mw2], $core);
    $response   = $dispatcher->handle(new ServerRequest('GET', '/'));

    self::assertSame('core', (string) $response->getBody());
    self::assertSame(['mw1:before', 'mw2:before', 'core', 'mw2:after', 'mw1:after'], $log);
  }
}

final class LoggingMiddleware implements MiddlewareInterface
{
  /** @var list<string> */
  private array $log;

  private string $name;

  /**
   * @param list<string> $log
   */
  public function __construct(array &$log, string $name)
  {
    $this->log  = &$log;
    $this->name = $name;
  }

  public function process(\Psr\Http\Message\ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
  {
    $this->log[] = $this->name . ':before';
    $response    = $handler->handle($request);
    $this->log[] = $this->name . ':after';

    return $response;
  }
}
