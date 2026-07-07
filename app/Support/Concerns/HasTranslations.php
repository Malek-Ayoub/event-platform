<?php

namespace App\Support\Concerns;

trait HasTranslations
{
    public function getTranslationsColumn(): string
    {
        return 'translations';
    }

    public function translate(string $field, ?string $locale = null): mixed
    {
        $locale ??= app()->getLocale();
        $translations = $this->getAttribute($this->getTranslationsColumn());

        if (! is_array($translations)) {
            return null;
        }

        return data_get($translations, $locale.'.'.$field);
    }

    public function setTranslation(string $field, mixed $value, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        $column = $this->getTranslationsColumn();
        $translations = $this->getAttribute($column);

        if (! is_array($translations)) {
            $translations = [];
        }

        data_set($translations, $locale.'.'.$field, $value);
        $this->setAttribute($column, $translations);
    }
}
