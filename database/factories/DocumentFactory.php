<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venue_id' => Venue::factory(),
            'documentable_type' => Event::class,
            'documentable_id' => Event::factory(),
            'name' => fake()->words(3, true).'.pdf',
            'path' => 'documents/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1024, 1048576),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Document $document): void {
            if ($document->documentable_id === null) {
                return;
            }

            $documentable = Event::query()->find($document->documentable_id);
            if ($documentable !== null) {
                $document->venue_id = $documentable->venue_id;
            }
        });
    }

    public function forDocumentable(object $documentable): static
    {
        return $this->state(fn (array $attributes) => [
            'documentable_type' => $documentable::class,
            'documentable_id' => $documentable->getKey(),
            'venue_id' => $documentable->getAttribute('venue_id') ?? $attributes['venue_id'],
        ]);
    }

    public function forVenue(Venue $venue): static
    {
        return $this->state(fn (array $attributes) => [
            'venue_id' => $venue->id,
        ]);
    }
}
