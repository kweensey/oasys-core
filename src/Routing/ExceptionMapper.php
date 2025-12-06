<?php declare(strict_types=1);

namespace Oasys\Routing;

use LogicException;
use ReflectionMethod;
use Throwable;

/**
 * Exception mapper
 */
class ExceptionMapper
{
  /** @var array<class-string<Throwable>, RouteTarget> Registered pairs */
  protected array $map = [];

  /**
   * @param array{0: class-string, 1: string} $fallbackHandler [controller FQCN, action method name]
   */
  public function __construct(array $fallbackHandler)
  {
    $this->register(Throwable::class, $fallbackHandler);
  }
  
  /**
   * Register target for exception
   * 
   * @param class-string<Throwable>           $exceptionClass Exception FQCN
   * @param array{0: class-string, 1: string} $handler        [controller FQCN, action method name]
   * 
   * @return self Self-reference
   * 
   * @throws LogicException if handler is malformed
   * @throws LogicException if handler class does not exist
   * @throws LogicException if handler method does not exist
   * @throws LogicException if handler method is not public
   */
  public function register(string $exceptionClass, array $handler): self
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
    
    $this->map[$exceptionClass] = new RouteTarget(...$handler);

    return $this;
  }

  /**
   * Match exception to target
   * 
   * @param Throwable $exception Exception instance
   * 
   * @return RouteTarget Target
   */
  public function match(Throwable $exception): RouteTarget
  {
    $class = $exception::class;

    while ($class !== false) {
      if (isset($this->map[$class])) {
        return $this->map[$class];
      }

      $class = get_parent_class($class);
    }

    return $this->map[Throwable::class];
  }
}
