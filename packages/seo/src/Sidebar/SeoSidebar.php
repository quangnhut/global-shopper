<?php

declare(strict_types=1);

namespace Shopper\Seo\Sidebar;

use Shopper\Sidebar\AbstractAdminSidebar;
use Shopper\Sidebar\Contracts\Builder\Group;
use Shopper\Sidebar\Contracts\Builder\Item;
use Shopper\Sidebar\Contracts\Builder\Menu;

final class SeoSidebar extends AbstractAdminSidebar
{
    public function extendWith(Menu $menu): Menu
    {
        $menu->group(__('shopper-seo::settings.menu'), function (Group $group): void {
            $group->weight(5);
            $group->setAuthorized();
            $group->collapsible();

            $group->item(__('shopper-seo::settings.title'), function (Item $item): void {
                $item->weight(1);
                $item->setAuthorized($this->user->hasPermissionTo('manage_seo'));
                $item->useSpa();
                $item->route('shopper.seo.settings');
                $item->setIcon('phosphor-magnifying-glass');
            });

            $group->item(__('shopper-seo::panel.content.menu'), function (Item $item): void {
                $item->weight(2);
                $item->setAuthorized($this->user->hasPermissionTo('manage_seo'));
                $item->useSpa();
                $item->route('shopper.seo.content');
                $item->setIcon('phosphor-list-magnifying-glass');
            });

            // Redirects are managed by the CMS package, when it is installed.
            if (class_exists(\Shopper\Cms\CmsServiceProvider::class)) {
                $group->item(__('shopper-cms::permalinks.redirects.title'), function (Item $item): void {
                    $item->weight(3);
                    $item->setAuthorized($this->user->hasPermissionTo('manage_permalinks'));
                    $item->useSpa();
                    $item->route('shopper.cms.settings.redirects');
                    $item->setIcon('phosphor-arrow-bend-up-right');
                });
            }
        });

        return $menu;
    }
}
