<template>
    <div class="min-h-screen bg-gray-50" :dir="currentDir" :style="currentFontStyle">
        <!-- Mobile Overlay -->
        <div
            v-if="sidebarOpen"
            @click="sidebarOpen = false"
            class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden transition-opacity"
        ></div>

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 z-50 w-64 bg-white transform transition-transform duration-200 ease-in-out lg:translate-x-0',
                isRtl ? 'right-0 border-l border-gray-200' : 'left-0 border-r border-gray-200',
                !sidebarOpen ? (isRtl ? 'translate-x-full' : '-translate-x-full') : 'translate-x-0',
            ]"
        >
            <div class="flex flex-col h-full">
                <!-- Logo -->
                <div class="flex items-center justify-between h-16 px-6 border-b border-gray-200 bg-gradient-to-r from-blue-600 to-blue-700">
                    <div :class="['flex items-center gap-3 min-w-0 flex-1']">
                        <div
                            class="flex-shrink-0 bg-white rounded-lg p-1.5 h-10 min-w-[2.5rem] max-w-[160px] flex items-center justify-center overflow-hidden shadow-sm"
                        >
                            <img
                                v-if="currentOrganizationLogoUrl"
                                :src="currentOrganizationLogoUrl"
                                :alt="currentOrganizationName"
                                class="max-h-full max-w-full object-contain"
                            />
                            <img
                                v-else
                                :src="brandLogoFull"
                                alt=""
                                class="max-h-9 w-auto max-w-[140px] object-contain object-left"
                            />
                        </div>
                        <h1 class="text-xl font-bold text-white truncate min-w-0">{{ currentOrganizationName }}</h1>
                    </div>
                    <button
                        @click="sidebarOpen = false"
                        class="lg:hidden p-2 rounded-md text-white hover:bg-blue-800 transition-colors"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">
                    <Link
                        :href="getRoute('dashboard')"
                        :class="[
                            'flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                            isCurrentRoute('/dashboard') || isCurrentRoute('/')
                                ? 'bg-blue-50 text-blue-700' 
                                : 'text-gray-700 hover:bg-gray-100'
                        ]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        {{ t('sidebar.dashboard') }}
                    </Link>

                    <Link
                        :href="getRoute('inbox.index')"
                        :class="[
                            'flex items-center gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                            isCurrentRoute('/inbox')
                                ? 'bg-blue-50 text-blue-700' 
                                : 'text-gray-700 hover:bg-gray-100'
                        ]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        {{ t('sidebar.inbox') }}
                    </Link>

                    <div>
                        <button
                            type="button"
                            @click="toggleMenu('coreData')"
                            :class="[
                                'w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                isCoreDataSectionActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18" />
                                </svg>
                                {{ t('sidebar.clients') }}
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isMenuOpen('coreData') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="isMenuOpen('coreData')" class="mt-1 space-y-1">
                            <Link
                                :href="getRoute('customers.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/customers')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ t('sidebar.customers') }}
                            </Link>
                            <Link
                                :href="getRoute('projects.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/projects')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                </svg>
                                {{ t('sidebar.projects') }}
                            </Link>
                            <Link
                                :href="getRoute('industries.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/industries')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                {{ t('sidebar.industries') }}
                            </Link>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            @click="toggleMenu('commerce')"
                            :class="[
                                'w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                isCommerceSectionActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m1.6 8L5.4 5M7 13l-1.2 6.5a1 1 0 001 1.2h12.4a1 1 0 001-1.2L19 13M9 19.5a.5.5 0 11-1 0 .5.5 0 011 0zm8 0a.5.5 0 11-1 0 .5.5 0 011 0z" />
                                </svg>
                                {{ t('sidebar.commerce') }}
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isMenuOpen('commerce') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="isMenuOpen('commerce')" class="mt-1 space-y-1">
                            <Link
                                :href="getRoute('invoices.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/invoices')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4m2 7H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ t('sidebar.invoices') }}
                            </Link>
                            <Link
                                :href="getRoute('products.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/products')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10l-8-4V7m8 14V11m0 0L4 7m8 4l8-4" />
                                </svg>
                                {{ t('sidebar.products') }}
                            </Link>
                            <Link
                                :href="getRoute('services.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/services')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-1.414-1.414a2 2 0 00-2.828 0l-8.486 8.486a2 2 0 00-.586 1.414V18h3.878a2 2 0 001.414-.586l8.486-8.486a2 2 0 000-2.828z" />
                                </svg>
                                {{ t('sidebar.services') }}
                            </Link>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            @click="toggleMenu('campaigns')"
                            :class="[
                                'w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                isCampaignsSectionActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                                </svg>
                                {{ t('sidebar.campaigns') }}
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isMenuOpen('campaigns') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="isMenuOpen('campaigns')" class="mt-1 space-y-1">
                            <Link
                                :href="getRoute('campaigns.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/campaigns')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h10M7 16h6" />
                                </svg>
                                {{ t('sidebar.campaign_list') }}
                            </Link>
                            <Link
                                :href="getRoute('campaign-templates.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/campaign-templates')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                {{ t('sidebar.templates') }}
                            </Link>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            @click="toggleMenu('telegram')"
                            :class="[
                                'w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                isTelegramSectionActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                                {{ t('sidebar.telegram') }}
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isMenuOpen('telegram') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="isMenuOpen('telegram')" class="mt-1 space-y-1">
                            <Link
                                :href="getRoute('telegram-crawler.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/telegram-crawler')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m-7 7h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ t('sidebar.crawler') }}
                            </Link>
                            <Link
                                :href="getRoute('telegram-groups.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/telegram-groups')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 0 014 0z" /></svg>
                                {{ t('sidebar.groups') }}
                            </Link>
                            <Link
                                :href="getRoute('telegram.scheduled-sends.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/telegram/scheduled-sends')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                {{ t('sidebar.scheduled_sends') }}
                            </Link>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            @click="toggleMenu('tools')"
                            :class="[
                                'w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                isToolsSectionActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ t('sidebar.tools') }}
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isMenuOpen('tools') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="isMenuOpen('tools')" class="mt-1 space-y-1">
                            <Link
                                :href="getRoute('media.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/media')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ t('sidebar.media') }}
                            </Link>
                            <Link
                                :href="getRoute('reports.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/reports')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                {{ t('sidebar.reports') }}
                            </Link>
                            <Link
                                :href="getRoute('scrap-tasks.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/scrap-tasks')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                </svg>
                                {{ t('sidebar.web_scraping') }}
                            </Link>
                        </div>
                    </div>

                    <div v-if="canAccessSettings">
                        <button
                            type="button"
                            @click="toggleMenu('settingsMenu')"
                            :class="[
                                'w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                isSettingsSubtreeActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ t('sidebar.settings') }}
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isMenuOpen('settingsMenu') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="isMenuOpen('settingsMenu')" class="mt-1 space-y-1">
                            <Link
                                v-if="canManageOrganizationSettings"
                                :href="settingsTabUrl('organization')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isSettingsOrganizationNavActive
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                {{ t('settings.tabs.organization_subscription') }}
                            </Link>
                            <Link
                                v-if="showUserManagementNav"
                                :href="getRoute('settings.users.index')"
                                :class="[
                                    'flex items-center gap-2 px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                    isCurrentRoute('/settings/users')
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-gray-700 hover:bg-gray-100'
                                ]"
                            >
                                {{ t('settings.tabs.users') }}
                            </Link>
                            <div v-if="canManageOrganizationSettings" class="space-y-1">
                                <button
                                    type="button"
                                    @click="toggleMenu('channelsNested')"
                                    :class="[
                                        'w-full flex items-center justify-between gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12',
                                        isChannelsMenuSectionActive
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-gray-600 hover:bg-gray-50'
                                    ]"
                                >
                                    <span>{{ t('sidebar.channels') }}</span>
                                    <svg class="w-4 h-4 flex-shrink-0 transition-transform" :class="{ 'rotate-180': isMenuOpen('channelsNested') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div v-if="isMenuOpen('channelsNested')" class="space-y-1">
                                    <Link
                                        v-for="item in channelNavItems"
                                        :key="item.tab"
                                        :href="settingsTabUrl(item.tab)"
                                        :class="[
                                            'flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors ltr:pl-16 rtl:pr-16',
                                            isSettingsTabNavActive(item.tab)
                                                ? 'bg-blue-50 text-blue-700'
                                                : 'text-gray-700 hover:bg-gray-100'
                                        ]"
                                    >
                                        {{ t(item.labelKey) }}
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SuperAdmin -->
                    <div v-if="isSuperAdmin" class="pt-3 mt-3 border-t border-gray-200">
                        <button
                            type="button"
                            @click="toggleMenu('superadmin')"
                            :class="[
                                'w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium rounded-lg transition-colors',
                                isSuperAdminSectionActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-700 hover:bg-gray-100'
                            ]"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" />
                                </svg>
                                {{ t('superadmin.title') }}
                            </span>
                            <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': isMenuOpen('superadmin') }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="isMenuOpen('superadmin')" class="mt-1 space-y-1">
                            <Link :href="getRoute('superadmin.social-media-platforms.index')" :class="['flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12', isCurrentRoute('/superadmin/social-media-platforms') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']">{{ t('superadmin.social_media_platforms') }}</Link>
                            <Link :href="getRoute('superadmin.languages.index')" :class="['flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12', isCurrentRoute('/superadmin/languages') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']">{{ t('superadmin.languages') }}</Link>
                            <Link :href="getRoute('superadmin.translations.index')" :class="['flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12', isCurrentRoute('/superadmin/translations') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']">{{ t('superadmin.translations') }}</Link>
                            <Link :href="getRoute('superadmin.organizations.index')" :class="['flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12', isCurrentRoute('/superadmin/organizations') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']">{{ t('superadmin.organizations') }}</Link>
                            <Link :href="getRoute('superadmin.plans.index')" :class="['flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12', isCurrentRoute('/superadmin/plans') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']">{{ t('superadmin.plans') }}</Link>
                            <Link :href="getRoute('superadmin.platform-notifications.index')" :class="['flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12', isCurrentRoute('/superadmin/platform-notifications') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']">{{ t('superadmin.platform_notifications') }}</Link>
                            <Link :href="getRoute('superadmin.subscriptions.index')" :class="['flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors ltr:pl-12 rtl:pr-12', isCurrentRoute('/superadmin/subscriptions') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100']">{{ t('superadmin.subscriptions') }}</Link>
                        </div>
                    </div>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <div :class="[isRtl ? 'lg:pr-64' : 'lg:pl-64']">
            <!-- Header -->
            <header class="sticky top-0 z-40 flex items-center justify-between h-16 px-4 bg-white border-b border-gray-200 shadow-sm lg:px-8">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="lg:hidden p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 transition-colors"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="flex-1 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <slot name="header" />
                    </h2>
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <div v-if="organizations.length" class="flex items-center space-x-2 rtl:space-x-reverse">
                            <label for="org-switcher" class="hidden md:block text-sm text-gray-500">{{ t('common.organization') }}</label>
                            <select
                                id="org-switcher"
                                v-model="selectedOrganizationId"
                                @change="switchOrganization"
                                class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option
                                    v-for="organization in organizations"
                                    :key="organization.id"
                                    :value="organization.id"
                                >
                                    {{ organization.name }}
                                </option>
                            </select>
                        </div>
                        <div v-if="languages.length" class="flex items-center space-x-2 rtl:space-x-reverse">
                            <label for="locale-switcher" class="hidden md:block text-sm text-gray-500">{{ t('common.language') }}</label>
                            <select
                                id="locale-switcher"
                                v-model="selectedLocale"
                                @change="switchLocale"
                                class="px-3 py-2 text-sm border border-gray-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option v-for="lang in languages" :key="lang.code" :value="lang.code">
                                    {{ lang.name }} ({{ lang.code }})
                                </option>
                            </select>
                        </div>
                        <div class="relative" ref="userMenuRef">
                            <button
                                type="button"
                                @click="userMenuOpen = !userMenuOpen"
                                class="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition-colors"
                            >
                                <div class="w-9 h-9 rounded-full bg-blue-600 text-white text-sm font-semibold flex items-center justify-center overflow-hidden">
                                    <img v-if="userAvatarUrl" :src="userAvatarUrl" :alt="t('common.avatar')" class="w-full h-full object-cover" />
                                    <span v-else>{{ userInitials }}</span>
                                </div>
                            </button>

                            <div
                                v-if="userMenuOpen"
                                :class="[
                                    'absolute mt-2 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50',
                                    isRtl ? 'left-0' : 'right-0',
                                ]"
                            >
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $page.props.auth.user?.name }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $page.props.auth.user?.email }}</p>
                                    <span
                                        v-if="$page.props.currentOrganizationRole"
                                        class="inline-block mt-2 px-2 py-0.5 text-[11px] font-medium rounded-full bg-blue-100 text-blue-700"
                                    >
                                        {{ formattedCurrentOrganizationRole }}
                                    </span>
                                </div>

                                <div class="p-2">
                                    <Link
                                        :href="getRoute('profile.index')"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 rounded-md hover:bg-gray-100"
                                        @click="userMenuOpen = false"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1118.88 17.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ t('common.profile') }}
                                    </Link>
                                    <button
                                        type="button"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-sm text-red-700 rounded-md hover:bg-red-50"
                                        @click="logoutFromMenu"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                        </svg>
                                        {{ t('common.logout') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-4 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from '@/composables/useI18n';

const sidebarOpen = ref(true);
/** Default branding when organization has no custom logo (public/) */
const brandLogoFull = '/brand/logo-full-96px.png';
const page = usePage();
const { load: loadLocaleMessages } = useI18n();
const organizations = computed(() => page.props.organizations || []);
const languages = computed(() => page.props.languages || []);
const selectedOrganizationId = ref(page.props.auth?.user?.current_organization_id || null);
const selectedLocale = ref(page.props.i18n?.locale || null);
const userMenuOpen = ref(false);
const userMenuRef = ref(null);
const openMenus = ref({
    coreData: false,
    commerce: false,
    campaigns: false,
    telegram: false,
    tools: false,
    settingsMenu: false,
    channelsNested: false,
    superadmin: false,
});

const channelNavItems = [
    { tab: 'smtp', labelKey: 'settings.tabs.smtp' },
    { tab: 'ronibot', labelKey: 'settings.tabs.ronibot' },
    { tab: 'telegram', labelKey: 'settings.tabs.telegram_inbox' },
    { tab: 'instagram', labelKey: 'settings.tabs.instagram_inbox' },
    { tab: 'tiktok', labelKey: 'settings.tabs.tiktok_inbox' },
    { tab: 'google-contacts', labelKey: 'settings.tabs.google_contacts' },
];
const currentOrganizationName = computed(() => {
    const fromProps = page.props.currentOrganization?.name;
    if (fromProps) {
        return fromProps;
    }
    const orgId = page.props.auth?.user?.current_organization_id;
    const current = organizations.value.find((organization) => String(organization.id) === String(orgId));
    return current?.name || 'RoniCRM';
});

const currentOrganizationLogoUrl = computed(() => page.props.currentOrganization?.logo_url || null);
const isCampaignsSectionActive = computed(() => {
    return isCurrentRoute('/campaigns') || isCurrentRoute('/campaign-templates');
});
const isCoreDataSectionActive = computed(() => {
    return isCurrentRoute('/customers')
        || isCurrentRoute('/projects')
        || isCurrentRoute('/industries');
});
const isCommerceSectionActive = computed(() => {
    return isCurrentRoute('/invoices')
        || isCurrentRoute('/products')
        || isCurrentRoute('/services');
});
const isTelegramSectionActive = computed(() => {
    return isCurrentRoute('/telegram-crawler')
        || isCurrentRoute('/telegram-groups')
        || isCurrentRoute('/telegram/scheduled-sends');
});
const isToolsSectionActive = computed(() => {
    return isCurrentRoute('/media')
        || isCurrentRoute('/reports')
        || isCurrentRoute('/scrap-tasks');
});
const canManageOrganizationSettings = computed(() => Boolean(page.props.canManageOrganizationSettings));
const showUserManagementNav = computed(() => (page.props.userManagementScope || 'none') !== 'none');

function settingsQueryTab() {
    const raw = String(page.url || '');
    if (!raw.includes('?')) {
        return null;
    }
    return new URLSearchParams(raw.split('?')[1] || '').get('tab');
}

const isSettingsOrganizationNavActive = computed(() => {
    if (!canManageOrganizationSettings.value) {
        return false;
    }
    const path = String(page.url || '').split('?')[0];
    if (path !== '/settings') {
        return false;
    }
    const t = settingsQueryTab();
    return t === 'organization' || t === null || t === '';
});

const isChannelsMenuSectionActive = computed(() => {
    if (!canManageOrganizationSettings.value) {
        return false;
    }
    const path = String(page.url || '').split('?')[0];
    if (path !== '/settings') {
        return false;
    }
    const t = settingsQueryTab() || '';
    return ['smtp', 'ronibot', 'telegram', 'instagram', 'tiktok', 'google-contacts'].includes(t);
});

const isSettingsSubtreeActive = computed(() => {
    return isSettingsOrganizationNavActive.value
        || isCurrentRoute('/settings/users')
        || isChannelsMenuSectionActive.value;
});

function isSettingsTabNavActive(tab) {
    return settingsQueryTab() === tab;
}

function settingsTabUrl(tab) {
    if (typeof window !== 'undefined' && window.route) {
        try {
            return window.route('settings.index', { tab });
        } catch (e) {
            // fall through
        }
    }
    return `/settings?tab=${encodeURIComponent(tab)}`;
}

const canAccessSettings = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    const currentOrganizationRole = page.props.currentOrganizationRole || null;
    return roles.includes('admin') || roles.includes('super_admin') || currentOrganizationRole === 'org_admin';
});
const isSuperAdmin = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('super_admin') || roles.includes('admin');
});
const isSuperAdminSectionActive = computed(() => {
    return isCurrentRoute('/superadmin');
});
const userAvatarUrl = computed(() => page.props.auth?.user?.avatar_url || null);
const currentLanguage = computed(() => {
    const code = selectedLocale.value;
    if (!code) return null;
    return languages.value.find((l) => l.code === code) || null;
});

