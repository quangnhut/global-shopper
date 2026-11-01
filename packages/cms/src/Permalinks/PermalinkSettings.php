<?php

declare(strict_types=1);

namespace Shopper\Cms\Permalinks;

use Shopper\Cms\Support\CmsCache;
use Shopper\Core\Models\Setting;

/**
 * WordPress's Settings → Permalinks (and the "Your homepage displays" pages), stored in `Setting`.
 */
final class PermalinkSettings
{
    public const string STRUCTURE = 'cms_permalink_structure';

    public const string CATEGORY_BASE = 'cms_category_base';

    public const string TAG_BASE = 'cms_tag_base';

    public const string HISTORY = 'cms_permalink_history';

    public const string FRONT_PAGE = 'cms_front_page_id';

    public const string POSTS_PAGE = 'cms_posts_page_id';

    public const string DEFAULT_STRUCTURE = '/%postname%/';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function structure(): PermalinkStructure
    {
        return new PermalinkStructure((string) ($this->get(self::STRUCTURE) ?? self::DEFAULT_STRUCTURE));
    }

    public function categoryBase(): string
    {
        return trim((string) ($this->get(self::CATEGORY_BASE) ?: 'category'), '/');
    }

    public function tagBase(): string
    {
        return trim((string) ($this->get(self::TAG_BASE) ?: 'tag'), '/');
    }

    /**
     * Previous structures, newest first, so old links keep resolving (and redirect).
     *
     * @return list<PermalinkStructure>
     */
    public function history(): array
    {
        return array_values(array_map(
            fn (string $structure): PermalinkStructure => new PermalinkStructure($structure),
            (array) ($this->get(self::HISTORY) ?? []),
        ));
    }

    public function frontPageId(): ?int
    {
        return filled($id = $this->get(self::FRONT_PAGE)) ? (int) $id : null;
    }

    public function postsPageId(): ?int
    {
        return filled($id = $this->get(self::POSTS_PAGE)) ? (int) $id : null;
    }

    /**
     * Like WordPress, links end with a slash when the post structure does.
     */
    public function trailingSlash(): bool
    {
        $structure = $this->structure();

        return $structure->isPlain() || str_ends_with($structure->value, '/');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): void
    {
        $previous = (string) ($this->get(self::STRUCTURE) ?? self::DEFAULT_STRUCTURE);
        $structure = array_key_exists('structure', $values) ? (string) $values['structure'] : $previous;

        if ($structure !== $previous) {
            $history = array_values(array_unique([$previous, ...(array) ($this->get(self::HISTORY) ?? [])]));
            $this->put(self::HISTORY, array_values(array_diff($history, [$structure])));
        }

        $map = [
            'structure' => self::STRUCTURE,
            'category_base' => self::CATEGORY_BASE,
            'tag_base' => self::TAG_BASE,
            'front_page_id' => self::FRONT_PAGE,
            'posts_page_id' => self::POSTS_PAGE,
        ];

        foreach ($map as $field => $key) {
            if (array_key_exists($field, $values)) {
                $this->put($key, $values[$field]);
            }
        }

        $this->values = null;
        CmsCache::flush();
    }

    private function get(string $key): mixed
    {
        $this->values ??= Setting::query()
            ->whereIn('key', [self::STRUCTURE, self::CATEGORY_BASE, self::TAG_BASE, self::HISTORY, self::FRONT_PAGE, self::POSTS_PAGE])
            ->pluck('value', 'key')
            ->all();

        return $this->values[$key] ?? null;
    }

    private function put(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'locked' => true]);
    }
}
