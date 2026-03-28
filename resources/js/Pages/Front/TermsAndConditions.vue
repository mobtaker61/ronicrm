<template>
    <FrontLayout
        variant="minimal"
        app-name="RoniCRM"
        :nav-home="t('front.nav_home')"
        :nav-privacy="t('front.privacy_policy')"
        :nav-terms="t('front.terms_title')"
        :nav-get-started="t('front.nav_get_started')"
        :nav-dashboard="t('front.nav_dashboard')"
        :locale-switch-aria="t('front.locale_switch_aria')"
        :footer-rights="t('front.footer_rights')"
    >
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">{{ t('front.terms_title') }}</h1>
            <p class="text-sm text-gray-500 mb-8">{{ t('front.last_updated_feb_2025') }}</p>
            <div class="prose prose-gray max-w-none space-y-6 text-gray-600">
                <p class="leading-relaxed">
                    {{ t('front.terms_intro') }}
                </p>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_1_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_1_body') }}
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_2_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_2_body') }}
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_3_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_3_body') }}
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_4_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_4_body') }}
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_5_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_5_body') }}
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_6_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_6_body') }}
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_7_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_7_body') }}
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">{{ t('front.terms_section_8_title') }}</h2>
                    <p class="leading-relaxed">
                        {{ t('front.terms_section_8_body_before_email') }}
                        <a href="mailto:support@ronicrm.com" class="text-blue-600 hover:underline">support@ronicrm.com</a>.
                        {{ t('front.terms_section_8_body_after_email') }}
                    </p>
                </section>
            </div>
            <div class="mt-10 pt-6 border-t border-gray-200">
                <Link :href="route('front.welcome')" class="text-blue-600 hover:text-blue-700 font-medium">{{ t('front.back_to_home_with_arrow') }}</Link>
            </div>
        </div>
    </FrontLayout>
</template>

<script setup>
import FrontLayout from '@/Layouts/FrontLayout.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();
</script>
