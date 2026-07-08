<?php

namespace Tests\Unit\Http;

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\Handler\ApiExceptionRenderer;
use App\Exceptions\Orders\InsufficientTicketsException;
use App\Exceptions\StaleModelException;
use App\Exceptions\TenantNotResolvedException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ApiExceptionRendererTest extends TestCase
{
    private ApiExceptionRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->renderer = new ApiExceptionRenderer;
    }

    private function apiTestResponse(?JsonResponse $response, Request $request): TestResponse
    {
        $this->assertNotNull($response);

        return $this->createTestResponse($response, $request);
    }

    #[Test]
    public function it_renders_validation_errors_for_api_requests(): void
    {
        $request = Request::create('/api/auth/login', 'POST');
        $exception = ValidationException::withMessages([
            'email' => ['The email field is required.'],
        ]);

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_renders_authentication_errors(): void
    {
        $request = Request::create('/api/auth/user', 'GET');
        $exception = new AuthenticationException;

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    #[Test]
    public function it_renders_authorization_errors(): void
    {
        $request = Request::create('/api/events', 'GET');
        $exception = new AuthorizationException('Forbidden action.');

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertForbidden()
            ->assertJsonPath('message', 'Forbidden action.');
    }

    #[Test]
    public function it_renders_model_not_found_as_404(): void
    {
        $request = Request::create('/api/events/999', 'GET');
        $exception = new ModelNotFoundException;

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    #[Test]
    public function it_renders_stale_model_conflicts_as_409(): void
    {
        $request = Request::create('/api/platform-settings', 'PUT');
        $exception = new StaleModelException('PlatformSetting', 1, 3);

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertStatus(409)
            ->assertJsonPath('message', $exception->getMessage());
    }

    #[Test]
    public function it_renders_tenant_errors_as_403(): void
    {
        $request = Request::create('/api/tenant/events', 'GET');
        $exception = new TenantNotResolvedException('Tenant could not be resolved.');

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertForbidden()
            ->assertJsonPath('message', 'Tenant could not be resolved.');
    }

    #[Test]
    public function it_renders_auth_domain_exceptions_as_401(): void
    {
        $request = Request::create('/api/auth/login', 'POST');
        $exception = new InvalidCredentialsException;

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertUnauthorized();
    }

    #[Test]
    public function it_renders_order_domain_exceptions_as_422(): void
    {
        $request = Request::create('/api/orders', 'POST');
        $exception = InsufficientTicketsException::forTicketType(5, 10, 2);

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertUnprocessable()
            ->assertJsonPath('message', $exception->getMessage());
    }

    #[Test]
    public function it_renders_http_exceptions_with_status_code(): void
    {
        $request = Request::create('/api/events', 'GET');
        $exception = new NotFoundHttpException('Route missing.');

        $response = $this->apiTestResponse(
            $this->renderer->render($request, $exception),
            $request,
        );

        $response->assertNotFound()
            ->assertJsonPath('message', 'Route missing.');
    }

    #[Test]
    public function it_skips_non_api_requests(): void
    {
        $request = Request::create('/login', 'GET');
        $exception = new AuthenticationException;

        $this->assertNull($this->renderer->render($request, $exception));
    }
}
