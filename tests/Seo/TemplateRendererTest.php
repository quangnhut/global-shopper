<?php

declare(strict_types=1);

use Shopper\Seo\Support\TemplateRenderer;

uses(Tests\Seo\TestCase::class);

describe(TemplateRenderer::class, function (): void {
    it('replaces variables and the separator', function (): void {
        expect(resolve(TemplateRenderer::class)->render('%title% %sep% %sitename%', ['title' => 'Hello', 'sitename' => 'Shop'], '|'))
            ->toBe('Hello | Shop');
    });

    it('drops empty variables without dangling separators', function (): void {
        $renderer = resolve(TemplateRenderer::class);

        expect($renderer->render('%sitename% %sep% %tagline%', ['sitename' => 'Shop', 'tagline' => '']))->toBe('Shop')
            ->and($renderer->render('%title% %sep% %category% %sep% %sitename%', ['title' => 'Hello', 'sitename' => 'Shop']))->toBe('Hello - Shop')
            ->and($renderer->render('%unknown% %title%', ['title' => 'Hello']))->toBe('Hello');
    });

    it('strips HTML from values', function (): void {
        expect(resolve(TemplateRenderer::class)->render('%excerpt%', ['excerpt' => "<p>Bold <strong>text</strong></p>\n"]))->toBe('Bold text');
    });

    it('shortens descriptions at a word boundary', function (): void {
        $text = str_repeat('word ', 60);

        expect(TemplateRenderer::excerpt($text))->toEndWith('word…')->and(mb_strlen(TemplateRenderer::excerpt($text)))->toBeLessThanOrEqual(156)
            ->and(TemplateRenderer::excerpt('<p>Short &amp; sweet</p>'))->toBe('Short & sweet');
    });
})->group('seo');
