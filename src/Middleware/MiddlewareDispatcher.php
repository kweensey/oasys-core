<?php declare(strict_types=1);

namespace Oasys\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Middleware dispatcher
 */
final readonly class MiddlewareDispatcher implements RequestHandlerInterface
{
  public function __construct(
    /** @var MiddlewareInterface[] Middleware instances */
    private array $middlewares,

    /** @var RequestHandlerInterface Core request handler */
    private RequestHandlerInterface $core
  ) {}

  /**
   * @inheritdoc
   * 
   * @throws RuntimeException if any middleware does not implement MiddlewareInterface
   */
  public function handle(ServerRequestInterface $request): ResponseInterface
  {
    $handler = $this->core;

    foreach (array_reverse($this->middlewares) as $middleware) {
      if (! $middleware instanceof MiddlewareInterface) {
        throw new RuntimeException(sprintf(
          'Invalid middleware type "%s": must be instance of %s.',
          is_object($middleware)
            ? $middleware::class
            : gettype($middleware),
          MiddlewareInterface::class
        ));
      }

      $handler = new MiddlewareHandler($middleware, $handler);
    }

    return $handler->handle($request);
  }
}
