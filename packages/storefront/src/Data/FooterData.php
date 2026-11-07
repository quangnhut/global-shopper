<?php

declare(strict_types=1);

namespace Shopper\Storefront\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class FooterData implements Arrayable
{
    /**
     * @param  array<string, string|null>  $company  name, store_name, address, business_registration, registration_issued, phone, email, working_hours
     * @param  array<int, array{title: string, links: array<int, LinkData>}>  $columns
     * @param  array<int, LinkData>  $badges
     * @param  array<int, LinkData>  $shippingPartners
     * @param  array<int, LinkData>  $paymentPartners
     * @param  array<int, LinkData>  $socials
     */
    public function __construct(
        public array $company,
        public array $columns,
        public array $badges,
        public array $shippingPartners,
        public array $paymentPartners,
        public array $socials,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $links = fn (array $links): array => array_map(fn (LinkData $link): array => $link->toArray(), $links);

        return [
            'company' => $this->company,
            'columns' => array_map(fn (array $column): array => ['title' => $column['title'], 'links' => $links($column['links'])], $this->columns),
            'badges' => $links($this->badges),
            'partners' => ['shipping' => $links($this->shippingPartners), 'payment' => $links($this->paymentPartners)],
            'socials' => $links($this->socials),
        ];
    }
}
