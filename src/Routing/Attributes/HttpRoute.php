<?php declare(strict_types=1);

namespace Oasys\Routing\Attributes;

use Oasys\Routing\HttpMethod;

abstract readonly class HttpRoute
{
  public function __construct(
    /** @var string URI path pattern */
    public string $path,

    /** @var HttpMethod HTTP method type */
    public HttpMethod $httpMethod
  ) {}
}
