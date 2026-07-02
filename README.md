# Oasys

[![Tests](https://github.com/kweensey/oasys-core/actions/workflows/tests.yml/badge.svg)](https://github.com/kweensey/oasys-core/actions/workflows/tests.yml)
[![Latest Stable Version](https://poser.pugx.org/kweensey/oasys-core/v)](https://packagist.org/packages/kweensey/oasys-core)
[![PHP Version Require](https://poser.pugx.org/kweensey/oasys-core/require/php)](https://packagist.org/packages/kweensey/oasys-core)
[![License](https://poser.pugx.org/kweensey/oasys-core/license)](https://packagist.org/packages/kweensey/oasys-core)

Minimal HTTP framework for modern PHP.

Works with any PSR-7 messages, PSR-15 middleware, and PSR-11 container.

- Manual and attribute-based routing
- Controller and method level middleware pipeline
- Exception-to-handler mapping
- Small dependency-free kernel

---

## Installation

Example using PHP-DI container and Guzzle's request/response:

```bash
composer require oasys/core
composer require php-di/php-di
composer require guzzlehttp/psr7
```

---

## Quick start

`public/index.php`:

```php
<?php declare(strict_types=1);

use App\Controller\IndexController;
use Oasys\Kernel;
use Oasys\Routing\ExceptionMapper;
use Oasys\Routing\Router;
use DI\ContainerBuilder;
use GuzzleHttp\Psr7\ServerRequest;

require __DIR__ . '/../vendor/autoload.php';

// Build DI container
$builder = new ContainerBuilder();

$container = $builder->build();

// Build router and register routes
$router = new Router();

$router->get('/', [IndexController::class, 'home']);

// Map default exception handler
$exceptionMapper = new ExceptionMapper([IndexController::class, 'error']);

// Build kernel
$kernel = new Kernel(
    $container,
    $router,
    $exceptionMapper
);

// Build request
$request = ServerRequest::fromGlobals();

// Resolve request
$response = $kernel->resolve($request);

// Emit response
Kernel::send($response);
```

`src/Controller/IndexController.php`:

```php
<?php declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class IndexController
{
    public function home(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(
            200,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            "Welcome home\n"
        );
    }

    public function error(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(
            500,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            "Internal server error\n"
        );
    }
}
```

---

## Routing

### Manual routes

You can register routes manually

```php
$router->get('/', [IndexController::class, 'home']);
$router->post('/contact-form', [ContactController::class, 'send']);
// put(), patch(), delete(), head(), options(), connect(), trace()
```

You can daisy-chain routes

```php
$router
    ->get('/', [IndexController::class, 'home'])
    ->post('/contact-form', [ContactController::class, 'send']);
```

### Route attributes

Alternatively, you can use attributes on controller method

```php
use Oasys\Routing\Attributes\Get;
// Post, Put, Patch, Delete, Head, Options, Connect, Trace

#[Get('/')]
public function home(ServerRequestInterface $request): ResponseInterface
{
    // ...
}
```

...and auto-wire routes

```php
$router->bind(IndexController::class);
```

You can use multiple attributes on a single method

```php
#[Post('/contact-form')]
#[Put('/contact-form')]
public function send(ServerRequestInterface $request): ResponseInterface
{
    // ...
}
```

### Path aliases

You can create dynamic route using alias by registering its pattern

```php
$router = new Router([
    'id' => '\d+',
    // other aliases...
]);
```

...and then use placeholder in the path

```php
$router->get('/user/{id}', [UserController::class, 'show']);

// or

#[Get('/user/{id}')]
public function show(ServerRequestInterface $request): ResponseInterface
{
    // ...
}
```

---

## Middleware

You can use any PSR-15 middleware

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TimingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start    = microtime(true);
        $response = $handler->handle($request);
        $elapsed  = number_format((microtime(true) - $start) * 1000, 2);

        return $response->withHeader('X-Response-Time-ms', (string) $elapsed);
    }
}
```

### Scope

You can apply middleware using `MiddlewareAttribute`

Class-level attributes apply to all methods; method-level attributes apply only to that action

```php
use Oasys\Middleware\MiddlewareAttribute as Middleware;

#[Middleware(TimingMiddleware::class)]
final class ProfileController
{
    #[Middleware(AuthMiddleware::class)]
    public function show(ServerRequestInterface $request): ResponseInterface
    {
        // ...
    }
}
```

### Parameters

You can supply parameters to the middleware

```php
#[Middleware(ContentTypeMiddleware::class, 'application/json' /* , other parameters...*/)]
public function show(ServerRequestInterface $request): ResponseInterface
{
    // ...
}
```

...and access them in the constructor

```php
<?php declare(strict_types=1);

namespace App\Middleware;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ContentTypeMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected string $supported

        // other parameters...
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getHeaderLine('Content-Type') !== $this->supported) {
            return new Response(
                400,
                ['Content-Type' => 'text/plain'],
                sprintf("Bad request: required Content-Type is: %s\n", $this->supported)
            );
        }

        return $handler->handle($request);
    }
}
```

---

## Exception handling

Register default handler for any exception thrown during runtime

```php
$exceptionMapper = new ExceptionMapper([ErrorController::class, 'serverError']);
```

You can register individual handlers for specific exception types

```php
$exceptionMapper->register(NotFoundException::class, [ErrorController::class, 'notFound']);
$exceptionMapper->register(ValidationException::class, [ErrorController::class, 'badRequest']);
```

You can daisy-chain mapping

```php
$exceptionMapper
    ->register(NotFoundException::class, [ErrorController::class, 'notFound'])
    ->register(ValidationException::class, [ErrorController::class, 'badRequest']);
```

When resolving a handler, `ExceptionMapper` will:
- use a handler registered for the exact exception class
- otherwise walk up the inheritance chain and use the first parent class that has a handler
- otherwise fall back to the default handler registered in constructor

Any exception thrown inside controllers or middleware is passed to the handler via request attribute

```php
<?php declare(strict_types=1);

namespace App\Controller;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ErrorController
{
    public function serverError(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Throwable|null $e */
        $e = $request->getAttribute(Throwable::class);

        return new Response(
            500,
            ['Content-Type' => 'text/plain; charset=utf-8'],
            sprintf("Internal Server Error: %s\n", $e?->getMessage())
        );
    }
}
```

---

## Summary

- Define controllers returning PSR-7 `ResponseInterface`
- Add routing attributes to methods and auto-wire routes, or register routes manually on `Router`
- Use `MiddlewareAttribute` to attach PSR-15 middleware to controllers and actions
- Use `ExceptionMapper` to send exceptions to dedicated controllers
- Let `Kernel` wire everything together and emit the response with `Kernel::send()`
