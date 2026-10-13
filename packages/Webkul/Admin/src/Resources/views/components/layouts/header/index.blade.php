<header class="sticky top-0 z-[10001] flex items-center justify-between gap-1 border-b border-gray-300 bg-white px-4 py-2.5 transition-all dark:border-gray-800 dark:bg-gray-900">  
    <!-- logo -->
    <div class="flex items-center gap-1.5">
        <!-- Sidebar Menu -->
        <x-admin::layouts.sidebar.mobile />
        
        <a href="{{ route('admin.dashboard.index') }}">
            @if ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
                <img
                    class="h-10"
                    src="{{ Storage::url($logo) }}"
                    alt="{{ config('app.name') }}"
                />
            @else
                <img
                    class="h-10 max-sm:hidden"
                    src="{{ request()->cookie('dark_mode') ? vite()->asset('images/dark-logo.svg') : vite()->asset('images/logo.svg') }}"
                    id="logo-image"
                    alt="{{ config('app.name') }}"
                />

                <img
                    class="h-10 sm:hidden"
                    src="{{ request()->cookie('dark_mode') ? vite()->asset('images/mobile-dark-logo.svg') : vite()->asset('images/mobile-light-logo.svg') }}"
                    id="logo-image"
                    alt="{{ config('app.name') }}"
                />
            @endif
        </a>
    </div>

    <div class="flex items-center gap-1.5 max-md:hidden">
        <!-- Mega Search Bar -->
        @include('admin::components.layouts.header.desktop.mega-search')

        <!-- Quick Creation Bar -->
        @include('admin::components.layouts.header.quick-creation')
    </div>

    <div class="flex items-center gap-2.5">
        <div class="md:hidden">
            <!-- Mega Search Bar -->
            @include('admin::components.layouts.header.mobile.mega-search')
        </div>
        
        <!-- Theme: light, dark or system -->
        <v-dark>
            <div class="flex">
                <span
                    class="{{ request()->cookie('dark_mode') ? 'icon-dark' : 'icon-light' }} p-1.5 rounded-md text-2xl cursor-pointer transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                ></span>
            </div>
        </v-dark>

        <div class="md:hidden">
            <!-- Quick Creation Bar -->
            @include('admin::components.layouts.header.quick-creation')
        </div>
        
        <!-- Admin profile -->
        <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
            <x-slot:toggle>
                @php($user = auth()->guard('user')->user())

                @if ($user->image)
                    <button class="flex h-9 w-9 cursor-pointer overflow-hidden rounded-full hover:opacity-80 focus:opacity-80">
                        <img
                            src="{{ $user->image_url }}"
                            class="h-full w-full object-cover"
                        />
                    </button>
                @else
                    <button class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-pink-400 font-semibold leading-6 text-white">
                        {{ substr($user->name, 0, 1) }}
                    </button>
                @endif
            </x-slot>

            <!-- Admin Dropdown -->
            <x-slot:content class="mt-2 border-t-0 !p-0">
                <div class="flex items-center gap-1.5 border border-x-0 border-b-gray-300 px-5 py-2.5 dark:border-gray-800">
                    @if ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
                        <img
                            src="{{ Storage::url($logo) }}"
                            alt="{{ config('app.name') }}"
                            width="24"
                            height="24"
                        />
                    @else
                        <img
                            src="{{ request()->cookie('dark_mode') ? vite()->asset('images/dark-logo.svg') : vite()->asset('images/logo.svg') }}"
                            id="logo-image"
                            alt="{{ config('app.name') }}"
                            width="24"
                            height="24"
                        />
                    @endif

                    <!-- Version -->
                    <p class="text-gray-400">
                        @lang('admin::app.layouts.app-version', ['version' => core()->version()])
                    </p>
                </div>

                <div class="grid gap-1 pb-2.5">
                    <a
                        class="cursor-pointer px-5 py-2 text-base text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                        href="{{ route('admin.user.account.edit') }}"
                    >
                        @lang('admin::app.layouts.my-account')
                    </a>

                    <!--Admin logout-->
                    <x-admin::form
                        method="DELETE"
                        action="{{ route('admin.session.destroy') }}"
                        id="adminLogout"
                    >
                    </x-admin::form>

                    <a
                        class="cursor-pointer px-5 py-2 text-base text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                        href="{{ route('admin.session.destroy') }}"
                        onclick="event.preventDefault(); document.getElementById('adminLogout').submit();"
                    >
                        @lang('admin::app.layouts.sign-out')
                    </a>
                </div>
            </x-slot>
        </x-admin::dropdown>
    </div>
