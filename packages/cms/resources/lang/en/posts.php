<?php

declare(strict_types=1);

return [

    'add_new' => 'Add new',
    'add_new_post' => 'Add new post',
    'add_new_page' => 'Add new page',
    'edit_post' => 'Edit post',
    'edit_page' => 'Edit page',
    'no_title' => '(no title)',

    'tabs' => [
        'all' => 'All',
        'mine' => 'Mine',
        'trash' => 'Trash',
    ],

    'columns' => [
        'title' => 'Title',
        'author' => 'Author',
        'categories' => 'Categories',
        'tags' => 'Tags',
        'date' => 'Date',
        'parent' => 'Parent',
    ],

    'date_labels' => [
        'published' => 'Published',
        'scheduled' => 'Scheduled',
        'modified' => 'Last modified',
    ],

    'filters' => [
        'month' => 'Date',
        'all_dates' => 'All dates',
        'category' => 'Category',
        'author' => 'Author',
    ],

    'actions' => [
        'quick_edit' => 'Quick edit',
        'bulk_edit' => 'Edit',
        'trash' => 'Move to trash',
        'restore' => 'Restore',
        'delete_permanently' => 'Delete permanently',
        'empty_trash' => 'Empty trash',
        'empty_trash_confirm' => 'Every item in the trash will be deleted permanently. This cannot be undone.',
        'save_draft' => 'Save draft',
        'save_as_pending' => 'Save as pending',
        'publish' => 'Publish',
        'schedule' => 'Schedule',
        'update' => 'Update',
        'submit_for_review' => 'Submit for review',
        'preview' => 'Preview',
        'no_change' => '— No change —',
    ],

    'notifications' => [
        'trashed' => ':count item moved to the trash.|:count items moved to the trash.',
        'restored' => ':count item restored from the trash.|:count items restored from the trash.',
        'deleted' => ':count item permanently deleted.|:count items permanently deleted.',
        'updated' => ':count item updated.|:count items updated.',
        'saved_draft' => 'Draft saved.',
        'published' => 'Published.',
        'scheduled' => 'Scheduled for :date.',
        'updated_single' => 'Updated.',
        'submitted' => 'Submitted for review.',
        'skipped' => ':count item was skipped because you cannot change it.|:count items were skipped because you cannot change them.',
    ],

    'form' => [
        'title' => 'Title',
        'slug' => 'Slug',
        'slug_help' => 'The last part of the URL. Leave empty to build it from the title.',
        'content' => 'Content',
        'excerpt' => 'Excerpt',
        'excerpt_help' => 'An optional hand-crafted summary of your content.',
        'publish' => 'Publish',
        'status' => 'Status',
        'visibility' => 'Visibility',
        'password' => 'Password',
        'password_help' => 'Leave empty to keep the current password.',
        'published_at' => 'Publish date',
        'published_at_help' => 'Leave empty to publish immediately. A future date schedules the publication.',
        'last_edited' => 'Last edited by :name on :date.',
        'trashed_notice' => 'This item is in the trash. Restore it to edit it.',
        'categories' => 'Categories',
        'primary_category' => 'Primary category',
        'primary_category_help' => 'Used in URLs and breadcrumbs when the post has several categories.',
        'tags' => 'Tags',
        'tags_help' => 'Separate tags with commas or the Enter key.',
        'page_attributes' => 'Page attributes',
        'parent' => 'Parent page',
        'no_parent' => '(no parent)',
        'template' => 'Template',
        'menu_order' => 'Order',
        'featured_image' => 'Featured image',
        'featured_image_help' => 'Choose an image from the uploads library or upload a new one.',
        'upload_image' => 'Upload image',
        'author' => 'Author',
        'unsaved_changes' => 'You have unsaved changes. Leave this page anyway?',
    ],

    'dashboard' => [
        'at_a_glance' => 'At a glance',
        'recent_drafts' => 'Your recent drafts',
        'no_drafts' => 'There are no drafts yet.',
        'published_count' => ':count published in :type|:count published in :type',
        'drafts_count' => ':count draft|:count drafts',
        'pending_count' => ':count pending|:count pending',
    ],

    'media' => [
        'add_media' => 'Add media',
        'insert' => 'Insert into post',
        'file' => 'File',
        'alt' => 'Alternative text',
        'alt_help' => 'Describe the image for screen readers and search engines.',
        'align' => 'Alignment',
        'align_none' => 'None',
        'align_left' => 'Left',
        'align_center' => 'Center',
        'align_right' => 'Right',
    ],

    'preview' => [
        'title' => 'Preview',
        'notice' => 'Preview of an unpublished version. This link expires :time.',
    ],

];
