<template>
    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <span>INBOX</span>
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
                        WhatsApp
                    </a>
                    <a
                        :href="route('inbox.index', { channel: 'telegram' })"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-l border-gray-200',
                            channel === 'telegram'
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        Telegram
                    </a>
                    <a
                        :href="route('inbox.index', { channel: 'instagram' })"
                        :class="[
                            'px-4 py-2 text-sm font-medium border-l border-gray-200',
                            channel === 'instagram'
                                ? 'bg-blue-600 text-white'
                                : 'bg-white text-gray-700 hover:bg-gray-50'
                        ]"
                    >
                        Instagram
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
                <!-- Conversations List (Left Sidebar) - 20% -->
                <div class="w-[20%] bg-white border-r border-gray-200 flex flex-col min-w-0 flex-shrink-0">
                    <!-- Search Header (Sticky) -->
                    <div class="flex-shrink-0 p-4 border-b border-gray-200 bg-gray-50 relative z-10">
                        <div class="relative">
                            <input
                                v-model="searchPhone"
                                @input="searchCustomers"
                                @focus="showSearchResults = true"
                                type="text"
                                :placeholder="channel === 'telegram' ? 'Search by name or Telegram...' : (channel === 'instagram' ? 'Search by name or Instagram...' : 'Search or enter phone number...')"
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
                                    class="px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 flex items-center space-x-3"
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
                                        <p class="text-xs text-gray-500">{{ result.phone || result.chat_id || result.ig_user_id }}</p>
                                    </div>
                                </div>
                                
                                <!-- Send to New Number (WhatsApp only) -->
                                <div
                                    v-if="channel === 'whatsapp' && searchPhone.trim() && searchResults.length === 0"
                                    class="px-4 py-3 border-t border-gray-200"
                                >
                                    <button
                                        @click="startNewConversation(searchPhone)"
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium flex items-center justify-center space-x-2"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>Send to {{ searchPhone }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Conversations List (Scrollable) -->
                    <div class="flex-1 overflow-y-auto">
                        <div
                            v-for="conv in filteredConversations"
                            :key="conv.phone || conv.chat_id || conv.ig_user_id"
                            @click="selectConversation(conv.phone || conv.chat_id || conv.ig_user_id)"
                            :class="[
                                'px-4 py-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition-colors',
                                selectedContact === (conv.phone || conv.chat_id || conv.ig_user_id) ? 'bg-blue-50 border-l-4 border-l-blue-600' : ''
                            ]"
                        >
                            <div class="flex items-center space-x-3">
                                <!-- Avatar -->
                                <div class="flex-shrink-0">
                                    <div
                                        v-if="conv.avatar"
                                        class="w-12 h-12 rounded-full bg-cover bg-center border-2 border-gray-200"
                                        :style="{ backgroundImage: `url(${conv.avatar})` }"
                                    ></div>
                                    <div
                                        v-else
                                        class="w-12 h-12 rounded-full bg-blue-500 flex items-center justify-center text-white font-semibold border-2 border-gray-200"
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
                                            class="text-xs text-gray-500 flex-shrink-0 ml-2"
                                        >
                                            {{ formatTime(conv.last_message_at) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm text-gray-600 truncate">
                                            {{ conv.last_message || 'No messages' }}
                                        </p>
                                        <span
                                            v-if="conv.unread_count > 0"
                                            class="bg-blue-600 text-white text-xs font-semibold px-2 py-1 rounded-full flex-shrink-0 ml-2"
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
                            <p>No conversations found</p>
                        </div>
                    </div>
                </div>

                <!-- Messages Area (Middle) - 50% -->
                <div class="w-[50%] flex flex-col bg-gray-50 min-w-0 flex-shrink-0">
                    <div v-if="selectedContact || (channel === 'instagram' && selectedCustomer && !selectedIgUserId)" class="flex-1 flex flex-col min-h-0">
                        <!-- Conversation Header (Sticky) -->
                        <div class="flex-shrink-0 bg-white px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                            <div class="flex items-center space-x-3 min-w-0">
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
                                    <p v-if="!hasCustomer && !noConversationYet" class="text-sm text-gray-500 truncate">{{ channel === 'instagram' ? selectedIgUserId : (channel === 'telegram' ? selectedChatId : selectedPhone) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2 flex-shrink-0">
                                <template v-if="noConversationYet">
                                    <Link
                                        :href="route('customers.show', selectedCustomer.id)"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors whitespace-nowrap"
                                    >
                                        View Customer
                                    </Link>
                                </template>
                                <template v-else-if="!hasCustomer">
                                    <button
                                        @click="showAssignCustomerModal = true"
                                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 text-sm font-medium transition-colors whitespace-nowrap"
                                    >
                                        Assign to Customer
                                    </button>
                                    <button
                                        @click="showCreateCustomerModal = true"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors whitespace-nowrap"
                                    >
                                        Add as Customer
                                    </button>
                                </template>
                                <Link
                                    v-else
                                    :href="route('customers.show', selectedConversation.customer_id)"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors whitespace-nowrap"
                                >
                                    View Customer
                                </Link>
                            </div>
                        </div>

                        <!-- No conversation yet (Instagram: customer has handle but no messages) -->
                        <div v-if="noConversationYet" class="flex-1 flex items-center justify-center p-8 bg-gray-50">
                            <div class="text-center max-w-md">
                                <p class="text-gray-600 mb-2">این مخاطب هنوز در اینستاگرام با شما گفتگو نکرده است.</p>
                                <p class="text-sm text-gray-500 mb-4">وقتی در اینستاگرام پیام دهد، اینجا نمایش داده می‌شود.</p>
                                <Link :href="route('customers.show', selectedCustomer.id)" class="text-blue-600 hover:underline font-medium">مشاهده کارت مخاطب</Link>
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
                                            alt="Media"
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
                                            <div class="flex items-center space-x-3">
                                                <svg class="w-10 h-10 text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                                </svg>
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-gray-900 truncate">
                                                        {{ getFileName(msg.media_url) }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 capitalize">
                                                        {{ msg.message_type || 'file' }}
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
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                        </div>

                        <!-- Message Input (Sticky Bottom) - hide when no conversation yet -->
                        <div v-if="!noConversationYet" class="flex-shrink-0 bg-white border-t border-gray-200 px-6 py-4">
                            <form @submit.prevent class="space-y-3">
                                <!-- File Upload Preview -->
                                <div v-if="selectedFile" class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-2 min-w-0">
                                        <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                        <span class="text-sm text-gray-700 truncate">{{ selectedFile.name }}</span>
                                    </div>
                                    <button
                                        type="button"
                                        @click="clearSelectedFile"
                                        class="text-red-600 hover:text-red-700 flex-shrink-0 ml-2"
                                    >
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="flex items-center space-x-3">
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
                                        title="آپلود فایل"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                        </svg>
                                    </button>
                                    <button
                                        type="button"
                                        @click="showMediaPicker = true"
                                        class="p-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-lg transition-colors flex-shrink-0"
                                        title="انتخاب از مدیا"
                                    >
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <textarea
                                        ref="messageTextarea"
                                        v-model="newMessage"
                                        @keydown.enter.exact="handleEnterKey"
                                        placeholder="Type a message... (Press Enter for new line, click Send to send)"
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
                                        {{ sendingMessage ? 'در حال ارسال...' : 'ارسال' }}
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
                            <p class="text-lg">{{ channel === 'telegram' ? 'Search for a contact with Telegram or open a conversation from the list' : (channel === 'instagram' ? 'Search for a contact with Instagram or open a conversation from the list' : 'Search for a contact or enter a phone number to start a conversation') }}</p>
                            <div v-if="channel === 'instagram'" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 max-w-md mx-auto text-right">
                                <p class="font-medium mb-1">ارسال پیام از طریق اینستاگرام</p>
                                <p class="mb-2">برای ارسال و دریافت DM اینستاگرام باید اپ متا (Facebook Developer) را به اکانت اینستاگرام Business/Creator وصل کنید و در تنظیمات (Settings) بخش Instagram، توکن دسترسی (Access Token) را وارد کنید. شناسه مخاطب (Instagram Scoped User ID) از API متا هنگام دریافت پیام یا از وب‌هوک به‌دست می‌آید.</p>
                                <a :href="route('settings.index')" class="text-amber-700 underline font-medium">رفتن به تنظیمات</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Info Panel (Right Side) - 30% -->
                <div v-if="selectedContact" class="w-[30%] bg-white border-l border-gray-200 flex flex-col min-w-0 flex-shrink-0 overflow-hidden">
                    <div v-if="selectedCustomer" class="flex-1 overflow-y-auto p-6">
                        <!-- Customer Header -->
                        <div class="mb-6 pb-6 border-b border-gray-200">
                            <div class="flex items-center space-x-4 mb-4">
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
                                    <p class="text-sm text-gray-500">{{ channel === 'instagram' ? selectedIgUserId : (channel === 'telegram' ? selectedChatId : selectedPhone) }}</p>
                                </div>
                            </div>
                            <Link
                                :href="route('customers.show', selectedCustomer.id)"
                                class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors text-center block"
                            >
                                View Full Profile
                            </Link>
                        </div>

                        <!-- Customer Details -->
                        <div class="space-y-6">
                            <!-- Basic Info -->
                            <div>
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Basic Information</h3>
                                <div class="space-y-2">
                                    <div v-if="selectedCustomer.type" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">Type</span>
                                        <span class="text-sm font-medium text-gray-900 capitalize">{{ selectedCustomer.type }}</span>
                                    </div>
                                    <div v-if="selectedCustomer.status" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">Status</span>
                                        <span class="text-sm font-medium text-gray-900 capitalize">{{ selectedCustomer.status }}</span>
                                    </div>
                                    <div v-if="selectedCustomer.industry" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">Industry</span>
                                        <span class="text-sm font-medium text-gray-900">{{ selectedCustomer.industry.name }}</span>
                                    </div>
                                    <div v-if="selectedCustomer.source" class="flex items-center justify-between py-2 border-b border-gray-100">
                                        <span class="text-sm text-gray-600">Source</span>
                                        <span class="text-sm font-medium text-gray-900 capitalize">{{ selectedCustomer.source }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div v-if="selectedCustomer.contacts && selectedCustomer.contacts.length > 0">
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Contact Information</h3>
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
                                <h3 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Social Media</h3>
                                <div class="space-y-2">
                                    <div
                                        v-for="sm in selectedCustomer.social_media"
                                        :key="sm.id"
                                        class="flex items-center space-x-2 py-2 border-b border-gray-100"
                                    >
                                        <i v-if="sm.social_media_type?.icon" :class="sm.social_media_type.icon" class="w-5 h-5 text-gray-600"></i>
                                        <span class="text-sm text-gray-600 flex-1">{{ sm.social_media_type?.name || 'Social Media' }}</span>
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
                            <p class="text-gray-500 mb-4">No customer information available</p>
                            <button
                                @click="showCreateCustomerModal = true"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-colors"
                            >
                                Add as Customer
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
                <h3 class="text-lg font-semibold mb-4 text-gray-900">Add as Customer</h3>
                <form @submit.prevent="createCustomer">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Name *
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
                                Email
                            </label>
                            <input
                                v-model="customerForm.email"
                                type="email"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ channel === 'telegram' ? 'Telegram Chat ID' : (channel === 'instagram' ? 'Instagram User ID' : 'Phone') }}
                            </label>
                            <input
                                :value="channel === 'instagram' ? selectedIgUserId : (channel === 'telegram' ? selectedChatId : selectedPhone)"
                                type="text"
                                disabled
                                class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                            />
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button
                            type="button"
                            @click="showCreateCustomerModal = false"
                            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="customerForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            {{ customerForm.processing ? 'Creating...' : 'Create Customer' }}
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
                <h3 class="text-lg font-semibold mb-4 text-gray-900">Assign to existing customer</h3>
                <div class="mb-4">
                    <input
                        v-model="assignSearchQuery"
                        type="text"
                        placeholder="Search by name..."
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
                        No customers found
                    </div>
                </div>
                <div class="flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="showAssignCustomerModal = false; assignCustomerId = null; assignSearchQuery = ''"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        :disabled="!assignCustomerId"
                        @click="submitAssignCustomer"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                    >
                        Assign
                    </button>
                </div>
            </div>
        </div>

        <MediaPickerModal
            :show="showMediaPicker"
            @close="showMediaPicker = false"
            @select="onMediaSelect"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MediaPickerModal from '@/Components/MediaPickerModal.vue';
import { debounce } from 'lodash-es';

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
    searchResults: {
        type: Array,
        default: () => [],
    },
    selectedCustomer: {
        type: Object,
        default: null,
    },
});

const channel = computed(() => props.channel || 'whatsapp');
const selectedContact = computed(() => {
    if (channel.value === 'telegram') return props.selectedChatId || null;
    if (channel.value === 'instagram') return props.selectedIgUserId || null;
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
const instagramPollPrevCount = ref(0);

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
});

// جستجو با حداقل ۲ کاراکتر و درخواست فقط searchResults از سرور
const searchCustomers = debounce(() => {
    const q = searchPhone.value.trim();
    if (q.length >= 2) {
        const params = { search_phone: q, channel: channel.value };
        if (channel.value === 'telegram' && props.selectedChatId) params.chat_id = props.selectedChatId;
        if (channel.value === 'instagram' && props.selectedIgUserId) params.ig_user_id = props.selectedIgUserId;
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
        const qDigits = q.replace(/\D/g, '');
        return name.includes(q) || (qDigits.length >= 2 && (phone.includes(qDigits) || chatId.includes(q) || igUserId.includes(q)));
    });
});

const selectedConversation = computed(() => {
    return props.conversations.find(c =>
        (channel.value === 'telegram' && c.chat_id === props.selectedChatId) ||
        (channel.value === 'instagram' && c.ig_user_id === props.selectedIgUserId) ||
        (channel.value === 'whatsapp' && c.phone === props.selectedPhone)
    );
});

const getDisplayName = (conversation) => {
    if (!conversation) return selectedContact.value;
    const id = channel.value === 'telegram' ? conversation.chat_id : (channel.value === 'instagram' ? conversation.ig_user_id : conversation.phone);
    if (conversation.name && conversation.name !== id) return conversation.name;
    return id || selectedContact.value;
};

const hasCustomer = computed(() => {
    return selectedConversation.value && selectedConversation.value.customer_id;
});

const noConversationYet = computed(() => {
    return channel.value === 'instagram' && props.selectedCustomer && !props.selectedIgUserId;
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
    if (!url) return 'File';
    try {
        const urlObj = new URL(url);
        const pathname = urlObj.pathname;
        const fileName = pathname.split('/').pop();
        return fileName || 'File';
    } catch {
        const parts = url.split('/');
        return parts[parts.length - 1] || 'File';
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
    const id = channel.value === 'telegram' ? customer.chat_id : (channel.value === 'instagram' ? customer.ig_user_id : customer.phone);
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
    else params.phone = contactId;
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: false,
    });
};

const showMediaPicker = ref(false);
const sendingMessage = ref(false);

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file size (50MB max)
        if (file.size > 50 * 1024 * 1024) {
            alert('File size must be less than 50MB');
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
    if (!assignCustomerId.value || channel.value !== 'instagram' || !props.selectedIgUserId) return;
    router.post(route('inbox.assign-customer'), {
        channel: 'instagram',
        ig_user_id: props.selectedIgUserId,
        customer_id: assignCustomerId.value,
    }, {
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

    if (minutes < 1) return 'Just now';
    if (minutes < 60) return `${minutes}m ago`;
    if (hours < 24) return `${hours}h ago`;
    if (days < 7) return `${days}d ago`;

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

function runInstagramPoll() {
    const params = { channel: 'instagram' };
    if (props.selectedIgUserId) params.ig_user_id = props.selectedIgUserId;
    else if (props.selectedCustomer?.id) params.customer_id = props.selectedCustomer.id;
    else return;
    instagramPollPrevCount.value = (page.props.messages || []).length;
    router.get(route('inbox.index'), params, {
        preserveState: true,
        preserveScroll: true,
        only: ['conversations', 'messages', 'selectedCustomer'],
        onFinish: () => {
            setTimeout(() => {
                if (document.hidden && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
                    const newLen = (page.props.messages || []).length;
                    if (newLen > instagramPollPrevCount.value) {
                        try {
                            new Notification('پیام جدید اینستاگرام', { body: 'یک پیام جدید دریافت شد.' });
                        } catch (_) {}
                        instagramPollPrevCount.value = newLen;
                    }
                }
            }, 300);
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

    // Instagram: request notification permission and start polling
    if (channel.value === 'instagram') {
        if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        runInstagramPoll();
        instagramPollInterval.value = setInterval(runInstagramPoll, 15000);
    }
});

onUnmounted(() => {
    if (instagramPollInterval.value) {
        clearInterval(instagramPollInterval.value);
    }
});
</script>
