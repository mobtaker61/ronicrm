<template>
    <AppLayout>
        <template #header>
            <span>{{ t('sidebar.inbox') }}</span>
        </template>

        <!-- هشدارهای flash: toast گوشهٔ صفحه، قابل بستن + خودکار پس از ~۱۰ ثانیه -->
        <Teleport to="body">
            <Transition name="inbox-flash-toast">
                <div
                    v-if="flashToastVisible && (flashSuccess || flashError)"
                    class="fixed top-20 end-4 z-[100] w-[min(100vw-2rem,28rem)] pointer-events-none"
                    role="status"
                    aria-live="polite"
                >
                    <div
                        :class="[
                            'pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg',
                            flashError
                                ? 'border-red-200 bg-red-50 text-red-900'
                                : 'border-green-200 bg-green-50 text-green-900'
                        ]"
                    >
                        <p class="min-w-0 flex-1 text-sm leading-relaxed break-words">
                            {{ flashSuccess || flashError }}
                        </p>
                        <button
                            type="button"
                            class="flex-shrink-0 rounded-lg p-1 text-current opacity-70 hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-current"
                            :aria-label="t('common.close')"
                            @click="dismissFlashToast"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Full Height Inbox Container - Negative margins to override AppLayout padding -->
        <div class="-m-4 lg:-m-8 h-[calc(100vh-64px)] flex flex-col bg-white overflow-hidden">
            <div class="flex flex-1 overflow-hidden">
                <!-- Conversations List (Left Sidebar) -->
                <div class="w-[26%] min-w-[220px] max-w-[320px] bg-white border-r border-gray-200 rtl:border-r-0 rtl:border-l flex flex-col flex-shrink-0">
                    <!-- Instagram: درخواست اجازه نوتیفیکیشن (مرورگر فقط با کلیک کاربر اجازه می‌دهد) -->
                    <div
                        v-if="(messageChannel === 'instagram' || messageChannel === 'tiktok' || channel === 'instagram' || channel === 'tiktok') && notificationPermission === 'default'"
                        class="flex-shrink-0 px-4 py-2 bg-amber-50 border-b border-amber-200"
                    >
                        <button
                            type="button"
                            @click="requestNotificationPermission"
                            class="w-full text-left rtl:text-right text-sm text-amber-800 hover:text-amber-900 py-1"
                        >
                            🔔 {{ t('inbox.click_to_enable_notifications') }}
                        </button>
                    </div>
                    <!-- Channel icons + Search -->
                    <div class="flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50 relative z-10">
                        <div class="flex items-center justify-center gap-1.5 mb-3" role="tablist" :aria-label="t('inbox.channel_filter_aria')">
                            <a
                                :href="route('inbox.index', { channel: 'all' })"
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-lg border transition-colors',
                                    channel === 'all'
                                        ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-100'
                                ]"
                                :title="t('inbox.all_channels')"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                </svg>
                            </a>
                            <a
                                :href="route('inbox.index', { channel: 'whatsapp' })"
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-lg border transition-colors',
                                    channel === 'whatsapp'
                                        ? 'border-green-600 bg-green-600 text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-green-600 hover:bg-green-50'
                                ]"
                                :title="t('inbox.whatsapp')"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.881 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </a>
                            <a
                                :href="route('inbox.index', { channel: 'telegram' })"
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-lg border transition-colors',
                                    channel === 'telegram'
                                        ? 'border-sky-600 bg-sky-600 text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-sky-500 hover:bg-sky-50'
                                ]"
                                :title="t('sidebar.telegram')"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                            </a>
                            <a
                                :href="route('inbox.index', { channel: 'instagram' })"
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-lg border transition-colors',
                                    channel === 'instagram'
                                        ? 'border-pink-600 bg-gradient-to-br from-purple-600 via-pink-600 to-orange-400 text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-pink-600 hover:bg-pink-50'
                                ]"
                                :title="t('settings.tabs.instagram_inbox')"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </a>
                            <a
                                :href="route('inbox.index', { channel: 'tiktok' })"
                                :class="[
                                    'flex h-10 w-10 items-center justify-center rounded-lg border transition-colors',
                                    channel === 'tiktok'
                                        ? 'border-gray-900 bg-gray-900 text-white shadow-sm'
                                        : 'border-gray-200 bg-white text-gray-900 hover:bg-gray-100'
                                ]"
                                :title="t('settings.tabs.tiktok_inbox')"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                            </a>
                        </div>
                        <div class="relative">
                            <input
                                v-model="searchPhone"
                                @input="searchCustomers"
                                @focus="showSearchResults = true"
                                type="text"
                                :placeholder="searchPlaceholder"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            
                            <!-- Search Results Dropdown (حداقل ۲ کاراکتر تا نتایج معنی‌دار باشد) -->
                            <div
                                v-if="showSearchResults && searchPhone.trim().length >= 2 && (searchResults.length > 0 || searchPhone.trim())"
                                class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto"
                            >
                                <!-- Customer Results -->
                                <div
                                    v-for="result in searchResults"
                                    :key="result.id"
                                    @click="selectCustomerFromSearch(result)"
                                    class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 flex items-center space-x-3 rtl:space-x-reverse"
                                >
                                    <div
                                        v-if="result.avatar"
                                        class="w-10 h-10 rounded-full bg-cover bg-center border-2 border-gray-200"
                                        :style="{ backgroundImage: `url(${result.avatar})` }"
                                    ></div>
                                    <div
                                        v-else
                                        class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold border-2 border-gray-200"
                                    >
                                        {{ result.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate">{{ result.name }}</p>
                                        <p class="text-xs text-gray-500">{{ result.phone || result.chat_id || result.ig_user_id || result.tiktok_open_id }}</p>
                                    </div>
                                </div>
                                
                                <!-- Send to New Number (WhatsApp only) -->
                                <div
                                    v-if="(channel === 'whatsapp' || channel === 'all') && searchPhone.trim() && searchResults.length === 0"
                                    class="px-4 py-3 border-t border-gray-200"
                                >
                                    <button
                                        @click="startNewConversation(searchPhone)"
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium flex items-center justify-center space-x-2 rtl:space-x-reverse"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>{{ t('inbox.send_to').replace(':value', searchPhone) }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conversations List (Scrollable) -->
                    <div class="flex-1 overflow-y-auto">
                        <div
                            v-for="conv in filteredConversations"
                            :key="(conv.channel || channel) + ':' + (conv.phone || conv.chat_id || conv.ig_user_id || conv.tiktok_open_id || '')"
                            @click="selectConversation(conv)"
                            :class="[
                                'px-4 py-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors',
                                isConversationSelected(conv) ? 'bg-blue-50 border-l-4 border-l-blue-600 rtl:border-l-0 rtl:border-r-4 rtl:border-r-blue-600' : ''
                            ]"
                        >
                            <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                <!-- Avatar -->
                                <div class="flex-shrink-0">
                                    <div
                                        v-if="conv.avatar"
                                        class="w-8 h-8 rounded-full bg-cover bg-center border border-gray-200"
                                        :style="{ backgroundImage: `url(${conv.avatar})` }"
                                    ></div>
                                    <div
                                        v-else
                                        class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-xs font-semibold border border-gray-200"
                                    >
                                        {{ conv.name.charAt(0).toUpperCase() }}
                                    </div>
                                </div>

                                <!-- Conversation Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between mb-1 gap-2">
                                        <h3 class="text-sm font-semibold text-gray-900 truncate min-w-0">
                                            {{ conv.name }}
                                        </h3>
                                        <div class="flex items-center flex-shrink-0 gap-1.5">
                                            <span
                                                v-if="channel === 'all' && conv.channel"
                                                class="inline-flex h-5 w-5 items-center justify-center rounded text-[10px] font-bold uppercase leading-none"
                                                :class="{
                                                    'bg-green-100 text-green-700': conv.channel === 'whatsapp',
                                                    'bg-sky-100 text-sky-700': conv.channel === 'telegram',
                                                    'bg-pink-100 text-pink-700': conv.channel === 'instagram',
                                                    'bg-gray-200 text-gray-800': conv.channel === 'tiktok',
                                                }"
                                                :title="channelLabel(conv.channel)"
                                            >
                                                {{ conv.channel === 'whatsapp' ? 'W' : conv.channel === 'telegram' ? 'T' : conv.channel === 'instagram' ? 'I' : 'K' }}
                                            </span>
                                            <span
                                                v-if="conv.last_message_at"
                                                class="text-xs text-gray-500"
                                            >
                                                {{ formatTime(conv.last_message_at) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm text-gray-600 truncate">
                                            {{ conv.last_message || t('inbox.no_messages') }}
                                        </p>
                                        <span
                                            v-if="conv.unread_count > 0"
                                            class="bg-blue-600 text-white text-xs font-semibold px-2 py-1 rounded-full flex-shrink-0 ltr:ml-2 rtl:mr-2"
                                        >
                                            {{ conv.unread_count }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="filteredConversations.length === 0"
                            class="px-4 py-8 text-center text-gray-500"
                        >
                            <p>{{ t('inbox.no_conversations_found') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Messages Area (Middle) -->
                <div class="flex-1 flex flex-col bg-gray-50 min-w-0">
                    <div v-if="selectedContact || (((messageChannel || channel) === 'instagram' || (messageChannel || channel) === 'tiktok') && selectedCustomer && !selectedContact)" class="flex-1 flex flex-col min-h-0">
                        <!-- Conversation Header (Sticky) -->
                        <div class="flex-shrink-0 bg-white px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <div class="flex items-center space-x-3 rtl:space-x-reverse min-w-0">
                                <div
                                    v-if="(selectedConversation?.avatar || selectedCustomer?.avatar)"
                                    class="w-10 h-10 rounded-full bg-cover bg-center border-2 border-gray-200 flex-shrink-0"
                                    :style="{ backgroundImage: `url(${selectedConversation?.avatar || selectedCustomer?.avatar})` }"
                                ></div>
                                <div
                                    v-else
                                    class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold border-2 border-gray-200 flex-shrink-0"
                                >
                                    {{ (getDisplayName(selectedConversation) || selectedCustomer?.name)?.charAt(0).toUpperCase() || selectedContact?.toString()?.charAt(0) || '?' }}
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-lg font-semibold text-gray-900 truncate">
                                        {{ getDisplayName(selectedConversation) || selectedCustomer?.name }}
                                    </h2>
                                    <p v-if="!hasCustomer && !noConversationYet" class="text-sm text-gray-500 truncate">{{ (messageChannel || channel) === 'instagram' ? selectedIgUserId : ((messageChannel || channel) === 'tiktok' ? selectedTikTokOpenId : ((messageChannel || channel) === 'telegram' ? selectedChatId : selectedPhone)) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 rtl:space-x-reverse flex-shrink-0">
                                <template v-if="noConversationYet">
                                    <Link
                                        :href="route('customers.show', selectedCustomer.id)"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors whitespace-nowrap"
                                    >
                                        {{ t('inbox.view_customer') }}
                                    </Link>
                                </template>
                                <template v-else-if="!hasCustomer">
                                    <button
                                        v-if="(messageChannel || channel) === 'instagram' || (messageChannel || channel) === 'tiktok'"
                                        @click="showAssignCustomerModal = true"
                                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors whitespace-nowrap"
                                    >
                                        {{ t('inbox.assign_to_customer') }}
                                    </button>
                                    <button
                                        @click="showCreateCustomerModal = true"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors whitespace-nowrap"
                                    >
                                        {{ t('inbox.add_as_customer') }}
                                    </button>
                                </template>
                                <Link
                                    v-else
                                    :href="route('customers.show', selectedConversation.customer_id)"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors whitespace-nowrap"
                                >
                                    {{ t('inbox.view_customer') }}
                                </Link>
                            </div>
                        </div>

                        <!-- No conversation yet (Instagram / TikTok: customer has contact but no thread) -->
                        <div v-if="noConversationYet" class="flex-1 flex items-center justify-center p-8 bg-gray-50">
                            <div class="text-center max-w-md">
                                <template v-if="(messageChannel || channel) === 'tiktok'">
                                    <p class="text-gray-600 mb-2">{{ t('inbox.tiktok_no_message_yet') }}</p>
                                    <p class="text-sm text-gray-500 mb-2">{{ t('inbox.tiktok_no_message_help_1') }}</p>
                                    <p class="text-xs text-gray-400 mb-4">{{ t('inbox.tiktok_no_message_help_2') }}</p>
                                </template>
                                <template v-else>
                                    <p class="text-gray-600 mb-2">{{ t('inbox.instagram_no_message_yet') }}</p>
                                    <p class="text-sm text-gray-500 mb-2">{{ t('inbox.instagram_no_message_help_1') }}</p>
                                    <p class="text-xs text-gray-400 mb-4">{{ t('inbox.instagram_no_message_help_2') }}</p>
                                </template>
                                <Link :href="route('customers.show', selectedCustomer.id)" class="text-blue-600 hover:underline font-medium">{{ t('inbox.view_customer_card') }}</Link>
                            </div>
                        </div>

                        <!-- Messages (Scrollable) -->
                        <div v-else ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4 bg-gray-50">
                            <div
                                v-for="msg in messages"
                                :key="msg.id"
                                :class="[
                                    'flex',
                                    msg.direction === 'outgoing' ? 'justify-end' : 'justify-start'
                                ]"
                            >
                                <div
                                    :class="[
                                        'max-w-xs lg:max-w-md px-4 py-2 rounded-lg shadow-sm',
                                        msg.direction === 'outgoing'
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-white text-gray-900'
                                    ]"
                                >
                                    <div
                                        v-if="msg.media_url"
                                        class="mb-2 rounded-lg overflow-hidden max-w-full"
                                    >
                                        <!-- Image / sticker — پیش‌نمایش کوچک؛ کلیک = مودال تمام‌صفحه -->
                                        <img
                                            v-if="isImageFile(msg.message_type, msg.media_url)"
                                            :src="cleanMediaUrl(msg.media_url)"
                                            :alt="t('common.media')"
                                            class="max-h-[256px] max-w-full w-auto object-contain rounded cursor-pointer hover:opacity-90 transition-opacity mx-auto block"
                                            @click="lightboxImageUrl = cleanMediaUrl(msg.media_url)"
                                            @error="(e) => { console.error('Image load error:', e, msg.media_url); handleImageError(e); }"
                                        />
                                        <!-- صوت — موج + پخش واقعی (بدون display:none روی audio) -->
                                        <VoiceWavePlayer
                                            v-else-if="isAudioFile(msg.message_type, msg.media_url)"
                                            :src="cleanMediaUrl(msg.media_url)"
                                            :audio-id="msg.id"
                                            v-model:active-audio-id="playingAudioId"
                                            :direction="msg.direction"
                                        />
                                        <!-- ویدیو — پخش درجا -->
                                        <video
                                            v-else-if="isVideoFile(msg.message_type, msg.media_url)"
                                            :src="cleanMediaUrl(msg.media_url)"
                                            controls
                                            playsinline
                                            preload="metadata"
                                            class="max-w-full max-h-[min(70vh,512px)] w-full rounded bg-black object-contain"
                                        />
                                        <!-- Other files -->
                                        <div
                                            v-else
                                            class="p-4 bg-gray-100 rounded-lg border border-gray-300 cursor-pointer hover:bg-gray-200 transition-colors"
                                            @click="openFileModal(msg.media_url)"
                                        >
                                            <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                                <svg class="w-10 h-10 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 truncate">
                                                        {{ getFileName(msg.media_url) }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 capitalize">
                                                        {{ msg.message_type || t('common.file') }}
                                                    </p>
                                                </div>
                                                <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-if="msg.message" class="text-sm whitespace-pre-wrap">{{ msg.message }}</p>
                                    <p
                                        :class="[
                                            'text-xs mt-1',
                                            msg.direction === 'outgoing' ? 'text-blue-100' : 'text-gray-500'
                                        ]"
                                    >
                                        {{ formatTime(msg.created_at) }}
                                    </p>
                                </div>
                            </div>

                            <div v-if="messages.length === 0" class="text-center text-gray-500 py-8">
                                <p>{{ t('inbox.no_messages_start_conversation') }}</p>
                            </div>
                        </div>

                        <!-- Message Input (Sticky Bottom) - hide when no conversation yet -->
                        <div v-if="!noConversationYet" class="flex-shrink-0 bg-white border-t border-gray-200 px-6 py-4">
                            <form @submit.prevent class="space-y-3">
                                <!-- File Upload Preview -->
                                <div v-if="selectedFile" class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-2 rtl:space-x-reverse min-w-0">
                                        <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        <span class="text-sm text-gray-700 truncate">{{ selectedFile.name }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearSelectedFile"
                                        class="text-red-600 hover:text-red-700 flex-shrink-0 ltr:ml-2 rtl:mr-2"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        @change="handleFileSelect"
                                        class="hidden"
                                        accept="*"
                                    />
                                    <button
                                        type="button"
                                        @click="$refs.fileInput?.click()"
                                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0"
                                        :title="t('inbox.upload_file')"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="showMediaPicker = true"
                                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0"
                                        :title="t('inbox.select_from_media')"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="showTemplatePicker = true"
                                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0"
                                        :title="t('inbox.select_template')"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                    </button>
                                    <textarea
                                        ref="messageTextarea"
                                        v-model="newMessage"
                                        @keydown.enter.exact="handleEnterKey"
                                        :placeholder="t('inbox.type_message_help')"
                                        rows="1"
                                        class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-0 resize-none"
                                        :style="{ minHeight: '40px', maxHeight: '120px', height: '40px' }"
                                        :disabled="sendingMessage"
                                        @input="autoResizeTextarea"
                                    ></textarea>
                                    <button
                                        type="button"
                                        @click="sendMessage"
                                        :disabled="sendingMessage || (!newMessage.trim() && !selectedFile)"
                                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium transition-colors flex-shrink-0 whitespace-nowrap"
                                    >
                                        {{ sendingMessage ? t('settings.sending') : t('inbox.send') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="flex-1 flex items-center justify-center text-gray-500 bg-gray-50"
                    >
                        <div class="text-center">
                            <svg
                                class="w-16 h-16 mx-auto mb-4 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                                />
                            </svg>
                            <p class="text-lg">
                                {{
                                    channel === 'all'
                                        ? t('inbox.empty_state_all')
                                        : (channel === 'telegram'
                                            ? t('inbox.empty_state_telegram')
                                            : (channel === 'instagram'
                                                ? t('inbox.empty_state_instagram')
                                                : (channel === 'tiktok'
                                                    ? t('inbox.empty_state_tiktok')
                                                    : t('inbox.empty_state_whatsapp'))))
                                }}
                            </p>
                            <div v-if="channel === 'instagram'" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 max-w-md mx-auto text-right">
                                <p class="font-medium mb-1">{{ t('inbox.instagram_send_messages_title') }}</p>
                                <p class="mb-2">{{ t('inbox.instagram_send_messages_help') }}</p>
                                <a :href="route('settings.index')" class="text-amber-700 underline font-medium">{{ t('inbox.go_to_settings') }}</a>
                            </div>
                            <div v-if="channel === 'tiktok'" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 max-w-md mx-auto text-right">
                                <p class="font-medium mb-1">{{ t('inbox.tiktok_send_messages_title') }}</p>
                                <p class="mb-2">{{ t('inbox.tiktok_send_messages_help') }}</p>
                                <a :href="route('settings.index')" class="text-amber-700 underline font-medium">{{ t('inbox.go_to_settings') }}</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Info Panel (Right Side) - 30% -->
                <div v-if="selectedContact" class="w-[30%] bg-white border-l border-gray-200 rtl:border-l-0 rtl:border-r flex flex-col min-w-0 flex-shrink-0 overflow-hidden">
                    <div v-if="selectedCustomer" class="flex-1 overflow-y-auto p-6">
                        <!-- Customer Header -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <div class="flex items-center space-x-4 rtl:space-x-reverse mb-4">
                                <div
                                    v-if="selectedCustomer.avatar"
                                    class="w-20 h-20 rounded-full bg-cover bg-center border-4 border-gray-200 flex-shrink-0"
                                    :style="{ backgroundImage: `url(${selectedCustomer.avatar.startsWith('http') ? selectedCustomer.avatar : '/storage/' + selectedCustomer.avatar})` }"
                                ></div>
                                <div
                                    v-else
                                    class="w-20 h-20 rounded-full bg-blue-500 flex items-center justify-center text-white text-2xl font-semibold border-4 border-gray-200 flex-shrink-0"
                                >
                                    {{ selectedCustomer.name.charAt(0).toUpperCase() }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h2 class="text-xl font-bold text-gray-900 truncate">{{ selectedCustomer.name }}</h2>
                                    <p class="text-sm text-gray-500">{{ (messageChannel || channel) === 'instagram' ? selectedIgUserId : ((messageChannel || channel) === 'tiktok' ? selectedTikTokOpenId : ((messageChannel || channel) === 'telegram' ? selectedChatId : selectedPhone)) }}</p>
                                </div>
                            </div>
                            <Link
                                :href="route('customers.show', selectedCustomer.id)"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors text-center block"
                            >
                                {{ t('inbox.view_full_profile') }}
                            </Link>
                        </div>

                        <!-- Customer Details -->
                        <div class="space-y-6">
                            <!-- Basic Info -->
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">{{ t('inbox.basic_information') }}</h3>
                                <div class="space-y-2">
                                    <div v-if="selectedCustomer.type" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">{{ t('customers.type') }}</span>
                                        <span class="text-sm font-medium text-gray-900 capitalize">{{ selectedCustomer.type }}</span>
                                    </div>
                                    <div v-if="selectedCustomer.status" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">{{ t('common.status') }}</span>
                                        <span class="text-sm font-medium text-gray-900 capitalize">{{ selectedCustomer.status }}</span>
                                    </div>
                                    <div v-if="selectedCustomer.industry" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">{{ t('customers.industry') }}</span>
                                        <span class="text-sm font-medium text-gray-900">{{ selectedCustomer.industry.name }}</span>
                                    </div>
                                    <div v-if="selectedCustomer.source" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">{{ t('customers.source') }}</span>
                                        <span class="text-sm font-medium text-gray-900 capitalize">{{ selectedCustomer.source }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div v-if="selectedCustomer.contacts && selectedCustomer.contacts.length > 0">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">{{ t('inbox.contact_information') }}</h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="contact in selectedCustomer.contacts"
                                        :key="contact.id"
                                        class="flex items-center justify-between py-2 border-b border-gray-100"
                                    >
                                        <span class="text-sm text-gray-600 capitalize">{{ contact.type }}</span>
                                        <span class="text-sm font-medium text-gray-900">{{ contact.value }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Social Media -->
                            <div v-if="selectedCustomer.social_media && selectedCustomer.social_media.length > 0">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">{{ t('customers.social_media') }}</h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="sm in selectedCustomer.social_media"
                                        :key="sm.id"
                                        class="flex items-center space-x-2 rtl:space-x-reverse py-2 border-b border-gray-100"
                                    >
                                        <i v-if="sm.social_media_type?.icon" :class="sm.social_media_type.icon" class="w-5 h-5 text-gray-600"></i>
                                        <span class="text-sm text-gray-600 flex-1">{{ sm.social_media_type?.name || t('customers.social_media') }}</span>
                                        <a :href="sm.url" target="_blank" class="text-sm font-medium text-blue-600 hover:text-blue-700 truncate max-w-[150px]">
                                            {{ sm.url }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- No Customer Info -->
                    <div v-else class="flex-1 flex items-center justify-center p-6">
                        <div class="text-center">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <p class="text-gray-500 mb-4">{{ t('inbox.no_customer_info') }}</p>
                            <button
                                @click="showCreateCustomerModal = true"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors"
                            >
                                {{ t('inbox.add_as_customer') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Customer Modal -->
        <div
            v-if="showCreateCustomerModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showCreateCustomerModal = false"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-semibold mb-4 text-gray-900">{{ t('inbox.add_as_customer') }}</h3>
                <form @submit.prevent="createCustomer">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('customers.name_required') }}
                            </label>
                            <input
                                v-model="customerForm.name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ t('common.email') }}
                            </label>
                            <input
                                v-model="customerForm.email"
                                type="email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ (messageChannel || channel) === 'telegram' ? t('inbox.telegram_chat_id') : ((messageChannel || channel) === 'instagram' ? t('inbox.instagram_user_id') : ((messageChannel || channel) === 'tiktok' ? t('inbox.tiktok_open_id_label') : t('customers.phone'))) }}
                            </label>
                            <input
                                :value="(messageChannel || channel) === 'instagram' ? selectedIgUserId : ((messageChannel || channel) === 'tiktok' ? selectedTikTokOpenId : ((messageChannel || channel) === 'telegram' ? selectedChatId : selectedPhone))"
                                type="text"
                                disabled
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            />
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 rtl:space-x-reverse mt-6">
                        <button
                            type="button"
                            @click="showCreateCustomerModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button
                            type="submit"
                            :disabled="customerForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            {{ customerForm.processing ? t('inbox.creating') : t('inbox.create_customer') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Assign to existing customer modal (Instagram: unknown sender) -->
        <div
            v-if="showAssignCustomerModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showAssignCustomerModal = false"
        >
            <div class="bg-white rounded-lg p-6 w-full max-w-md shadow-xl">
                <h3 class="text-lg font-semibold mb-4 text-gray-900">{{ t('inbox.assign_existing_customer') }}</h3>
                <div class="mb-4">
                    <input
                        v-model="assignSearchQuery"
                        type="text"
                        :placeholder="t('inbox.search_by_name')"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div class="max-h-60 overflow-y-auto border border-gray-200 rounded-md mb-4">
                    <div
                        v-for="c in assignCustomers"
                        :key="c.id"
                        @click="assignCustomerId = c.id"
                        :class="[
                            'px-4 py-3 cursor-pointer hover:bg-gray-50 border-b border-gray-100 last:border-0',
                            assignCustomerId === c.id ? 'bg-blue-50 ring-1 ring-blue-500' : ''
                        ]"
                    >
                        <span class="font-medium text-gray-900">{{ c.name }}</span>
                    </div>
                    <div v-if="assignSearchQuery.trim().length >= 2 && assignCustomers.length === 0" class="px-4 py-6 text-center text-gray-500">
                        {{ t('inbox.no_customers_found') }}
                    </div>
                </div>
                <div class="flex justify-end space-x-3 rtl:space-x-reverse">
                    <button
                        type="button"
                        @click="showAssignCustomerModal = false; assignCustomerId = null; assignSearchQuery = ''"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        type="button"
                        :disabled="!assignCustomerId"
                        @click="submitAssignCustomer"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                    >
                        {{ t('inbox.assign') }}
                    </button>
                </div>
            </div>
        </div>

        <MediaPickerModal
            :show="showMediaPicker"
            @close="showMediaPicker = false"
            @select="onMediaSelect"
        />

        <TemplatePickerModal
            :show="showTemplatePicker"
            :templates="templates"
            @close="showTemplatePicker = false"
            @select="onTemplateSelect"
        />

        <Teleport to="body">
            <div
                v-if="lightboxImageUrl"
                class="fixed inset-0 z-[200] flex items-center justify-center bg-black/85 p-4"
                role="dialog"
                aria-modal="true"
                @click.self="lightboxImageUrl = null"
            >
                <button
                    type="button"
                    class="absolute top-4 right-4 rtl:right-auto rtl:left-4 z-10 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20"
                    :aria-label="t('common.close')"
                    @click="lightboxImageUrl = null"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
                <img
                    :src="lightboxImageUrl"
                    alt=""
                    class="max-h-[100vh] max-w-full object-contain select-none"
                    @click.stop
                />
            </div>
        </Teleport>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MediaPickerModal from '@/Components/MediaPickerModal.vue';
import TemplatePickerModal from '@/Components/TemplatePickerModal.vue';
import VoiceWavePlayer from '@/Components/VoiceWavePlayer.vue';
import { debounce } from 'lodash-es';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    channel: {
        type: String,
        default: 'all',
    },
    messageChannel: {
        type: String,
        default: null,
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    messages: {
        type: Array,
        default: () => [],
    },
    selectedPhone: {
        type: String,
        default: null,
    },
    selectedChatId: {
        type: String,
        default: null,
    },
    selectedIgUserId: {
        type: String,
        default: null,
    },
    selectedTikTokOpenId: {
        type: String,
        default: null,
    },
    searchResults: {
        type: Array,
        default: () => [],
    },
    selectedCustomer: {
        type: Object,
        default: null,
    },
    templates: {
        type: Array,
        default: () => [],
    },
});

const channel = computed(() => props.channel || 'all');
const messageChannel = computed(() => props.messageChannel ?? null);
const selectedContact = computed(() => {
    if (channel.value === 'all') {
        return props.selectedTikTokOpenId || props.selectedIgUserId || props.selectedChatId || props.selectedPhone || null;
    }
    if (channel.value === 'telegram') return props.selectedChatId || null;
    if (channel.value === 'instagram') return props.selectedIgUserId || null;
    if (channel.value === 'tiktok') return props.selectedTikTokOpenId || null;
    return props.selectedPhone || null;
});

const searchPlaceholder = computed(() => {
    if (channel.value === 'all') return t('inbox.search_all_channels');
    if (channel.value === 'telegram') return t('inbox.search_name_or_telegram');
    if (channel.value === 'instagram') return t('inbox.search_name_or_instagram');
    if (channel.value === 'tiktok') return t('inbox.search_name_or_tiktok');
    return t('inbox.search_or_phone');
});

function channelLabel(ch) {
    if (ch === 'whatsapp') return t('inbox.whatsapp');
    if (ch === 'telegram') return t('sidebar.telegram');
    if (ch === 'instagram') return t('settings.tabs.instagram_inbox');
    if (ch === 'tiktok') return t('settings.tabs.tiktok_inbox');
    return ch || '';
}

function isConversationSelected(conv) {
    if (channel.value === 'all' && conv.channel) {
        if (conv.channel === 'telegram') return conv.chat_id === props.selectedChatId && !!props.selectedChatId;
        if (conv.channel === 'instagram') return conv.ig_user_id === props.selectedIgUserId && !!props.selectedIgUserId;
        if (conv.channel === 'tiktok') return conv.tiktok_open_id === props.selectedTikTokOpenId && !!props.selectedTikTokOpenId;
        if (conv.channel === 'whatsapp') return conv.phone === props.selectedPhone && !!props.selectedPhone;
        return false;
    }
    const id = conv.phone || conv.chat_id || conv.ig_user_id || conv.tiktok_open_id;
    return selectedContact.value === id;
}

const searchPhone = ref('');
const showSearchResults = ref(false);
const newMessage = ref('');
const selectedFile = ref(null);
const fileInput = ref(null);
const showCreateCustomerModal = ref(false);
const showAssignCustomerModal = ref(false);
const assignSearchQuery = ref('');
const assignCustomers = ref([]);
const assignCustomerId = ref(null);
const messagesContainer = ref(null);
const messageTextarea = ref(null);
const lightboxImageUrl = ref(null);
/** فقط یک VoiceWavePlayer فعال */
const playingAudioId = ref(null);

/** حذف پسوند غیراستاندارد مثل ;codecs=opus از URL فایل‌های واتساپ */
function cleanMediaUrl(url) {
    if (!url) return '';
    try {
        const u = new URL(url);
        const segments = u.pathname.split('/');
        const last = segments.pop();
        if (last && last.includes(';')) {
            segments.push(last.split(';')[0]);
            u.pathname = segments.join('/') || '/';
        }
        return u.toString();
    } catch {
        const s = String(url);
        const i = s.indexOf(';');
        if (i !== -1 && (s.includes('.ogg') || s.includes('.opus'))) {
            return s.slice(0, i);
        }
        return s;
    }
}
const page = usePage();

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const flashToastVisible = ref(true);
let flashAutoHideTimer = null;

function dismissFlashToast() {
    flashToastVisible.value = false;
    if (flashAutoHideTimer) {
        clearTimeout(flashAutoHideTimer);
        flashAutoHideTimer = null;
    }
}

watch(
    () => [flashSuccess.value, flashError.value],
    () => {
        flashToastVisible.value = true;
        if (flashAutoHideTimer) {
            clearTimeout(flashAutoHideTimer);
            flashAutoHideTimer = null;
        }
        if (flashSuccess.value || flashError.value) {
            flashAutoHideTimer = setTimeout(() => {
                flashToastVisible.value = false;
                flashAutoHideTimer = null;
            }, 10000);
        }
    },
    { immediate: true }
);

const instagramPollInterval = ref(null);
const telegramPollInterval = ref(null);
const tiktokPollInterval = ref(null);
const instagramPollPrevCount = ref(0);
const instagramPollPrevUnread = ref(0);
const tiktokPollPrevCount = ref(0);
const tiktokPollPrevUnread = ref(0);
const notificationPermission = ref(typeof Notification !== 'undefined' ? Notification.permission : 'denied');

const sendForm = useForm({
    to_phone: props.selectedPhone,
    message: '',
    media_file: null,
    media_url: null,
});

const customerForm = useForm({
    phone: props.selectedPhone,
    name: '',
    email: '',
    channel: props.messageChannel || (props.channel === 'all' ? 'whatsapp' : props.channel) || 'whatsapp',
    chat_id: props.selectedChatId || '',
    ig_user_id: props.selectedIgUserId || '',
    tiktok_open_id: props.selectedTikTokOpenId || '',
});

// جستجو با حداقل ۲ کاراکتر و درخواست فقط searchResults از سرور
const searchCustomers = debounce(() => {
    const q = searchPhone.value.trim();
    if (q.length >= 2) {
        const params = { search_phone: q, channel: channel.value };
        if (channel.value === 'all') {
            if (props.selectedPhone) params.phone = props.selectedPhone;
            if (props.selectedChatId) params.chat_id = props.selectedChatId;
            if (props.selectedIgUserId) params.ig_user_id = props.selectedIgUserId;
            if (props.selectedTikTokOpenId) params.tiktok_open_id = props.selectedTikTokOpenId;
        } else {
            if (channel.value === 'telegram' && props.selectedChatId) params.chat_id = props.selectedChatId;
            if (channel.value === 'instagram' && props.selectedIgUserId) params.ig_user_id = props.selectedIgUserId;
            if (channel.value === 'tiktok' && props.selectedTikTokOpenId) params.tiktok_open_id = props.selectedTikTokOpenId;
            if (channel.value === 'whatsapp' && props.selectedPhone) params.phone = props.selectedPhone;
        }
        router.get(route('inbox.index'), params, {
            preserveState: true,
            preserveScroll: true,
            only: ['searchResults'],
        });
    }
}, 280);

// نتایج جستجو از سرور (نام یا شماره)
const searchResults = computed(() => {
    return props.searchResults || [];
});

watch(() => searchPhone.value, () => {
    if (searchPhone.value.trim().length >= 2) {
        showSearchResults.value = true;
    }
});

watch(showAssignCustomerModal, (visible) => {
    if (visible) {
        assignCustomerId.value = null;
        loadAssignCustomers();
    }
});
watch(assignSearchQuery, () => {
    if (showAssignCustomerModal.value) loadAssignCustomers();
});

// لیست مکالمات: اگر جستجو خالی است همه را نشان بده، وگرنه فقط مواردی که نام یا شناسه با جستجو تطابق دارد
const filteredConversations = computed(() => {
    const list = props.conversations || [];
    const q = searchPhone.value.trim().toLowerCase();
    if (!q || q.length < 2) return list;
    return list.filter((c) => {
        const name = (c.name || '').toLowerCase();
        const phone = (c.phone || '').replace(/\D/g, '');
        const chatId = (c.chat_id || '').toString();
        const igUserId = (c.ig_user_id || '').toString();
        const tiktokOpenId = (c.tiktok_open_id || '').toString();
        const qDigits = q.replace(/\D/g, '');
        return name.includes(q) || (qDigits.length >= 2 && (phone.includes(qDigits) || chatId.includes(q) || igUserId.includes(q) || tiktokOpenId.includes(q)));
    });
});

const selectedConversation = computed(() => {
    return props.conversations.find((c) => {
        if (channel.value === 'all') {
            if (c.channel === 'telegram') return c.chat_id === props.selectedChatId && !!props.selectedChatId;
            if (c.channel === 'instagram') return c.ig_user_id === props.selectedIgUserId && !!props.selectedIgUserId;
            if (c.channel === 'tiktok') return c.tiktok_open_id === props.selectedTikTokOpenId && !!props.selectedTikTokOpenId;
            if (c.channel === 'whatsapp') return c.phone === props.selectedPhone && !!props.selectedPhone;
            return false;
        }
        return (
            (channel.value === 'telegram' && c.chat_id === props.selectedChatId) ||
            (channel.value === 'instagram' && c.ig_user_id === props.selectedIgUserId) ||
            (channel.value === 'tiktok' && c.tiktok_open_id === props.selectedTikTokOpenId) ||
            (channel.value === 'whatsapp' && c.phone === props.selectedPhone)
        );
    });
});

const getDisplayName = (conversation) => {
    if (!conversation) return selectedContact.value;
    const mc = messageChannel.value || channel.value;
    const id = mc === 'telegram'
        ? conversation.chat_id
        : (mc === 'instagram'
            ? conversation.ig_user_id
            : (mc === 'tiktok' ? conversation.tiktok_open_id : conversation.phone));
    if (conversation.name && conversation.name !== id) return conversation.name;
    return id || selectedContact.value;
};

const hasCustomer = computed(() => {
    return selectedConversation.value && selectedConversation.value.customer_id;
});

const noConversationYet = computed(() => {
    const mc = messageChannel.value || channel.value;
    return (mc === 'instagram' || mc === 'tiktok')
        && props.selectedCustomer
        && !(mc === 'instagram' ? props.selectedIgUserId : props.selectedTikTokOpenId);
});

const openFileModal = (fileUrl) => {
    window.open(cleanMediaUrl(fileUrl), '_blank');
};

const handleImageError = (event) => {
    // Hide broken image
    event.target.style.display = 'none';
};

/** انواع پیام APIهای اینستاگرام/تلگرام/تیک‌تاک گاهی با حروف بزرگ یا نام‌های متفاوت می‌آید */
const normMsgType = (t) => String(t ?? '').toLowerCase().trim();

const isImageFile = (messageType, url) => {
    const mt = normMsgType(messageType);
    if (['image', 'sticker', 'photo', 'picture'].includes(mt)) return true;
    if (!url) return false;
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg', '.heic', '.heif'];
    const lowerUrl = url.toLowerCase().split('?')[0].split(';')[0];
    return imageExtensions.some((ext) => lowerUrl.includes(ext));
};

const isVideoFile = (messageType, url) => {
    const mt = normMsgType(messageType);
    if (['video', 'movie', 'clip', 'reel', 'ig_reel', 'short'].includes(mt)) return true;
    if (!url) return false;
    // .ogg معمولاً صوت واتساپ است؛ با isAudioFile زودتر بررسی می‌شود
    const exts = ['.mp4', '.webm', '.mov', '.m4v', '.3gp', '.mkv'];
    const lower = url.toLowerCase().split(';')[0].split('?')[0];
    return exts.some((ext) => lower.includes(ext));
};

const isAudioFile = (messageType, url) => {
    const mt = normMsgType(messageType);
    if (['audio', 'voice', 'ptt', 'sound', 'voicenote', 'voice_message'].includes(mt)) return true;
    if (!url) return false;
    const lower = url.toLowerCase().split(';')[0].split('?')[0];
    const exts = ['.ogg', '.opus', '.mp3', '.m4a', '.aac', '.wav'];
    return exts.some((ext) => lower.includes(ext));
};

const getFileName = (url) => {
    if (!url) return t('common.file');
    try {
        const urlObj = new URL(url);
        let fileName = urlObj.pathname.split('/').pop() || '';
        if (fileName.includes(';')) {
            fileName = fileName.split(';')[0];
        }
        return fileName || t('common.file');
    } catch {
        const raw = url.split('/').pop() || '';
        return raw.includes(';') ? raw.split(';')[0] : raw || t('common.file');
    }
};

const handleEnterKey = (event) => {
    // Prevent form submission on Enter
    event.preventDefault();
    // Allow new line in textarea by inserting newline
    const textarea = event.target;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = newMessage.value;
    newMessage.value = text.substring(0, start) + '\n' + text.substring(end);
    // Move cursor after the newline
    nextTick(() => {
        textarea.selectionStart = textarea.selectionEnd = start + 1;
        autoResizeTextarea();
    });
};

const autoResizeTextarea = () => {
    nextTick(() => {
        if (messageTextarea.value) {
            messageTextarea.value.style.height = 'auto';
            messageTextarea.value.style.height = Math.min(messageTextarea.value.scrollHeight, 120) + 'px';
        }
    });
};

const clearSelectedFile = () => {
    selectedFile.value = null;
    sendForm.media_file = null;
    sendForm.media_url = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const selectCustomerFromSearch = (customer) => {
    if (channel.value === 'all') {
        showSearchResults.value = false;
        searchPhone.value = '';
        if (customer.phone) {
            router.get(route('inbox.index'), { channel: 'all', phone: customer.phone }, { preserveState: false });
            return;
        }
        if (customer.chat_id) {
            router.get(route('inbox.index'), { channel: 'all', chat_id: customer.chat_id }, { preserveState: false });
            return;
        }
        if (customer.ig_user_id) {
            router.get(route('inbox.index'), { channel: 'all', ig_user_id: customer.ig_user_id }, { preserveState: false });
            return;
        }
        if (customer.tiktok_open_id) {
            router.get(route('inbox.index'), { channel: 'all', tiktok_open_id: customer.tiktok_open_id }, { preserveState: false });
            return;
        }
        router.get(route('inbox.index'), { channel: 'all', customer_id: customer.id }, { preserveState: false });
        return;
    }
    if (channel.value === 'instagram' && !customer.ig_user_id) {
        showSearchResults.value = false;
        searchPhone.value = '';
        router.get(route('inbox.index'), { channel: 'instagram', customer_id: customer.id }, { preserveState: false });
        return;
    }
    if (channel.value === 'tiktok' && !customer.tiktok_open_id) {
        showSearchResults.value = false;
        searchPhone.value = '';
        router.get(route('inbox.index'), { channel: 'tiktok', customer_id: customer.id }, { preserveState: false });
        return;
    }
    const id = channel.value === 'telegram'
        ? customer.chat_id
        : (channel.value === 'instagram'
            ? customer.ig_user_id
            : (channel.value === 'tiktok' ? customer.tiktok_open_id : customer.phone));
    if (!id) return;
    showSearchResults.value = false;
    searchPhone.value = '';
    selectConversation({
        channel: channel.value,
        phone: channel.value === 'whatsapp' ? id : null,
        chat_id: channel.value === 'telegram' ? id : null,
        ig_user_id: channel.value === 'instagram' ? id : null,
        tiktok_open_id: channel.value === 'tiktok' ? id : null,
    });
};

const startNewConversation = (phoneOrChatId) => {
    showSearchResults.value = false;
    searchPhone.value = '';
    const params = { channel: channel.value };
    if (channel.value === 'all') {
        params.channel = 'all';
        params.phone = phoneOrChatId;
    } else {
        params.phone = phoneOrChatId;
    }
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: false,
    });
};

const selectConversation = (conv) => {
    const params = { channel: channel.value };
    if (channel.value === 'all') {
        params.channel = 'all';
        const ch = conv.channel;
        if (ch === 'telegram') params.chat_id = conv.chat_id;
        else if (ch === 'instagram') params.ig_user_id = conv.ig_user_id;
        else if (ch === 'tiktok') params.tiktok_open_id = conv.tiktok_open_id;
        else params.phone = conv.phone;
    } else {
        if (channel.value === 'telegram') params.chat_id = conv.chat_id;
        else if (channel.value === 'instagram') params.ig_user_id = conv.ig_user_id;
        else if (channel.value === 'tiktok') params.tiktok_open_id = conv.tiktok_open_id;
        else params.phone = conv.phone;
    }
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: false,
    });
};

const showMediaPicker = ref(false);
const showTemplatePicker = ref(false);

const templates = computed(() => props.templates || []);
const sendingMessage = ref(false);

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file size (50MB max)
        if (file.size > 50 * 1024 * 1024) {
            alert(t('inbox.file_size_limit_50mb'));
            event.target.value = '';
            return;
        }
        
        selectedFile.value = file;
        sendForm.media_file = file;
        sendForm.media_url = null;
    }
};

const onMediaSelect = (file) => {
    const fullUrl = file.url.startsWith('http') ? file.url : (window.location.origin + (file.url.startsWith('/') ? file.url : '/' + file.url));
    sendForm.media_url = fullUrl;
    sendForm.media_file = null;
    selectedFile.value = { name: file.name, url: fullUrl };
};

function stripHtmlToText(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const text = tmp.textContent || tmp.innerText || '';
    return text.replace(/\n{3,}/g, '\n\n').trim();
}

const onTemplateSelect = ({ content, image }) => {
    const plainText = stripHtmlToText(content);
    newMessage.value = (newMessage.value ? newMessage.value + '\n' : '') + (plainText || '');
    if (image) {
        const fullUrl = image.startsWith('http') ? image : (window.location.origin + (image.startsWith('/') ? image : '/' + image));
        sendForm.media_url = fullUrl;
        sendForm.media_file = null;
        selectedFile.value = { name: 'template-image', url: fullUrl };
    }
    nextTick(() => autoResizeTextarea());
};

const sendMessage = () => {
    if ((!newMessage.value.trim() && !selectedFile.value) || !selectedContact.value) {
        return;
    }
    const mc = messageChannel.value;
    if (!mc) {
        return;
    }

    const formData = new FormData();
    const token = typeof window !== 'undefined' && document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) formData.append('_token', token);
    formData.append('channel', mc);
    if (mc === 'telegram') {
        formData.append('to_chat_id', selectedContact.value);
    } else if (mc === 'instagram') {
        formData.append('to_ig_user_id', selectedContact.value);
    } else if (mc === 'tiktok') {
        formData.append('to_tiktok_open_id', selectedContact.value);
    } else {
        formData.append('to_phone', selectedContact.value);
    }
    formData.append('message', newMessage.value || '');
    if (selectedFile.value instanceof File) {
        formData.append('media_file', selectedFile.value);
    } else if (sendForm.media_url) {
        formData.append('media_url', sendForm.media_url);
    }

    sendingMessage.value = true;
    router.post(route('inbox.send'), formData, {
        preserveState: false,
        preserveScroll: false,
        forceFormData: true,
        onSuccess: () => {
            newMessage.value = '';
            clearSelectedFile();
            if (messageTextarea.value) {
                messageTextarea.value.style.height = '40px';
            }
            nextTick(() => scrollToBottom());
        },
        onError: (errors) => {
            console.error('Error sending message:', errors);
            const msg = errors?.media_file?.[0] || errors?.message?.[0] || (typeof errors === 'object' && Object.values(errors).flat().find(Boolean));
            if (msg) alert(msg);
        },
        onFinish: () => {
            sendingMessage.value = false;
        },
    });
};

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const createCustomer = () => {
    const mc = messageChannel.value || (channel.value === 'all' ? 'whatsapp' : channel.value);
    customerForm.phone = mc === 'whatsapp' ? props.selectedPhone : '';
    customerForm.channel = mc;
    customerForm.chat_id = mc === 'telegram' ? props.selectedChatId : '';
    customerForm.ig_user_id = mc === 'instagram' ? props.selectedIgUserId : '';
    customerForm.tiktok_open_id = mc === 'tiktok' ? props.selectedTikTokOpenId : '';
    customerForm.post(route('inbox.create-customer'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            showCreateCustomerModal.value = false;
            customerForm.reset();
        },
    });
};

const loadAssignCustomers = debounce(() => {
    const q = assignSearchQuery.value.trim();
    const url = route('inbox.customers-for-assign') + (q ? '?q=' + encodeURIComponent(q) : '');
    fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => { assignCustomers.value = Array.isArray(data) ? data : []; })
        .catch(() => { assignCustomers.value = []; });
}, 250);

const submitAssignCustomer = () => {
    if (!assignCustomerId.value) return;
    const mc = messageChannel.value || channel.value;
    if (mc === 'instagram' && !props.selectedIgUserId) return;
    if (mc === 'tiktok' && !props.selectedTikTokOpenId) return;
    if (mc !== 'instagram' && mc !== 'tiktok') return;
    const payload = {
        channel: mc,
        customer_id: assignCustomerId.value,
    };
    if (mc === 'instagram') payload.ig_user_id = props.selectedIgUserId;
    if (mc === 'tiktok') payload.tiktok_open_id = props.selectedTikTokOpenId;
    router.post(route('inbox.assign-customer'), payload, {
        preserveState: false,
        onSuccess: () => {
            showAssignCustomerModal.value = false;
            assignCustomerId.value = null;
            assignSearchQuery.value = '';
            assignCustomers.value = [];
        },
    });
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diff = now - date;
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);

    if (minutes < 1) return t('inbox.just_now');
    if (minutes < 60) return t('inbox.minutes_ago').replace(':count', String(minutes));
    if (hours < 24) return t('inbox.hours_ago').replace(':count', String(hours));
    if (days < 7) return t('inbox.days_ago').replace(':count', String(days));

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
    });
};