</header>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-dark-template"
    >
        <!-- 12Nexus: Light / Dark / System instead of a light-dark toggle -->
        <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
            <x-slot:toggle>
                <button
                    type="button"
                    class="flex rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                    :title="labels.title + ': ' + labels[mode]"
                    :aria-label="labels.title + ': ' + labels[mode]"
                >
                    <span v-if="mode !== 'system'" :class="mode === 'dark' ? 'icon-dark' : 'icon-light'"></span>

                    <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="12" rx="1.5" />
                        <path d="M8 20h8M12 16v4" />
                    </svg>
                </button>
            </x-slot>

            <x-slot:menu class="!min-w-36">
                <x-admin::dropdown.menu.item
                    v-for="option in ['light', 'dark', 'system']"
                    @click="set(option)"
                >
                    <div class="flex w-full items-center gap-2">
                        <span v-if="option !== 'system'" class="text-xl" :class="option === 'dark' ? 'icon-dark' : 'icon-light'"></span>

                        <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="3" y="4" width="18" height="12" rx="1.5" />
                            <path d="M8 20h8M12 16v4" />
                        </svg>

                        <span class="flex-1" v-text="labels[option]"></span>

                        <span v-if="mode === option" class="icon-tick text-lg text-brandColor"></span>
                    </div>
                </x-admin::dropdown.menu.item>
            </x-slot>
        </x-admin::dropdown>
    </script>

    <script type="module">
        app.component('v-dark', {
            template: '#v-dark-template',

            data() {
                return {
                    // light, dark or system; before any choice, what dark_mode says.
                    mode: this.cookie('theme_mode') || (parseInt(this.cookie('dark_mode') || 0) ? 'dark' : 'light'),

                    labels: {
                        title: "@lang('admin::app.layouts.theme.title')",
                        light: "@lang('admin::app.layouts.theme.light')",
                        dark: "@lang('admin::app.layouts.theme.dark')",
                        system: "@lang('admin::app.layouts.theme.system')",
                    },

                    // Default logos, light → dark; a logo set in Configuration is left alone.
                    logos: {
                        "{{ vite()->asset('images/logo.svg') }}": "{{ vite()->asset('images/dark-logo.svg') }}",
                        "{{ vite()->asset('images/mobile-light-logo.svg') }}": "{{ vite()->asset('images/mobile-dark-logo.svg') }}",
                    },

                    media: window.matchMedia('(prefers-color-scheme: dark)'),
                };
            },

            mounted() {
                // Follow the device while on "system".
                this.media.addEventListener('change', () => {
                    if (this.mode === 'system') {
                        this.apply();
                    }
                });

                this.apply();
            },

            methods: {
                set(mode) {
                    this.mode = mode;

                    document.cookie = 'theme_mode=' + mode + '; path=/; max-age=31536000; SameSite=Lax';

                    this.apply();
                },

                apply() {
                    const dark = this.mode === 'dark' || (this.mode === 'system' && this.media.matches);

                    document.cookie = 'dark_mode=' + (dark ? 1 : 0) + '; path=/; max-age=31536000; SameSite=Lax';

                    if (document.documentElement.classList.contains('dark') !== dark) {
                        document.documentElement.classList.toggle('dark', dark);

                        this.$emitter.emit('change-theme', dark ? 'dark' : 'light');
                    }

                    document.querySelectorAll('img#logo-image').forEach((image) => {
                        for (const [light, darkLogo] of Object.entries(this.logos)) {
                            if (dark && image.getAttribute('src') === light) {
                                image.src = darkLogo;
                            } else if (! dark && image.getAttribute('src') === darkLogo) {
                                image.src = light;
                            }
                        }
                    });
                },

                cookie(name) {
                    for (const cookie of document.cookie.split(';')) {
                        const [key, value] = cookie.trim().split('=');

                        if (key === name) {
                            return value;
                        }
                    }

                    return null;
                },
            },
        });
    </script>
@endPushOnce