const currentDir = computed(() => {
    // Prefer server dir: it uses app locale + Language row, not the org-filtered `languages` list
    // (SuperAdmin / restricted orgs may omit the active locale from `languages` while session stays fa).
    const fromHtml = page.props.html?.dir;
    if (fromHtml === 'rtl' || fromHtml === 'ltr') {
        return fromHtml;
    }

    return currentLanguage.value?.direction === 'rtl' ? 'rtl' : 'ltr';
});

const isRtl = computed(() => currentDir.value === 'rtl');

const currentFontStyle = computed(() => {
    const f = currentLanguage.value?.font_family;
    if (!f) return {};
    const stack = `${String(f).trim()}, ui-sans-serif, system-ui, sans-serif`;
    return { fontFamily: stack };
});
const formattedCurrentOrganizationRole = computed(() => {
    const raw = page.props.currentOrganizationRole;
    if (!raw) {
        return '';
    }

    return String(raw)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (ch) => ch.toUpperCase());
});
const userInitials = computed(() => {
    const name = (page.props.auth?.user?.name || '').trim();
    if (!name) {
        return 'U';
    }

    const parts = name.split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] || '';
    const second = parts.length > 1 ? (parts[1]?.[0] || '') : '';
    return (first + second).toUpperCase() || 'U';
});

