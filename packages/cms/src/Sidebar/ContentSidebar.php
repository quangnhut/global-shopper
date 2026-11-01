<?php

declare(strict_types=1);

namespace Shopper\Cms\Sidebar;

use Shopper\Sidebar\AbstractAdminSidebar;
use Shopper\Sidebar\Contracts\Builder\Group;
use Shopper\Sidebar\Contracts\Builder\Item;
use Shopper\Sidebar\Contracts\Builder\Menu;

final class ContentSidebar extends AbstractAdminSidebar
{
    public function extendWith(Menu $menu): Menu
    {
        $menu->group(__('shopper-cms::taxonomies.content'), function (Group $group): void {
            $group->weight(4);
            $group->setAuthorized();
            $group->collapsible();

            $group->item(__('shopper-cms::post-types.post.plural'), function (Item $item): void {
                $item->weight(1);
                $item->setAuthorized($this->user->hasPermissionTo('browse_posts'));
                $item->useSpa();
                $item->route('shopper.cms.posts.index');
                $item->setIcon('phosphor-newspaper');

                $item->item(__('shopper-cms::taxonomies.categories.menu'), function (Item $item): void {
                    $item->weight(1);
                    $item->setAuthorized($this->user->hasPermissionTo('browse_post_categories'));
                    $item->useSpa();
                    $item->route('shopper.cms.post-categories.index');
                });

                $item->item(__('shopper-cms::taxonomies.tags.menu'), function (Item $item): void {
                    $item->weight(2);
                    $item->setAuthorized($this->user->hasPermissionTo('browse_post_tags'));
                    $item->useSpa();
                    $item->route('shopper.cms.post-tags.index');
                });
            });

            $group->item(__('shopper-cms::post-types.page.plural'), function (Item $item): void {
                $item->weight(2);
                $item->setAuthorized($this->user->hasPermissionTo('browse_pages'));
                $item->useSpa();
                $item->route('shopper.cms.pages.index');
                $item->setIcon('phosphor-files');
            });
        });

        return $menu;
    }
}
