<?php declare(strict_types=1);

namespace Oasys\Tests\Routing;

use Oasys\Routing\HttpMethod;
use PHPUnit\Framework\TestCase;

final class HttpMethodTest extends TestCase
{
  public function testBackedValuesMatchNames(): void
  {
    foreach (HttpMethod::cases() as $method) {
      self::assertSame($method->name, $method->value);
    }
  }

  public function testTryFromValidValues(): void
  {
    self::assertSame(HttpMethod::GET, HttpMethod::tryFrom('GET'));
    self::assertSame(HttpMethod::POST, HttpMethod::tryFrom('POST'));
    self::assertSame(HttpMethod::PUT, HttpMethod::tryFrom('PUT'));
    self::assertSame(HttpMethod::PATCH, HttpMethod::tryFrom('PATCH'));
    self::assertSame(HttpMethod::DELETE, HttpMethod::tryFrom('DELETE'));
    self::assertSame(HttpMethod::HEAD, HttpMethod::tryFrom('HEAD'));
    self::assertSame(HttpMethod::OPTIONS, HttpMethod::tryFrom('OPTIONS'));
    self::assertSame(HttpMethod::CONNECT, HttpMethod::tryFrom('CONNECT'));
    self::assertSame(HttpMethod::TRACE, HttpMethod::tryFrom('TRACE'));
  }

  public function testTryFromInvalidValueReturnsNull(): void
  {
    self::assertNull(HttpMethod::tryFrom('get'));
    self::assertNull(HttpMethod::tryFrom('FOO'));
    self::assertNull(HttpMethod::tryFrom(''));
  }
}