// Route helper function
const getRoute = (name) => {
    if (typeof window !== 'undefined' && window.route) {
        try {
            return window.route(name);
        } catch (e) {
            // Fallback to direct URLs
        }
    }
    // Fallback routes
    const routes = {
        'dashboard': '/dashboard',
        'customers.index': '/customers',
        'campaigns.index': '/campaigns',
        'campaign-templates.index': '/campaign-templates',
        'inbox.index': '/inbox',
        'media.index': '/media',
        'industries.index': '/industries',
        'projects.index': '/projects',
        'invoices.index': '/invoices',
        'products.index': '/products',
        'services.index': '/services',
        'reports.index': '/reports',
        'scrap-tasks.index': '/scrap-tasks',
        'settings.index': '/settings',
        'settings.users.index': '/settings/users',
        'profile.index': '/profile',
        'superadmin.social-media-platforms.index': '/superadmin/social-media-platforms',
        'superadmin.languages.index': '/superadmin/languages',
        'superadmin.translations.index': '/superadmin/translations',
        'superadmin.organizations.index': '/superadmin/organizations',
        'superadmin.plans.index': '/superadmin/plans',
        'superadmin.platform-notifications.index': '/superadmin/platform-notifications',
        'superadmin.subscriptions.index': '/superadmin/subscriptions',
    };
    return routes[name] || '/';
};

