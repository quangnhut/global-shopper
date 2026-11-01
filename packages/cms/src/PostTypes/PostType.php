<?php

declare(strict_types=1);

namespace Shopper\Cms\PostTypes;

use Illuminate\Support\Str;

final readonly class PostType
{
    /**
     * @param  list<string>  $supports
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $singularLabel,
        public bool $hierarchical = false,
        public array $supports = [],
    ) {}

    /**
     * @param  array{label?: string, singular_label?: string, hierarchical?: bool, supports?: list<string>}  $definition
     */
    public static function fromArray(string $key, array $definition): self
    {
        return new self(
            key: $key,
            label: $definition['label'] ?? $key,
            singularLabel: $definition['singular_label'] ?? $definition['label'] ?? $key,
            hierarchical: $definition['hierarchical'] ?? false,
            supports: $definition['supports'] ?? [],
        );
    }

    public function supports(string $feature): bool
    {
        return in_array($feature, $this->supports, true);
    }

    /**
     * WordPress-style permission names for this type: `permission('edit')` → `edit_posts`,
     * `permission('edit', others: true)` → `edit_others_posts`, `permission('publish')` → `publish_posts`.
     */
    public function permission(string $ability, bool $others = false): string
    {
        $plural = Str::plural($this->key);

        return $others ? "{$ability}_others_{$plural}" : "{$ability}_{$plural}";
    }

    public function getLabel(): string
    {
        return __($this->label);
    }

    public function getSingularLabel(): string
    {
        return __($this->singularLabel);
    }
}
