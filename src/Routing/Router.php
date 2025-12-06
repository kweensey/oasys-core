<?php declare(strict_types=1);

namespace Oasys\Routing;

use Oasys\Routing\Attributes\HttpRoute;
use Psr\Http\Message\ServerRequestInterface;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionAttribute;

/**
 * HTTP router
 */
class Router
{
  /** @var array<string, array<string, RouteTarget>> Registered routes */
  protected array $routes = [];

  public function __construct(
    /** @var array<string, string> URI path pattern aliases */
    protected array $aliases = []
  ) {}

  /**
   * Register a GET route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function get(string $path, array $handler): self
  {
    return $this->register(HttpMethod::GET, $path, $handler);
  }

  /**
   * Register a POST route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function post(string $path, array $handler): self
  {
    return $this->register(HttpMethod::POST, $path, $handler);
  }

  /**
   * Register a PUT route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function put(string $path, array $handler): self
  {
    return $this->register(HttpMethod::PUT, $path, $handler);
  }

  /**
   * Register a PATCH route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function patch(string $path, array $handler): self
  {
    return $this->register(HttpMethod::PATCH, $path, $handler);
  }

  /**
   * Register a DELETE route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function delete(string $path, array $handler): self
  {
    return $this->register(HttpMethod::DELETE, $path, $handler);
  }

  /**
   * Register a HEAD route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function head(string $path, array $handler): self
  {
    return $this->register(HttpMethod::HEAD, $path, $handler);
  }

  /**
   * Register an OPTIONS route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function options(string $path, array $handler): self
  {
    return $this->register(HttpMethod::OPTIONS, $path, $handler);
  }

  /**
   * Register a CONNECT route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function connect(string $path, array $handler): self
  {
    return $this->register(HttpMethod::CONNECT, $path, $handler);
  }

  /**
   * Register a TRACE route
   *
   * @param string                            $path    URI path pattern
   * @param array{0: class-string, 1: string} $handler [controller FQCN, action method name]
   *
   * @return self Self-reference
   */
  public function trace(string $path, array $handler): self
  {
    return $this->register(HttpMethod::TRACE, $path, $handler);
  }

  /**
   * Register a controller via route attributes
   * 
   * @param class-string $controllerClass Controller FQCN
   * 
   * @return self Self-reference
   */
  public function bind(string $controllerClass): self
  {
    $class = new ReflectionClass($controllerClass);

    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
      foreach ($method->getAttributes(HttpRoute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
        $route = $attribute->newInstance();

        $this->register(
          $route->httpMethod,
          $route->path,
          [$controllerClass, $method->getName()]
        );
      }
    }

    return $this;
  }

  /**
   * Match request to target
   *
   * @param ServerRequestInterface $request Request
   *
   * @return RouteTarget|null Target
   */
  public function match(ServerRequestInterface $request): ?RouteTarget
  {
    $httpMethod = HttpMethod::tryFrom($request->getMethod());

    if ($httpMethod === null) {
      return null;
    }

    $uriPath = $request
      ->getUri()
      ->getPath();

    foreach ($this->routes[$httpMethod->value] ?? [] as $pattern => $target) {
      if (preg_match('#^' . $pattern . '$#', $uriPath) === 1) {
        return $target;
      }
    }

    return null;
  }

  /**
   * Register route
   *
   * @param HttpMethod                        $httpMethod HTTP method type
   * @param string                            $path       URI path pattern
   * @param array{0: class-string, 1: string} $handler    [controller FQCN, action method name]
   *
   * @return self Self-reference
   * 
   * @throws LogicException if handler is malformed
   * @throws LogicException if handler class does not exist
   * @throws LogicException if handler method does not exist
   * @throws LogicException if handler method is not public
   * @throws LogicException if the resulting route pattern is not a valid regular expression
   */
  protected function register(HttpMethod $httpMethod, string $path, array $handler): self
  {
    if (
      count($handler) !== 2 ||
      ! array_is_list($handler) ||
      ! is_string($handler[0]) ||
      ! is_string($handler[1]) ||
      $handler[0] === '' ||
      $handler[1] === ''
    ) {
      throw new LogicException(sprintf(
        'Invalid route handler format "%s": must be [controller FQCN, action method name].',
        json_encode($handler)
      ));
    }

    if (! class_exists($handler[0])) {
      throw new LogicException(sprintf(
        'Invalid route handler %s::%s: class does not exist.',
        ...$handler
      ));
    }

    if (! method_exists(...$handler)) {
      throw new LogicException(sprintf(
        'Invalid route handler %s::%s: method does not exist.',
        ...$handler
      ));
    }

    $method = new ReflectionMethod(...$handler);

    if (! $method->isPublic()) {
      throw new LogicException(sprintf(
        'Invalid route handler %s::%s: method is not public.',
        ...$handler
      ));
    }

    $pattern = $this->applyAliases($path);

    if (@preg_match('#^' . $pattern . '$#', '') === false) {
      throw new LogicException(sprintf(
        'Invalid route pattern "%s" for handler %s::%s.',
        $pattern,
        ...$handler
      ));
    }

    $this->routes[$httpMethod->value][$pattern] = new RouteTarget(...$handler);

    return $this;
  }

  /**
   * Apply aliases to the path
   *
   * @param string $path URI path pattern
   *
   * @return string Regex pattern
   */
  protected function applyAliases(string $path): string
  {
    foreach ($this->aliases as $alias => $pattern) {
      $path = str_replace('{' . $alias . '}', $pattern, $path);
    }

    return $path;
  }
}
