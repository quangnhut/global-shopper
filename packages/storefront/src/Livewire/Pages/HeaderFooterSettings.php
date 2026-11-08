<?php

declare(strict_types=1);

namespace Shopper\Storefront\Livewire\Pages;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Shopper\Cms\Models\Post;
use Shopper\Components\Form\UploadPicker;
use Shopper\Core\Models\Carrier;
use Shopper\Core\Models\Category;
use Shopper\Core\Models\Legal;
use Shopper\Core\Models\PaymentMethod;
use Shopper\Livewire\Pages\AbstractPageComponent;
use Shopper\Storefront\Settings\StorefrontSettings;
use Shopper\Traits\HandlesAuthorizationExceptions;

/**
 * Homepage → Header & footer: hotline, logo, category menu, company details, footer links, badges and partners.
 * Name, address, email and phone come from Settings → General and are only shown here.
 *
 * @property-read Schema $form
 */
class HeaderFooterSettings extends AbstractPageComponent implements HasActions, HasSchemas
{
    use HandlesAuthorizationExceptions;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public const array LINK_TYPES = ['page', 'legal', 'url'];

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(StorefrontSettings $settings): void
    {
        $this->authorize('manage_homepage');

        $this->form->fill(array_intersect_key($settings->all(), StorefrontSettings::DEFAULTS));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('storefront')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tabs\Tab::make(__('shopper-storefront::admin.settings.header'))
                            ->icon('untitledui-layout-top')
                            ->schema($this->headerFields()),
                        Tabs\Tab::make(__('shopper-storefront::admin.settings.company'))
                            ->icon('untitledui-building-07')
                            ->schema($this->companyFields()),
                        Tabs\Tab::make(__('shopper-storefront::admin.settings.footer_links'))
                            ->icon('untitledui-link-03')
                            ->schema($this->footerLinkFields()),
                        Tabs\Tab::make(__('shopper-storefront::admin.settings.badges_partners'))
                            ->icon('untitledui-check-verified-02')
                            ->schema($this->badgeAndPartnerFields()),
                        Tabs\Tab::make(__('shopper-storefront::admin.settings.checkout'))
                            ->icon('untitledui-credit-card-02')
                            ->schema($this->checkoutFields()),
                    ]),
            ]);
    }

    public function save(StorefrontSettings $settings): void
    {
        $this->authorize('manage_homepage');

        $data = $this->form->getState();

        $data['footer_columns'] = collect($data['footer_columns'] ?? [])
            ->map(fn (array $column): array => [
                'title' => $column['title'],
                'links' => collect($column['links'] ?? [])
                    ->map(fn (array $link): array => [
                        'type' => $link['type'],
                        'id' => in_array($link['type'], ['page', 'legal'], true) ? (int) ($link['id'] ?? 0) ?: null : null,
                        'url' => $link['type'] === 'url' ? ($link['url'] ?? null) : null,
                        'label' => filled($link['label'] ?? null) ? $link['label'] : null,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
        $data['extra_partners'] = array_values($data['extra_partners'] ?? []);
        $data['translate_enabled'] = (bool) ($data['translate_enabled'] ?? true);

        foreach (['menu_category_ids', 'carrier_ids', 'payment_method_ids'] as $key) {
            $data[$key] = array_values(array_map('intval', $data[$key] ?? []));
        }

        $settings->save(array_intersect_key($data, StorefrontSettings::DEFAULTS));

        Notification::make()
            ->title(__('shopper-storefront::admin.settings.saved'))
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    public function missingLegalDetails(): array
    {
        return resolve(StorefrontSettings::class)->missingLegalDetails();
    }

    public function render(): View
    {
        return view('shopper-storefront::livewire.pages.header-footer-settings')
            ->title(__('shopper-storefront::admin.settings.title'));
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function headerFields(): array
    {
        return [
            Grid::make()->schema([
                TextInput::make('hotline')
                    ->label(__('shopper-storefront::admin.settings.hotline'))
                    ->helperText(__('shopper-storefront::admin.settings.hotline_help'))
                    ->tel()
                    ->maxLength(30),
                TextInput::make('announcement')
                    ->label(__('shopper-storefront::admin.settings.announcement'))
                    ->helperText(__('shopper-storefront::admin.settings.announcement_help'))
                    ->maxLength(160),
                UploadPicker::make('header_logo')
                    ->label(__('shopper-storefront::admin.settings.header_logo'))
                    ->helperText(__('shopper-storefront::admin.settings.header_logo_help')),
                Select::make('menu_category_ids')
                    ->label(__('shopper-storefront::admin.settings.menu_categories'))
                    ->helperText(__('shopper-storefront::admin.settings.menu_categories_help'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => Category::query()->enabled()->whereNull('parent_id')->orderBy('position')->pluck('name', 'id')->all()),
                Toggle::make('translate_enabled')
                    ->label(__('shopper-storefront::admin.settings.translate_enabled'))
                    ->helperText(__('shopper-storefront::admin.settings.translate_enabled_help'))
                    ->columnSpanFull(),
            ]),
        ];
    }

    /**
     * Bank account of the "online pay" method (VietQR transfer) and pickup instructions.
     *
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function checkoutFields(): array
    {
        return [
            Section::make(__('shopper-storefront::admin.settings.bank'))
                ->description(__('shopper-storefront::admin.settings.bank_help'))
                ->compact()
                ->schema([
                    Grid::make()->schema([
                        TextInput::make('bank_name')
                            ->label(__('shopper-storefront::admin.settings.bank_name'))
                            ->placeholder('Vietcombank')
                            ->maxLength(100),
                        TextInput::make('bank_bin')
                            ->label(__('shopper-storefront::admin.settings.bank_bin'))
                            ->helperText(__('shopper-storefront::admin.settings.bank_bin_help'))
                            ->placeholder('970436')
                            ->regex('/^\d{6}$/')
                            ->maxLength(6),
                        TextInput::make('bank_account_number')
                            ->label(__('shopper-storefront::admin.settings.bank_account_number'))
                            ->regex('/^[\d\s]{4,30}$/')
                            ->maxLength(30),
                        TextInput::make('bank_account_name')
                            ->label(__('shopper-storefront::admin.settings.bank_account_name'))
                            ->helperText(__('shopper-storefront::admin.settings.bank_account_name_help'))
                            ->maxLength(100),
                    ]),
                ]),
            Textarea::make('pickup_note')
                ->label(__('shopper-storefront::admin.settings.pickup_note'))
                ->helperText(__('shopper-storefront::admin.settings.pickup_note_help'))
                ->rows(3)
                ->maxLength(500),
        ];
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function companyFields(): array
    {
        return [
            Section::make(__('shopper-storefront::admin.settings.from_general'))
                ->description(__('shopper-storefront::admin.settings.from_general_help'))
                ->compact()
                ->schema([
                    TextInput::make('general_preview')
                        ->hiddenLabel()
                        ->disabled()
                        ->dehydrated(false)
                        ->formatStateUsing(fn (): string => collect([
                            resolve(StorefrontSettings::class)->companyName(),
                            resolve(StorefrontSettings::class)->address(),
                            resolve(StorefrontSettings::class)->get('store.email'),
                        ])->filter()->implode(' · ') ?: '—'),
                ]),
            Grid::make()->schema([
                TextInput::make('business_registration')
                    ->label(__('shopper-storefront::admin.settings.business_registration'))
                    ->helperText(__('shopper-storefront::admin.settings.business_registration_help'))
                    ->maxLength(100),
                TextInput::make('registration_issued')
                    ->label(__('shopper-storefront::admin.settings.registration_issued'))
                    ->placeholder(__('shopper-storefront::admin.settings.registration_issued_placeholder'))
                    ->maxLength(255),
                Textarea::make('working_hours')
                    ->label(__('shopper-storefront::admin.settings.working_hours'))
                    ->placeholder('8:00 – 21:00, Thứ 2 – Chủ nhật')
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]),
        ];
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function footerLinkFields(): array
    {
        return [
            Repeater::make('footer_columns')
                ->hiddenLabel()
                ->helperText(__('shopper-storefront::admin.settings.footer_columns_help'))
                ->addActionLabel(__('shopper-storefront::admin.settings.add_column'))
                ->reorderableWithDragAndDrop()
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                ->maxItems(4)
                ->defaultItems(0)
                ->schema([
                    TextInput::make('title')
                        ->label(__('shopper-storefront::admin.settings.column_title'))
                        ->required()
                        ->maxLength(60),
                    Repeater::make('links')
                        ->label(__('shopper-storefront::admin.settings.links'))
                        ->addActionLabel(__('shopper-storefront::admin.settings.add_link'))
                        ->reorderableWithDragAndDrop()
                        ->defaultItems(0)
                        ->maxItems(12)
                        ->columns(3)
                        ->schema([
                            ToggleButtons::make('type')
                                ->label(__('shopper-storefront::admin.settings.link_type'))
                                ->options([
                                    'page' => __('shopper-storefront::admin.settings.link_types.page'),
                                    'legal' => __('shopper-storefront::admin.settings.link_types.legal'),
                                    'url' => __('shopper-storefront::admin.settings.link_types.url'),
                                ])
                                ->default('page')
                                ->inline()
                                ->required()
                                ->live()
                                ->columnSpanFull(),
                            Select::make('id')
                                ->label(fn (Get $get): string => $get('type') === 'legal'
                                    ? __('shopper-storefront::admin.settings.link_types.legal')
                                    : __('shopper-storefront::admin.settings.link_types.page'))
                                ->options(fn (Get $get): array => $get('type') === 'legal'
                                    ? Legal::query()->enabled()->orderBy('title')->pluck('title', 'id')->all()
                                    : Post::configuredClass()::query()->ofType('page')->published()->orderBy('title')->pluck('title', 'id')->all())
                                ->searchable()
                                ->required(fn (Get $get): bool => in_array($get('type'), ['page', 'legal'], true))
                                ->visible(fn (Get $get): bool => in_array($get('type'), ['page', 'legal'], true))
                                ->columnSpan(2),
                            TextInput::make('url')
                                ->label(__('shopper-storefront::admin.settings.link_types.url'))
                                ->placeholder('/lien-he')
                                ->required(fn (Get $get): bool => $get('type') === 'url')
                                ->visible(fn (Get $get): bool => $get('type') === 'url')
                                ->rule('regex:/^(\/(?!\/)|https?:\/\/|mailto:|tel:)\S*$/')
                                ->maxLength(255)
                                ->columnSpan(2),
                            TextInput::make('label')
                                ->label(__('shopper-storefront::admin.settings.link_label'))
                                ->helperText(__('shopper-storefront::admin.settings.link_label_help'))
                                ->required(fn (Get $get): bool => $get('type') === 'url')
                                ->maxLength(80),
                        ]),
                ]),
        ];
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    private function badgeAndPartnerFields(): array
    {
        return [
            Section::make(__('shopper-storefront::admin.settings.badges'))
                ->columns()
                ->schema([
                    TextInput::make('bct_url')
                        ->label(__('shopper-storefront::admin.settings.bct_url'))
                        ->helperText(__('shopper-storefront::admin.settings.bct_url_help'))
                        ->url()
                        ->maxLength(255),
                    UploadPicker::make('bct_badge')
                        ->label(__('shopper-storefront::admin.settings.bct_badge')),
                    TextInput::make('dmca_url')
                        ->label(__('shopper-storefront::admin.settings.dmca_url'))
                        ->helperText(__('shopper-storefront::admin.settings.dmca_url_help'))
                        ->url()
                        ->maxLength(255),
                    UploadPicker::make('dmca_badge')
                        ->label(__('shopper-storefront::admin.settings.dmca_badge')),
                ]),
            Section::make(__('shopper-storefront::admin.settings.partners'))
                ->description(__('shopper-storefront::admin.settings.partners_help'))
                ->columns()
                ->schema([
                    Select::make('carrier_ids')
                        ->label(__('shopper-storefront::admin.settings.carriers'))
                        ->multiple()
                        ->options(fn (): array => Carrier::query()->enabled()->orderBy('name')->pluck('name', 'id')->all()),
                    Select::make('payment_method_ids')
                        ->label(__('shopper-storefront::admin.settings.payment_methods'))
                        ->multiple()
                        ->options(fn (): array => PaymentMethod::query()->enabled()->orderBy('title')->pluck('title', 'id')->all()),
                    Repeater::make('extra_partners')
                        ->label(__('shopper-storefront::admin.settings.extra_partners'))
                        ->helperText(__('shopper-storefront::admin.settings.extra_partners_help'))
                        ->addActionLabel(__('shopper-storefront::admin.settings.add_partner'))
                        ->reorderableWithDragAndDrop()
                        ->defaultItems(0)
                        ->maxItems(20)
                        ->columns(3)
                        ->columnSpanFull()
                        ->schema([
                            TextInput::make('name')
                                ->label(__('shopper::forms.label.name'))
                                ->required()
                                ->maxLength(80),
                            ToggleButtons::make('group')
                                ->label(__('shopper-storefront::admin.settings.partner_group'))
                                ->options([
                                    'shipping' => __('shopper-storefront::admin.settings.partner_groups.shipping'),
                                    'payment' => __('shopper-storefront::admin.settings.partner_groups.payment'),
                                ])
                                ->default('shipping')
                                ->inline()
                                ->required(),
                            TextInput::make('url')
                                ->label(__('shopper-storefront::admin.settings.link_types.url'))
                                ->url()
                                ->maxLength(255),
                            UploadPicker::make('logo')
                                ->label(__('shopper::forms.label.logo'))
                                ->required()
                                ->columnSpanFull(),
                        ]),
                ]),
        ];
    }
}
