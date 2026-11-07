<?php declare(strict_types=1);

namespace Oasys\Tests;

use Closure;
use DI\ContainerBuilder;
use InvalidArgumentException;
use LogicException;
use Oasys\Kernel;
use Oasys\Middleware\MiddlewareAttribute as Middleware;
use Oasys\Routing\ExceptionMapper;
use Oasys\Routing\Router;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionAttribute;
use ReflectionMethod;
use RuntimeException;
use stdClass;

final class MiddlewareResolverTest extends TestCase
{
  public function testRegisteredMiddlewareWithoutParametersUsesGet(): void
  {
    $instance = new PlainMiddleware();
    $container = new PsrContainer([PlainMiddleware::class => $instance]);

    self::assertSame($instance, $this->resolve($container, 'plain'));
    self::assertSame([PlainMiddleware::class], $container->getCalls);
    self::assertSame(0, $container->hasCalls);
  }

  public function testGetIsPreferredOverMakeWithoutParameters(): void
  {
    $instance = new PlainMiddleware();
    $container = new MakeContainer(
      [PlainMiddleware::class => $instance],
      static fn (): never => throw new RuntimeException('make() must not be called')
    );

    self::assertSame($instance, $this->resolve($container, 'plain'));
    self::assertSame([], $container->makeCalls);
  }

  public function testMakeReceivesNamedParametersAndCanInjectDependencies(): void
  {
    $dependency = new TestDependency();
    $container = new MakeContainer(
      [],
      static fn (string $id, array $args): MiddlewareInterface => new $id(
        $dependency,
        ...$args
      )
    );

    $resolved = $this->resolve($container, 'injected');

    self::assertInstanceOf(InjectedMiddleware::class, $resolved);
    self::assertSame($dependency, $resolved->dependency);
    self::assertSame('admin', $resolved->role);
    self::assertSame([[InjectedMiddleware::class, ['role' => 'admin']]], $container->makeCalls);
    self::assertSame([], $container->getCalls);
  }

  public function testCallableEntryReceivesNamedParametersWithoutMake(): void
  {
    $received = null;
    $container = new PsrContainer([
      RoleMiddleware::class => static function (string $role) use (&$received): MiddlewareInterface {
        $received = $role;
        return new RoleMiddleware($role);
      },
    ]);

    $resolved = $this->resolve($container, 'role');

    self::assertInstanceOf(RoleMiddleware::class, $resolved);
    self::assertSame('admin', $received);
    self::assertSame('admin', $resolved->role);
  }

  public function testInvokableMiddlewareIsTreatedAsMiddlewareNotAsFactory(): void
  {
    $instance = new InvokableMiddleware();
    $container = new PsrContainer([InvokableMiddleware::class => $instance]);

    self::assertSame($instance, $this->resolve($container, 'invokable'));
    self::assertSame(0, $instance->invocations);
  }

  public function testCallableEntryWithoutParametersIsInvoked(): void
  {
    $calls = 0;
    $container = new PsrContainer([
      PlainMiddleware::class => static function () use (&$calls): MiddlewareInterface {
        ++$calls;
        return new PlainMiddleware();
      },
    ]);

    self::assertInstanceOf(PlainMiddleware::class, $this->resolve($container, 'plain'));
    self::assertSame(1, $calls);
  }

  public function testCallableReturnedByMakeReceivesNamedParameters(): void
  {
    $container = new MakeContainer(
      [],
      static fn (string $id, array $args): Closure =>
        static fn (string $role): MiddlewareInterface => new RoleMiddleware($role)
    );

    $resolved = $this->resolve($container, 'role');

    self::assertInstanceOf(RoleMiddleware::class, $resolved);
    self::assertSame('admin', $resolved->role);
    self::assertSame([[RoleMiddleware::class, ['role' => 'admin']]], $container->makeCalls);
  }

