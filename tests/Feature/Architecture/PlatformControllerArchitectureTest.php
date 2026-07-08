<?php

namespace Tests\Feature\Architecture;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\PlatformSettingController;
use App\Http\Controllers\Api\TaxRateController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class PlatformControllerArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $platformControllers = [
        TaxRateController::class,
        PlatformSettingController::class,
    ];

    /** @var list<string> */
    private array $forbiddenControllerPatterns = [
        'response()->json(',
        'DB::',
        'ActivityLog::',
        'OutboxEvent::',
        'OutboxService',
        'ActivityLogService',
        'OrderService',
        'PaymentService',
    ];

    #[Test]
    public function platform_controllers_extend_base_api_controller(): void
    {
        foreach ($this->platformControllers as $controller) {
            $this->assertTrue(
                is_subclass_of($controller, BaseApiController::class),
                "{$controller} must extend ".BaseApiController::class,
            );
        }
    }

    #[Test]
    public function platform_controllers_do_not_import_domain_models(): void
    {
        foreach ($this->platformControllers as $controller) {
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
    public function platform_controllers_inject_exactly_one_domain_service(): void
    {
        foreach ($this->platformControllers as $controller) {
            $constructor = (new ReflectionClass($controller))->getConstructor();
            $this->assertNotNull($constructor);

            $serviceParameters = array_filter(
                $constructor->getParameters(),
                fn ($parameter) => ($type = $parameter->getType()) instanceof \ReflectionNamedType
                    && str_contains($type->getName(), 'Service'),
            );

            $this->assertCount(
                1,
                $serviceParameters,
                "{$controller} must inject exactly one domain *Service",
            );
        }
    }

    #[Test]
    public function platform_controller_actions_use_form_requests(): void
    {
        foreach ($this->platformControllers as $controller) {
            foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $controller) {
                    continue;
                }

                $parameters = $method->getParameters();
                $this->assertCount(1, $parameters, "{$controller}::{$method->getName()} must accept one FormRequest");

                $typeName = $parameters[0]->getType() instanceof \ReflectionNamedType
                    ? $parameters[0]->getType()->getName()
                    : null;

                $this->assertNotNull($typeName);
                $this->assertStringContainsString('Request', $typeName);
                $this->assertNotSame(Request::class, $typeName);
            }
        }
    }
}
