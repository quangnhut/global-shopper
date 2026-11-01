<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Swap the CMS models with your own implementation. Your model must
    | extend (or behave like) the default one and implement its contract.
    |
    */

    'models' => [
        'post' => Shopper\Cms\Models\Post::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Post Types
    |--------------------------------------------------------------------------
    |
    | Like WordPress, posts and pages share one table and are told apart by
    | their type. Hierarchical types (pages) can have a parent and an order.
    | Labels are translation keys.
    |
    */

    'post_types' => [
        'post' => [
            'label' => 'shopper-cms::post-types.post.plural',
            'singular_label' => 'shopper-cms::post-types.post.singular',
            'hierarchical' => false,
            'supports' => ['title', 'editor', 'excerpt', 'author', 'featured_image', 'categories', 'tags', 'revisions'],
        ],
        'page' => [
            'label' => 'shopper-cms::post-types.page.plural',
            'singular_label' => 'shopper-cms::post-types.page.singular',
            'hierarchical' => true,
            'supports' => ['title', 'editor', 'author', 'featured_image', 'page_attributes', 'revisions'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Templates
    |--------------------------------------------------------------------------
    |
    | Templates an editor can pick for a page. The key is stored on the page,
    | the value is a translation key for its label.
    |
    */

    'templates' => [
        'default' => 'shopper-cms::post-types.templates.default',
        'full-width' => 'shopper-cms::post-types.templates.full_width',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trash
    |--------------------------------------------------------------------------
    |
    | Number of days trashed posts and pages are kept before they are deleted
    | permanently (WordPress's EMPTY_TRASH_DAYS). Set to 0 to never empty it.
    |
    */

    'trash_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Preview Links
    |--------------------------------------------------------------------------
    |
    | Minutes a signed preview link of unpublished content stays valid, and the
    | URL prefix of those links (outside the admin, so reviewers need no account).
    |
    */

    'preview_minutes' => 30,

    'preview_path' => 'cms-preview',

    /*
    |--------------------------------------------------------------------------
    | Public URLs
    |--------------------------------------------------------------------------
    |
    | `frontend_url` is where posts and pages are shown (your storefront),
    | defaults to APP_URL. The permalink structure itself is set in the admin
    | (Settings → Permalinks). `reserved` lists extra first path segments that
    | belong to your application and must never be resolved as content.
    |
    */

    'frontend_url' => env('SHOPPER_CMS_FRONTEND_URL'),

    'permalinks' => [
        'reserved' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Content API and optional public pages
    |--------------------------------------------------------------------------
    |
    | The JSON API (`{prefix}/cms/resolve`, `{prefix}/cms/posts`) lets any
    | storefront render CMS content; responses are cached `cache_ttl` seconds
    | (0 disables) and flushed when content changes. `routes.enabled` adds a
    | fallback route that renders posts, pages and archives with Blade views
    | (publish them with `vendor:publish --tag=shopper-cms-views`).
    |
    */

    'api' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => ['api', 'throttle:120,1'],
        'cache_ttl' => 3600,
        'per_page' => 10,
    ],

    'routes' => [
        'enabled' => (bool) env('SHOPPER_CMS_ROUTES', false),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Revisions
    |--------------------------------------------------------------------------
    |
    | Every save that changes the title, content or excerpt keeps a revision.
    | `limit`: null keeps all of them, 0 keeps none, N keeps the latest N
    | (WordPress's WP_POST_REVISIONS). The editor autosaves every
    | `autosave_interval` seconds while there are unsaved changes.
    |
    */

    'revisions' => [
        'enabled' => true,
        'limit' => null,
        'autosave_interval' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Editor (TinyMCE)
    |--------------------------------------------------------------------------
    |
    | TinyMCE is self-hosted under the GPL (`license_key` 'gpl'): its files are
    | published to `base_path` with `vendor:publish --tag=shopper-cms-assets`.
    | Set a commercial key here if you have one. `options` are passed to
    | tinymce.init() as is; the toolbar mirrors WordPress's classic editor.
    |
    */

    'editor' => [
        'license_key' => env('SHOPPER_CMS_TINYMCE_LICENSE_KEY', 'gpl'),
        'base_path' => 'vendor/shopper-cms/tinymce',
        'options' => [
            'height' => 500,
            'menubar' => false,
            'branding' => false,
            'promotion' => false,
            'convert_urls' => false,
            'relative_urls' => false,
            'image_caption' => true,
            'image_advtab' => true,
            'plugins' => 'advlist autolink lists link image charmap anchor searchreplace visualblocks code fullscreen media table wordcount',
            'toolbar' => 'blocks | bold italic | bullist numlist blockquote | alignleft aligncenter alignright | link unlink | addmedia image media | fullscreen | code '
                .'| strikethrough hr forecolor | pastetext removeformat charmap | outdent indent | table | undo redo',
            'toolbar_mode' => 'sliding',
            'block_formats' => 'Paragraph=p; Heading 2=h2; Heading 3=h3; Heading 4=h4; Preformatted=pre',
            // WordPress's alignment classes, used by "Add media" (storefront themes should style them too).
            'content_style' => 'img{max-width:100%;height:auto}'
                .'.alignleft{float:left;margin:0 1em 1em 0}'
                .'.aligncenter{display:block;margin:0 auto 1em}'
                .'.alignright{float:right;margin:0 0 1em 1em}',
        ],
    ],

];
