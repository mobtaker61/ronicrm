<template>
    <div class="min-h-screen flex flex-col bg-white text-gray-900 font-sans antialiased" :dir="htmlDir">
        <!-- Marketing header (landing / future blog, features, etc.) -->
        <header
            v-if="variant === 'marketing'"
            class="sticky top-0 z-50 border-b border-gray-100/80 bg-white/90 backdrop-blur-md"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16 lg:h-[4.25rem]">
                    <Link :href="route('front.welcome')" class="flex items-center gap-3 shrink-0 min-w-0">
                        <img
                            :src="brandLogoFull"
                            :alt="appName"
                            class="h-9 w-auto max-w-[min(200px,55vw)] object-contain object-left"
                        />
                    </Link>

                    <nav class="hidden lg:flex items-center justify-center gap-8 flex-1 px-8">
                        <a
                            :href="`${homeUrl}#about`"
                            class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {{ navAbout }}
                        </a>
                        <a
                            :href="`${homeUrl}#features`"
                            class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {{ navFeatures }}
                        </a>
                        <a
                            :href="`${homeUrl}#pricing`"
                            class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {{ navPricing }}
                        </a>
                        <a
                            :href="`${homeUrl}#contact`"
                            class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {{ navContact }}
                        </a>
                        <slot name="header-nav-extra" />
                    </nav>

                    <div class="hidden lg:flex items-center gap-3 shrink-0">
                        <LocaleDropdown
                            v-if="publicLocales.length > 1"
                            :locales="publicLocales"
                            :current-locale="currentLocale"
                            :aria-label="localeSwitchAria"
                            align-end
                            @select="setPublicLocale"
                        />
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                            class="text-sm font-semibold text-gray-700 hover:text-blue-600 px-3 py-2 rounded-xl transition-colors"
                        >
                            {{ navDashboard }}
                        </Link>
                        <Link
                            v-else
                            :href="route('login')"
                            class="inline-flex items-center justify-center rounded-2xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700 transition-colors"
                        >
                            {{ navGetStarted }}
                        </Link>
                    </div>

                    <button
                        type="button"
                        class="lg:hidden p-2 rounded-xl text-gray-600 hover:bg-gray-100"
                        :aria-label="navMenu"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg v-if="!mobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div
                    v-if="mobileMenuOpen && variant === 'marketing'"
                    class="lg:hidden py-4 border-t border-gray-100 space-y-1 pb-6"
                >
                    <a
                        :href="`${homeUrl}#about`"
                        class="block py-2.5 text-gray-700 font-medium"
                        @click="mobileMenuOpen = false"
                    >
                        {{ navAbout }}
                    </a>
                    <a
                        :href="`${homeUrl}#features`"
                        class="block py-2.5 text-gray-700 font-medium"
                        @click="mobileMenuOpen = false"
                    >
                        {{ navFeatures }}
                    </a>
                    <a
                        :href="`${homeUrl}#pricing`"
                        class="block py-2.5 text-gray-700 font-medium"
                        @click="mobileMenuOpen = false"
                    >
                        {{ navPricing }}
                    </a>
                    <a
                        :href="`${homeUrl}#contact`"
                        class="block py-2.5 text-gray-700 font-medium"
                        @click="mobileMenuOpen = false"
                    >
                        {{ navContact }}
                    </a>
                    <slot name="header-nav-extra-mobile" />
                    <div class="pt-4 flex flex-col gap-3">
                        <LocaleDropdown
                            v-if="publicLocales.length > 1"
                            :locales="publicLocales"
                            :current-locale="currentLocale"
                            :aria-label="localeSwitchAria"
                            block
                            align-end
                            @select="onLocaleSelectFromMenu"
                        />
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                            class="block text-center py-3 rounded-xl bg-blue-600 text-white font-semibold"
                            @click="mobileMenuOpen = false"
                        >
                            {{ navDashboard }}
                        </Link>
                        <Link
                            v-else
                            :href="registerTabUrl"
                            class="block text-center py-3 rounded-xl bg-blue-600 text-white font-semibold"
                            @click="mobileMenuOpen = false"
                        >
                            {{ navGetStarted }}
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <!-- Minimal header (legal pages, future simple static pages) -->
        <header
            v-else
            class="sticky top-0 z-50 border-b border-gray-200 bg-white"
        >
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <Link :href="route('front.welcome')" class="flex items-center gap-3 min-w-0">
                        <img
                            :src="brandLogoFull"
                            :alt="appName"
                            class="h-8 w-auto max-w-[180px] object-contain object-left"
                        />
                    </Link>
                    <nav class="hidden sm:flex items-center gap-5">
                        <Link
                            :href="route('front.welcome')"
                            class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {{ navHome }}
                        </Link>
                        <Link
                            :href="route('front.privacy')"
                            class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {{ navPrivacy }}
                        </Link>
                        <Link
                            :href="route('front.terms')"
                            class="text-sm font-medium text-gray-600 hover:text-blue-600 transition-colors"
                        >
                            {{ navTerms }}
                        </Link>
                        <LocaleDropdown
                            v-if="publicLocales.length > 1"
                            :locales="publicLocales"
                            :current-locale="currentLocale"
                            :aria-label="localeSwitchAria"
                            align-end
                            @select="setPublicLocale"
                        />
                        <Link
                            v-if="$page.props.auth?.user"
                            :href="route('dashboard')"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            {{ navDashboard }}
                        </Link>
                        <Link
                            v-else
                            :href="registerTabUrl"
                            class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            {{ navGetStarted }}
                        </Link>
                    </nav>
                    <button
                        type="button"
                        class="sm:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                v-if="!mobileMenuOpen"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
                <div v-if="mobileMenuOpen" class="sm:hidden py-4 border-t border-gray-200 space-y-2">
                    <Link :href="route('front.welcome')" class="block py-2 text-gray-600" @click="mobileMenuOpen = false">
                        {{ navHome }}
                    </Link>
                    <Link :href="route('front.privacy')" class="block py-2 text-gray-600" @click="mobileMenuOpen = false">
                        {{ navPrivacy }}
                    </Link>
                    <Link :href="route('front.terms')" class="block py-2 text-gray-600" @click="mobileMenuOpen = false">
                        {{ navTerms }}
                    </Link>
                    <Link
                        v-if="$page.props.auth?.user"
                        :href="route('dashboard')"
                        class="block py-2 font-medium text-blue-600"
                        @click="mobileMenuOpen = false"
                    >
                        {{ navDashboard }}
                    </Link>
                    <LocaleDropdown
                        v-if="publicLocales.length > 1"
                        :locales="publicLocales"
                        :current-locale="currentLocale"
                        :aria-label="localeSwitchAria"
                        block
                        align-end
                        @select="onLocaleSelectFromMenu"
                    />
                    <Link
                        v-if="!$page.props.auth?.user"
                        :href="registerTabUrl"
                        class="block py-2 font-medium text-blue-600"
                        @click="mobileMenuOpen = false"
                    >
                        {{ navGetStarted }}
                    </Link>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <slot />
        </main>

        <slot name="pre-footer" />

        <!-- Marketing footer -->
        <footer v-if="variant === 'marketing'" class="bg-slate-900 text-slate-300">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 lg:py-16">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 lg:gap-12">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <div class="mb-4">
                            <img
                                :src="brandLogoFull"
                                :alt="appName"
                                class="h-8 w-auto max-w-[200px] object-contain object-left opacity-95"
                            />
                        </div>
                        <p class="text-sm leading-relaxed text-slate-400 max-w-sm">
                            {{ footerTagline }}
                        </p>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">
                            {{ footerColProduct }}
                        </h3>
                        <ul class="space-y-3 text-sm">
                            <li>
                                <a :href="`${homeUrl}#features`" class="hover:text-white transition-colors">{{ navFeatures }}</a>
                            </li>
                            <li>
                                <a :href="`${homeUrl}#pricing`" class="hover:text-white transition-colors">{{ navPricing }}</a>
                            </li>
                            <li>
                                <Link :href="registerTabUrl" class="hover:text-white transition-colors">{{ navGetStarted }}</Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">
                            {{ footerColResources }}
                        </h3>
                        <ul class="space-y-3 text-sm">
                            <li>
                                <Link :href="route('front.privacy')" class="hover:text-white transition-colors">{{ navPrivacy }}</Link>
                            </li>
                            <li>
                                <Link :href="route('front.terms')" class="hover:text-white transition-colors">{{ navTerms }}</Link>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-4">
                            {{ footerColContact }}
                        </h3>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            {{ footerContactHint }}
                        </p>
                        <a
                            :href="`${homeUrl}#contact`"
                            class="inline-flex mt-4 text-sm font-semibold text-blue-400 hover:text-blue-300 transition-colors"
                        >
                            {{ footerContactCta }}
                        </a>
                    </div>
                </div>
                <slot name="footer-extra" />
                <div
                    class="mt-12 pt-8 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4"
                >
                    <p class="text-xs text-slate-500">
                        © {{ year }} {{ appName }}. {{ footerRights }}
                    </p>
                    <div class="flex items-center gap-4 text-slate-500">
                        <span class="text-xs">{{ footerSocialLabel }}</span>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Minimal footer -->
        <footer v-else class="bg-white border-t border-gray-200 mt-auto">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <span class="text-sm font-semibold text-gray-700">{{ appName }}</span>
                    <nav class="flex flex-wrap items-center justify-center gap-6 text-sm text-gray-500">
                        <Link :href="route('front.welcome')" class="hover:text-blue-600 transition-colors">{{ navHome }}</Link>
                        <Link :href="route('front.privacy')" class="hover:text-blue-600 transition-colors">{{ navPrivacy }}</Link>
                        <Link :href="route('front.terms')" class="hover:text-blue-600 transition-colors">{{ navTerms }}</Link>
                        <Link v-if="!$page.props.auth?.user" :href="registerTabUrl" class="hover:text-blue-600 transition-colors">
                            {{ navGetStarted }}
                        </Link>
                    </nav>
                </div>
                <p class="mt-6 text-center sm:text-left text-xs text-gray-400">
                    © {{ year }} {{ appName }}. {{ footerRights }}
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import LocaleDropdown from '@/Components/Front/LocaleDropdown.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const brandLogoFull = '/brand/logo-full-96px.png';

