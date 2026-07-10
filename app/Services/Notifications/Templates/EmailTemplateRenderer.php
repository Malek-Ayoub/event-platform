<?php

namespace App\Services\Notifications\Templates;

use App\Exceptions\Notifications\NotificationTemplateNotFoundException;
use App\Models\EmailTemplate;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\Notifications\Data\RenderedNotificationTemplate;

final class EmailTemplateRenderer
{
    /**
     * @param  array<string, scalar|null>  $variables
     */
    public function render(string $slug, array $variables, ?int $venueId = null): RenderedNotificationTemplate
    {
        $template = $this->resolveTemplate($slug, $venueId);

        return new RenderedNotificationTemplate(
            subject: $this->interpolate($template['subject'], $variables),
            body: $this->interpolate($template['body'], $variables),
            slug: $slug,
        );
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function resolveTemplate(string $slug, ?int $venueId): array
    {
        if ($venueId !== null) {
            $venueTemplate = EmailTemplate::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->active()
                ->where('venue_id', $venueId)
                ->where('slug', $slug)
                ->first();

            if ($venueTemplate !== null) {
                return [
                    'subject' => $venueTemplate->subject,
                    'body' => $venueTemplate->body,
                ];
            }
        }

        $platformTemplate = EmailTemplate::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->active()
            ->whereNull('venue_id')
            ->where('slug', $slug)
            ->first();

        if ($platformTemplate !== null) {
            return [
                'subject' => $platformTemplate->subject,
                'body' => $platformTemplate->body,
            ];
        }

        $configuredTemplates = config('notifications.templates', []);
        $configured = is_array($configuredTemplates) ? ($configuredTemplates[$slug] ?? null) : null;

        if (is_array($configured) && isset($configured['subject'], $configured['body'])) {
            return [
                'subject' => (string) $configured['subject'],
                'body' => (string) $configured['body'],
            ];
        }

        throw NotificationTemplateNotFoundException::forSlug($slug, $venueId);
    }

    /**
     * @param  array<string, scalar|null>  $variables
     */
    private function interpolate(string $content, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $key => $value) {
            $replacements['{{'.$key.'}}'] = (string) ($value ?? '');
        }

        return strtr($content, $replacements);
    }
}
