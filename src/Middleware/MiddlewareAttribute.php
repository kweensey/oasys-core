<?php declare(strict_types=1);

namespace Oasys\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Attribute;
use InvalidArgumentException;

/**
 * Middleware attribute
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class MiddlewareAttribute
{
  /** @var array<string, mixed> Arguments */
  public array $args;

  public function __construct(
    /** @var class-string<MiddlewareInterface> Middleware FQCN */
    public string $middlewareClass,

    /** @param mixed Arguments */
    mixed ...$args
  ) {
    foreach (array_keys($args) as $name) {
      if (! is_string($name)) {
        throw new InvalidArgumentException('Middleware arguments must be named.');
      }
    }

    $this->args = $args;
  }
}