const isCurrentRoute = (pattern) => {
    const current = String(page.url || '').split('?')[0];
    const normalizedPattern = String(pattern || '');
    if (pattern.includes('*')) {
        const base = normalizedPattern.replace('*', '');
        return current.startsWith(base);
    }
    return current === normalizedPattern || current.startsWith(`${normalizedPattern}/`);
};

const logout = () => {
    router.post('/logout');
};
const logoutFromMenu = () => {
    userMenuOpen.value = false;
    logout();
};

const toggleMenu = (menuKey) => {
    if (isMenuOpen(menuKey) && isMenuRouteActive(menuKey)) {
        return;
    }

    openMenus.value[menuKey] = !openMenus.value[menuKey];
};

const isMenuRouteActive = (menuKey) => {
    switch (menuKey) {
        case 'coreData':
            return isCoreDataSectionActive.value;
        case 'commerce':
            return isCommerceSectionActive.value;
        case 'campaigns':
            return isCampaignsSectionActive.value;
        case 'telegram':
            return isTelegramSectionActive.value;
        case 'tools':
            return isToolsSectionActive.value;
        case 'settingsMenu':
            return isSettingsSubtreeActive.value;
        case 'channelsNested':
            return isChannelsMenuSectionActive.value;
        case 'superadmin':
            return isSuperAdminSectionActive.value;
        default:
            return false;
    }
};

