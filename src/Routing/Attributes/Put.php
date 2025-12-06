<?php declare(strict_types=1);

namespace Oasys\Routing\Attributes;

use Oasys\Routing\HttpMethod;
use Attribute;

/**
 * PUT request route attribute
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Put extends HttpRoute
{
  /**
   * @param string $path URI path pattern
   */
  public function __construct(string $path)
  {
    parent::__construct($path, HttpMethod::PUT);
  }
}