// Watch for new messages and scroll to bottom
watch(() => props.messages, (newMessages) => {
    // Debug: log messages with media
    const messagesWithMedia = newMessages.filter(m => m.media_url);
    if (messagesWithMedia.length > 0) {
        console.log('Messages with media:', messagesWithMedia);
    }
    nextTick(() => {
        scrollToBottom();
    });
}, { deep: true });

function requestNotificationPermission() {
    if (typeof Notification === 'undefined') return;
    Notification.requestPermission().then((p) => {
        notificationPermission.value = p;
    });
}

function runInstagramPoll() {
    if (channel.value !== 'instagram' && !(channel.value === 'all' && props.messageChannel === 'instagram')) {
        return;
    }
    const params = { channel: channel.value };
    if (props.selectedIgUserId) params.ig_user_id = props.selectedIgUserId;
    else if (props.selectedCustomer?.id && !props.selectedIgUserId) params.customer_id = props.selectedCustomer.id;
    const convs = page.props.conversations || [];
    instagramPollPrevCount.value = (page.props.messages || []).length;
    instagramPollPrevUnread.value = convs.reduce((s, c) => s + (c.unread_count || 0), 0);
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['conversations', 'messages', 'selectedCustomer'],
        onFinish: () => {
            setTimeout(() => {
                if (document.hidden && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                    const newMessages = page.props.messages || [];
                    const newConvs = page.props.conversations || [];
                    const newLen = newMessages.length;
                    const newUnread = newConvs.reduce((s, c) => s + (c.unread_count || 0), 0);
                    const hasNew = newLen > instagramPollPrevCount.value || newUnread > instagramPollPrevUnread.value;
                    if (hasNew) {
                        try {
                            new Notification(t('inbox.new_instagram_message'), { body: t('inbox.you_received_new_message') });
                        } catch (_) {}
                    }
                    instagramPollPrevCount.value = newLen;
                    instagramPollPrevUnread.value = newUnread;
                }
            }, 600);
        },
    });
}

