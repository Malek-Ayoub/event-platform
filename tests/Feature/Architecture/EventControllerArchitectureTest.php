<?php

namespace Tests\Feature\Architecture;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\TicketTypeController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class EventControllerArchitectureTest extends TestCase
{
    /** @var list<class-string> */
    private array $eventControllers = [
        EventController::class,
        CategoryController::class,
        TicketTypeController::class,
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
        'TicketService',
    ];

    #[Test]
    public function event_controllers_extend_base_api_controller(): void
    {
        foreach ($this->eventControllers as $controller) {
            $this->assertTrue(
                is_subclass_of($controller, BaseApiController::class),
                "{$controller} must extend ".BaseApiController::class,
            );
        }
    }

    #[Test]
    public function event_controllers_do_not_import_domain_models(): void
    {
        foreach ($this->eventControllers as $controller) {
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
    public function event_controllers_do_not_use_forbidden_patterns(): void
    {
        foreach ($this->eventControllers as $controller) {
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
    public function event_controllers_inject_exactly_one_domain_service(): void
    {
        $expected = [
            EventController::class => EventController::class,
            CategoryController::class => CategoryController::class,
            TicketTypeController::class => TicketTypeController::class,
        ];

        foreach ($this->eventControllers as $controller) {
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

        unset($expected);
    }

    #[Test]
    public function event_controller_actions_use_form_requests(): void
    {
        foreach ($this->eventControllers as $controller) {
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

    #[Test]
    public function event_controllers_use_api_resources_and_response_helpers(): void
    {
        foreach ($this->eventControllers as $controller) {
            $source = file_get_contents((new ReflectionClass($controller))->getFileName());
            $this->assertIsString($source);
            $this->assertTrue(
                str_contains($source, 'respondResource')
                    || str_contains($source, 'respondCreated')
                    || str_contains($source, 'respondPaginated')
                    || str_contains($source, 'respondPlainMessage'),
                "{$controller} must use BaseApiController response helpers",
            );
            $this->assertStringContainsString('Resource', $source);
        }
    }
}
