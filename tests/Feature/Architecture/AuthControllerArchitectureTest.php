<?php

namespace Tests\Feature\Architecture;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\PasswordController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class AuthControllerArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $authControllers = [
        AuthController::class,
        PasswordController::class,
    ];

    /** @var list<string> */
    private array $forbiddenControllerPatterns = [
        'response()->json(',
        'DB::transaction(',
        'ActivityLog::',
        'OutboxEvent::',
        'OutboxService',
        'ActivityLogService',
    ];

    #[Test]
    public function auth_controllers_extend_base_api_controller(): void
    {
        foreach ($this->authControllers as $controller) {
            $this->assertTrue(
                is_subclass_of($controller, BaseApiController::class),
                "{$controller} must extend ".BaseApiController::class,
            );
        }
    }

    #[Test]
    public function auth_controllers_do_not_import_domain_models(): void
    {
        foreach ($this->authControllers as $controller) {
            $source = file_get_contents((new ReflectionClass($controller))->getFileName());
            $this->assertIsString($source);

            $this->assertDoesNotMatchRegularExpression(
                '/use App\\\\Models\\\\/',
                $source,
                "{$controller} must not import App\\Models\\* directly",
            );
        }
    }

    #[Test]
    public function auth_controllers_do_not_use_forbidden_patterns(): void
    {
        foreach ($this->authControllers as $controller) {
            $source = file_get_contents((new ReflectionClass($controller))->getFileName());
            $this->assertIsString($source);

            foreach ($this->forbiddenControllerPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$controller} must not use {$pattern}",
                );
            }
        }
    }

    #[Test]
    public function auth_controller_actions_use_form_requests(): void
    {
        foreach ($this->authControllers as $controller) {
            foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $controller) {
                    continue;
                }

                $parameters = $method->getParameters();

                $this->assertCount(
                    1,
                    $parameters,
                    "{$controller}::{$method->getName()} must accept exactly one FormRequest parameter",
                );

                $type = $parameters[0]->getType();
                $this->assertNotNull($type, "{$controller}::{$method->getName()} must type-hint a FormRequest");

                $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;
                $this->assertNotNull($typeName);
                $this->assertStringContainsString(
                    'Request',
                    $typeName,
                    "{$controller}::{$method->getName()} must type-hint a dedicated FormRequest",
                );
                $this->assertNotSame(
                    Request::class,
                    $typeName,
                    "{$controller}::{$method->getName()} must not use Illuminate\\Http\\Request",
                );
            }
        }
    }

    #[Test]
    public function auth_controllers_inject_services(): void
    {
        foreach ($this->authControllers as $controller) {
            $constructor = (new ReflectionClass($controller))->getConstructor();
            $this->assertNotNull($constructor, "{$controller} must define a constructor for service injection");

            $this->assertNotEmpty(
                $constructor->getParameters(),
                "{$controller} constructor must inject at least one service",
            );

            $hasService = false;

            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof \ReflectionNamedType && str_contains($type->getName(), 'Service')) {
                    $hasService = true;
                    break;
                }
            }

            $this->assertTrue($hasService, "{$controller} must inject at least one *Service dependency");
        }
    }

    #[Test]
    public function auth_controllers_use_api_resources(): void
    {
        $source = file_get_contents((new ReflectionClass(AuthController::class))->getFileName());
        $this->assertIsString($source);

        $this->assertStringContainsString('ApiTokenResource', $source);
        $this->assertStringContainsString('CurrentUserResource', $source);
        $this->assertStringContainsString('respondResource', $source);
        $this->assertStringContainsString('respondCreated', $source);
        $this->assertStringContainsString('respondPlainMessage', $source);
    }
}
