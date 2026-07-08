<?php

namespace Tests\Unit\Http;

use App\Http\Resources\Auth\CurrentUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    use RefreshDatabase;

    private function apiTestResponse(JsonResponse $response, string $uri = '/api/test'): TestResponse
    {
        return $this->createTestResponse($response, Request::create($uri));
    }

    #[Test]
    public function it_wraps_success_payload_in_data(): void
    {
        $response = $this->apiTestResponse(ApiResponse::success(['id' => 1]));

        $response
            ->assertOk()
            ->assertExactJson(['data' => ['id' => 1]]);
    }

    #[Test]
    public function it_includes_optional_meta_on_success(): void
    {
        $response = $this->apiTestResponse(
            ApiResponse::success(['id' => 1], 200, ['trace' => 'abc']),
        );

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => ['id' => 1],
                'meta' => ['trace' => 'abc'],
            ]);
    }

    #[Test]
    public function it_returns_message_payload(): void
    {
        $response = $this->apiTestResponse(ApiResponse::message('Saved.', 202));

        $response
            ->assertStatus(202)
            ->assertExactJson(['data' => ['message' => 'Saved.']]);
    }

    #[Test]
    public function it_returns_json_resource_with_data_wrapper(): void
    {
        $user = User::factory()->create();

        $response = $this->apiTestResponse(ApiResponse::resource(new CurrentUserResource($user)));

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    #[Test]
    public function it_returns_created_resource_with_201(): void
    {
        $user = User::factory()->create();

        $response = $this->apiTestResponse(ApiResponse::created(new CurrentUserResource($user)));

        $response
            ->assertCreated()
            ->assertJsonPath('data.id', $user->id);
    }

    #[Test]
    public function it_returns_structured_error_payload(): void
    {
        $response = $this->apiTestResponse(
            ApiResponse::error('Invalid input.', 422, [
                'email' => ['The email field is required.'],
            ]),
        );

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Invalid input.',
                'errors' => [
                    'email' => ['The email field is required.'],
                ],
            ]);
    }

    #[Test]
    public function it_paginates_resource_collections(): void
    {
        $users = User::factory()->count(3)->create();

        $paginator = new LengthAwarePaginator(
            $users->take(2),
            $users->count(),
            2,
            1,
            ['path' => 'http://localhost/api/users'],
        );

        /** @var AnonymousResourceCollection $collection */
        $collection = CurrentUserResource::collection($paginator);

        $response = $this->apiTestResponse(ApiResponse::paginated($collection, $paginator));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }
}
