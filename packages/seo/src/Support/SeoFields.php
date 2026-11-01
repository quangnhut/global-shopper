<?php

declare(strict_types=1);

namespace Shopper\Seo\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Shopper\Seo\Models\SeoMeta;

/**
 * Reads and writes the SEO panel's state for any model. Shop entities keep their title/description in their
 * existing `seo_title` / `seo_description` columns (the admin already edits them); everything else is stored in
 * `seo_meta`.
 */
final class SeoFields
{
    public const array META_FIELDS = [
        'title', 'description', 'focus_keyword', 'canonical_url', 'robots_index', 'robots_follow', 'robots_advanced',
        'exclude_from_sitemap', 'breadcrumb_title', 'og_title', 'og_description', 'og_image_upload_id',
        'twitter_title', 'twitter_description', 'twitter_image_upload_id', 'schema_type', 'schema_custom',
    ];

    /** @var array<string, bool> */
    private static array $columns = [];

    public static function meta(Model $model): ?SeoMeta
    {
        if (! $model->exists) {
            return null;
        }

        return SeoMeta::query()
            ->where('seoable_type', $model->getMorphClass())
            ->where('seoable_id', $model->getKey())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(Model $model): array
    {
        $meta = self::meta($model);
        $state = Arr::only($meta?->attributesToArray() ?? [], self::META_FIELDS);

        foreach (['title', 'description'] as $field) {
            if (self::usesColumn($model, $field)) {
                $state[$field] = $model->getAttribute("seo_{$field}");
            }
        }

        $state['robots_index'] = self::tristate($meta?->robots_index);
        $state['robots_follow'] = self::tristate($meta?->robots_follow);
        $state['robots_advanced'] = $meta?->robots_advanced['flags'] ?? [];
        $state['max_image_preview'] = $meta?->robots_advanced['max_image_preview'] ?? null;
        $state['schema_custom'] = $meta?->schema_custom === null ? null : json_encode($meta->schema_custom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state  the panel's state (only the keys present are changed)
     */
    public static function save(Model $model, array $state, bool $canEditScripts = false): SeoMeta
    {
        $values = Arr::only($state, self::META_FIELDS);

        foreach (['title', 'description'] as $field) {
            if (array_key_exists($field, $values) && self::usesColumn($model, $field)) {
                $model->forceFill(["seo_{$field}" => $values[$field] ?: null])->save();
                unset($values[$field]);
            }
        }

        foreach (['robots_index', 'robots_follow'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = self::fromTristate($values[$field]);
            }
        }

        if (array_key_exists('robots_advanced', $state) || array_key_exists('max_image_preview', $state)) {
            $values['robots_advanced'] = array_filter([
                'flags' => array_values((array) ($state['robots_advanced'] ?? [])),
                'max_image_preview' => $state['max_image_preview'] ?? null,
            ]) ?: null;
        }

        if (array_key_exists('schema_custom', $values)) {
            if ($canEditScripts) {
                $values['schema_custom'] = filled($values['schema_custom']) ? json_decode((string) $values['schema_custom'], true) : null;
            } else {
                unset($values['schema_custom']);
            }
        }

        if (array_key_exists('canonical_url', $values) && ! self::isSafeUrl($values['canonical_url'])) {
            $values['canonical_url'] = null;
        }

        return SeoMeta::query()->updateOrCreate(
            ['seoable_type' => $model->getMorphClass(), 'seoable_id' => $model->getKey()],
            $values,
        );
    }

    /**
     * Whether the model has its own `seo_title` / `seo_description` column.
     */
    public static function usesColumn(Model $model, string $field): bool
    {
        $key = $model->getTable().'.seo_'.$field;

        return self::$columns[$key] ??= Schema::hasColumn($model->getTable(), "seo_{$field}");
    }

    public static function isSafeUrl(?string $url): bool
    {
        return $url === null || $url === '' || preg_match('#^(https?://|/)#i', $url) === 1;
    }

    private static function tristate(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => 'default',
        };
    }

    private static function fromTristate(mixed $value): ?bool
    {
        return match ($value) {
            'yes', true => true,
            'no', false => false,
            default => null,
        };
    }
}
