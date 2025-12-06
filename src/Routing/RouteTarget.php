<?php declare(strict_types=1);

namespace Oasys\Routing;

/**
 * Route target
 */
final readonly class RouteTarget
{
  public function __construct(
    /** @var class-string Controller FQCN */
    public string $controllerClass,

    /** @var string Action method name */
    public string $action
  ) {}
}
