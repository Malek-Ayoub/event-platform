<?php

namespace Tests\Feature\Architecture;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlatformSettingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\TaxRateController;
use App\Http\Controllers\Api\TicketTypeController;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Consolidated controller architecture guard (Phase 6.8 / §6.9).
 *
 * Replaces the per-domain *ControllerArchitectureTest files (Auth, Event,
 * Commerce, Order, Payment, Platform) with a single guard covering every
 * API controller, plus the Payment SSOT extension (§6.10): only
 * PaymentService may mark an Order as paid.
 */
class ControllerArchitectureGuardTest extends TestCase
{
    /** @var list<class-string> */
    private array $allControllers = [
        AuthController::class,
        PasswordController::class,
        EventController::class,
        PublicEventController::class,
        CategoryController::class,
        TicketTypeController::class,
        ProductController::class,
        ProductVariantController::class,
        CouponController::class,
        PromoCodeController::class,
        OrderController::class,
        PaymentController::class,
        TaxRateController::class,
        PlatformSettingController::class,
    ];

    /**
     * Domain services each controller may legitimately depend on beyond its
     * own — used to police cross-aggregate/cross-domain leakage (§6.9, §6.10).
     *
     * @var array<class-string, list<string>>
     */
    private array $forbiddenServicePatterns = [
        AuthController::class => [],
        PasswordController::class => [],
        EventController::class => ['OrderService', 'PaymentService', 'TicketService'],
        PublicEventController::class => ['OrderService', 'PaymentService', 'TicketService'],
        CategoryController::class => ['OrderService', 'PaymentService', 'TicketService'],
        TicketTypeController::class => ['OrderService', 'PaymentService', 'TicketService'],
        ProductController::class => ['OrderService', 'PaymentService', 'TicketService', 'EventService'],
        ProductVariantController::class => ['OrderService', 'PaymentService', 'TicketService', 'EventService'],
        CouponController::class => ['OrderService', 'PaymentService', 'TicketService', 'EventService'],
        PromoCodeController::class => ['OrderService', 'PaymentService', 'TicketService', 'EventService'],
        OrderController::class => ['PaymentService', 'TicketService', 'EventService'],
        PaymentController::class => [
            'OrderService', 'RefundService', 'CommissionService', 'TicketService', 'EventService', 'OrderStatus::Paid',
            'PaymentGatewayService', 'PaymentGatewayRegistry', 'PaymentVerificationGateway',
        ],
        TaxRateController::class => ['OrderService', 'PaymentService'],
        PlatformSettingController::class => ['OrderService', 'PaymentService'],
    ];

    /** @var list<string> */
    private array $universallyForbiddenPatterns = [
        'response()->json(',
        'DB::',
        'ActivityLog::',
        'OutboxEvent::',
        'OutboxService',
        'ActivityLogService',
        '::query()->create(',
        '::query()->update(',
        '->save()',
    ];

    #[Test]
    public function all_controllers_extend_base_api_controller(): void
    {
        foreach ($this->allControllers as $controller) {
            $this->assertTrue(
                is_subclass_of($controller, BaseApiController::class),
                "{$controller} must extend ".BaseApiController::class,
            );
        }
    }

    #[Test]
    public function all_controllers_do_not_import_domain_models(): void
    {
        foreach ($this->allControllers as $controller) {
            $source = $this->sourceOf($controller);

            $this->assertDoesNotMatchRegularExpression(
                '/use App\\\\Models\\\\/',
                $source,
                "{$controller} must not import App\\Models\\* directly",
            );
        }
    }

    #[Test]
    public function all_controllers_do_not_use_universally_forbidden_patterns(): void
    {
        foreach ($this->allControllers as $controller) {
            $source = $this->sourceOf($controller);

            foreach ($this->universallyForbiddenPatterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$controller} must not use {$pattern}",
                );
            }
        }
    }

    #[Test]
    public function controllers_do_not_leak_into_other_domain_services(): void
    {
        foreach ($this->forbiddenServicePatterns as $controller => $patterns) {
            $source = $this->sourceOf($controller);

            foreach ($patterns as $pattern) {
                $this->assertStringNotContainsString(
                    $pattern,
                    $source,
                    "{$controller} must not reference {$pattern} — cross-domain/aggregate boundary violation",
                );
            }
        }
    }

    #[Test]
    public function all_controllers_inject_at_least_one_domain_service(): void
    {
        foreach ($this->allControllers as $controller) {
            $constructor = (new ReflectionClass($controller))->getConstructor();
            $this->assertNotNull($constructor, "{$controller} must define a constructor for service injection");

            $serviceParameters = array_filter(
                $constructor->getParameters(),
                fn ($parameter) => ($type = $parameter->getType()) instanceof ReflectionNamedType
                    && str_contains($type->getName(), 'Service'),
            );

            $this->assertNotEmpty(
                $serviceParameters,
                "{$controller} must inject at least one domain *Service",
            );
        }
    }

    #[Test]
    public function all_controller_actions_use_dedicated_form_requests(): void
    {
        foreach ($this->allControllers as $controller) {
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

                $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
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
    public function all_controller_actions_do_not_return_models_directly(): void
    {
        foreach ($this->allControllers as $controller) {
            foreach ((new ReflectionClass($controller))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $controller) {
                    continue;
                }

                $returnType = $method->getReturnType();
                $typeName = $returnType instanceof ReflectionNamedType ? $returnType->getName() : null;

                $this->assertNotNull($typeName, "{$controller}::{$method->getName()} must declare a return type");
                $this->assertStringNotContainsString(
                    'App\\Models\\',
                    $typeName,
                    "{$controller}::{$method->getName()} must not return an Eloquent model directly",
                );
            }
        }
    }

    #[Test]
    public function all_controllers_use_resources_and_response_helpers(): void
    {
        foreach ($this->allControllers as $controller) {
            $source = $this->sourceOf($controller);

            $this->assertTrue(
                str_contains($source, 'respondResource')
                    || str_contains($source, 'respondCreated')
                    || str_contains($source, 'respondPaginated')
                    || str_contains($source, 'respondPlainMessage')
                    || str_contains($source, 'respondSuccess')
                    || str_contains($source, 'respondMessage'),
                "{$controller} must use BaseApiController response helpers",
            );
        }
    }

    #[Test]
    public function payment_controller_depends_only_on_instruction_and_verification_services(): void
    {
        $source = $this->sourceOf(PaymentController::class);

        $this->assertStringContainsString('PaymentInstructionService', $source);
        $this->assertStringContainsString('PaymentVerificationService', $source);
        $this->assertStringContainsString('PaymentService', $source);
    }

    /**
     * OrderStatus::Paid, and it must not do so directly (that responsibility
     * belongs solely to PaymentService — enforced separately in
     * ServiceArchitectureGuardTest::only_payment_service_marks_orders_as_paid()).
     */
    #[Test]
    public function no_controller_sets_order_status_to_paid_directly(): void
    {
        foreach ($this->allControllers as $controller) {
            $source = $this->sourceOf($controller);

            $this->assertStringNotContainsString(
                'OrderStatus::Paid',
                $source,
                "{$controller} must not reference OrderStatus::Paid — PaymentService is the SSOT",
            );
        }
    }

    /**
     * @param  class-string  $controller
     */
    private function sourceOf(string $controller): string
    {
        $source = file_get_contents((new ReflectionClass($controller))->getFileName());
        $this->assertIsString($source);

        return $source;
    }
}
