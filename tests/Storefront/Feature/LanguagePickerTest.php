<?php

declare(strict_types=1);

use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Storefront\Support\StorefrontCache;

uses(Tests\Storefront\PublicRoutesTestCase::class);

require_once __DIR__.'/../Support/helpers.php';

describe('language picker (Google Translate)', function (): void {
    it('shows Vietnamese, English and Chinese with their flags, never translated by Google', function (): void {
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-haspopup="listbox"', false)
            ->assertSeeInOrder(['Tiếng Việt', 'English', '中文 (简体)'])
            ->assertSee('lang="zh-CN"', false)
            ->assertSee('class="notranslate relative"', false)
            ->assertSee('#DA251D', false) // Vietnam
            ->assertSee('#012169', false) // United Kingdom
            ->assertSee('#EE1C25', false) // China
            ->assertSee('id="google_translate_element"', false)
            ->assertSee("Alpine.store('sfTranslate'", false);
    });

    it('loads the Google script only on demand', function (): void {
        $html = $this->get('/')->assertOk()->getContent();

        expect($html)->not->toMatch('/<script[^>]+src="[^"]*translate\.google\.com/')
            ->and($html)->toContain('element.js?cb=googleTranslateElementInit');
    });

    it('keeps prices out of the translation', function (): void {
        $product = storefrontProduct(['name' => 'MacBook Pro 14"'], amount: 49_975_000);

        $this->get(route('shopper.storefront.product', $product->slug))
            ->assertOk()
            ->assertSee('<span translate="no">'.shopper_money_format(49_975_000, shopper_currency()).'</span>', false);
    });

    it('is hidden when an admin turns it off', function (): void {
        resolve(StorefrontSettings::class)->save(['translate_enabled' => false]);
        StorefrontCache::flush();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('aria-haspopup="listbox"', false)
            ->assertDontSee('google_translate_element', false)
            ->assertDontSee("Alpine.store('sfTranslate'", false);
    });

    it('is hidden when the config turns it off', function (): void {
        config()->set('shopper.storefront.translate.enabled', false);
        StorefrontCache::flush();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('google_translate_element', false);
    });

    it('tells headless storefronts about it', function (): void {
        $this->getJson(route('shopper.storefront.api.home'))
            ->assertOk()
            ->assertJsonPath('header.translate.enabled', true)
            ->assertJsonPath('header.translate.page_language', 'vi')
            ->assertJsonPath('header.translate.languages.2', ['code' => 'zh-CN', 'label' => '中文 (简体)', 'short' => '中文', 'flag' => 'cn']);

        config()->set('shopper.storefront.translate.enabled', false);
        StorefrontCache::flush();

        $this->getJson(route('shopper.storefront.api.home'))
            ->assertJsonPath('header.translate.enabled', false)
            ->assertJsonPath('header.translate.script', null)
            ->assertJsonPath('header.translate.languages', []);
    });
});