  public function testCallableFactoryFailurePropagates(): void
  {
    $container = new PsrContainer([
      RoleMiddleware::class => static function (string $role): never {
        throw new RuntimeException('factory exploded');
      },
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('factory exploded');
    $this->resolve($container, 'role');
  }

  public function testMissingEntryFallsBackToDirectConstructionWithoutParameters(): void
  {
    $container = new PsrContainer();

    self::assertInstanceOf(PlainMiddleware::class, $this->resolve($container, 'plain'));
    self::assertSame([PlainMiddleware::class], $container->getCalls);
  }

  public function testMissingEntryFallsBackToDirectConstructionWithNamedParameters(): void
  {
    $container = new PsrContainer();

    $resolved = $this->resolve($container, 'role');

    self::assertInstanceOf(RoleMiddleware::class, $resolved);
    self::assertSame('admin', $resolved->role);
  }

  public function testMissingMakeDefinitionFallsBackToDirectConstruction(): void
  {
    $container = new MakeContainer();

    $resolved = $this->resolve($container, 'role');

    self::assertInstanceOf(RoleMiddleware::class, $resolved);
    self::assertSame('admin', $resolved->role);
    self::assertSame([[RoleMiddleware::class, ['role' => 'admin']]], $container->makeCalls);
    self::assertSame([], $container->getCalls);
  }

  public function testDirectConstructionAcceptsNonScalarNamedValues(): void
  {
    $resolved = $this->resolve(new PsrContainer(), 'payload');

    self::assertInstanceOf(PayloadMiddleware::class, $resolved);
    self::assertSame(['admin', 'editor'], $resolved->roles);
    self::assertTrue($resolved->strict);
  }

  public function testExistingMiddlewareWithParametersAndNoMakeIsRejected(): void
  {
    $container = new PsrContainer([
      RoleMiddleware::class => new RoleMiddleware('original'),
    ]);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('cannot accept arguments through shared service');

    $this->resolve($container, 'role');
  }

  public function testMissingNonexistentClassPropagatesNotFound(): void
  {
    $this->expectException(EntryNotFound::class);
    $this->resolve(new PsrContainer(), 'unknown');
  }

  public function testMissingNonexistentClassThroughMakePropagatesNotFound(): void
  {
    $this->expectException(EntryNotFound::class);
    $this->resolve(new MakeContainer(), 'unknown');
  }

  public function testInvalidContainerEntryIsRejected(): void
  {
    $container = new PsrContainer([PlainMiddleware::class => new stdClass()]);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('must implement MiddlewareInterface');
    $this->resolve($container, 'plain');
  }

  public function testCallableReturningNonMiddlewareIsRejected(): void
  {
    $container = new PsrContainer([
      RoleMiddleware::class => static fn (string $role): stdClass => new stdClass(),
    ]);

    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('resolved to stdClass');
    $this->resolve($container, 'role');
  }

  public function testConstructorExceptionIsNotSwallowed(): void
  {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('constructor exploded');
    $this->resolve(new PsrContainer(), 'broken');
  }

  public function testContainerFailureIsNotReplacedByDirectConstruction(): void
  {
    $container = new class extends PsrContainer {
      public function get(string $id): mixed
      {
        throw new RuntimeException('container exploded');
      }
    };

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('container exploded');
    $this->resolve($container, 'plain');
  }

  public function testDirectConstructionCannotInjectMissingDependencies(): void
  {
    $this->expectException(\ArgumentCountError::class);
    $this->resolve(new PsrContainer(), 'injected');
  }

  public function testIncorrectNamedConstructorParameterFailsDuringNativeConstruction(): void
  {
    $this->expectException(\Error::class);
    $this->expectExceptionMessage('Unknown named parameter');
    $this->resolve(new PsrContainer(), 'typo');
  }

  public function testPositionalAttributeArgumentsAreRejected(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Middleware arguments must be named');
    $this->resolve(new PsrContainer(), 'positional');
  }

  public function testMixedPositionalAndNamedAttributeArgumentsAreRejected(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->resolve(new PsrContainer(), 'mixed');
  }

  public function testMakeCannotProveThatParametersWereConsumed(): void
  {
    // Known limitation: a container may return an already-built/aliased instance.
    // The resolver cannot verify that make() consumed the provided arguments.
    $instance = new RoleMiddleware('original');
    $container = new MakeContainer([], static fn (): MiddlewareInterface => $instance);

    self::assertSame($instance, $this->resolve($container, 'role'));
    self::assertSame('original', $instance->role);
  }

  public function testPhpDiAutowiringEnabledWithNamedOverrides(): void
  {
    $container = (new ContainerBuilder())->build();
    $resolved = $this->resolve($container, 'injected');

    self::assertInstanceOf(InjectedMiddleware::class, $resolved);
    self::assertInstanceOf(TestDependency::class, $resolved->dependency);
    self::assertSame('admin', $resolved->role);
  }

  public function testPhpDiAutowiringDisabledFallsBackToNew(): void
  {
    $builder = new ContainerBuilder();
    $builder->useAutowiring(false);
    $container = $builder->build();

    $resolved = $this->resolve($container, 'role');

    self::assertInstanceOf(RoleMiddleware::class, $resolved);
    self::assertSame('admin', $resolved->role);
  }

  public function testPhpDiFactoryReceivesNamedParameters(): void
  {
    $builder = new ContainerBuilder();
    $builder->useAutowiring(false);
    $builder->addDefinitions([
      RoleMiddleware::class => \DI\factory(
        static fn (string $role): MiddlewareInterface => new RoleMiddleware($role)
      ),
    ]);

    $resolved = $this->resolve($builder->build(), 'role');

    self::assertInstanceOf(RoleMiddleware::class, $resolved);
    self::assertSame('admin', $resolved->role);
  }

  public function testPhpDiExplicitDefinitionWorksWithAutowiringDisabled(): void
  {
    $builder = new ContainerBuilder();
    $builder->useAutowiring(false);
    $builder->addDefinitions([
      RoleMiddleware::class => \DI\create(RoleMiddleware::class),
    ]);

    $resolved = $this->resolve($builder->build(), 'role');

    self::assertInstanceOf(RoleMiddleware::class, $resolved);
    self::assertSame('admin', $resolved->role);
  }

  private function resolve(ContainerInterface $container, string $method): MiddlewareInterface
  {
    $attribute = (new ReflectionMethod(MiddlewareScenarios::class, $method))
      ->getAttributes(Middleware::class)[0];

    return (new MiddlewareKernelHarness($container))->resolveAttribute($attribute);
  }
}

final class MiddlewareKernelHarness extends Kernel
{
  public function __construct(ContainerInterface $container)
  {
    parent::__construct(
      $container,
      new Router(),
      new ExceptionMapper([MiddlewareScenarios::class, 'plain'])
    );
  }

  public function resolveAttribute(ReflectionAttribute $attribute): MiddlewareInterface
  {
    return $this->instantiateMiddleware($attribute);
  }
}

final class MiddlewareScenarios
{
  #[Middleware(PlainMiddleware::class)]
  public function plain(): void {}

  #[Middleware(InvokableMiddleware::class)]
  public function invokable(): void {}

  #[Middleware(RoleMiddleware::class, role: 'admin')]
  public function role(): void {}

  #[Middleware(InjectedMiddleware::class, role: 'admin')]
  public function injected(): void {}

  #[Middleware(PayloadMiddleware::class, roles: ['admin', 'editor'], strict: true)]
  public function payload(): void {}

  #[Middleware(BrokenMiddleware::class)]
  public function broken(): void {}

  #[Middleware(UnknownMiddleware::class, role: 'admin')]
  public function unknown(): void {}

  #[Middleware(RoleMiddleware::class, rol: 'admin')]
  public function typo(): void {}

  #[Middleware(RoleMiddleware::class, 'admin')]
  public function positional(): void {}

  #[Middleware(RoleMiddleware::class, 'admin', strict: true)]
  public function mixed(): void {}
}

abstract class PassThroughMiddleware implements MiddlewareInterface
{
  public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
  ): ResponseInterface {
    return $handler->handle($request);
  }
}

final class PlainMiddleware extends PassThroughMiddleware {}

final class InvokableMiddleware extends PassThroughMiddleware
{
  public int $invocations = 0;

