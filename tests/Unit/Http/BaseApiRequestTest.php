<?php

namespace Tests\Unit\Http;

use App\DTOs\BaseDTO;
use App\Http\Requests\Api\BaseApiRequest;
use Illuminate\Support\Facades\Validator;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseApiRequestTest extends TestCase
{
    #[Test]
    public function it_maps_validated_input_to_dto(): void
    {
        $request = new class extends BaseApiRequest
        {
            protected function dtoClass(): ?string
            {
                return SampleRequestDTO::class;
            }

            public function authorize(): bool
            {
                return true;
            }

            /**
             * @return array<string, list<string>>
             */
            public function rules(): array
            {
                return [
                    'name' => ['required', 'string'],
                    'quantity' => ['required', 'integer', 'min:1'],
                ];
            }
        };

        $request->merge([
            'name' => 'VIP',
            'quantity' => 2,
        ]);

        $validator = Validator::make($request->all(), $request->rules());
        $request->setValidator($validator);

        $dto = $request->toDto();

        $this->assertInstanceOf(SampleRequestDTO::class, $dto);
        $this->assertSame('VIP', $dto->name);
        $this->assertSame(2, $dto->quantity);
    }

    #[Test]
    public function it_requires_dto_class_override(): void
    {
        $request = new class extends BaseApiRequest
        {
            public function authorize(): bool
            {
                return true;
            }

            /**
             * @return array<string, list<string>>
             */
            public function rules(): array
            {
                return [];
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must override dtoClass()');

        $request->toDto();
    }
}

readonly class SampleRequestDTO extends BaseDTO
{
    public function __construct(
        public string $name,
        public int $quantity,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            quantity: (int) $data['quantity'],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity,
        ];
    }
}