const isMenuOpen = (menuKey) => {
    return Boolean(openMenus.value[menuKey]) || isMenuRouteActive(menuKey);
};

const switchOrganization = () => {
    if (!selectedOrganizationId.value) {
        return;
    }

    router.post('/organizations/current', {
        organization_id: selectedOrganizationId.value,
    }, {
        preserveState: false,
        preserveScroll: false,
    });
};

const switchLocale = async () => {
    if (!selectedLocale.value) {
        return;
    }
    const code = selectedLocale.value;
    const langMeta = languages.value.find((l) => String(l.code) === String(code));
    if (langMeta && typeof document !== 'undefined') {
        const dir = langMeta.direction === 'rtl' ? 'rtl' : 'ltr';
        document.documentElement.setAttribute('dir', dir);
        document.documentElement.setAttribute('lang', String(code).replace('_', '-'));
    }
    try {
        await window.axios.post('/i18n/locale', { locale: code });
        // Apply locale messages immediately without requiring hard refresh.
        await loadLocaleMessages(code, `/i18n/${code}.json`);
        router.reload({
            preserveState: true,
            preserveScroll: true,
            only: ['i18n', 'languages', 'html'],
        });
    } catch (e) {
        // If setting locale fails, fallback to full reload.
        window.location.reload();
    }
};