defineProps({
    appName: { type: String, default: 'RoniCRM' },
    /** marketing: full nav + dark footer; minimal: compact header/footer for legal pages */
    variant: { type: String, default: 'marketing', validator: (v) => ['marketing', 'minimal'].includes(v) },
    /** i18n strings (pass from page so layout stays dumb, or override per page) */
    navAbout: { type: String, default: 'About' },
    navFeatures: { type: String, default: 'Features' },
    navPricing: { type: String, default: 'Pricing' },
    navContact: { type: String, default: 'Contact' },
    navGetStarted: { type: String, default: 'Get started' },
    navDashboard: { type: String, default: 'Dashboard' },
    navHome: { type: String, default: 'Home' },
    navPrivacy: { type: String, default: 'Privacy' },
    navTerms: { type: String, default: 'Terms' },
    navMenu: { type: String, default: 'Menu' },
    /** aria-label for the locale dropdown */
    localeSwitchAria: { type: String, default: 'Language' },
    footerTagline: { type: String, default: '' },
    footerColProduct: { type: String, default: 'Product' },
    footerColResources: { type: String, default: 'Resources' },
    footerColContact: { type: String, default: 'Contact' },
    footerContactHint: { type: String, default: '' },
    footerContactCta: { type: String, default: '' },
    footerRights: { type: String, default: 'All rights reserved.' },
    footerSocialLabel: { type: String, default: '' },
});

const page = usePage();
const htmlDir = computed(() => (page.props.html?.dir === 'rtl' ? 'rtl' : 'ltr'));
const mobileMenuOpen = ref(false);
const year = new Date().getFullYear();
const homeUrl = computed(() => route('front.welcome'));

const registerTabUrl = computed(() => {
    let base = '/login';
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
        try {
            base = window.route('login');
        } catch {
            /* ignore */
        }
    }
    return base + (base.includes('?') ? '&' : '?') + 'tab=register';
});

const publicLocales = computed(() => page.props.publicLocales ?? []);
const currentLocale = computed(() => String(page.props.i18n?.locale ?? ''));

function onLocaleSelectFromMenu(code) {
    setPublicLocale(code);
    mobileMenuOpen.value = false;
}

function setPublicLocale(code) {
    if (code === currentLocale.value) {
        return;
    }
    let url = '/locale';
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
        try {
            url = window.route('locale.set');
        } catch {
            /* Ziggy route missing in dev fallback */
        }
    }
    router.post(url, { locale: code }, { preserveScroll: true });
}
</script>
