<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap the home page models with your own implementation. Your model must
    | extend (or behave like) the default one and implement its contract.
    |
    */

    'models' => [
        'home_section' => Shopper\Storefront\Models\HomeSection::class,
        'home_section_item' => Shopper\Storefront\Models\HomeSectionItem::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storefront URL
    |--------------------------------------------------------------------------
    |
    | Where the customer-facing site lives, used by the admin "View home page"
    | button. Defaults to APP_URL.
    |
    */

    'url' => env('SHOPPER_STOREFRONT_URL'),

    /*
    |--------------------------------------------------------------------------
    | Locale
    |--------------------------------------------------------------------------
    |
    | Language of the storefront pages and of the API (prices, labels). The API
    | also accepts `?locale=` with one of `locales`.
    |
    */

    'locale' => env('SHOPPER_STOREFRONT_LOCALE', 'vi'),

    'locales' => ['vi', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Seconds the home page data (header, sections, footer) stays cached. It is
    | also dropped whenever what it shows changes, and never outlives the next
    | start or end of a scheduled section.
    |
    */

    'cache_ttl' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Storefront paths
    |--------------------------------------------------------------------------
    |
    | Paths of the Blade storefront's own pages. Products, categories and brands
    | use `shopper.seo.paths`; posts and pages use the CMS permalinks.
    |
    */

    'paths' => [
        'search' => '/search',
        'legal' => '/policies/{slug}',
        'login' => '/login',
        'register' => '/register',
        'password_request' => '/forgot-password',
        'cart' => '/cart',
        'checkout' => '/checkout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blade storefront
    |--------------------------------------------------------------------------
    |
    | The package ships a Blade theme (home page, search, customer sign-in).
    | Its routes are off by default so the application's own routes win;
    | turn them on with SHOPPER_STOREFRONT_ROUTES=true.
    |
    */

    'routes' => [
        'enabled' => (bool) env('SHOPPER_STOREFRONT_ROUTES', false),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON API
    |--------------------------------------------------------------------------
    |
    | `GET {prefix}/storefront/home` and `GET {prefix}/storefront/search` for
    | headless storefronts.
    |
    */

    'api' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => ['api', 'throttle:120,1'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    |
    | Payment methods offered at checkout: kind => `payment_methods.slug`. Only
    | enabled methods are shown, and OnePay only when its driver is configured.
    | There is no shipping: customers collect their order at the office.
    |
    */

    'checkout' => [
        'payment_methods' => [
            'office' => 'office',
            'bank_transfer' => 'bank-transfer',
            'onepay' => 'onepay',
        ],
        'note_max_length' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram order alerts
    |--------------------------------------------------------------------------
    |
    | A JSON file (not in git) with `enabled`, `bot_token`, `chat_ids`,
    | `message_thread_id` and `admin_order_url`. See workbench/telegram.example.json.
    | Without the file no message is sent and orders are still placed.
    |
    */

    'telegram' => [
        'config_path' => env('SHOPPER_TELEGRAM_CONFIG', base_path('telegram.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Page translation (Google Translate)
    |--------------------------------------------------------------------------
    |
    | Language picker in the header that translates the page in the browser
    | with Google's website translator. The script is loaded only once a
    | customer picks another language, and that sends the page text to Google.
    | `languages` keys are Google language codes; `flag` is a flag component code.
    | Admins can also hide the picker under Storefront → Header & footer.
    |
    */

    'translate' => [
        'enabled' => (bool) env('SHOPPER_STOREFRONT_TRANSLATE', true),
        'page_language' => 'vi',
        'script' => 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit',
        'languages' => [
            'vi' => ['label' => 'Tiếng Việt', 'short' => 'VI', 'flag' => 'vn'],
            'en' => ['label' => 'English', 'short' => 'EN', 'flag' => 'gb'],
            'zh-CN' => ['label' => '中文 (简体)', 'short' => '中文', 'flag' => 'cn'],
        ],
    ],

];
