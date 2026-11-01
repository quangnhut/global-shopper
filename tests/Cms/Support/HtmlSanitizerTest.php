<?php

declare(strict_types=1);

use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Support\HtmlSanitizer;

uses(Tests\Cms\TestCase::class);

beforeEach(function (): void {
    $this->sanitizer = resolve(HtmlSanitizer::class);
});

describe(HtmlSanitizer::class, function (): void {
    it('removes scripts, event handlers and javascript links', function (string $dirty, string $forbidden): void {
        expect($this->sanitizer->clean($dirty))->not->toContain($forbidden);
    })->with([
        'script tag' => ['<p>Hi</p><script>alert(1)</script>', '<script'],
        'onerror' => ['<img src="https://example.com/a.png" onerror="alert(1)" alt="">', 'onerror'],
        'onclick' => ['<p onclick="alert(1)">Click</p>', 'onclick'],
        'javascript link' => ['<a href="javascript:alert(1)">x</a>', 'javascript:'],
        'data url link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', 'data:'],
        'style tag' => ['<style>body{display:none}</style><p>x</p>', '<style'],
        'iframe elsewhere' => ['<iframe src="https://evil.example.com/embed"></iframe>', 'evil.example.com'],
        'object' => ['<object data="https://evil.example.com/x.swf"></object>', '<object'],
        'css expression' => ['<p style="background-image:url(javascript:alert(1))">x</p>', 'javascript'],
    ]);

    it('keeps what the editor produces', function (string $html): void {
        expect($this->sanitizer->clean($html))->toBe($html);
    })->with([
        'formatting' => ['<p><strong>Bold</strong> and <em>italic</em> with <a href="https://shopper.dev" title="Shopper">a link</a></p>'],
        'heading and lists' => ['<h2>Title</h2><ul><li>One</li></ul><ol><li>Two</li></ol>'],
        'alignment and colour' => ['<p style="text-align:center;color:#e11d48;">Centered</p>'],
        'table' => ['<table><thead><tr><th>Name</th></tr></thead><tbody><tr><td>Value</td></tr></tbody></table>'],
        'aligned image' => ['<img src="https://example.com/a.png" alt="Banner" class="aligncenter" />'],
        'figure' => ['<figure class="image"><img src="https://example.com/a.png" alt="A" /><figcaption>Caption</figcaption></figure>'],
        'relative and mail links' => ['<p><a href="/about">About</a> <a href="mailto:team@shopper.dev">Mail</a></p>'],
        'youtube embed' => ['<iframe src="https://www.youtube.com/embed/abc123" width="560" height="315" title="Video"></iframe>'],
    ]);

    it('drops classes that are not alignment classes', function (): void {
        expect($this->sanitizer->clean('<p class="hidden alignleft">x</p>'))->toBe('<p>x</p>')
            ->and($this->sanitizer->clean('<img src="https://example.com/a.png" alt="" class="hidden alignleft" />'))
            ->toBe('<img src="https://example.com/a.png" alt="" class="alignleft" />');
    });

    it('leaves empty values alone', function (): void {
        expect($this->sanitizer->clean(null))->toBeNull()
            ->and($this->sanitizer->clean(''))->toBe('');
    });
})->group('cms', 'security');

describe(SavePostAction::class, function (): void {
    it('sanitizes the content and the excerpt on save', function (): void {
        $post = resolve(SavePostAction::class)->execute([
            'title' => 'Unsafe',
            'content' => '<p>Safe</p><script>alert(1)</script><img src="x" onerror="alert(1)" alt="">',
            'excerpt' => '<a href="javascript:alert(1)">Read</a>',
        ]);

        expect($post->refresh()->content)->not->toContain('<script', 'onerror')
            ->toContain('<p>Safe</p>')
            ->and($post->excerpt)->not->toContain('javascript:');
    });
})->group('cms', 'security');
