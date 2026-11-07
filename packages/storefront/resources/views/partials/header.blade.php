@php
    /** @var \Shopper\Storefront\Data\HeaderData $header */
    $user = auth()->guard(config('shopper.auth.guard', 'web'))->user();
    $phoneHref = $header->hotline ? 'tel:' . preg_replace('/[^0-9+]/', '', $header->hotline) : null;
@endphp

<header class="sf-header" x-data="{ drawer: false }" x-on:keydown.escape.window="drawer = false">
    @if (filled($header->announcement))
        <div class="bg-brand-700 px-4 py-1.5 text-center text-xs font-medium text-white sm:text-sm">
            {{ $header->announcement }}
        </div>
    @endif

    <div class="bg-brand-600 text-white">
        <div class="sf-container flex flex-wrap items-center gap-x-3 gap-y-2 py-3 lg:flex-nowrap lg:gap-x-6">
            <button
                type="button"
                class="-ml-1 rounded-md p-1.5 hover:bg-white/10 lg:hidden"
                x-on:click="drawer = true"
                aria-label="{{ __('shopper-storefront::storefront.header.open_menu') }}"
            >
                <x-shopper-storefront::icon name="menu" class="size-6" />
            </button>

            <a href="{{ route('shopper.storefront.home') }}" class="flex shrink-0 items-center gap-2" aria-label="{{ $header->storeName ?? config('app.name') }}">
                @if ($header->logo)
                    <img src="{{ $header->logo }}" alt="{{ $header->storeName }}" class="h-9 w-auto max-w-40 object-contain" />
                @else
                    <span class="text-xl font-extrabold tracking-tight">{{ $header->storeName ?? config('app.name') }}</span>
                @endif
            </a>

            <div class="order-last w-full lg:order-none lg:flex-1">
                <livewire:shopper-storefront.header-search :query="request()->routeIs('shopper.storefront.search') ? (string) request('q') : null" />
            </div>

            <div class="ml-auto flex items-center gap-1 sm:gap-3 lg:ml-0">
                @if ($header->hotline)
                    <a href="{{ $phoneHref }}" class="hidden items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-white/10 md:flex">
                        <x-shopper-storefront::icon name="phone" class="size-6" />
                        <span class="text-left leading-tight">
                            <span class="block text-xs text-white/80">{{ __('shopper-storefront::storefront.header.hotline') }}</span>
                            <span class="block text-sm font-semibold">{{ $header->hotline }}</span>
                        </span>
                    </a>
                @endif

                <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-white/10"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open"
                        aria-haspopup="true"
                        aria-label="{{ __('shopper-storefront::storefront.header.account') }}"
                    >
                        <x-shopper-storefront::icon name="user" class="size-6" />
                        <span class="hidden text-left leading-tight sm:block">
                            @auth(config('shopper.auth.guard', 'web'))
                                <span class="block text-xs text-white/80">{{ __('shopper-storefront::storefront.header.hello') }}</span>
                                <span class="block max-w-28 truncate text-sm font-semibold">{{ $user->first_name ?: $user->email }}</span>
                            @else
                                <span class="block text-xs text-white/80">{{ __('shopper-storefront::storefront.header.account') }}</span>
                                <span class="block text-sm font-semibold">{{ __('shopper-storefront::storefront.header.sign_in') }}</span>
                            @endauth
                        </span>
                    </button>

                    <div
                        x-cloak
                        x-show="open"
                        x-transition.opacity
                        class="absolute right-0 z-40 mt-2 w-56 rounded-xl bg-white p-2 text-sm text-gray-800 shadow-lg ring-1 ring-black/5"
                    >
                        @auth(config('shopper.auth.guard', 'web'))
                            <p class="truncate px-3 py-2 text-xs text-gray-500">{{ $user->email }}</p>
                            @if (method_exists($user, 'can') && $user->can('access_dashboard'))
                                <a href="{{ route('shopper.dashboard') }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100">
                                    {{ __('shopper-storefront::storefront.header.admin') }}
                                </a>
                            @endif
                            <form method="POST" action="{{ route('shopper.storefront.logout') }}">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-gray-100">
                                    {{ __('shopper-storefront::storefront.header.sign_out') }}
                                </button>
                            </form>
                        @else
                            <a href="{{ route('shopper.storefront.login') }}" class="block rounded-lg bg-brand-600 px-3 py-2 text-center font-semibold text-white hover:bg-brand-700">
                                {{ __('shopper-storefront::storefront.header.sign_in') }}
                            </a>
                            <a href="{{ route('shopper.storefront.register') }}" class="mt-1 block rounded-lg px-3 py-2 text-center font-medium hover:bg-gray-100">
                                {{ __('shopper-storefront::storefront.header.sign_up') }}
                            </a>
                        @endauth
                    </div>
                </div>

                <livewire:shopper-storefront.mini-cart />
            </div>
        </div>
    </div>

    @if ($header->categories !== [])
        <nav class="hidden border-b border-gray-200 bg-white lg:block" aria-label="{{ __('shopper-storefront::storefront.header.categories') }}">
            <ul class="sf-container flex items-center gap-1 text-sm font-medium text-gray-700">
                <li class="flex items-center gap-2 py-3 pr-3 font-semibold text-brand-700">
                    <x-shopper-storefront::icon name="grid" />
                    {{ __('shopper-storefront::storefront.header.categories') }}
                </li>
                @foreach ($header->categories as $category)
                    <li class="group relative" x-data="{ open: false }" x-on:mouseenter="open = true" x-on:mouseleave="open = false">
                        <a
                            href="{{ $category->url }}"
                            class="flex items-center gap-1 rounded-md px-3 py-3 hover:text-brand-700"
                            @if ($category->children !== []) x-on:focus="open = true" aria-haspopup="true" x-bind:aria-expanded="open" @endif
                        >
                            {{ $category->label }}
                            @if ($category->children !== [])
                                <x-shopper-storefront::icon name="chevron-down" class="size-4 text-gray-400" />
                            @endif
                        </a>
                        @if ($category->children !== [])
                            <ul
                                x-cloak
                                x-show="open"
                                x-transition.opacity
                                x-on:focusout="if (! $el.contains($event.relatedTarget)) open = false"
                                class="absolute left-0 top-full z-30 min-w-56 rounded-xl bg-white p-2 shadow-lg ring-1 ring-black/5"
                            >
                                @foreach ($category->children as $child)
                                    <li>
                                        <a href="{{ $child->url }}" class="block rounded-lg px-3 py-2 hover:bg-gray-100 hover:text-brand-700">{{ $child->label }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    {{-- Mobile drawer --}}
    <div x-cloak x-show="drawer" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('shopper-storefront::storefront.header.menu') }}">
        <div class="absolute inset-0 bg-black/40" x-on:click="drawer = false" x-show="drawer" x-transition.opacity></div>
        <div
            class="absolute inset-y-0 left-0 flex w-80 max-w-[85%] flex-col overflow-y-auto bg-white text-gray-800 shadow-xl"
            x-show="drawer"
            x-transition:enter="transition duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:leave="transition duration-150"
            x-transition:leave-end="-translate-x-full"
            x-trap.noscroll="drawer"
        >
            <div class="flex items-center justify-between bg-brand-600 px-4 py-3 text-white">
                <span class="font-semibold">{{ __('shopper-storefront::storefront.header.menu') }}</span>
                <button type="button" class="rounded-md p-1 hover:bg-white/10" x-on:click="drawer = false" aria-label="{{ __('shopper-storefront::storefront.header.close_menu') }}">
                    <x-shopper-storefront::icon name="close" class="size-6" />
                </button>
            </div>

            <div class="border-b border-gray-100 p-4 text-sm">
                @auth(config('shopper.auth.guard', 'web'))
                    <p class="font-semibold">{{ $user->first_name ?: $user->email }}</p>
                    <form method="POST" action="{{ route('shopper.storefront.logout') }}" class="mt-2">
                        @csrf
                        <button type="submit" class="text-brand-700 underline">{{ __('shopper-storefront::storefront.header.sign_out') }}</button>
                    </form>
                @else
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('shopper.storefront.login') }}" class="rounded-lg bg-brand-600 px-3 py-2 text-center font-semibold text-white">{{ __('shopper-storefront::storefront.header.sign_in') }}</a>
                        <a href="{{ route('shopper.storefront.register') }}" class="rounded-lg px-3 py-2 text-center font-medium ring-1 ring-gray-200">{{ __('shopper-storefront::storefront.header.sign_up') }}</a>
                    </div>
                @endauth
            </div>

            <ul class="flex-1 p-2 text-sm">
                @foreach ($header->categories as $category)
                    <li x-data="{ open: false }">
                        <div class="flex items-center">
                            <a href="{{ $category->url }}" class="flex-1 rounded-lg px-3 py-2.5 font-medium hover:bg-gray-100">{{ $category->label }}</a>
                            @if ($category->children !== [])
                                <button type="button" class="rounded-lg p-2 hover:bg-gray-100" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="{{ $category->label }}">
                                    <x-shopper-storefront::icon name="chevron-down" class="size-4 transition" x-bind:class="open && 'rotate-180'" />
                                </button>
                            @endif
                        </div>
                        @if ($category->children !== [])
                            <ul x-show="open" x-collapse class="ml-3 border-l border-gray-100 pl-2">
                                @foreach ($category->children as $child)
                                    <li><a href="{{ $child->url }}" class="block rounded-lg px-3 py-2 text-gray-600 hover:bg-gray-100">{{ $child->label }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($header->hotline)
                <a href="{{ $phoneHref }}" class="m-4 flex items-center justify-center gap-2 rounded-lg bg-brand-50 px-3 py-2.5 text-sm font-semibold text-brand-700">
                    <x-shopper-storefront::icon name="phone" />
                    {{ __('shopper-storefront::storefront.header.hotline') }}: {{ $header->hotline }}
                </a>
            @endif
        </div>
    </div>
</header>
