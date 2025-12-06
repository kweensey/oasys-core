<?php declare(strict_types=1);

namespace Oasys\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware handler
 */
final readonly class MiddlewareHandler implements RequestHandlerInterface
{
  public function __construct(
    /** @var MiddlewareInterface Middleware instance */
    private MiddlewareInterface $middleware,

    /** @var RequestHandlerInterface Next handler in the chain */
    private RequestHandlerInterface $next
  ) {}

  /**
   * @inheritdoc
   */
  public function handle(ServerRequestInterface $request): ResponseInterface
  {
    return $this->middleware->process($request, $this->next);
  }
}
