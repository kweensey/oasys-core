<?php declare(strict_types=1);

namespace Oasys\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * Route handler
 */
final readonly class RouteHandler implements RequestHandlerInterface
{
  public function __construct(
    /** @var object Controller instance */
    private object $controller,

    /** @var string Action method name */
    private string $action
  ) {}

  /**
   * @inheritdoc
   * 
   * @throws RuntimeException if the controller action does not return a ResponseInterface instance
   */
  public function handle(ServerRequestInterface $request): ResponseInterface
  {
    $response = $this->controller->{$this->action}($request);

    if (! $response instanceof ResponseInterface) {
      throw new RuntimeException(sprintf(
        'Invalid return type "%s" for target %s::%s: must be an instance of %s.',
        is_object($response)
          ? $response::class
          : gettype($response),
        $this->controller::class,
        $this->action,
        ResponseInterface::class
      ));
    }

    return $response;
  }
}
