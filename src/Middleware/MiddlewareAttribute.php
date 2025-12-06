<?php declare(strict_types=1);

namespace Oasys\Middleware;

use Psr\Http\Server\MiddlewareInterface;
use Attribute;

/**
 * Middleware attribute
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class MiddlewareAttribute
{
  /** @var array Arguments */
  public array $args;

  public function __construct(
    /** @var class-string<MiddlewareInterface> Middleware FQCN */
    public string $middlewareClass,

    /** @param mixed Arguments <scalar> */
    mixed ...$args
  ) {
    $this->args = $args;
  }
}
