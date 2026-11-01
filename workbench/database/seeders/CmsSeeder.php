<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Shopper\Cms\Actions\SavePostAction;
use Shopper\Cms\Actions\SavePostCategoryAction;
use Shopper\Cms\Actions\SyncPostTermsAction;
use Shopper\Cms\Actions\TrashPostAction;
use Shopper\Cms\Enums\PostStatus;
use Shopper\Cms\Enums\PostVisibility;
use Shopper\Cms\Models\Post;
use Shopper\Cms\Models\PostCategory;

final class CmsSeeder extends Seeder
{
    /**
     * Post title => [category slugs, tag names]. Posts not listed fall back to "Uncategorized".
     *
     * @var array<string, array{0: list<string>, 1: list<string>}>
     */
    private array $terms = [
        'How to choose the right headphones' => [['buying-guides', 'audio'], ['headphones', 'Sony', 'Apple']],
        '10 kitchen tools every home cook needs' => [['buying-guides'], ['kitchen', 'gift ideas']],
        'Our summer collection is here' => [['news'], ['fashion', 'Nike', 'Adidas']],
        'Laptop buying guide 2026' => [['buying-guides'], ['laptops', 'Apple', 'Samsung']],
        'Black Friday preview' => [['news', 'deals'], ['deals', 'gift ideas']],
        'Sustainable packaging: our progress' => [['company'], ['sustainability']],
        'Ideas for the holiday gift guide' => [['buying-guides', 'deals'], ['gift ideas']],
        'VIP early access' => [['deals'], ['deals']],
    ];

    public function run(
        SavePostAction $savePost,
        TrashPostAction $trashPost,
        SavePostCategoryAction $saveCategory,
        SyncPostTermsAction $syncTerms,
    ): void {
        $userModel = config('auth.providers.users.model');
        $authorId = $userModel::query()->where('email', 'admin@laravelshopper.dev')->value('id');

        $save = fn (array $data) => $savePost->execute([...$data, 'author_id' => $authorId]);

        // WordPress's default content.
        $save([
            'title' => 'Hello world!',
            'content' => '<p>Welcome to Shopper. This is your first post. Edit or delete it, then start writing!</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subMonths(3),
        ]);

        $save([
            'type' => 'page',
            'title' => 'Sample Page',
            'content' => '<p>This is an example page. It is different from a blog post because it will stay in one place '
                .'and will show up in your site navigation. Most people start with an About page.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subMonths(3),
        ]);

        $save([
            'type' => 'page',
            'title' => 'Privacy Policy',
            'content' => '<h2>Who we are</h2><p>Suggested text: Our website address is: '.config('app.url').'.</p>',
            'status' => PostStatus::Draft,
        ]);

        $about = $save([
            'type' => 'page',
            'title' => 'About Us',
            'content' => '<p>We sell carefully selected electronics, fashion and home goods.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subMonths(2),
            'menu_order' => 1,
        ]);

        $save([
            'type' => 'page',
            'title' => 'Our Team',
            'content' => '<p>Meet the people behind the store.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subMonths(2),
            'parent_id' => $about->id,
            'template' => 'full-width',
        ]);

        $save([
            'type' => 'page',
            'title' => 'Contact',
            'content' => '<p>Write to us at store@laravelshopper.dev.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subMonths(2),
            'menu_order' => 2,
        ]);

        $posts = [
            ['How to choose the right headphones', PostStatus::Published, now()->subDays(40)],
            ['10 kitchen tools every home cook needs', PostStatus::Published, now()->subDays(25)],
            ['Our summer collection is here', PostStatus::Published, now()->subDays(10)],
            ['Laptop buying guide 2026', PostStatus::Published, now()->subDays(3)],
            ['Black Friday preview', PostStatus::Scheduled, now()->addWeeks(4)],
            ['Sustainable packaging: our progress', PostStatus::Pending, null],
            ['Ideas for the holiday gift guide', PostStatus::Draft, null],
            ['Internal: Q4 content calendar', PostStatus::Private, now()->subDays(5)],
        ];

        foreach ($posts as [$title, $status, $publishedAt]) {
            $save([
                'title' => $title,
                'excerpt' => fake()->sentence(20),
                'content' => '<p>'.implode('</p><p>', fake()->paragraphs(4)).'</p>',
                'status' => $status,
                'published_at' => $publishedAt,
            ]);
        }

        $save([
            'title' => 'VIP early access',
            'content' => '<p>Thanks for being a loyal customer. Here is your early access code.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(7),
            'visibility' => PostVisibility::Password,
            'password' => 'vip2026',
        ]);

        $this->seedTerms($saveCategory, $syncTerms);

        $trashPost->execute($save([
            'title' => 'Old announcement',
            'content' => '<p>This post was moved to the trash.</p>',
            'status' => PostStatus::Published,
            'published_at' => now()->subYear(),
        ]));
    }

    private function seedTerms(SavePostCategoryAction $saveCategory, SyncPostTermsAction $syncTerms): void
    {
        $guides = $saveCategory->execute(['name' => 'Buying Guides', 'description' => 'Help choosing the right product.']);
        $saveCategory->execute(['name' => 'Audio', 'parent_id' => $guides->id, 'description' => 'Headphones and speakers.']);
        $saveCategory->execute(['name' => 'News', 'description' => 'What is new in the store.']);
        $saveCategory->execute(['name' => 'Deals', 'description' => 'Promotions and discounts.']);
        $saveCategory->execute(['name' => 'Company', 'description' => 'About us and how we work.']);

        Post::query()->ofType('post')->get()->each(function (Post $post) use ($syncTerms): void {
            [$categorySlugs, $tags] = $this->terms[$post->title] ?? [[], []];

            $syncTerms->execute(
                $post,
                PostCategory::query()->whereIn('slug', $categorySlugs)->pluck('id')->all(),
                $tags,
            );
        });
    }
}
