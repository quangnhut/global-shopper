<?php

declare(strict_types=1);

namespace Shopper\Seo\Registry;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Shopper\Seo\Contracts\PathResolver;
use Shopper\Seo\Contracts\SeoSource;
use Shopper\Seo\Sources\ShopPathResolver;

/**
 * Content types handled by SEO: shop entities from config, CMS content registered by the CMS package.
 */
final class SeoableRegistry
{
    /**
     * @var array<string, array{label: string, model: class-string<Model>, source: ?class-string<SeoSource>}>
     */
    private array $types = [];

    /** @var array<int, class-string<PathResolver>> */
    private array $resolvers = [];

    /** @var array<string, SeoSource> */
    private array $sources = [];

    /**
     * @param  array<string, array{label: string, model: string, source?: ?class-string<SeoSource>}>  $definitions  `model` is a class or a `shopper.models.*` key
     */
    public static function fromConfig(array $definitions): self
    {
        $registry = new self;
        $registry->resolvePathsWith(ShopPathResolver::class);

        foreach ($definitions as $key => $definition) {
            $model = class_exists($definition['model']) ? $definition['model'] : config("shopper.models.{$definition['model']}");

            if (is_string($model) && class_exists($model)) {
                $registry->register($key, $model, $definition['label'], $definition['source'] ?? null);
            }
        }

        return $registry;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  class-string<SeoSource>|null  $source  describes entries for the head output and sitemaps
     */
    public function register(string $key, string $model, string $label, ?string $source = null): self
    {
        $this->types[$key] = ['label' => $label, 'model' => $model, 'source' => $source];
        unset($this->sources[$key]);

        return $this;
    }

    /**
     * Turns a public path into an entry (the CMS adds its permalink resolver; shop paths come from config).
     *
     * @param  class-string<PathResolver>  $resolver
     */
    public function resolvePathsWith(string $resolver): self
    {
        if (! in_array($resolver, $this->resolvers, true)) {
            $this->resolvers[] = $resolver;
        }

        return $this;
    }

    /**
     * @return array<int, class-string<PathResolver>>
     */
    public function pathResolvers(): array
    {
        return $this->resolvers;
    }

    public function source(string $key): ?SeoSource
    {
        $class = $this->types[$key]['source'] ?? null;

        if ($class === null) {
            return null;
        }

        return $this->sources[$key] ??= resolve($class);
    }

    /**
     * @return array<string, array{label: string, model: class-string<Model>, source: ?class-string<SeoSource>}>
     */
    public function all(): array
    {
        return $this->types;
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @return class-string<Model>
     */
    public function model(string $key): string
    {
        return $this->types[$key]['model'] ?? throw new InvalidArgumentException("SEO type [{$key}] is not registered.");
    }

    public function label(string $key): string
    {
        return __($this->types[$key]['label'] ?? $key);
    }

    /**
     * The type key of a model instance (or null when it is not handled by SEO).
     */
    public function keyFor(Model $model): ?string
    {
        foreach ($this->types as $key => $type) {
            if ($model instanceof $type['model']) {
                return $this->narrow($key, $model);
            }
        }

        return null;
    }

    /**
     * Posts and pages share a model: tell them apart by the model's own `type` attribute when registered.
     */
    private function narrow(string $key, Model $model): string
    {
        $type = $model->getAttribute('type');

        return is_string($type) && $this->has($type) && $this->types[$type]['model'] === $this->types[$key]['model'] ? $type : $key;
    }
}
