<?php declare(strict_types=1);

namespace Oasys\Routing;

use RuntimeException;
use Throwable;

/**
 * Not found exception
 */
final class NotFoundException extends RuntimeException
{
  public function __construct(
    /** @var string Message */
    string $message = 'Route not found.',

    /** @var int HTTP status code */
    int $code = 404,

    /** @var Throwable|null Previous throwable */
    ?Throwable $previous = null
  ) {
    parent::__construct($message, $code, $previous);
  }
}
