<?php declare(strict_types=1);

namespace Oasys;

use Oasys\Middleware\MiddlewareAttribute;
use Oasys\Middleware\MiddlewareDispatcher;
use Oasys\Routing\ExceptionMapper;
use Oasys\Routing\NotFoundException;
use Oasys\Routing\Router;
use Oasys\Routing\RouteHandler;
use Oasys\Routing\RouteTarget;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use Throwable;

/**
 * Framework kernel
 */
class Kernel
{
  /** @var int Dispatch depth counter */
  private int $dispatchCount = 0;

  public function __construct(
    /** @var ContainerInterface DI container */
    private ContainerInterface $container,

    /** @var Router HTTP router */
    private Router $router,

    /** @var ExceptionMapper Exception mapper */
    private ExceptionMapper $exceptionMapper,

    /** @var int Maximum dispatch depth */
    private int $dispatchDepth = 3
  ) {}

  /**
   * Resolve request to response
   * 
   * @param ServerRequestInterface $request Request
   * 
   * @return ResponseInterface Response
   */
  public function resolve(ServerRequestInterface $request): ResponseInterface
  {
    $this->dispatchCount = 0;

    $target = $this->router->match($request);

    return $this->dispatch($target, $request);
  }

  /**
   * Dispatch a route
   *
   * @param RouteTarget|null       $target  Target
   * @param ServerRequestInterface $request Request
   * 
   * @return ResponseInterface Response
   * 
   * @throws LogicException if the maximum dispatch depth is exceeded
   */
  protected function dispatch(?RouteTarget $target, ServerRequestInterface $request): ResponseInterface
  {
    try {
      if ($target === null) {
        throw new NotFoundException();
      }

      $middlewares = $this->resolveMiddlewares($target);
      $controller  = $this->container->get($target->controllerClass);
      $core        = new RouteHandler($controller, $target->action);
      $dispatcher  = new MiddlewareDispatcher($middlewares, $core);

      return $dispatcher->handle($request);
    } catch (Throwable $exception) {
      if (++$this->dispatchCount > $this->dispatchDepth) {
        throw new LogicException('Too many dispatch attempts.', 0, $exception);
      }

      return $this->dispatch(
        $this->exceptionMapper->match($exception),
        $request->withAttribute(Throwable::class, $exception)
      );
    }
  }

  /**
   * Resolve middlewares for the route
   *
   * @param RouteTarget $target Target
   *
   * @return MiddlewareInterface[] Middlewares
   */
  protected function resolveMiddlewares(RouteTarget $target): array
  {
    $class  = new ReflectionClass($target->controllerClass);
    $method = $class->getMethod($target->action);

    $attributes = [
      ...$class->getAttributes(MiddlewareAttribute::class, ReflectionAttribute::IS_INSTANCEOF),
      ...$method->getAttributes(MiddlewareAttribute::class, ReflectionAttribute::IS_INSTANCEOF)
    ];

    return array_map(
      function (ReflectionAttribute $attribute): MiddlewareInterface {
        /** @var MiddlewareAttribute $meta */
        $meta = $attribute->newInstance();

        // return new $meta->middlewareClass(...$meta->args);

        return $this->container->make(
          $meta->middlewareClass,
          $meta->args
        );
      },
      $attributes
    );
  }

  /**
   * Send response to the client
   *
   * @param ResponseInterface $response Response
   */
  public static function send(ResponseInterface $response): void
  {
    $statusLine = sprintf(
      'HTTP/%s %s %s',
      $response->getProtocolVersion(),
      $response->getStatusCode(),
      $response->getReasonPhrase()
    );

    header_remove();
    header($statusLine, true, $response->getStatusCode());

    foreach ($response->getHeaders() as $name => $values) {
      $replace = true;

      foreach ($values as $value) {
        $headerLine = sprintf('%s: %s', $name, $value);

        header($headerLine, $replace);

        $replace = false;
      }
    }

    $body = $response->getBody();

    while (! $body->eof()) {
      print $body->read(8192);
    }
  }
}