  public function __invoke(): MiddlewareInterface
  {
    ++$this->invocations;
    return $this;
  }
}

final class RoleMiddleware extends PassThroughMiddleware
{
  public function __construct(public string $role) {}
}

final class TestDependency {}

final class InjectedMiddleware extends PassThroughMiddleware
{
  public function __construct(
    public TestDependency $dependency,
    public string $role
  ) {}
}

final class PayloadMiddleware extends PassThroughMiddleware
{
  public function __construct(
    public array $roles,
    public bool $strict
  ) {}
}

final class BrokenMiddleware extends PassThroughMiddleware
{
  public function __construct()
  {
    throw new RuntimeException('constructor exploded');
  }
}

class EntryNotFound extends RuntimeException implements NotFoundExceptionInterface {}

class PsrContainer implements ContainerInterface
{
  public array $getCalls = [];
  public int $hasCalls = 0;

  public function __construct(protected array $entries = []) {}

  public function get(string $id): mixed
  {
    $this->getCalls[] = $id;

    if (! array_key_exists($id, $this->entries)) {
      throw new EntryNotFound($id);
    }

    return $this->entries[$id];
  }

  public function has(string $id): bool
  {
    ++$this->hasCalls;
    return array_key_exists($id, $this->entries);
  }
}

final class MakeContainer extends PsrContainer
{
  public array $makeCalls = [];

  public function __construct(
    array $entries = [],
    private ?Closure $maker = null
  ) {
    parent::__construct($entries);
  }

  public function make(string $id, array $parameters = []): mixed
  {
    $this->makeCalls[] = [$id, $parameters];

    if ($this->maker === null) {
      throw new EntryNotFound($id);
    }

    return ($this->maker)($id, $parameters);
  }
}