function runTelegramPoll() {
    if (channel.value !== 'telegram' && !(channel.value === 'all' && props.messageChannel === 'telegram')) {
        return;
    }
    const params = { channel: channel.value };
    if (props.selectedChatId) params.chat_id = props.selectedChatId;
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['conversations', 'messages', 'selectedCustomer'],
    });
}

function runTikTokPoll() {
    if (channel.value !== 'tiktok' && !(channel.value === 'all' && props.messageChannel === 'tiktok')) {
        return;
    }
    const params = { channel: channel.value };
    if (props.selectedTikTokOpenId) params.tiktok_open_id = props.selectedTikTokOpenId;
    else if (props.selectedCustomer?.id && !props.selectedTikTokOpenId) params.customer_id = props.selectedCustomer.id;
    const convs = page.props.conversations || [];
    tiktokPollPrevCount.value = (page.props.messages || []).length;
    tiktokPollPrevUnread.value = convs.reduce((s, c) => s + (c.unread_count || 0), 0);
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['conversations', 'messages', 'selectedCustomer'],
        onFinish: () => {
            setTimeout(() => {
                if (document.hidden && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                    const newMessages = page.props.messages || [];
                    const newConvs = page.props.conversations || [];
                    const newLen = newMessages.length;
                    const newUnread = newConvs.reduce((s, c) => s + (c.unread_count || 0), 0);
                    const hasNew = newLen > tiktokPollPrevCount.value || newUnread > tiktokPollPrevUnread.value;
                    if (hasNew) {
                        try {
                            new Notification(t('inbox.new_tiktok_message'), { body: t('inbox.you_received_new_message') });
                        } catch (_) {}
                    }
                    tiktokPollPrevCount.value = newLen;
                    tiktokPollPrevUnread.value = newUnread;
                }
            }, 600);
        },
    });
}

