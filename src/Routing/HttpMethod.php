<?php declare(strict_types=1);

namespace Oasys\Routing;

/**
 * HTTP method type
 */
enum HttpMethod: string
{
  case GET     = 'GET';
  case POST    = 'POST';
  case PUT     = 'PUT';
  case PATCH   = 'PATCH';
  case DELETE  = 'DELETE';
  case HEAD    = 'HEAD';
  case OPTIONS = 'OPTIONS';
  case CONNECT = 'CONNECT';
  case TRACE   = 'TRACE';
}
