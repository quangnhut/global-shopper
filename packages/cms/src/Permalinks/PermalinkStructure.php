<?php

declare(strict_types=1);

namespace Shopper\Cms\Permalinks;

/**
 * A WordPress permalink structure such as `/%year%/%monthnum%/%postname%/`. An empty structure is
 * "Plain" (`/?p=123`).
 */
final readonly class PermalinkStructure
{
    /**
     * Tag => regex of its value.
     */
    public const array TAGS = [
        '%year%' => '\d{4}',
        '%monthnum%' => '\d{2}',
        '%day%' => '\d{2}',
        '%hour%' => '\d{2}',
        '%minute%' => '\d{2}',
        '%second%' => '\d{2}',
        '%post_id%' => '\d+',
        '%postname%' => '[^/]+',
        '%category%' => '.+?',
        '%author%' => '[^/]+',
    ];

    public string $value;

    public function __construct(string $value)
    {
        $value = mb_trim($value);
        $this->value = $value === '' ? '' : '/'.mb_ltrim($value, '/');
    }

    public function isPlain(): bool
    {
        return $this->value === '';
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        preg_match_all('/%[a-z_]+%/', $this->value, $matches);

        return $matches[0];
    }

    /**
     * Validation errors (translation keys), empty when the structure is usable.
     *
     * @param  list<string>  $reserved  first path segments the structure may not start with
     * @return list<string>
     */
    public function errors(array $reserved = []): array
    {
        if ($this->isPlain()) {
            return [];
        }

        $errors = [];
        $tags = $this->tags();

        if (array_diff($tags, array_keys(self::TAGS)) !== []) {
            $errors[] = 'shopper-cms::permalinks.errors.unknown_tag';
        }

        if (! in_array('%postname%', $tags, true) && ! in_array('%post_id%', $tags, true)) {
            $errors[] = 'shopper-cms::permalinks.errors.needs_identifier';
        }

        $first = explode('/', mb_trim($this->value, '/'))[0];

        if ($first !== '' && ! str_contains($first, '%') && in_array($first, $reserved, true)) {
            $errors[] = 'shopper-cms::permalinks.errors.reserved';
        }

        return $errors;
    }

    /**
     * Regex matching a path (without leading/trailing slashes) and capturing each tag by name.
     */
    public function regex(): string
    {
        $pattern = preg_quote(mb_trim($this->value, '/'), '#');

        foreach (self::TAGS as $tag => $tagPattern) {
            $name = mb_trim($tag, '%');
            $pattern = str_replace(preg_quote($tag, '#'), "(?P<{$name}>{$tagPattern})", $pattern);
        }

        return "#^{$pattern}$#u";
    }

    /**
     * @param  array<string, string|int>  $values  tag name without % => value
     */
    public function build(array $values): string
    {
        $path = $this->value;

        foreach ($values as $name => $value) {
            $path = str_replace("%{$name}%", (string) $value, $path);
        }

        return $path;
    }
}
