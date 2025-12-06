<?php declare(strict_types=1);

namespace Oasys\Tests\Routing;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Oasys\Routing\RouteHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class RouteHandlerTest extends TestCase
{
  public function testReturnsControllerResponse(): void
  {
    $controller = new class {
      public function index(ServerRequest $request): ResponseInterface
      {
        return new Response(200, [], 'OK');
      }
    };

    $handler  = new RouteHandler($controller, 'index');
    $request  = new ServerRequest('GET', '/');
    $response = $handler->handle($request);

    self::assertInstanceOf(ResponseInterface::class, $response);
    self::assertSame(200, $response->getStatusCode());
    self::assertSame('OK', (string) $response->getBody());
  }

  public function testThrowsWhenControllerReturnsNonResponse(): void
  {
    $controller = new class {
      public function index(ServerRequest $request): string
      {
        return 'not a response';
      }
    };

    $handler = new RouteHandler($controller, 'index');
    $request = new ServerRequest('GET', '/');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Invalid return type');

    $handler->handle($request);
  }
}
