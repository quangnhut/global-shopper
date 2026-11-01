<?php

declare(strict_types=1);

use Shopper\Cms\PostTypes\PostType;
use Shopper\Cms\PostTypes\PostTypeRegistry;

uses(Tests\Cms\TestCase::class);

describe(PostTypeRegistry::class, function (): void {
    it('registers `post` and `page` from the config', function (): void {
        $registry = resolve(PostTypeRegistry::class);

        expect(array_keys($registry->all()))->toBe(['post', 'page'])
            ->and($registry->get('post')->hierarchical)->toBeFalse()
            ->and($registry->get('post')->supports('categories'))->toBeTrue()
            ->and($registry->get('page')->hierarchical)->toBeTrue()
            ->and($registry->get('page')->getLabel())->toBe('Pages');
    });

    it('accepts custom post types', function (): void {
        $registry = resolve(PostTypeRegistry::class)
            ->register(new PostType('portfolio', 'Portfolio', 'Project', supports: ['title']));

        expect($registry->has('portfolio'))->toBeTrue()
            ->and($registry->get('portfolio')->getSingularLabel())->toBe('Project');
    });

    it('throws for an unknown post type', function (): void {
        resolve(PostTypeRegistry::class)->get('unknown');
    })->throws(InvalidArgumentException::class);
})->group('cms');
