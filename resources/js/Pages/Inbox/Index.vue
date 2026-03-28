<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>{{ t('sidebar.inbox') }}</span>
                <div class="flex rounded-lg border border-gray-200 overflow-hidden">
                    <a
                        :href="route('inbox.index', { channel: 'whatsapp' })"
                        :class="[
                            'px-4 py-2 text-sm font-medium',
                            channel === 'whatsapp'
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        {{ t('inbox.whatsapp') }}
                    </a>
                    <a
                        :href="route('inbox.index', { channel: 'telegram' })"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-l border-gray-200 rtl:border-l-0 rtl:border-r',
                            channel === 'telegram'
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        {{ t('sidebar.telegram') }}
                    </a>
                    <a
                        :href="route('inbox.index', { channel: 'instagram' })"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-l border-gray-200 rtl:border-l-0 rtl:border-r',
                            channel === 'instagram'
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        {{ t('settings.tabs.instagram_inbox') }}
                    </a>
                    <a
                        :href="route('inbox.index', { channel: 'tiktok' })"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-l border-gray-200 rtl:border-l-0 rtl:border-r',
                            channel === 'tiktok'
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        {{ t('settings.tabs.tiktok_inbox') }}
                    </a>
                </div>
            </div>
        </template>

        <!-- Success/Error Messages -->
        <div v-if="$page.props.flash?.success || $page.props.flash?.error" class="absolute top-20 left-0 right-0 z-50 px-4 lg:px-8">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg shadow-lg">
                {{ $page.props.flash.error }}
            </div>
        </div>

        <!-- Full Height Inbox Container - Negative margins to override AppLayout padding -->
        <div class="-m-4 lg:-m-8 h-[calc(100vh-64px)] flex flex-col bg-white overflow-hidden">
            <div class="flex flex-1 overflow-hidden">
                <!-- Conversations List (Left Sidebar) -->
                <div class="w-[26%] min-w-[220px] max-w-[320px] bg-white border-r border-gray-200 rtl:border-r-0 rtl:border-l flex flex-col flex-shrink-0">
                    <!-- Instagram: درخواست اجازه نوتیفیکیشن (مرورگر فقط با کلیک کاربر اجازه می‌دهد) -->
                    <div
                        v-if="(channel === 'instagram' || channel === 'tiktok') && notificationPermission === 'default'"
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
                    <!-- Search Header (Sticky) -->
                    <div class="flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50 relative z-10">
                        <div class="relative">
                            <input
                                v-model="searchPhone"
                                @input="searchCustomers"
                                @focus="showSearchResults = true"
                                type="text"
                                :placeholder="channel === 'telegram' ? t('inbox.search_name_or_telegram') : (channel === 'instagram' ? t('inbox.search_name_or_instagram') : (channel === 'tiktok' ? t('inbox.search_name_or_tiktok') : t('inbox.search_or_phone')))"
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
                                    v-if="channel === 'whatsapp' && searchPhone.trim() && searchResults.length === 0"
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
                            :key="conv.phone || conv.chat_id || conv.ig_user_id || conv.tiktok_open_id"
                            @click="selectConversation(conv.phone || conv.chat_id || conv.ig_user_id || conv.tiktok_open_id)"
                            :class="[
                                'px-4 py-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors',
                                selectedContact === (conv.phone || conv.chat_id || conv.ig_user_id || conv.tiktok_open_id) ? 'bg-blue-50 border-l-4 border-l-blue-600 rtl:border-l-0 rtl:border-r-4 rtl:border-r-blue-600' : ''
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
                                    <div class="flex items-center justify-between mb-1">
                                        <h3 class="text-sm font-semibold text-gray-900 truncate">
                                            {{ conv.name }}
                                        </h3>
                                        <span
                                            v-if="conv.last_message_at"
                                            class="text-xs text-gray-500 flex-shrink-0 ltr:ml-2 rtl:mr-2"
                                        >
                                            {{ formatTime(conv.last_message_at) }}
                                        </span>
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
                    <div v-if="selectedContact || ((channel === 'instagram' || channel === 'tiktok') && selectedCustomer && !selectedContact)" class="flex-1 flex flex-col min-h-0">
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
                                    <p v-if="!hasCustomer && !noConversationYet" class="text-sm text-gray-500 truncate">{{ channel === 'instagram' ? selectedIgUserId : (channel === 'tiktok' ? selectedTikTokOpenId : (channel === 'telegram' ? selectedChatId : selectedPhone)) }}</p>
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
                                        v-if="channel === 'instagram' || channel === 'tiktok'"
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
                                <template v-if="channel === 'tiktok'">
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
                                        <!-- Image files -->
                                        <img
                                            v-if="isImageFile(msg.message_type, msg.media_url)"
                                            :src="msg.media_url"
                                            :alt="t('common.media')"
                                            class="max-w-full h-auto rounded cursor-pointer hover:opacity-90 transition-opacity"
                                            @click="openFileModal(msg.media_url)"
                                            @error="(e) => { console.error('Image load error:', e, msg.media_url); handleImageError(e); }"
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
                                    channel === 'telegram'
                                        ? t('inbox.empty_state_telegram')
                                        : (channel === 'instagram'
                                            ? t('inbox.empty_state_instagram')
                                            : (channel === 'tiktok'
                                                ? t('inbox.empty_state_tiktok')
                                                : t('inbox.empty_state_whatsapp')))
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
                                    <p class="text-sm text-gray-500">{{ channel === 'instagram' ? selectedIgUserId : (channel === 'tiktok' ? selectedTikTokOpenId : (channel === 'telegram' ? selectedChatId : selectedPhone)) }}</p>
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
                                {{ channel === 'telegram' ? t('inbox.telegram_chat_id') : (channel === 'instagram' ? t('inbox.instagram_user_id') : (channel === 'tiktok' ? t('inbox.tiktok_open_id_label') : t('customers.phone'))) }}
                            </label>
                            <input
                                :value="channel === 'instagram' ? selectedIgUserId : (channel === 'tiktok' ? selectedTikTokOpenId : (channel === 'telegram' ? selectedChatId : selectedPhone))"
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
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MediaPickerModal from '@/Components/MediaPickerModal.vue';
import TemplatePickerModal from '@/Components/TemplatePickerModal.vue';
import { debounce } from 'lodash-es';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    channel: {
        type: String,
        default: 'whatsapp',
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

const channel = computed(() => props.channel || 'whatsapp');
const selectedContact = computed(() => {
    if (channel.value === 'telegram') return props.selectedChatId || null;
    if (channel.value === 'instagram') return props.selectedIgUserId || null;
    if (channel.value === 'tiktok') return props.selectedTikTokOpenId || null;
    return props.selectedPhone || null;
});

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
const page = usePage();
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
    channel: props.channel || 'whatsapp',
    chat_id: props.selectedChatId || '',
    ig_user_id: props.selectedIgUserId || '',
    tiktok_open_id: props.selectedTikTokOpenId || '',
});

