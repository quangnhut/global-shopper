<?php

declare(strict_types=1);

return [

    'title' => 'Permalinks',
    'description' => 'Choose how the URLs of posts, pages, categories and tags look.',
    'common' => 'Common settings',
    'structures' => [
        'plain' => 'Plain',
        'day_and_name' => 'Day and name',
        'month_and_name' => 'Month and name',
        'numeric' => 'Numeric',
        'post_name' => 'Post name',
        'custom' => 'Custom structure',
    ],
    'custom_help' => 'Available tags: :tags',
    'optional' => 'Optional',
    'optional_help' => 'Change the prefix of category and tag URLs. Leave empty for the defaults "category" and "tag".',
    'category_base' => 'Category base',
    'tag_base' => 'Tag base',
    'reading' => 'Your homepage displays',
    'front_page' => 'Homepage',
    'posts_page' => 'Posts page',
    'latest_posts' => 'Your latest posts',
    'saved' => 'Permalink settings saved.',
    'example' => 'Example: :url',

    'errors' => [
        'unknown_tag' => 'The structure contains an unknown tag.',
        'needs_identifier' => 'The structure must contain %postname% or %post_id%.',
        'reserved' => 'The structure cannot start with a path used by the application.',
        'base_reserved' => 'This prefix is already used by the application.',
    ],

    'redirects' => [
        'description' => 'Send visitors and search engines from old URLs to new ones.',
        'title' => 'Redirects',
        'single' => 'redirect',
        'create' => 'Add redirect',
        'from' => 'From',
        'to' => 'To',
        'status' => 'Type',
        'hits' => 'Hits',
        'last_hit' => 'Last hit',
        'source' => 'Source',
        'auto' => 'Automatic',
        'manual' => 'Manual',
        'gone' => 'Gone (410)',
        'loop' => 'This redirect would create a loop.',
        'same' => 'A path cannot redirect to itself.',
    ],

];
