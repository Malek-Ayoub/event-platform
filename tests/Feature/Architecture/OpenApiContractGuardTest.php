<?php

namespace Tests\Feature\Architecture;

use App\OpenApi\OpenApiContractRegistry;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ensures OpenAPI documentation stays in sync with the API contract (Phase 6.8).
 *
 * Source of truth: FormRequest + ApiResource + DTO.
 * OpenAPI (`app/OpenApi/`) is a read-only projection — update both in the same commit.
 */
class OpenApiContractGuardTest extends TestCase
{
    /** @var array<string, mixed>|null */
    private static ?array $spec = null;

    /**
     * @return array<string, mixed>
     */
    private function openApiSpec(): array
    {
        if (self::$spec === null) {
            Artisan::call('l5-swagger:generate');

            $path = storage_path('api-docs/api-docs.json');
            $this->assertFileExists($path, 'OpenAPI spec must be generated at storage/api-docs/api-docs.json');

            $decoded = json_decode((string) file_get_contents($path), true);
            $this->assertIsArray($decoded);

            self::$spec = $decoded;
        }

        return self::$spec;
    }

    /**
     * @return list<string>
     */
    private function documentedOperationIds(): array
    {
        $spec = $this->openApiSpec();
        $ids = [];

        foreach ($spec['paths'] ?? [] as $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $operation) {
                if (is_array($operation) && isset($operation['operationId'])) {
                    $ids[] = (string) $operation['operationId'];
                }
            }
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function requiredRouteNames(): array
    {
        $names = [];

        foreach (OpenApiContractRegistry::documentedRouteFiles() as $file) {
            $this->assertFileExists($file);
            $contents = (string) file_get_contents($file);

            preg_match_all("/->name\('([^']+)'\)/", $contents, $matches);

            foreach ($matches[1] as $name) {
                $names[] = $name;
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    #[Test]
    public function l5_swagger_generate_produces_valid_openapi_document(): void
    {
        $spec = $this->openApiSpec();

        $this->assertSame('3.0.0', $spec['openapi'] ?? null);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertNotEmpty($spec['paths']);
        $this->assertArrayHasKey('components', $spec);
    }

    #[Test]
    public function every_named_api_route_is_documented_with_matching_operation_id(): void
    {
        $required = $this->requiredRouteNames();
        $documented = $this->documentedOperationIds();

        $missing = array_values(array_diff($required, $documented));

        $this->assertSame(
            [],
            $missing,
            'These named routes are missing from OpenAPI (operationId must equal route name): '.implode(', ', $missing),
        );
    }

    #[Test]
    public function core_contract_projections_exist_as_openapi_schemas(): void
    {
        $schemas = array_keys($this->openApiSpec()['components']['schemas'] ?? []);

        foreach (OpenApiContractRegistry::coreProjections() as $routeName => $projection) {
            if (isset($projection['request'])) {
                $schemaName = OpenApiContractRegistry::schemaNameFor($projection['request']);
                $this->assertContains(
                    $schemaName,
                    $schemas,
                    "Route {$routeName} request projection schema [{$schemaName}] must exist — mirror of {$projection['request']}",
                );
            }

            if (isset($projection['resource'])) {
                $schemaName = OpenApiContractRegistry::schemaNameFor($projection['resource']);
                $this->assertContains(
                    $schemaName,
                    $schemas,
                    "Route {$routeName} resource projection schema [{$schemaName}] must exist — mirror of {$projection['resource']}",
                );
            }
        }
    }

    #[Test]
    public function open_api_schemas_are_not_orphaned(): void
    {
        $spec = $this->openApiSpec();
        $schemaNames = array_keys($spec['components']['schemas'] ?? []);
        $referenced = $this->collectSchemaReferences($spec['paths'] ?? []);

        $orphaned = array_values(array_diff($schemaNames, $referenced));

        $this->assertSame(
            [],
            $orphaned,
            'Orphan OpenAPI schemas (never referenced from paths): '.implode(', ', $orphaned),
        );
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function collectSchemaReferences(array $node): array
    {
        $refs = [];

        array_walk_recursive($node, function (mixed $value, string|int $key) use (&$refs): void {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#/components/schemas/')) {
                $refs[] = substr($value, strlen('#/components/schemas/'));
            }
        });

        return array_values(array_unique($refs));
    }

    #[Test]
    public function core_operations_include_request_or_response_examples(): void
    {
        $spec = $this->openApiSpec();
        $coreRoutes = array_keys(OpenApiContractRegistry::coreProjections());

        foreach ($coreRoutes as $routeName) {
            $operation = $this->findOperationById($spec, $routeName);
            $this->assertNotNull($operation, "Core route {$routeName} must be documented");

            $hasExample = isset($operation['requestBody'])
                || $this->responseHasExample($operation);

            $this->assertTrue(
                $hasExample,
                "Core route {$routeName} should document request or response examples",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<string, mixed>|null
     */
    private function findOperationById(array $spec, string $operationId): ?array
    {
        foreach ($spec['paths'] ?? [] as $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $operation) {
                if (is_array($operation) && ($operation['operationId'] ?? null) === $operationId) {
                    return $operation;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function responseHasExample(array $operation): bool
    {
        foreach ($operation['responses'] ?? [] as $response) {
            if (! is_array($response)) {
                continue;
            }

            $content = $response['content']['application/json'] ?? null;

            if (! is_array($content)) {
                continue;
            }

            if (isset($content['example']) || isset($content['schema'])) {
                return true;
            }
        }

        return false;
    }
}
