<?php

declare(strict_types=1);

namespace Shopper\Storefront\Sidebar;

use Shopper\Sidebar\AbstractAdminSidebar;
use Shopper\Sidebar\Contracts\Builder\Group;
use Shopper\Sidebar\Contracts\Builder\Item;
use Shopper\Sidebar\Contracts\Builder\Menu;

final class StorefrontSidebar extends AbstractAdminSidebar
{
    public function extendWith(Menu $menu): Menu
    {
        $menu->group(__('shopper-storefront::admin.menu.group'), function (Group $group): void {
            $group->weight(4);
            $group->setAuthorized();
            $group->collapsible();

            $group->item(__('shopper-storefront::admin.menu.homepage'), function (Item $item): void {
                $item->weight(0);
                $item->setAuthorized($this->user->hasPermissionTo('manage_homepage'));
                $item->useSpa();
                $item->route('shopper.storefront.sections.index');
                $item->setIcon('phosphor-house-line');

                $item->item(__('shopper-storefront::admin.menu.sections'), function (Item $item): void {
                    $item->weight(1);
                    $item->setAuthorized($this->user->hasPermissionTo('manage_homepage'));
                    $item->useSpa();
                    $item->route('shopper.storefront.sections.index');
                });

                $item->item(__('shopper-storefront::admin.menu.settings'), function (Item $item): void {
                    $item->weight(2);
                    $item->setAuthorized($this->user->hasPermissionTo('manage_homepage'));
                    $item->useSpa();
                    $item->route('shopper.storefront.settings');
                });
            });
        });

        return $menu;
    }
}