const handleDocumentClick = (event) => {
    if (!userMenuOpen.value) {
        return;
    }

    if (userMenuRef.value && !userMenuRef.value.contains(event.target)) {
        userMenuOpen.value = false;
    }
};

watch(
    () => page.props.auth?.user?.current_organization_id,
    (newValue) => {
        selectedOrganizationId.value = newValue || null;
    }
);

watch(
    () => page.props.i18n?.locale,
    (newValue) => {
        selectedLocale.value = newValue || null;
    }
);

watch(
    () => [page.props.html?.dir, page.props.html?.lang],
    ([dir, lang]) => {
        if (typeof document === 'undefined') {
            return;
        }
        if (dir === 'rtl' || dir === 'ltr') {
            document.documentElement.setAttribute('dir', dir);
        }
        if (lang && typeof lang === 'string') {
            document.documentElement.setAttribute('lang', lang);
        }
    },
    { immediate: true }
);

watch(
    () => languages.value,
    (langs) => {
        const codes = (langs || []).map((l) => String(l.code));
        if (!codes.length) {
            return;
        }
        const cur = String(selectedLocale.value || '');
        if (cur && !codes.includes(cur)) {
            selectedLocale.value = codes[0];
        }
    },
    { deep: true }
);

onMounted(() => {
    document.addEventListener('click', handleDocumentClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleDocumentClick);
});
</script>
