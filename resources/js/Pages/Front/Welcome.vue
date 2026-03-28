<template>
    <FrontLayout
        variant="marketing"
        :nav-about="t('front.nav_about')"
        :nav-features="t('front.nav_features')"
        :nav-pricing="t('front.nav_pricing')"
        :nav-contact="t('front.nav_contact')"
        :nav-get-started="t('front.nav_get_started')"
        :nav-dashboard="t('front.nav_dashboard')"
        :nav-menu="t('front.nav_menu')"
        :locale-switch-aria="t('front.locale_switch_aria')"
        :footer-tagline="t('front.footer_tagline')"
        :footer-col-product="t('front.footer_col_product')"
        :footer-col-resources="t('front.footer_col_resources')"
        :footer-col-contact="t('front.footer_col_contact')"
        :footer-contact-hint="t('front.footer_contact_hint')"
        :footer-contact-cta="t('front.footer_contact_cta')"
        :footer-rights="t('front.footer_rights')"
        :footer-social-label="t('front.footer_social_label')"
    >
        <template #pre-footer>
            <section class="bg-gradient-to-r from-amber-500 to-orange-500 text-white">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-14">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-8">
                        <div class="max-w-xl">
                            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">
                                {{ t('front.newsletter_title') }}
                            </h2>
                        </div>
                        <form class="flex flex-col sm:flex-row gap-3 w-full lg:max-w-md shrink-0" @submit.prevent="onNewsletterSubmit">
                            <input
                                v-model="newsletterEmail"
                                type="email"
                                required
                                autocomplete="email"
                                :placeholder="t('front.newsletter_placeholder')"
                                class="flex-1 rounded-2xl border-0 px-4 py-3 text-gray-900 placeholder:text-gray-500 shadow-sm focus:ring-2 focus:ring-white/80"
                            />
                            <button
                                type="submit"
                                class="rounded-2xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800 transition-colors whitespace-nowrap"
                            >
                                {{ t('front.newsletter_cta') }}
                            </button>
                        </form>
                    </div>
                    <p v-if="newsletterThanks" class="mt-4 text-sm font-medium text-white/90">
                        {{ t('front.newsletter_thanks') }}
                    </p>
                </div>
            </section>
        </template>

        <!-- Hero -->
        <section
            id="about"
            class="relative overflow-hidden bg-gradient-to-br from-indigo-50 via-white to-violet-50"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
                    <div>
                        <h1 class="text-4xl sm:text-5xl lg:text-[3.25rem] font-extrabold text-gray-900 tracking-tight leading-[1.1]">
                            {{ t('front.welcome_title') }}
                        </h1>
                        <p class="mt-6 text-lg text-gray-600 leading-relaxed">
                            {{ t('front.welcome_subtitle') }}
                        </p>
                        <form class="mt-8 flex flex-col sm:flex-row gap-3 max-w-lg" @submit.prevent="goTryFree">
                            <input
                                v-model="heroEmail"
                                type="email"
                                autocomplete="email"
                                :placeholder="t('front.hero_email_placeholder')"
                                class="flex-1 rounded-2xl border border-gray-200 bg-white px-4 py-3.5 text-gray-900 shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <button
                                type="submit"
                                class="rounded-2xl bg-blue-600 px-8 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700 transition-colors whitespace-nowrap"
                            >
                                {{ t('front.hero_try_free') }}
                            </button>
                        </form>
                        <div class="mt-10 flex items-center gap-4">
                            <div class="flex -space-x-2 rtl:space-x-reverse">
                                <span
                                    v-for="i in 4"
                                    :key="i"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border-2 border-white bg-gradient-to-br from-blue-400 to-indigo-500 text-xs font-bold text-white"
                                >
                                    {{ String.fromCharCode(64 + i) }}
                                </span>
                            </div>
                            <p class="text-sm font-medium text-gray-600">
                                {{ t('front.hero_social_proof') }}
                            </p>
                        </div>
                    </div>
                    <div class="relative lg:justify-self-end w-full max-w-lg mx-auto lg:mx-0">
                        <div
                            class="relative aspect-[4/3] rounded-3xl bg-white shadow-2xl shadow-indigo-900/10 border border-gray-100 p-6 flex flex-col gap-4"
                        >
                            <div class="flex items-center justify-between">
                                <div class="h-3 w-24 rounded-full bg-gray-200" />
                                <div class="flex gap-2">
                                    <span class="h-8 w-8 rounded-full bg-green-100" />
                                    <span class="h-8 w-8 rounded-full bg-amber-100" />
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 flex-1">
                                <div class="rounded-2xl bg-blue-50 p-4 flex flex-col">
                                    <span class="text-xs font-semibold text-blue-800">{{ t('front.mock_inbox') }}</span>
                                    <div class="mt-3 space-y-2">
                                        <div class="h-2 w-full rounded bg-blue-200/80" />
                                        <div class="h-2 w-[80%] rounded bg-blue-200/60" />
                                    </div>
                                </div>
                                <div class="rounded-2xl bg-violet-50 p-4 flex flex-col justify-end">
                                    <span class="text-xs font-semibold text-violet-800">{{ t('front.mock_chart') }}</span>
                                    <div class="mt-3 flex items-end gap-1 h-16">
                                        <span class="flex-1 rounded-t bg-violet-300 h-[40%]" />
                                        <span class="flex-1 rounded-t bg-violet-400 h-[70%]" />
                                        <span class="flex-1 rounded-t bg-violet-500 h-[55%]" />
                                        <span class="flex-1 rounded-t bg-violet-400 h-[90%]" />
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-4 flex items-center gap-3">
                                <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 shrink-0" />
                                <div class="flex-1 space-y-2">
                                    <div class="h-2.5 w-1/2 rounded bg-gray-200" />
                                    <div class="h-2 w-3/4 rounded bg-gray-200" />
                                </div>
                            </div>
                        </div>
                        <div
                            class="absolute -top-4 -right-4 hidden sm:block w-28 rounded-2xl bg-white shadow-lg border border-gray-100 p-3 text-xs font-medium text-gray-700"
                        >
                            {{ t('front.mock_notification') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Logos strip -->
        <section class="border-y border-gray-100 bg-gray-50/80 py-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <p class="text-center text-xs font-semibold uppercase tracking-wider text-gray-400 mb-8">
                    {{ t('front.trusted_by') }}
                </p>
                <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6 opacity-50 grayscale">
                    <span
                        v-for="n in 5"
                        :key="n"
                        class="h-8 w-24 rounded-lg bg-gray-300"
                    />
                </div>
            </div>
        </section>

        <!-- How it works -->
        <section id="features" class="py-20 lg:py-28 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-center text-3xl sm:text-4xl font-bold text-gray-900 tracking-tight">
                    {{ t('front.how_it_works_title') }}
                </h2>
                <div class="mt-14 grid sm:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                    <article class="rounded-3xl bg-sky-100/90 p-8 shadow-sm border border-sky-100">
                        <div class="h-12 w-12 rounded-2xl bg-sky-500/20 flex items-center justify-center text-sky-700 mb-5">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">{{ t('front.feature_contacts_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ t('front.feature_contacts_desc') }}</p>
                    </article>
                    <article class="rounded-3xl bg-amber-100/90 p-8 shadow-sm border border-amber-100">
                        <div class="h-12 w-12 rounded-2xl bg-amber-500/20 flex items-center justify-center text-amber-800 mb-5">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">{{ t('front.feature_campaigns_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ t('front.feature_campaigns_desc') }}</p>
                    </article>
                    <article class="rounded-3xl bg-emerald-100/90 p-8 shadow-sm border border-emerald-100">
                        <div class="h-12 w-12 rounded-2xl bg-emerald-500/20 flex items-center justify-center text-emerald-800 mb-5">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">{{ t('front.feature_inbox_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ t('front.feature_inbox_desc') }}</p>
                    </article>
                    <article class="rounded-3xl bg-pink-100/90 p-8 shadow-sm border border-pink-100">
                        <div class="h-12 w-12 rounded-2xl bg-pink-500/20 flex items-center justify-center text-pink-800 mb-5">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">{{ t('front.feature_analytics_title') }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ t('front.feature_analytics_desc') }}</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- Highlight row -->
        <section class="py-20 bg-gray-50 border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900 tracking-tight">
                            {{ t('front.highlight_dashboard_title') }}
                        </h2>
                        <p class="mt-4 text-gray-600 leading-relaxed">
                            {{ t('front.highlight_dashboard_desc') }}
                        </p>
                        <Link
                            :href="route('login')"
                            class="mt-8 inline-flex items-center rounded-2xl border-2 border-gray-900 px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-gray-900 hover:text-white transition-colors"
                        >
                            {{ t('front.highlight_read_more') }}
                        </Link>
                    </div>
                    <div class="rounded-3xl bg-white border border-gray-200 shadow-xl p-6 space-y-4">
                        <div class="flex gap-2">
                            <span class="h-3 w-3 rounded-full bg-red-400" />
                            <span class="h-3 w-3 rounded-full bg-amber-400" />
                            <span class="h-3 w-3 rounded-full bg-green-400" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="col-span-2 rounded-2xl bg-slate-100 h-32 flex items-end p-4 gap-1">
                                <span v-for="h in [40, 65, 45, 80, 55, 90, 70]" :key="h" class="flex-1 rounded-t bg-blue-500/80" :style="{ height: h + '%' }" />
                            </div>
                            <div class="rounded-2xl bg-blue-50 p-4 flex flex-col justify-center items-center">
                                <span class="text-2xl font-bold text-blue-700">87%</span>
                                <span class="text-xs text-blue-600/80 text-center mt-1">{{ t('front.mock_response_rate') }}</span>
                            </div>
                        </div>
                        <div class="h-24 rounded-2xl bg-gradient-to-r from-indigo-100 to-violet-100" />
                    </div>
                </div>
            </div>
        </section>

        <!-- Dark analytics band -->
        <section class="py-20 lg:py-28 bg-slate-900 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl sm:text-4xl font-bold tracking-tight">
                    {{ t('front.analytics_band_title') }}
                </h2>
                <p class="mt-4 text-slate-400 max-w-2xl mx-auto">
                    {{ t('front.analytics_band_subtitle') }}
                </p>
                <div class="mt-14 rounded-3xl bg-slate-800/80 border border-slate-700 p-6 lg:p-10 shadow-2xl">
                    <div class="grid md:grid-cols-3 gap-6 text-left">
                        <div class="md:col-span-2 rounded-2xl bg-slate-900/50 p-6 h-48 flex items-end gap-1">
                            <span v-for="(h, i) in [35, 50, 42, 68, 55, 72, 48, 80, 62, 88, 75, 92]" :key="i" class="flex-1 rounded-t bg-blue-500/70" :style="{ height: h + '%' }" />
                        </div>
                        <div class="space-y-4">
                            <div class="rounded-2xl bg-slate-900/50 p-5">
                                <div class="text-sm text-slate-400">{{ t('front.mock_pipeline') }}</div>
                                <div class="mt-2 text-2xl font-bold">128</div>
                            </div>
                            <div class="rounded-2xl bg-slate-900/50 p-5 flex items-center gap-4">
                                <div class="relative h-16 w-16">
                                    <svg class="h-16 w-16 -rotate-90" viewBox="0 0 36 36">
                                        <path
                                            class="text-slate-700"
                                            stroke="currentColor"
                                            stroke-width="3"
                                            fill="none"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                        <path
                                            class="text-emerald-400"
                                            stroke="currentColor"
                                            stroke-width="3"
                                            stroke-dasharray="72, 100"
                                            stroke-linecap="round"
                                            fill="none"
                                            d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                        />
                                    </svg>
                                    <span class="absolute inset-0 flex items-center justify-center text-sm font-bold">72%</span>
                                </div>
                                <div>
                                    <div class="text-sm text-slate-400">{{ t('front.mock_conversion') }}</div>
                                    <div class="font-semibold">{{ t('front.mock_on_track') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing -->
        <section id="pricing" class="py-20 lg:py-28 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto">
                    <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 tracking-tight">
                        {{ t('front.pricing_title') }}
                    </h2>
                    <div v-if="showBillingToggle" class="mt-8 inline-flex items-center gap-3 rounded-full bg-gray-100 p-1.5">
                        <button
                            type="button"
                            class="rounded-full px-5 py-2 text-sm font-semibold transition-colors"
                            :class="billingPeriod === 'monthly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'"
                            @click="billingPeriod = 'monthly'"
                        >
                            {{ t('front.pricing_monthly') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-5 py-2 text-sm font-semibold transition-colors"
                            :class="billingPeriod === 'yearly' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500'"
                            @click="billingPeriod = 'yearly'"
                        >
                            {{ t('front.pricing_yearly') }}
                        </button>
                    </div>
                </div>

                <div class="mt-14 grid md:grid-cols-2 xl:grid-cols-3 gap-8">
                    <article
                        v-for="(plan, idx) in displayedPlans"
                        :key="plan.id"
                        class="relative rounded-3xl border p-8 flex flex-col transition-shadow"
                        :class="
                            idx === highlightedPlanIndex
                                ? 'border-blue-500 bg-blue-50/50 shadow-xl shadow-blue-900/10 ring-2 ring-blue-500'
                                : 'border-gray-200 bg-white shadow-sm hover:shadow-md'
                        "
                    >
                        <span
                            v-if="idx === highlightedPlanIndex"
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-blue-600 px-4 py-1 text-xs font-bold text-white"
                        >
                            {{ t('front.pricing_best_value') }}
                        </span>
                        <h3 class="text-xl font-bold text-gray-900">{{ plan.name }}</h3>
                        <p class="mt-1 text-xs text-gray-500 font-mono">{{ plan.code }}</p>
                        <div class="mt-6">
                            <span class="text-4xl font-extrabold text-gray-900">{{ formatMoney(plan.price_cents, plan.currency) }}</span>
                            <span class="text-gray-500 text-sm ms-1">
                                / {{ billingLabel(plan) }}
                            </span>
                        </div>
                        <ul class="mt-8 space-y-3 flex-1">
                            <template v-if="plan.limits_json && Object.keys(plan.limits_json).length">
                                <li
                                    v-for="(val, key) in plan.limits_json"
                                    :key="key"
                                    class="flex gap-2 text-sm text-gray-600"
                                >
                                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span><span class="font-medium text-gray-800">{{ key }}:</span> {{ prettyLimit(val) }}</span>
                                </li>
                            </template>
                            <li v-else class="text-sm text-gray-500">{{ t('front.pricing_no_limits') }}</li>
                        </ul>
                        <Link
                            :href="route('login')"
                            class="mt-8 block text-center rounded-2xl py-3.5 text-sm font-semibold transition-colors"
                            :class="
                                idx === highlightedPlanIndex
                                    ? 'bg-blue-600 text-white hover:bg-blue-700'
                                    : 'bg-gray-900 text-white hover:bg-gray-800'
                            "
                        >
                            {{ t('front.pricing_get_started') }}
                        </Link>
                    </article>
                </div>
                <p v-if="!displayedPlans.length" class="mt-12 text-center text-gray-500">
                    {{ t('front.pricing_empty') }}
                </p>
            </div>
        </section>

        <!-- Integrations -->
        <section class="py-20 bg-gray-50 border-y border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl font-bold text-gray-900">{{ t('front.integration_title') }}</h2>
                <div class="mt-12 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div
                        v-for="item in integrationItems"
                        :key="item.titleKey"
                        class="rounded-3xl bg-white border border-gray-100 p-8 shadow-sm text-start"
                    >
                        <div class="text-3xl mb-4" aria-hidden="true">{{ item.icon }}</div>
                        <h3 class="font-bold text-gray-900">{{ t(item.titleKey) }}</h3>
                        <p class="mt-2 text-sm text-gray-600 leading-relaxed">{{ t(item.descKey) }}</p>
                    </div>
                </div>
                <Link
                    :href="route('login')"
                    class="mt-10 inline-flex items-center rounded-2xl border-2 border-gray-300 bg-white px-8 py-3 text-sm font-semibold text-gray-800 hover:border-gray-400 transition-colors"
                >
                    {{ t('front.integration_view_all') }}
                </Link>
            </div>
        </section>

        <!-- Testimonials -->
        <section class="py-20 lg:py-28 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-center text-3xl font-bold text-gray-900">{{ t('front.testimonials_title') }}</h2>
                <div class="mt-14 grid md:grid-cols-3 gap-8">
                    <figure
                        v-for="i in 3"
                        :key="i"
                        class="rounded-3xl border border-gray-100 bg-gray-50/80 p-8 shadow-sm"
                    >
                        <blockquote class="text-gray-700 leading-relaxed">
                            «{{ t('front.testimonial_' + i + '_quote') }}»
                        </blockquote>
                        <figcaption class="mt-6 flex items-center gap-3">
                            <span class="h-12 w-12 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 shrink-0" />
                            <div>
                                <div class="font-semibold text-gray-900">{{ t('front.testimonial_' + i + '_name') }}</div>
                                <div class="text-sm text-gray-500">{{ t('front.testimonial_' + i + '_role') }}</div>
                            </div>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- Web app CTA -->
        <section class="py-16 bg-gradient-to-br from-blue-600 to-indigo-700 text-white">
            <div class="max-w-4xl mx-auto px-4 text-center">
                <h2 class="text-2xl sm:text-3xl font-bold">{{ t('front.cta_web_title') }}</h2>
                <Link
                    :href="route('login')"
                    class="mt-8 inline-flex items-center justify-center rounded-2xl bg-white px-10 py-4 text-sm font-bold text-blue-700 shadow-lg hover:bg-blue-50 transition-colors"
                >
                    {{ t('front.cta_web_button') }}
                </Link>
            </div>
        </section>

        <!-- FAQ + Contact -->
        <section id="contact" class="py-20 lg:py-28 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-20">
                    <div>
                        <h2 class="text-3xl font-bold text-gray-900">{{ t('front.faq_title') }}</h2>
                        <p class="mt-4 text-gray-600">{{ t('front.faq_intro') }}</p>
                    </div>
                    <div class="space-y-3">
                        <details
                            v-for="n in 4"
                            :key="n"
                            class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm open:shadow-md transition-shadow"
                        >
                            <summary class="font-semibold text-gray-900 cursor-pointer list-none flex items-center justify-between gap-4">
                                {{ t('front.faq_q' + n) }}
                                <svg class="w-5 h-5 text-gray-400 group-open:rotate-180 transition-transform shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </summary>
                            <p class="mt-3 text-sm text-gray-600 leading-relaxed">
                                {{ t('front.faq_a' + n) }}
                            </p>
                        </details>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>

<script setup>
import FrontLayout from '@/Layouts/FrontLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    plans: { type: Array, default: () => [] },
});

const { t } = useI18n();

const billingPeriod = ref('monthly');
const heroEmail = ref('');
const newsletterEmail = ref('');
const newsletterThanks = ref(false);

const hasMonthly = computed(() => props.plans.some((p) => p.billing_period === 'monthly'));
const hasYearly = computed(() => props.plans.some((p) => p.billing_period === 'yearly'));
const showBillingToggle = computed(() => hasMonthly.value && hasYearly.value);

const displayedPlans = computed(() => {
    if (showBillingToggle.value) {
        return props.plans.filter((p) => p.billing_period === billingPeriod.value);
    }
    return props.plans;
});

const highlightedPlanIndex = computed(() => {
    const list = displayedPlans.value;
    if (!list.length) {
        return -1;
    }
    const proIdx = list.findIndex((p) => String(p.code).toLowerCase() === 'pro');
    if (proIdx >= 0) {
        return proIdx;
    }
    return list.length === 1 ? 0 : Math.min(1, list.length - 1);
});

const integrationItems = [
    { icon: '💬', titleKey: 'front.integration_wa_title', descKey: 'front.integration_wa_desc' },
    { icon: '✈️', titleKey: 'front.integration_tg_title', descKey: 'front.integration_tg_desc' },
    { icon: '📸', titleKey: 'front.integration_ig_title', descKey: 'front.integration_ig_desc' },
    { icon: '✉️', titleKey: 'front.integration_email_title', descKey: 'front.integration_email_desc' },
];

function formatMoney(cents, currency) {
    const v = Number(cents || 0) / 100;
    return `${v.toFixed(2)} ${currency || ''}`.trim();
}

function prettyLimit(v) {
    if (v === null || v === undefined) {
        return '';
    }
    if (Array.isArray(v)) {
        return v.join(', ');
    }
    if (typeof v === 'object') {
        return JSON.stringify(v);
    }
    return String(v);
}

function billingLabel(plan) {
    const interval = plan.billing_interval > 1 ? ` ×${plan.billing_interval}` : '';
    return `${plan.billing_period}${interval}`;
}

function goTryFree() {
    const q = heroEmail.value ? `?email=${encodeURIComponent(heroEmail.value)}` : '';
    window.location.href = route('login') + q;
}

function onNewsletterSubmit() {
    newsletterThanks.value = true;
    newsletterEmail.value = '';
}
</script>
