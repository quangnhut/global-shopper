<?php

declare(strict_types=1);

return [

    'menu' => 'SEO',
    'title' => 'SEO settings',
    'saved' => 'SEO settings saved.',

    'tabs' => [
        'general' => 'General',
        'titles' => 'Titles & meta',
        'social' => 'Social',
        'schema' => 'Schema',
        'indexing' => 'Indexing',
        'tracking' => 'Tracking & verification',
    ],

    'fields' => [
        'title_template' => 'SEO title',
        'description_template' => 'Meta description',
        'meta_description' => 'Meta description',
        'preview' => 'Search result preview',
        'noindex' => 'Hide from search engines (noindex)',
        'noindex_help' => 'Entries can still override this one by one.',
        'sitemap' => 'Include in the XML sitemap',
        'schema_type' => 'Schema type',
        'default_image' => 'Default social image',
        'variables' => 'Variables: :variables',
    ],

    'general' => [
        'identity' => 'Site identity',
        'site_name' => 'Website name',
        'tagline' => 'Tagline',
        'separator' => 'Title separator',
        'site_icon' => 'Website avatar (site icon)',
        'site_icon_help' => 'A square image, at least 512 × 512 pixels. Used for the favicon, the Apple touch icon, the web app manifest and as the default logo.',
        'home' => 'Homepage',
        'visibility' => 'Search engine visibility',
        'discourage' => 'Discourage search engines from indexing this site',
        'discourage_help' => 'Adds noindex, nofollow to every page and blocks crawlers in robots.txt. Use it on staging sites only.',
        'discourage_banner' => 'Search engines are currently asked not to index this site.',
    ],

    'social' => [
        'default_image' => 'Default Open Graph image',
        'default_image_help' => 'Shown when a shared page has no image of its own. 1200 × 630 pixels works best.',
        'facebook_url' => 'Facebook page URL',
        'facebook_app_id' => 'Facebook app ID',
        'twitter_handle' => 'X (Twitter) username',
        'twitter_card' => 'Default card type',
        'profiles' => 'Other profiles',
        'profiles_help' => 'Instagram, LinkedIn, YouTube, TikTok… listed in the schema as `sameAs`.',
    ],

    'schema' => [
        'publisher' => 'Who runs this site?',
        'entity' => 'This site represents',
        'organization' => 'An organization',
        'person' => 'A person',
        'name' => 'Name',
        'logo' => 'Logo',
        'local_business' => 'Local business type',
        'local_business_help' => 'Only if customers can visit a physical store.',
        'opening_hours' => 'Opening hours',
        'website' => 'Website',
        'search_url' => 'Site search URL',
        'search_url_help' => 'Enables the search box in Google results. Must contain {search_term_string}.',
        'article_type' => 'Default type of blog posts',
    ],

    'indexing' => [
        'archives' => 'Hide these pages from search engines',
        'date_archives' => 'Date archives',
        'author_archives' => 'Author archives',
        'search' => 'Search results',
        'paginated' => 'Page 2 and further of archives',
        'empty_terms' => 'Categories and tags without content',
        'paths' => 'Paths to hide',
        'paths_help' => 'URL patterns that get noindex, for example /cart* or /account/*. Use * as a wildcard.',
        'pattern' => 'Path pattern',
        'nofollow' => 'Also nofollow',
    ],

    'tracking' => [
        'analytics' => 'Analytics',
        'gtm' => 'Google Tag Manager container ID',
        'gtm_help' => 'Added to the head and right after the opening body tag of every page.',
        'ga4' => 'Google Analytics 4 measurement ID',
        'ga4_help' => 'Leave empty when Google Analytics is set up inside Tag Manager.',
        'verification' => 'Site verification',
        'verification_help' => 'Paste only the content value of the meta tag, not the whole tag.',
        'custom_code' => 'Custom code',
        'custom_code_warning' => 'This code is added to every page as is and can run scripts. Only paste code from sources you trust.',
        'custom_code_locked' => 'You need the "Manage SEO scripts and custom code" permission to change this code.',
        'custom_head' => 'Before </head>',
        'custom_body_open' => 'After <body>',
        'custom_body_close' => 'Before </body>',
    ],

    'robots' => [
        'help' => 'A "Sitemap:" line with your sitemap URL is added automatically.',
    ],

    'preview' => [
        'sample_title' => 'Your post or product title',
        'sample_excerpt' => 'A short summary of the content, as it would appear under the title in search results.',
        'sample_category' => 'News',
    ],

];
