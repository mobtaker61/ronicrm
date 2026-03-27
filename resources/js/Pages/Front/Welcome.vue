<template>
    <FrontLayout app-name="RoniCRM">
        <div class="bg-gradient-to-br from-blue-50 via-white to-indigo-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24">
                <div class="text-center max-w-3xl mx-auto">
                    <h1 class="text-4xl sm:text-5xl font-bold text-gray-900 mb-6">
                        {{ t('front.welcome_title') }}
                    </h1>
                    <p class="text-lg text-gray-600 mb-10 leading-relaxed">
                        {{ t('front.welcome_subtitle') }}
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center justify-center px-8 py-3 text-base font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-lg"
                        >
                            {{ t('auth.login') }}
                        </Link>
                        <Link
                            :href="route('front.privacy')"
                            class="inline-flex items-center justify-center px-8 py-3 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                        >
                            {{ t('front.privacy_policy') }}
                        </Link>
                    </div>
                </div>
                <div class="mt-20 grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">
                    <div class="p-6 rounded-xl bg-white/80 shadow-sm">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">{{ t('front.feature_contacts_title') }}</h3>
                        <p class="text-sm text-gray-600">{{ t('front.feature_contacts_desc') }}</p>
                    </div>
                    <div class="p-6 rounded-xl bg-white/80 shadow-sm">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-lg bg-green-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">{{ t('front.feature_campaigns_title') }}</h3>
                        <p class="text-sm text-gray-600">{{ t('front.feature_campaigns_desc') }}</p>
                    </div>
                    <div class="p-6 rounded-xl bg-white/80 shadow-sm">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-lg bg-indigo-100 flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">{{ t('front.feature_inbox_title') }}</h3>
                        <p class="text-sm text-gray-600">{{ t('front.feature_inbox_desc') }}</p>
                    </div>
                </div>
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