// جستجو با حداقل ۲ کاراکتر و درخواست فقط searchResults از سرور
const searchCustomers = debounce(() => {
    const q = searchPhone.value.trim();
    if (q.length >= 2) {
        const params = { search_phone: q, channel: channel.value };
        if (channel.value === 'telegram' && props.selectedChatId) params.chat_id = props.selectedChatId;
        if (channel.value === 'instagram' && props.selectedIgUserId) params.ig_user_id = props.selectedIgUserId;
        if (channel.value === 'tiktok' && props.selectedTikTokOpenId) params.tiktok_open_id = props.selectedTikTokOpenId;
        if (channel.value === 'whatsapp' && props.selectedPhone) params.phone = props.selectedPhone;
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
    return props.conversations.find(c =>
        (channel.value === 'telegram' && c.chat_id === props.selectedChatId) ||
        (channel.value === 'instagram' && c.ig_user_id === props.selectedIgUserId) ||
        (channel.value === 'tiktok' && c.tiktok_open_id === props.selectedTikTokOpenId) ||
        (channel.value === 'whatsapp' && c.phone === props.selectedPhone)
    );
});

const getDisplayName = (conversation) => {
    if (!conversation) return selectedContact.value;
    const id = channel.value === 'telegram'
        ? conversation.chat_id
        : (channel.value === 'instagram'
            ? conversation.ig_user_id
            : (channel.value === 'tiktok' ? conversation.tiktok_open_id : conversation.phone));
    if (conversation.name && conversation.name !== id) return conversation.name;
    return id || selectedContact.value;
};

const hasCustomer = computed(() => {
    return selectedConversation.value && selectedConversation.value.customer_id;
});

const noConversationYet = computed(() => {
    return (channel.value === 'instagram' || channel.value === 'tiktok')
        && props.selectedCustomer
        && !(channel.value === 'instagram' ? props.selectedIgUserId : props.selectedTikTokOpenId);
});

const openImageModal = (imageUrl) => {
    // Simple image modal - open in new tab for now
    window.open(imageUrl, '_blank');
};

const openFileModal = (fileUrl) => {
    // Open file in new tab
    window.open(fileUrl, '_blank');
};

const handleImageError = (event) => {
    // Hide broken image
    event.target.style.display = 'none';
};

const isImageFile = (messageType, url) => {
    if (messageType === 'image') return true;
    if (!url) return false;
    const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.webp', '.bmp', '.svg'];
    const lowerUrl = url.toLowerCase();
    return imageExtensions.some(ext => lowerUrl.includes(ext));
};

const getFileName = (url) => {
    if (!url) return t('common.file');
    try {
        const urlObj = new URL(url);
        const pathname = urlObj.pathname;
        const fileName = pathname.split('/').pop();
        return fileName || t('common.file');
    } catch {
        const parts = url.split('/');
        return parts[parts.length - 1] || t('common.file');
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
    selectConversation(id);
};

const startNewConversation = (phoneOrChatId) => {
    showSearchResults.value = false;
    searchPhone.value = '';
    selectConversation(phoneOrChatId);
};

const selectConversation = (contactId) => {
    const params = { channel: channel.value };
    if (channel.value === 'telegram') params.chat_id = contactId;
    else if (channel.value === 'instagram') params.ig_user_id = contactId;
    else if (channel.value === 'tiktok') params.tiktok_open_id = contactId;
    else params.phone = contactId;
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

    const formData = new FormData();
    const token = typeof window !== 'undefined' && document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) formData.append('_token', token);
    formData.append('channel', channel.value);
    if (channel.value === 'telegram') {
        formData.append('to_chat_id', selectedContact.value);
    } else if (channel.value === 'instagram') {
        formData.append('to_ig_user_id', selectedContact.value);
    } else if (channel.value === 'tiktok') {
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
    customerForm.phone = channel.value === 'whatsapp' ? props.selectedPhone : '';
    customerForm.channel = channel.value;
    customerForm.chat_id = channel.value === 'telegram' ? props.selectedChatId : '';
    customerForm.ig_user_id = channel.value === 'instagram' ? props.selectedIgUserId : '';
    customerForm.tiktok_open_id = channel.value === 'tiktok' ? props.selectedTikTokOpenId : '';
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
    if (channel.value === 'instagram' && !props.selectedIgUserId) return;
    if (channel.value === 'tiktok' && !props.selectedTikTokOpenId) return;
    if (channel.value !== 'instagram' && channel.value !== 'tiktok') return;
    const payload = {
        channel: channel.value,
        customer_id: assignCustomerId.value,
    };
    if (channel.value === 'instagram') payload.ig_user_id = props.selectedIgUserId;
    if (channel.value === 'tiktok') payload.tiktok_open_id = props.selectedTikTokOpenId;
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
    const params = { channel: 'instagram' };
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
    const params = { channel: 'telegram' };
    if (props.selectedChatId) params.chat_id = props.selectedChatId;
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['conversations', 'messages', 'selectedCustomer'],
    });
}

function runTikTokPoll() {
    const params = { channel: 'tiktok' };
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

onMounted(() => {
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
    if (channel.value === 'instagram') {
        runInstagramPoll();
        instagramPollInterval.value = setInterval(runInstagramPoll, 15000);
    }

    // Telegram: real-time polling (مشابه اینستاگرام)
    if (channel.value === 'telegram') {
        runTelegramPoll();
        telegramPollInterval.value = setInterval(runTelegramPoll, 15000);
    }

    if (channel.value === 'tiktok') {
        runTikTokPoll();
        tiktokPollInterval.value = setInterval(runTikTokPoll, 15000);
    }
});

onUnmounted(() => {
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
