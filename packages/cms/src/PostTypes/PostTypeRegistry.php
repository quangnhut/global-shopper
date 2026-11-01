<?php

declare(strict_types=1);

namespace Shopper\Cms\PostTypes;

use InvalidArgumentException;

final class PostTypeRegistry
{
    /**
     * @var array<string, PostType>
     */
    private array $types = [];

    /**
     * @param  array<string, array{label?: string, singular_label?: string, hierarchical?: bool, supports?: list<string>}>  $definitions
     */
    public static function fromConfig(array $definitions): self
    {
        $registry = new self;

        foreach ($definitions as $key => $definition) {
            $registry->register(PostType::fromArray($key, $definition));
        }

        return $registry;
    }

    public function register(PostType $type): self
    {
        $this->types[$type->key] = $type;

        return $this;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    public function get(string $key): PostType
    {
        return $this->types[$key] ?? throw new InvalidArgumentException("Post type [{$key}] is not registered.");
    }

    /**
     * @return array<string, PostType>
     */
    public function all(): array
    {
        return $this->types;
    }
}