function onLightboxEscape(e) {
    if (e.key === 'Escape') {
        lightboxImageUrl.value = null;
    }
}

onMounted(() => {
    window.addEventListener('keydown', onLightboxEscape);

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.relative')) {
            showSearchResults.value = false;
        }
    });

    // Scroll to bottom of messages on mount
    if (props.messages.length > 0) {
        nextTick(() => {
            scrollToBottom();
        });
    }

    // Instagram: start polling (اجازه نوتیفیکیشن با کلیک روی بنر درخواست می‌شود)
    if (channel.value === 'instagram' || (channel.value === 'all' && props.messageChannel === 'instagram')) {
        runInstagramPoll();
        instagramPollInterval.value = setInterval(runInstagramPoll, 15000);
    }

    // Telegram: real-time polling (مشابه اینستاگرام)
    if (channel.value === 'telegram' || (channel.value === 'all' && props.messageChannel === 'telegram')) {
        runTelegramPoll();
        telegramPollInterval.value = setInterval(runTelegramPoll, 15000);
    }

    if (channel.value === 'tiktok' || (channel.value === 'all' && props.messageChannel === 'tiktok')) {
        runTikTokPoll();
        tiktokPollInterval.value = setInterval(runTikTokPoll, 15000);
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', onLightboxEscape);

    if (flashAutoHideTimer) {
        clearTimeout(flashAutoHideTimer);
    }
    if (instagramPollInterval.value) {
        clearInterval(instagramPollInterval.value);
    }
    if (telegramPollInterval.value) {
        clearInterval(telegramPollInterval.value);
    }
    if (tiktokPollInterval.value) {
        clearInterval(tiktokPollInterval.value);
    }
});
</script>

<style scoped>
.inbox-flash-toast-enter-active,
.inbox-flash-toast-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.inbox-flash-toast-enter-from,
.inbox-flash-toast-leave-to {
    opacity: 0;
    transform: translateY(-0.5rem);
}
</style>
