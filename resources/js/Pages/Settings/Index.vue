<template>
    <AppLayout>
        <template #header>
            Settings
        </template>

        <div class="space-y-6">
            <!-- Success/Error Messages -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>
            <div
                v-if="$page.props.flash?.google_sync_errors && $page.props.flash.google_sync_errors.length"
                class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-lg"
            >
                <p class="font-medium text-sm mb-2">جزئیات خطاهای همگام‌سازی Google:</p>
                <ul class="list-disc list-inside text-sm space-y-1">
                    <li v-for="(err, i) in $page.props.flash.google_sync_errors" :key="i">{{ err }}</li>
                </ul>
            </div>

            <!-- Tabs -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button
                            v-if="isAdmin"
                            @click="activeTab = 'social-media'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'social-media'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Social Media Platforms
                        </button>
                        <button
                            v-if="canManageOrganizationSettings"
                            @click="activeTab = 'smtp'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'smtp'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            SMTP Settings
                        </button>
                        <button
                            v-if="canManageOrganizationSettings"
                            @click="activeTab = 'ronibot'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'ronibot'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Ronibot Settings
                        </button>
                        <button
                            v-if="canManageOrganizationSettings"
                            @click="activeTab = 'telegram'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'telegram'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Telegram (Inbox)
                        </button>
                        <button
                            v-if="canManageOrganizationSettings"
                            @click="activeTab = 'instagram'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'instagram'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Instagram (Inbox)
                        </button>
                        <button
                            v-if="canManageOrganizationSettings"
                            @click="activeTab = 'google-contacts'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'google-contacts'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Google Contacts
                        </button>
                        <button
                            v-if="isAdmin"
                            @click="activeTab = 'languages'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'languages'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Languages
                        </button>
                        <button
                            v-if="isAdmin"
                            @click="activeTab = 'users'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'users'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Users Management
                        </button>
                        <button
                            v-if="isSuperAdmin"
                            @click="activeTab = 'organizations'"
                            :class="[
                                'px-6 py-4 text-sm font-medium border-b-2 transition-colors',
                                activeTab === 'organizations'
                                    ? 'border-blue-500 text-blue-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            ]"
                        >
                            Organizations
                        </button>
                    </nav>
                </div>

                <!-- Social Media Tab -->
                <div v-if="activeTab === 'social-media' && isAdmin" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Social Media Platforms</h2>
                        <button
                            @click="showAddModal = true"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            Add Platform
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="type in socialMediaTypes"
                            :key="type.id"
                            class="p-4 bg-gray-50 rounded-lg border border-gray-200"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <div v-if="type.icon" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100">
                                            <i :class="type.icon" class="text-xl text-gray-700"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ type.name }}</h3>
                                        <span
                                            v-if="type.is_active"
                                            class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full"
                                        >
                                            Active
                                        </span>
                                        <span
                                            v-else
                                            class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full"
                                        >
                                            Inactive
                                        </span>
                                    </div>
                                    <div class="mt-2 space-y-1">
                                        <p v-if="type.icon" class="text-sm text-gray-600">
                                            <span class="font-medium">Icon Class:</span> <code class="bg-gray-100 px-1 rounded">{{ type.icon }}</code>
                                        </p>
                                        <p v-if="type.base_url" class="text-sm text-gray-600">
                                            <span class="font-medium">Base URL:</span> 
                                            <a :href="type.base_url" target="_blank" class="text-blue-600 hover:underline">
                                                {{ type.base_url }}
                                            </a>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">Sort Order:</span> {{ type.sort_order }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex space-x-2">
                                    <button
                                        @click="editSocialMediaType(type)"
                                        class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteSocialMediaType(type)"
                                        class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="socialMediaTypes.length === 0" class="text-center text-gray-500 py-8">
                            No social media platforms added yet.
                        </p>
                    </div>
                </div>

                <!-- SMTP Settings Tab -->
                <div v-if="activeTab === 'smtp' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">SMTP Email Settings</h2>
                    
                    <form @submit.prevent="saveSmtpSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center space-x-2">
                                    <input
                                        v-model="smtpForm.enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Enable SMTP</span>
                                </label>
                                <label class="flex items-center space-x-2">
                                    <input
                                        v-model="smtpForm.save_to_sent"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Save copies to Sent folder</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Host *</label>
                                <input
                                    v-model="smtpForm.host"
                                    type="text"
                                    required
                                    placeholder="smtp.gmail.com"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">SMTP Port *</label>
                                <input
                                    v-model.number="smtpForm.port"
                                    type="number"
                                    required
                                    min="1"
                                    max="65535"
                                    placeholder="587"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Encryption *</label>
                                <select
                                    v-model="smtpForm.encryption"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">None</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                                <input
                                    v-model="smtpForm.username"
                                    type="text"
                                    required
                                    placeholder="your-email@gmail.com"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                                <input
                                    v-model="smtpForm.password"
                                    type="password"
                                    placeholder="Leave empty to keep existing password"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Leave empty to keep existing password</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">From Email Address *</label>
                                <input
                                    v-model="smtpForm.from_address"
                                    type="email"
                                    required
                                    placeholder="noreply@example.com"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">From Name *</label>
                                <input
                                    v-model="smtpForm.from_name"
                                    type="text"
                                    required
                                    placeholder="RoniCRM"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        <!-- IMAP Settings (for saving to Sent folder) -->
                        <div v-if="smtpForm.save_to_sent" class="border-t pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">IMAP Settings (for Sent folder)</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">IMAP Host *</label>
                                    <input
                                        v-model="smtpForm.imap_host"
                                        type="text"
                                        placeholder="imap.gmail.com"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <p class="mt-1 text-xs text-amber-700">برای نمایش ایمیل‌ها در پوشه Sent سرور حتماً پر کنید. Gmail: imap.gmail.com — سایر سرویس‌ها اغلب imap.دامنه شما</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">IMAP Port</label>
                                    <input
                                        v-model.number="smtpForm.imap_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        placeholder="993"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">Usually 993 for SSL, 143 for TLS</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">IMAP Encryption</label>
                                    <select
                                        v-model="smtpForm.imap_encryption"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="ssl">SSL</option>
                                        <option value="tls">TLS</option>
                                        <option value="none">None</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <p class="text-sm text-yellow-800 mb-2">
                                    <strong>Important:</strong> IMAP requires PHP IMAP extension to be installed.
                                </p>
                                <p class="text-xs text-yellow-700 mb-2">
                                    <strong>For Laragon:</strong>
                                </p>
                                <ol class="text-xs text-yellow-700 list-decimal list-inside space-y-1 mb-2">
                                    <li>First, enable <code>zip</code> extension in Laragon (PHP > Extensions > zip)</li>
                                    <li>Download <code>php_imap.dll</code> for PHP 8.4 from PECL or use Laragon's extension manager</li>
                                    <li>Place it in: <code>C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\ext\</code></li>
                                    <li>Enable <code>extension=imap</code> in php.ini</li>
                                    <li>Restart Laragon</li>
                                </ol>
                                <p class="text-xs text-yellow-700">
                                    <strong>Note:</strong> If IMAP is not available, emails will be saved via BCC (sent to your inbox, not Sent folder).
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t">
                            <div class="flex items-center space-x-4">
                                <input
                                    v-model="testEmail"
                                    type="email"
                                    placeholder="test@example.com"
                                    :disabled="testSmtpForm.processing"
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                />
                                <button
                                    type="button"
                                    @click="testSmtp"
                                    :disabled="testSmtpForm.processing || !testEmail"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ testSmtpForm.processing ? 'Sending...' : 'Send Test Email' }}
                                </button>
                            </div>
                            <button
                                type="submit"
                                :disabled="smtpForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ smtpForm.processing ? 'Saving...' : 'Save SMTP Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Languages Tab (Admin Only) -->
                <div v-if="activeTab === 'languages' && isAdmin" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Languages Management</h2>
                        <button
                            @click="showLanguageModal = true; languageForm = { code: '', name: '', sort_order: 0 }; editingLanguage = null"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                        >
                            Add Language
                        </button>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="lang in languages"
                            :key="lang.id"
                            class="flex items-center justify-between py-3 px-4 bg-gray-50 rounded-lg border border-gray-200"
                        >
                            <div>
                                <span class="font-medium text-gray-900">{{ lang.name }}</span>
                                <span class="text-sm text-gray-500 mr-3">({{ lang.code }})</span>
                            </div>
                            <div class="flex gap-2">
                                <button @click="editLanguage(lang)" class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                                <button @click="deleteLanguage(lang)" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                            </div>
                        </div>
                        <p v-if="!languages?.length" class="text-gray-500 py-4">No languages defined yet.</p>
                    </div>
                    <!-- Language Modal -->
                    <div
                        v-if="showLanguageModal"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                        @click.self="showLanguageModal = false"
                    >
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                            <h3 class="text-lg font-semibold mb-4">{{ editingLanguage ? 'Edit Language' : 'Add Language' }}</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Language code (e.g. en, fa) *</label>
                                    <input v-model="languageForm.code" type="text" maxlength="10" class="w-full px-3 py-2 border rounded-md" :disabled="!!editingLanguage" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                    <input v-model="languageForm.name" type="text" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sort order</label>
                                    <input v-model.number="languageForm.sort_order" type="number" min="0" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-6">
                                <button @click="showLanguageModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                                <button @click="saveLanguage" :disabled="languageSaving || !languageForm.code || !languageForm.name" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                    {{ languageSaving ? 'Saving...' : 'Save' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Management Tab (Admin Only) -->
                <div v-if="activeTab === 'users' && isAdmin" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900">Users Management</h2>
                        <button
                            @click="showCreateUserModal = true"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium"
                        >
                            Add New User
                        </button>
                    </div>

                    <!-- Users List -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Roles</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ user.username || '-' }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ user.email }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-for="role in user.roles"
                                                    :key="role"
                                                    class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full"
                                                >
                                                    {{ role }}
                                                </span>
                                                <span v-if="user.roles.length === 0" class="text-sm text-gray-400">No roles</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-500">{{ formatDate(user.created_at) }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                @click="editUser(user)"
                                                class="text-blue-600 hover:text-blue-900 mr-4"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                @click="deleteUser(user)"
                                                :disabled="user.id === $page.props.auth?.user?.id"
                                                :class="[
                                                    'text-red-600 hover:text-red-900',
                                                    user.id === $page.props.auth?.user?.id ? 'opacity-50 cursor-not-allowed' : ''
                                                ]"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="!users || users.length === 0" class="px-6 py-8 text-center text-gray-500">
                            <p>No users found.</p>
                        </div>
                    </div>

                    <!-- Create/Edit User Modal -->
                    <div
                        v-if="showCreateUserModal || showEditUserModal"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                        @click.self="closeUserModal"
                    >
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 max-h-[90vh] overflow-y-auto">
                            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ showEditUserModal ? 'Edit User' : 'Create New User' }}
                                </h3>
                                <button
                                    @click="closeUserModal"
                                    class="text-gray-400 hover:text-gray-500"
                                >
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <form @submit.prevent="saveUser" class="p-6 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                    <input
                                        v-model="userForm.name"
                                        type="text"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <div v-if="userForm.errors.name" class="mt-1 text-sm text-red-600">{{ userForm.errors.name }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Username *</label>
                                    <input
                                        v-model="userForm.username"
                                        type="text"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <div v-if="userForm.errors.username" class="mt-1 text-sm text-red-600">{{ userForm.errors.username }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                    <input
                                        v-model="userForm.email"
                                        type="email"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <div v-if="userForm.errors.email" class="mt-1 text-sm text-red-600">{{ userForm.errors.email }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Password {{ showEditUserModal ? '(leave blank to keep current)' : '*' }}
                                    </label>
                                    <input
                                        v-model="userForm.password"
                                        type="password"
                                        :required="!showEditUserModal"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <div v-if="userForm.errors.password" class="mt-1 text-sm text-red-600">{{ userForm.errors.password }}</div>
                                </div>

                                <div v-if="!showEditUserModal || userForm.password">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                                    <input
                                        v-model="userForm.password_confirmation"
                                        type="password"
                                        :required="!showEditUserModal || !!userForm.password"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <div v-if="userForm.errors.password_confirmation" class="mt-1 text-sm text-red-600">{{ userForm.errors.password_confirmation }}</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Roles</label>
                                    <div v-if="roles && roles.length > 0" class="space-y-2 border border-gray-200 rounded-lg p-3 bg-gray-50 max-h-40 overflow-y-auto">
                                        <label
                                            v-for="role in roles"
                                            :key="role.id"
                                            class="flex items-center space-x-2 cursor-pointer hover:bg-gray-100 p-2 rounded"
                                        >
                                            <input
                                                v-model="userForm.roles"
                                                type="checkbox"
                                                :value="role.name"
                                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                            />
                                            <span class="text-sm text-gray-700 font-medium">{{ role.name }}</span>
                                        </label>
                                    </div>
                                    <div v-else class="text-sm text-gray-500 italic">No roles available</div>
                                    <div v-if="userForm.errors.roles" class="mt-1 text-sm text-red-600">{{ userForm.errors.roles }}</div>
                                </div>

                                <div class="flex justify-end space-x-3 pt-4">
                                    <button
                                        type="button"
                                        @click="closeUserModal"
                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="userForm.processing"
                                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                    >
                                        {{ userForm.processing ? 'Saving...' : 'Save' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Organizations Tab (Super Admin Only) -->
                <div v-if="activeTab === 'organizations' && isSuperAdmin" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Organizations</h2>
                            <p class="text-sm text-gray-500 mt-1">Manage organizations across the whole platform.</p>
                        </div>
                    </div>

                    <form @submit.prevent="saveOrganization" class="mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                                <input
                                    v-model="organizationForm.name"
                                    type="text"
                                    required
                                    placeholder="Organization name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Slug *</label>
                                <input
                                    v-model="organizationForm.slug"
                                    type="text"
                                    required
                                    placeholder="organization-slug"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex items-center pt-7">
                                <label class="flex items-center space-x-2">
                                    <input
                                        v-model="organizationForm.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-gray-700">Active</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button
                                v-if="editingOrganization"
                                type="button"
                                @click="resetOrganizationForm"
                                class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-100 text-sm"
                            >
                                Cancel Edit
                            </button>
                            <button
                                type="submit"
                                :disabled="organizationForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm"
                            >
                                {{ organizationForm.processing ? 'Saving...' : (editingOrganization ? 'Update Organization' : 'Create Organization') }}
                            </button>
                        </div>
                    </form>

                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Slug</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Members</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="organization in organizations" :key="organization.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ organization.name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ organization.slug }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span
                                                :class="organization.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                                                class="px-2 py-1 rounded-full text-xs font-medium"
                                            >
                                                {{ organization.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ organization.users_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                @click="editOrganization(organization)"
                                                class="text-blue-600 hover:text-blue-900 mr-4"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                @click="openMembersModal(organization)"
                                                class="text-indigo-600 hover:text-indigo-900 mr-4"
                                            >
                                                Members
                                            </button>
                                            <button
                                                @click="deleteOrganization(organization)"
                                                :disabled="organization.slug === 'roni-plus'"
                                                :class="[
                                                    'text-red-600 hover:text-red-900',
                                                    organization.slug === 'roni-plus' ? 'opacity-50 cursor-not-allowed' : ''
                                                ]"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="!organizations.length" class="px-6 py-8 text-center text-gray-500">
                            No organizations found.
                        </div>
                    </div>

                    <div
                        v-if="showMembersModal"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                        @click.self="closeMembersModal"
                    >
                        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    Manage Members - {{ selectedOrganizationForMembers?.name }}
                                </h3>
                                <button @click="closeMembersModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                            </div>
                            <div class="p-6 space-y-5">
                                <form @submit.prevent="addOrganizationMember" class="p-4 border border-gray-200 rounded-lg bg-gray-50 space-y-4">
                                    <h4 class="text-sm font-semibold text-gray-800">Add Member</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">User *</label>
                                            <select v-model="memberForm.user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                                <option value="">Select user</option>
                                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Role *</label>
                                            <select v-model="memberForm.role_in_org" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                                <option value="org_admin">org_admin</option>
                                                <option value="org_manager">org_manager</option>
                                                <option value="org_agent">org_agent</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">Status *</label>
                                            <select v-model="memberForm.status" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                                <option value="active">active</option>
                                                <option value="inactive">inactive</option>
                                            </select>
                                        </div>
                                        <div class="flex items-end">
                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input v-model="memberForm.is_default" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                Default org for user
                                            </label>
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit" :disabled="memberForm.processing || !selectedOrganizationForMembers" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm">
                                            {{ memberForm.processing ? 'Adding...' : 'Add Member' }}
                                        </button>
                                    </div>
                                </form>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Default</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <tr v-for="member in selectedOrganizationMembers" :key="member.id">
                                                <td class="px-4 py-2 text-sm text-gray-900">{{ member.name }}</td>
                                                <td class="px-4 py-2 text-sm text-gray-500">{{ member.email }}</td>
                                                <td class="px-4 py-2">
                                                    <select v-model="member.role_in_org" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                                        <option value="org_admin">org_admin</option>
                                                        <option value="org_manager">org_manager</option>
                                                        <option value="org_agent">org_agent</option>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <select v-model="member.status" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                                        <option value="active">active</option>
                                                        <option value="inactive">inactive</option>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <input v-model="member.is_default" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                </td>
                                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                                    <button @click="updateOrganizationMember(member)" class="text-blue-600 hover:text-blue-900 mr-3 text-sm">Save</button>
                                                    <button @click="removeOrganizationMember(member)" class="text-red-600 hover:text-red-900 text-sm">Remove</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div v-if="!selectedOrganizationMembers.length" class="px-4 py-6 text-center text-sm text-gray-500">
                                        No members in this organization.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ronibot Settings Tab -->
                <div v-if="activeTab === 'ronibot' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Ronibot Settings</h2>
                    
                    <form @submit.prevent="saveRonibotSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <label class="flex items-center space-x-2">
                                    <input
                                        v-model="ronibotForm.enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Enable Ronibot</span>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">API URL *</label>
                                <input
                                    v-model="ronibotForm.api_url"
                                    type="url"
                                    required
                                    placeholder="https://ronibot.com/api/create-message"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Ronibot API endpoint URL</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">App Key *</label>
                                <input
                                    v-model="ronibotForm.appkey"
                                    type="text"
                                    required
                                    placeholder="Enter your Ronibot App Key"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Your Ronibot application key</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Auth Key *</label>
                                <input
                                    v-model="ronibotForm.authkey"
                                    type="text"
                                    required
                                    placeholder="Enter your Ronibot Auth Key"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Your Ronibot authentication key</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                                <input
                                    v-model="ronibotForm.webhook_url"
                                    type="url"
                                    placeholder="https://crm.roniplus.ae/wpwebhook"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Webhook URL for receiving incoming WhatsApp messages from Ronibot</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t">
                            <div class="flex items-center space-x-4">
                                <input
                                    v-model="testPhone"
                                    type="text"
                                    placeholder="Phone number (e.g., 971501234567)"
                                    :disabled="testRonibotForm.processing"
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                />
                                <input
                                    v-model="testMessage"
                                    type="text"
                                    placeholder="Test message (optional)"
                                    :disabled="testRonibotForm.processing"
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                />
                                <button
                                    type="button"
                                    @click="testRonibot"
                                    :disabled="testRonibotForm.processing || !testPhone"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ testRonibotForm.processing ? 'Sending...' : 'Send Test Message' }}
                                </button>
                            </div>
                            <button
                                type="submit"
                                :disabled="ronibotForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ ronibotForm.processing ? 'Saving...' : 'Save Ronibot Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Telegram Settings Tab -->
                <div v-if="activeTab === 'telegram' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Telegram (Inbox)</h2>

                    <!-- User Account Connection (for Inbox + Group Crawler) -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Telegram User Account</h3>
                        <p class="text-gray-600 text-sm mb-4">Connect your Telegram account to view DMs, crawl groups, and send messages. Scan the QR code with your Telegram app.</p>

                        <!-- Not Connected -->
                        <div v-if="!telegramConnection" class="p-6 border border-gray-200 rounded-lg bg-gray-50">
                            <div v-if="telegramNeeds2fa" class="mb-4 p-4 border border-amber-200 bg-amber-50 rounded-lg">
                                <p class="text-sm text-amber-800 mb-3">
                                    Two-step verification is enabled on this Telegram account. Enter your Telegram 2FA password to finish connection.
                                </p>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input
                                        v-model="telegram2faPassword"
                                        type="password"
                                        placeholder="Telegram 2FA password"
                                        class="flex-1 px-3 py-2 border border-amber-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    />
                                    <button
                                        type="button"
                                        @click="submitTelegram2fa"
                                        :disabled="telegram2faLoading"
                                        class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-50"
                                    >
                                        {{ telegram2faLoading ? 'Verifying...' : 'Complete 2FA Login' }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="telegramQrSvg" class="mb-4">
                                <p class="text-sm text-gray-700 mb-2">Scan this QR code with your Telegram app:</p>
                                <div class="inline-block p-4 bg-white rounded-lg" v-html="telegramQrSvg"></div>
                                <p class="text-xs text-amber-600 mt-2">Warning: Userbot usage carries account ban risk. Avoid flooding or spamming.</p>
                            </div>
                            <div v-if="telegramQrError" class="text-red-600 text-sm mb-4">{{ telegramQrError }}</div>
                            <div v-if="!telegramQrSvg && !telegramNeeds2fa" class="mb-4">
                                <button
                                    type="button"
                                    @click="startTelegramQr"
                                    :disabled="telegramQrLoading"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {{ telegramQrLoading ? 'Loading...' : 'Connect via QR Code' }}
                                </button>
                            </div>
                            <div class="mt-5 pt-4 border-t border-gray-200">
                                <p class="text-sm text-gray-700 mb-2">Or connect with phone number + OTP code:</p>
                                <div class="flex flex-col sm:flex-row gap-2 mb-2">
                                    <input
                                        v-model="telegramPhoneNumber"
                                        type="text"
                                        placeholder="+989121234567"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <button
                                        type="button"
                                        @click="startTelegramPhoneLogin"
                                        :disabled="telegramPhoneLoginLoading"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {{ telegramPhoneLoginLoading ? 'Sending...' : 'Send OTP' }}
                                    </button>
                                </div>
                                <div v-if="telegramWaitingOtp" class="flex flex-col sm:flex-row gap-2">
                                    <input
                                        v-model="telegramOtpCode"
                                        type="text"
                                        placeholder="Enter OTP code"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <button
                                        type="button"
                                        @click="completeTelegramPhoneLogin"
                                        :disabled="telegramOtpLoginLoading"
                                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {{ telegramOtpLoginLoading ? 'Verifying...' : 'Verify OTP' }}
                                    </button>
                                </div>
                            </div>
                            <p v-if="telegramQrPolling" class="text-sm text-gray-500">Waiting for scan... QR is stable, connection will complete automatically after scan</p>
                        </div>

                        <!-- Connected -->
                        <div v-else class="p-6 border border-gray-200 rounded-lg bg-white flex flex-wrap items-start gap-6">
                            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-2xl">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900">{{ telegramConnection.telegram_username ? '@' + telegramConnection.telegram_username : 'Telegram Account' }}</p>
                                <p class="text-sm text-gray-500" v-if="telegramConnection.phone">{{ telegramConnection.phone }}</p>
                                <p class="text-sm text-green-600 mt-1">Connected</p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="resetTelegramSession"
                                        class="px-4 py-2 border border-amber-200 text-amber-700 rounded-lg hover:bg-amber-50 text-sm font-medium"
                                    >
                                        Reset Session
                                    </button>
                                    <button
                                        type="button"
                                        @click="disconnectTelegram"
                                        class="px-4 py-2 border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium"
                                    >
                                        Disconnect
                                    </button>
                                </div>
                                <p class="text-xs text-amber-600">If you get "lightstate" error, click Reset Session and re-connect via QR code.</p>
                            </div>
                        </div>
                        <p v-if="telegramConnection" class="text-sm mt-2">
                            <a :href="route('inbox.index', { channel: 'telegram' })" class="text-blue-600 hover:underline">Open Inbox →</a>
                            <span class="text-gray-500 mx-2">|</span>
                            <a :href="route('telegram-crawler.index')" class="text-blue-600 hover:underline">Telegram Group Crawl →</a>
                        </p>
                    </div>

                    <!-- Telegram Group Categories -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Telegram Group Categories</h3>
                        <p class="text-sm text-gray-600 mb-4">Define custom categories for Telegram groups to filter and manage them.</p>
                        <div class="flex flex-wrap gap-3 items-center mb-4">
                            <input
                                v-model="newCategoryName"
                                type="text"
                                placeholder="New category name"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm w-48"
                                @keyup.enter="addTelegramGroupCategory"
                            />
                            <button
                                type="button"
                                @click="addTelegramGroupCategory"
                                :disabled="!newCategoryName.trim() || categorySaving"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm"
                            >
                                {{ categorySaving ? 'Saving...' : 'Add' }}
                            </button>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="cat in telegramGroupCategories"
                                :key="cat.id"
                                class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg border border-gray-200"
                            >
                                <span class="font-medium text-gray-900">{{ cat.name }}</span>
                                <div class="flex gap-2">
                                    <button
                                        v-if="editingCategoryId !== cat.id"
                                        @click="startEditCategory(cat)"
                                        class="text-blue-600 hover:text-blue-800 text-sm"
                                    >
                                        Edit
                                    </button>
                                    <template v-else>
                                        <input
                                            v-model="editCategoryName"
                                            type="text"
                                            class="px-2 py-1 border border-gray-300 rounded text-sm w-40"
                                            @keyup.enter="saveEditCategory"
                                        />
                                        <button @click="saveEditCategory" class="text-green-600 hover:text-green-800 text-sm">Save</button>
                                        <button @click="cancelEditCategory" class="text-gray-500 hover:text-gray-700 text-sm">Cancel</button>
                                    </template>
                                    <button
                                        @click="deleteTelegramGroupCategory(cat)"
                                        class="text-red-600 hover:text-red-800 text-sm"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <p v-if="!telegramGroupCategories?.length" class="text-gray-500 text-sm py-2">No categories defined yet.</p>
                        </div>
                    </div>

                    <!-- Bot (legacy / optional) -->
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Telegram Bot (optional)</h3>
                        <p class="text-sm text-gray-500 mb-4">Create a bot via @BotFather to receive messages via Webhook.</p>
                    </div>
                    <form @submit.prevent="saveTelegramSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <label class="flex items-center space-x-2">
                                    <input
                                        v-model="telegramForm.enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">Enable Telegram Inbox</span>
                                </label>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Bot Token *</label>
                                <input
                                    v-model="telegramForm.bot_token"
                                    type="text"
                                    placeholder="123456:ABC-DEF..."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Create a bot via @BotFather and paste the token here.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                                <p class="mt-1 text-xs text-gray-500 mb-2">با ذخیره تنظیمات، این آدرس به‌طور خودکار ثبت می‌شود. در سرور: بعد از deploy، کش را پاک کنید و دکمه «ثبت وب‌هوک همینک» را بزنید.</p>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <input
                                        :value="telegramWebhookUrlDisplay"
                                        type="text"
                                        readonly
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-700 font-mono text-sm"
                                    />
                                    <button
                                        type="button"
                                        @click="copyTelegramWebhookUrl"
                                        class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 text-sm font-medium whitespace-nowrap"
                                    >
                                        {{ webhookCopied ? 'Copied' : 'Copy' }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="registerTelegramWebhook"
                                        class="px-4 py-2 bg-amber-500 text-white rounded-md hover:bg-amber-600 text-sm font-medium whitespace-nowrap"
                                    >
                                        ثبت وب‌هوک همینک
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-4 border-t">
                            <p class="text-xs text-gray-500">Test only checks if the token is valid (no message is sent).</p>
                            <button
                                type="button"
                                @click="testTelegram"
                                :disabled="telegramForm.processing || testTelegramForm.processing || !telegramForm.bot_token"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ testTelegramForm.processing ? 'Testing...' : 'Test Bot Token' }}
                            </button>
                            <button
                                type="submit"
                                :disabled="telegramForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ telegramForm.processing ? 'Saving...' : 'Save Telegram Settings' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Instagram Settings Tab -->
                <div v-if="activeTab === 'instagram' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Instagram (Inbox)</h2>

                    <!-- Not Connected -->
                    <div v-if="!instagramConnection" class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-gray-700 mb-4">Connect your Instagram Business or Creator account via Meta. No Instagram password is required—authorization is done through Meta.</p>
                        <a :href="route('settings.instagram.connect')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                            Connect Instagram Business
                        </a>
                    </div>

                    <!-- Connected -->
                    <div v-else class="mb-8 space-y-6">
                        <div class="p-6 border border-gray-200 rounded-lg bg-white flex flex-wrap items-start gap-6">
                            <img v-if="instagramConnection.ig_profile_pic_url" :src="instagramConnection.ig_profile_pic_url" alt="Profile" class="w-16 h-16 rounded-full object-cover" />
                            <div v-else class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-2xl font-bold">{{ (instagramConnection.ig_username || 'IG').charAt(0).toUpperCase() }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900">{{ instagramConnection.ig_username || 'Instagram Account' }}</p>
                                <p class="text-sm text-gray-500">IG Business Account ID: {{ instagramConnection.ig_business_account_id }}</p>
                                <p v-if="instagramConnection.page_id" class="text-sm text-gray-500">Page ID: {{ instagramConnection.page_id }}</p>
                                <p class="text-sm mt-2">
                                    <span :class="instagramConnection.token_valid ? 'text-green-600' : 'text-amber-600'">{{ instagramConnection.token_valid ? 'Token valid' : 'Token expired' }}</span>
                                    <span v-if="instagramConnection.webhook_verified_at" class="text-gray-500 ml-2"> · Webhook verified</span>
                                    <span v-if="instagramConnection.last_webhook_event_at" class="text-gray-500 ml-2"> · Last event: {{ formatDate(instagramConnection.last_webhook_event_at) }}</span>
                                </p>
                            </div>
                            <form @submit.prevent="disconnectInstagram" class="inline">
                                <button type="submit" class="px-4 py-2 border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium">Disconnect</button>
                            </form>
                        </div>
                        <p class="text-sm">
                            <a :href="route('inbox.index', { channel: 'instagram' })" class="text-blue-600 hover:underline">Open Inbox →</a> to view DMs and reply (only to users who messaged first).
                        </p>
                    </div>

                    <!-- Webhook & legacy token (for App Dashboard setup and fallback) -->
                    <div class="mb-8 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                        <p class="font-medium mb-2">Meta App setup:</p>
                        <ul class="list-disc list-inside space-y-1 mb-2">
                            <li>Create an app at <a href="https://developers.facebook.com" target="_blank" class="underline">Facebook Developers</a> and add Instagram (API setup with Instagram login).</li>
                            <li>Use Business Login and request <code class="bg-amber-100 px-1 rounded">instagram_business_basic</code>, <code class="bg-amber-100 px-1 rounded">instagram_business_manage_messages</code>.</li>
                            <li>Set the Webhook URL and Verify Token in your app. When Meta verifies, we echo the challenge.</li>
                        </ul>
                    </div>
                    <form @submit.prevent="saveInstagramSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <label class="flex items-center space-x-2">
                                <input v-model="instagramForm.enabled" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-medium text-gray-700">Enable Instagram Inbox (legacy / fallback)</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL (Meta)</label>
                            <div class="flex gap-2">
                                <input :value="instagramWebhookUrl" type="text" readonly class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600 text-sm" />
                                <button type="button" @click="copyInstagramWebhookUrl" class="px-3 py-2 border border-gray-300 rounded-md bg-white hover:bg-gray-50 text-sm font-medium text-gray-700 whitespace-nowrap">{{ instagramWebhookCopied ? 'Copied!' : 'Copy' }}</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Webhook Verify Token</label>
                            <input v-model="instagramForm.webhook_verify_token" type="text" placeholder="e.g. roniplus_ig_webhook_2026" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Page Access Token (optional fallback)</label>
                            <input v-model="instagramForm.access_token" type="password" placeholder="EAAxxxx..." class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            <p class="mt-1 text-xs text-gray-500">If you do not use Connect, you can paste a token here. Prefer Connect for security.</p>
                        </div>
                        <button type="submit" :disabled="instagramForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">{{ instagramForm.processing ? 'Saving...' : 'Save' }}</button>
                    </form>

                    <!-- Developer Diagnostics (admin) -->
                    <div v-if="isAdmin && instagramConnection" class="mt-10 pt-8 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Developer Diagnostics</h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Scopes</p>
                                <p class="text-sm text-gray-700">{{ (instagramConnection.scopes || []).join(', ') || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">Token status</p>
                                <p class="text-sm">{{ instagramConnection.token_valid ? 'Valid' : 'Expired' }} <span v-if="instagramConnection.token_expires_at">(expires {{ formatDate(instagramConnection.token_expires_at) }})</span></p>
                            </div>
                            <form @submit.prevent="revalidateInstagram" class="inline">
                                <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium">Revalidate Token</button>
                            </form>
                            <div v-if="instagramWebhookEvents.length">
                                <p class="text-sm font-medium text-gray-600 mb-2">Last 20 webhook events (PII redacted)</p>
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">Type</th><th class="px-3 py-2 text-left">Sender</th><th class="px-3 py-2 text-left">Time</th></tr></thead>
                                        <tbody>
                                            <tr v-for="e in instagramWebhookEvents" :key="e.id" class="border-t border-gray-100"><td class="px-3 py-2">{{ e.event_type }}</td><td class="px-3 py-2">{{ e.sender_id }}</td><td class="px-3 py-2">{{ formatDate(e.created_at) }}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reviewer Instructions (admin) -->
                    <div v-if="isAdmin" class="mt-10 pt-8 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">App Review – Reviewer Instructions</h3>
                        <div class="p-4 bg-gray-100 rounded-lg text-sm text-gray-800 whitespace-pre-line">{{ reviewerInstructions }}</div>
                    </div>
                </div>

                <!-- Google Contacts (CRM → Google, one-way) -->
                <div v-if="activeTab === 'google-contacts' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">همگام‌سازی مخاطبین با Google Contacts</h2>
                    <p class="text-sm text-gray-600 mb-6 max-w-3xl">
                        اتصال یک‌طرفه از CRM به مخاطبین همان حساب Google که با آن OAuth می‌زنید.
                        نام کامل هر مشتری به صورت <strong>First / Middle / Last</strong> (بر اساس فاصله بین کلمات) ارسال می‌شود؛ ایمیل و تلفن از فیلدهای مشتری و روش‌های تماس خوانده می‌شود.
                        در صورت وجود <strong>آواتار</strong> در CRM، تصویر با API گوگل (<code class="bg-gray-100 px-1 text-xs">updateContactPhoto</code>) روی همان مخاطب در Google قرار می‌گیرد.
                        پس از ایجاد یا ویرایش مشتری در CRM نیز همگام‌سازی خودکار با Google (در پس‌زمینه) انجام می‌شود.
                    </p>

                    <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 space-y-2">
                        <p><span class="font-medium">Redirect URI در Google Cloud Console باید دقیقاً این باشد:</span></p>
                        <code class="block bg-white border px-3 py-2 rounded text-xs break-all">{{ googleRedirectUriDisplay }}</code>
                        <p class="text-xs text-gray-500">در .env می‌توانید با <code class="bg-gray-100 px-1">GOOGLE_REDIRECT_URI</code> همین URL را صریح تنظیم کنید.</p>
                    </div>

                    <div v-if="!googleContactsIntegration" class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-gray-700 mb-4">ابتدا حساب Google را وصل کنید (نیاز به scope مخاطبین). پس از اتصال می‌توانید همهٔ مشتریان CRM را به Google بفرستید.</p>
                        <a
                            :href="route('settings.google-contacts.connect')"
                            class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700"
                        >
                            اتصال به Google
                        </a>
                    </div>

                    <div v-else class="mb-8 space-y-6">
                        <div class="p-6 border border-gray-200 rounded-lg bg-white flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-gray-900">{{ googleContactsIntegration.account_email || 'Google account' }}</p>
                                <p v-if="googleContactsIntegration.connected_at" class="text-sm text-gray-500">متصل از: {{ formatDate(googleContactsIntegration.connected_at) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    :disabled="googleBulkSyncBusy"
                                    @click="startGoogleBulkSync"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 text-sm font-medium"
                                >
                                    {{ googleBulkSyncBusy ? 'در حال همگام‌سازی…' : 'همگام‌سازی همه مشتریان' }}
                                </button>
                                <form @submit.prevent="disconnectGoogleContacts">
                                    <button type="submit" class="px-4 py-2 border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium">
                                        قطع اتصال
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div
                            v-if="googleBulkProgress && googleBulkProgress.status && googleBulkProgress.status !== 'idle'"
                            class="p-4 border border-gray-200 rounded-lg bg-gray-50 space-y-2"
                        >
                            <div class="flex justify-between text-sm text-gray-700">
                                <span>
                                    وضعیت:
                                    <strong>{{ googleBulkStatusLabel }}</strong>
                                </span>
                                <span v-if="googleBulkProgress.total != null">
                                    {{ googleBulkProgress.processed ?? 0 }} / {{ googleBulkProgress.total }}
                                    <span class="text-gray-500 mr-2">موفق: {{ googleBulkProgress.success ?? 0 }} · ناموفق: {{ googleBulkProgress.failed ?? 0 }}</span>
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="bg-emerald-600 h-2.5 rounded-full transition-all duration-300"
                                    :style="{ width: googleBulkProgressPercent + '%' }"
                                ></div>
                            </div>
                            <p v-if="googleBulkProgress.message" class="text-sm text-red-700">{{ googleBulkProgress.message }}</p>
                            <ul v-if="googleBulkProgress.errors && googleBulkProgress.errors.length" class="text-xs text-amber-900 list-disc list-inside max-h-32 overflow-y-auto">
                                <li v-for="(ge, gi) in googleBulkProgress.errors" :key="gi">{{ ge }}</li>
                            </ul>
                            <p class="text-xs text-gray-500">
                                اگر از صف <code class="bg-gray-100 px-1">database</code>/<code class="bg-gray-100 px-1">redis</code> استفاده می‌کنید، worker باید اجرا شود (<code class="bg-gray-100 px-1">php artisan queue:work</code>). با درایور <code class="bg-gray-100 px-1">sync</code> بلافاصله بعد از پاسخ سرور اجرا می‌شود.
                            </p>
                        </div>

                        <p class="text-xs text-gray-500">
                            شناسهٔ Google هر مشتری در دیتابیس ذخیره می‌شود؛ ارسال مجدد همان مخاطب را در Google به‌روز می‌کند.
                            دستور کنسول (با نوار پیشرفت): <code class="bg-gray-100 px-1">php artisan google:sync-contacts</code>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div
            v-if="showAddModal || editingType"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeModal"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        {{ editingType ? 'Edit' : 'Add' }} Social Media Platform
                    </h3>

                    <form @submit.prevent="saveSocialMediaType" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Icon</label>
                            <input
                                v-model="form.icon"
                                type="text"
                                placeholder="e.g., instagram, facebook"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">Icon class name or identifier</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Base URL</label>
                            <input
                                v-model="form.base_url"
                                type="url"
                                placeholder="https://instagram.com/"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">Base URL for generating full links (e.g., https://instagram.com/)</p>
                        </div>

                        <div class="flex items-center space-x-4">
                            <label class="flex items-center space-x-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-gray-700">Active</span>
                            </label>

                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Sort Order</label>
                                <input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    initialTab: {
        type: String,
        default: 'social-media',
    },
    isAdmin: {
        type: Boolean,
        default: false,
    },
    canManageOrganizationSettings: {
        type: Boolean,
        default: false,
    },
    users: {
        type: Array,
        default: () => [],
    },
    organizations: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    socialMediaTypes: {
        type: Array,
        default: () => [],
    },
    smtpSettings: {
        type: Object,
        default: () => ({}),
    },
    ronibotSettings: {
        type: Object,
        default: () => ({}),
    },
    telegramSettings: {
        type: Object,
        default: () => ({}),
    },
    instagramSettings: {
        type: Object,
        default: () => ({}),
    },
    instagramConnection: {
        type: Object,
        default: null,
    },
    telegramConnection: {
        type: Object,
        default: null,
    },
    instagramWebhookEvents: {
        type: Array,
        default: () => [],
    },
    googleContactsIntegration: {
        type: Object,
        default: null,
    },
    googleContactsRedirectUri: {
        type: String,
        default: '',
    },
});

const activeTab = ref(props.initialTab || 'social-media');
const showAddModal = ref(false);
const page = usePage();
const isSuperAdmin = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('super_admin') || roles.includes('admin');
});
const languages = computed(() => page.props.languages || []);
const telegramGroupCategories = computed(() => page.props.telegramGroupCategories || []);

// Languages
const showLanguageModal = ref(false);
const editingLanguage = ref(null);
const languageForm = ref({ code: '', name: '', sort_order: 0 });
const languageSaving = ref(false);
async function saveLanguage() {
    languageSaving.value = true;
    try {
        if (editingLanguage.value) {
            await axios.put(route('settings.languages.update', editingLanguage.value.id), languageForm.value);
        } else {
            await axios.post(route('settings.languages.store'), languageForm.value);
        }
        showLanguageModal.value = false;
        router.reload({ preserveState: true });
    } catch (e) {
        alert(e.response?.data?.message || 'خطا');
    } finally {
        languageSaving.value = false;
    }
}
function editLanguage(lang) {
    editingLanguage.value = lang;
    languageForm.value = { code: lang.code, name: lang.name, sort_order: lang.sort_order ?? 0 };
    showLanguageModal.value = true;
}
async function deleteLanguage(lang) {
    if (!confirm(`Delete language "${lang.name}"?`)) return;
    try {
        await axios.delete(route('settings.languages.destroy', lang.id));
        router.reload({ preserveState: true });
    } catch (e) {
        alert(e.response?.data?.message || 'خطا');
    }
}

// Telegram Group Categories
const newCategoryName = ref('');
const categorySaving = ref(false);
const editingCategoryId = ref(null);
const editCategoryName = ref('');
async function addTelegramGroupCategory() {
    if (!newCategoryName.value.trim()) return;
    categorySaving.value = true;
    try {
        await axios.post(route('settings.telegram-group-categories.store'), { name: newCategoryName.value.trim() });
        newCategoryName.value = '';
        router.reload({ preserveState: true });
    } catch (e) {
        alert(e.response?.data?.message || 'خطا');
    } finally {
        categorySaving.value = false;
    }
}
function startEditCategory(cat) {
    editingCategoryId.value = cat.id;
    editCategoryName.value = cat.name;
}
async function saveEditCategory() {
    try {
        await axios.put(route('settings.telegram-group-categories.update', editingCategoryId.value), { name: editCategoryName.value });
        cancelEditCategory();
        router.reload({ preserveState: true });
    } catch (e) {
        alert(e.response?.data?.message || 'خطا');
    }
}
function cancelEditCategory() {
    editingCategoryId.value = null;
    editCategoryName.value = '';
}
async function deleteTelegramGroupCategory(cat) {
    if (!confirm(`Delete category "${cat.name}"?`)) return;
    try {
        await axios.delete(route('settings.telegram-group-categories.destroy', cat.id));
        router.reload({ preserveState: true });
    } catch (e) {
        alert(e.response?.data?.message || 'خطا');
    }
}
const editingType = ref(null);
const testEmail = ref('');

// User Management
const showCreateUserModal = ref(false);
const showEditUserModal = ref(false);
const editingUser = ref(null);
const editingOrganization = ref(null);
const showMembersModal = ref(false);
const selectedOrganizationForMembers = ref(null);
const selectedOrganizationMembers = ref([]);

const organizationForm = useForm({
    name: '',
    slug: '',
    is_active: true,
});

const memberForm = useForm({
    user_id: '',
    role_in_org: 'org_agent',
    status: 'active',
    is_default: false,
});

const userForm = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

const form = useForm({
    name: '',
    icon: '',
    base_url: '',
    is_active: true,
    sort_order: 0,
});

const smtpForm = useForm({
    host: props.smtpSettings.host || '',
    port: props.smtpSettings.port || 587,
    username: props.smtpSettings.username || '',
    password: '',
    encryption: props.smtpSettings.encryption || 'tls',
    from_address: props.smtpSettings.from_address || '',
    from_name: props.smtpSettings.from_name || '',
    enabled: props.smtpSettings.enabled || false,
    save_to_sent: props.smtpSettings.save_to_sent || false,
    imap_host: props.smtpSettings.imap_host || '',
    imap_port: props.smtpSettings.imap_port || 993,
    imap_encryption: props.smtpSettings.imap_encryption || 'ssl',
});

const ronibotForm = useForm({
    api_url: props.ronibotSettings.api_url || 'https://ronibot.com/api/create-message',
    appkey: props.ronibotSettings.appkey || '',
    authkey: props.ronibotSettings.authkey || '',
    webhook_url: props.ronibotSettings.webhook_url || 'https://crm.roniplus.ae/wpwebhook',
    enabled: props.ronibotSettings.enabled || false,
});

const telegramForm = useForm({
    bot_token: props.telegramSettings.bot_token || '',
    enabled: props.telegramSettings.enabled || false,
});

// Telegram user connection (QR)
const telegramQrSvg = ref('');
const telegramQrLoading = ref(false);
const telegramQrError = ref('');
const telegramQrPolling = ref(false);
const telegramNeeds2fa = ref(false);
const telegram2faPassword = ref('');
const telegram2faLoading = ref(false);
const telegramQrConnId = ref(null); // Ensure poll uses same session as displayed QR
const telegramQrEndpoint = '/settings/telegram/qr-code';
const telegramStatusEndpoint = '/settings/telegram/status';
const telegram2faEndpoint = '/settings/telegram/complete-2fa';
const telegramStartPhoneEndpoint = '/settings/telegram/start-phone-login';
const telegramCompletePhoneEndpoint = '/settings/telegram/complete-phone-login';
const telegramPhoneNumber = ref('');
const telegramOtpCode = ref('');
const telegramWaitingOtp = ref(false);
const telegramPhoneLoginLoading = ref(false);
const telegramOtpLoginLoading = ref(false);
let telegramQrPollTimer = null;
let telegramQrPollInFlight = false;

const parseJsonResponse = async (res) => {
    const contentType = (res.headers.get('content-type') || '').toLowerCase();
    if (!contentType.includes('application/json')) {
        const text = await res.text();
        throw new Error(`Unexpected non-JSON response (${res.status}). ${text.slice(0, 120)}`);
    }
    return await res.json();
};

const startTelegramQr = async () => {
    if (telegramQrPollTimer) {
        clearInterval(telegramQrPollTimer);
        telegramQrPollTimer = null;
    }
    telegramQrPolling.value = false;
    telegramQrLoading.value = true;
    telegramQrError.value = '';
    telegramQrSvg.value = '';
    telegramNeeds2fa.value = false;
    telegram2faPassword.value = '';
    telegramWaitingOtp.value = false;
    telegramOtpCode.value = '';
    telegramQrConnId.value = null;
    try {
        const res = await fetch(telegramQrEndpoint, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await parseJsonResponse(res);
        if (data.error) {
            telegramQrError.value = data.error;
            return;
        }
        if (data.logged_in) {
            window.location.href = route('settings.index', { tab: 'telegram' });
            return;
        }
        if (data.needs_2fa) {
            telegramNeeds2fa.value = true;
            telegramQrError.value = 'Two-step verification is enabled on this Telegram account. Please complete 2FA login.';
            telegramQrPolling.value = false;
            return;
        }
        if (data.qr_svg) {
            telegramQrSvg.value = data.qr_svg;
            telegramQrConnId.value = data.conn_id ?? null;
            telegramQrPolling.value = true;
            pollTelegramQr(); // First poll immediately - must be in "wait" when user scans
            telegramQrPollTimer = setInterval(pollTelegramQr, 1500); // Poll every 1.5s, wait=10s each
        }
    } catch (e) {
        telegramQrError.value = e.message || 'Failed to load QR';
    } finally {
        telegramQrLoading.value = false;
    }
};

const checkTelegramStatus = async () => {
    const params = new URLSearchParams();
    if (telegramQrConnId.value) params.set('conn_id', String(telegramQrConnId.value));
    const res = await fetch(
        params.toString() ? `${telegramStatusEndpoint}?${params.toString()}` : telegramStatusEndpoint,
        {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }
    );
    const data = await parseJsonResponse(res);
    if (data.conn_id) telegramQrConnId.value = data.conn_id;
    return data;
};

const startTelegramPhoneLogin = async () => {
    const phone = (telegramPhoneNumber.value || '').trim();
    if (!phone) {
        telegramQrError.value = 'Please enter phone number with country code.';
        return;
    }
    telegramPhoneLoginLoading.value = true;
    telegramQrError.value = '';
    telegramNeeds2fa.value = false;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(telegramStartPhoneEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                phone,
                conn_id: telegramQrConnId.value,
            }),
        });
        const data = await parseJsonResponse(res);
        if (data.conn_id) telegramQrConnId.value = data.conn_id;
        if (data.logged_in) {
            window.location.href = route('settings.index', { tab: 'telegram' });
            return;
        }
        if (data.needs_2fa) {
            telegramNeeds2fa.value = true;
            telegramQrError.value = 'Two-step verification is enabled. Complete 2FA login.';
            return;
        }
        if (data.waiting_code || data.success) {
            telegramWaitingOtp.value = true;
            telegramQrError.value = '';
            return;
        }
        telegramQrError.value = data.error || 'Could not start phone login.';
    } catch (e) {
        telegramQrError.value = e.message || 'Phone login request failed.';
    } finally {
        telegramPhoneLoginLoading.value = false;
    }
};

const completeTelegramPhoneLogin = async () => {
    const code = (telegramOtpCode.value || '').trim();
    if (!code) {
        telegramQrError.value = 'Please enter OTP code.';
        return;
    }
    telegramOtpLoginLoading.value = true;
    telegramQrError.value = '';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const res = await fetch(telegramCompletePhoneEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                code,
                conn_id: telegramQrConnId.value,
            }),
        });
        const data = await parseJsonResponse(res);
        if (data.logged_in || data.success) {
            window.location.href = route('settings.index', { tab: 'telegram' });
            return;
        }
        if (data.needs_2fa) {
            telegramNeeds2fa.value = true;
            telegramQrError.value = 'Two-step verification is enabled. Complete 2FA login.';
            return;
        }
        telegramQrError.value = data.error || 'OTP verification failed.';
    } catch (e) {
        telegramQrError.value = e.message || 'OTP verification request failed.';
    } finally {
        telegramOtpLoginLoading.value = false;
    }
};

const submitTelegram2fa = async () => {
    const password = (telegram2faPassword.value || '').trim();
    if (!password) {
        telegramQrError.value = 'Please enter your Telegram 2FA password.';
        return;
    }
    telegram2faLoading.value = true;
    telegramQrError.value = '';
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const payload = {
            password,
            conn_id: telegramQrConnId.value,
        };
        const res = await fetch(telegram2faEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        const data = await parseJsonResponse(res);
        if (data.logged_in || data.success) {
            window.location.href = route('settings.index', { tab: 'telegram' });
            return;
        }
        telegramQrError.value = data.error || '2FA verification failed.';
    } catch (e) {
        telegramQrError.value = e.message || '2FA request failed.';
    } finally {
        telegram2faLoading.value = false;
    }
};

const pollTelegramQr = async () => {
    if (telegramQrPollInFlight) return;
    telegramQrPollInFlight = true;
    try {
        const params = new URLSearchParams({ wait: '1' });
        if (telegramQrConnId.value) params.set('conn_id', String(telegramQrConnId.value));
        const res = await fetch(telegramQrEndpoint + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await parseJsonResponse(res);
        if (data.logged_in) {
            if (telegramQrPollTimer) clearInterval(telegramQrPollTimer);
            telegramQrPolling.value = false;
            window.location.href = route('settings.index', { tab: 'telegram' });
        } else if (data.needs_2fa) {
            if (telegramQrPollTimer) clearInterval(telegramQrPollTimer);
            telegramQrPolling.value = false;
            telegramNeeds2fa.value = true;
            telegramQrError.value = 'Two-step verification is enabled on this Telegram account. Please complete 2FA login.';
        } else if (data.qr_svg) {
            telegramQrSvg.value = data.qr_svg;
            if (data.conn_id) telegramQrConnId.value = data.conn_id;
        } else if (data.error) {
            telegramQrError.value = data.error;
        }
    } catch (e) {
        telegramQrError.value = e.message || 'Poll failed. Keep scanning—will retry.';
    } finally {
        telegramQrPollInFlight = false;
    }
};

const disconnectTelegram = () => {
    if (!confirm('Disconnect Telegram? You can reconnect anytime.')) return;
    router.post(route('settings.telegram.disconnect'), {}, {
        preserveScroll: true,
        preserveState: false, // Force fresh page props so telegramConnection updates
    });
};

const resetTelegramSession = () => {
    if (!confirm('Reset session? This fixes "lightstate" errors. You must re-connect via QR code.')) return;
    router.post(route('settings.telegram.reset-session'), {}, { preserveScroll: true });
};

const registerTelegramWebhook = () => {
    router.post(route('settings.telegram.register-webhook'), {}, { preserveScroll: true });
};

const instagramForm = useForm({
    enabled: props.instagramSettings.enabled || false,
    access_token: props.instagramSettings.access_token || '',
    webhook_verify_token: props.instagramSettings.webhook_verify_token || '',
});

const instagramWebhookUrl = typeof window !== 'undefined' && window.location?.origin
    ? `${window.location.origin}/instagram-webhook`
    : 'https://yourdomain.com/instagram-webhook';
const instagramWebhookCopied = ref(false);
const copyInstagramWebhookUrl = () => {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(instagramWebhookUrl);
        instagramWebhookCopied.value = true;
        setTimeout(() => { instagramWebhookCopied.value = false; }, 2000);
    }
};

const telegramWebhookUrlDisplay = computed(() =>
    props.telegramSettings?.webhook_url_computed
        || (typeof window !== 'undefined' && window.location?.origin ? `${window.location.origin}/telegram-webhook` : 'https://yourdomain.com/telegram-webhook')
);

const webhookCopied = ref(false);
const copyTelegramWebhookUrl = () => {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(telegramWebhookUrlDisplay.value || '');
        webhookCopied.value = true;
        setTimeout(() => { webhookCopied.value = false; }, 2000);
    }
};

const editSocialMediaType = (type) => {
    editingType.value = type;
    form.name = type.name;
    form.icon = type.icon || '';
    form.base_url = type.base_url || '';
    form.is_active = type.is_active;
    form.sort_order = type.sort_order;
    showAddModal.value = true;
};

const deleteSocialMediaType = (type) => {
    if (confirm(`Are you sure you want to delete "${type.name}"?`)) {
        router.delete(route('settings.social-media-types.destroy', type.id), {
            preserveState: true,
            preserveScroll: true,
        });
    }
};

const saveSocialMediaType = () => {
    if (editingType.value) {
        form.put(route('settings.social-media-types.update', editingType.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    } else {
        form.post(route('settings.social-media-types.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    }
};

const closeModal = () => {
    showAddModal.value = false;
    editingType.value = null;
    form.reset();
    form.is_active = true;
    form.sort_order = 0;
};

const saveSmtpSettings = () => {
    smtpForm.post(route('settings.smtp.update'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Success message is handled by flash
        },
    });
};

const saveRonibotSettings = () => {
    ronibotForm.post(route('settings.ronibot.update'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {},
    });
};

const saveTelegramSettings = () => {
    telegramForm.post(route('settings.telegram.update'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {},
    });
};

const saveInstagramSettings = () => {
    instagramForm.post(route('settings.instagram.update'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {},
    });
};

const disconnectInstagram = () => {
    if (!confirm('Disconnect this Instagram account? You can connect again later.')) return;
    router.post(route('settings.instagram.disconnect'), {}, { preserveScroll: true });
};

const googleRedirectUriDisplay = computed(() => {
    if (props.googleContactsRedirectUri) {
        return props.googleContactsRedirectUri;
    }
    if (typeof window !== 'undefined' && window.location?.origin) {
        return `${window.location.origin}/settings/google-contacts/callback`;
    }
    return '';
});

const googleBulkProgress = ref(null);
const googleBulkPolling = ref(null);
const googleBulkSyncBusy = ref(false);

const googleBulkProgressPercent = computed(() => {
    const p = googleBulkProgress.value;
    if (!p || !p.total || p.total <= 0) {
        return 0;
    }
    const done = Math.min(p.processed ?? 0, p.total);
    return Math.round((done / p.total) * 100);
});

const googleBulkStatusLabel = computed(() => {
    const s = googleBulkProgress.value?.status;
    if (s === 'queued') {
        return 'در صف…';
    }
    if (s === 'running') {
        return 'در حال پردازش…';
    }
    if (s === 'done') {
        return 'تمام شد';
    }
    if (s === 'failed') {
        return 'خطا';
    }
    return s || '—';
});

function stopGoogleBulkPolling() {
    if (googleBulkPolling.value) {
        clearInterval(googleBulkPolling.value);
        googleBulkPolling.value = null;
    }
}

async function pollGoogleBulkOnce() {
    try {
        const { data } = await window.axios.get(route('settings.google-contacts.sync-progress'));
        googleBulkProgress.value = data;
        if (data.status === 'done' || data.status === 'failed') {
            googleBulkSyncBusy.value = false;
            stopGoogleBulkPolling();
        }
    } catch {
        /* ignore */
    }
}

async function startGoogleBulkSync() {
    googleBulkSyncBusy.value = true;
    try {
        const { data } = await window.axios.post(route('settings.google-contacts.sync-start'));
        if (!data.ok) {
            throw new Error(data.message || 'شروع همگام‌سازی ناموفق');
        }
        await pollGoogleBulkOnce();
        stopGoogleBulkPolling();
        googleBulkPolling.value = setInterval(pollGoogleBulkOnce, 700);
    } catch (e) {
        googleBulkSyncBusy.value = false;
        const msg = e.response?.data?.message || e.message || 'خطا';
        alert(msg);
    }
}

watch(
    () => activeTab.value,
    async (t) => {
        if (t !== 'google-contacts') {
            stopGoogleBulkPolling();
            return;
        }
        await pollGoogleBulkOnce();
        const st = googleBulkProgress.value?.status;
        if (st === 'running' || st === 'queued') {
            googleBulkSyncBusy.value = true;
            stopGoogleBulkPolling();
            googleBulkPolling.value = setInterval(pollGoogleBulkOnce, 700);
        }
    }
);

onMounted(async () => {
    if (activeTab.value === 'google-contacts') {
        await pollGoogleBulkOnce();
        const st = googleBulkProgress.value?.status;
        if (st === 'running' || st === 'queued') {
            googleBulkSyncBusy.value = true;
            stopGoogleBulkPolling();
            googleBulkPolling.value = setInterval(pollGoogleBulkOnce, 700);
        }
    }
});

onUnmounted(() => {
    stopGoogleBulkPolling();
});

const disconnectGoogleContacts = () => {
    if (!confirm('اتصال Google قطع شود؟')) {
        return;
    }
    router.post(route('settings.google-contacts.disconnect'), {}, { preserveScroll: true });
};

const revalidateInstagram = () => {
    router.post(route('settings.instagram.revalidate'), {}, { preserveScroll: true });
};

const reviewerInstructions = `1. Log in to the CRM with the test credentials provided (app credentials, not Instagram).
2. Go to Settings → Instagram (Inbox).
3. Click "Connect Instagram Business" and complete authorization with a test Instagram Business/Creator account.
4. After redirect, you will see the connected profile (username, ID) and webhook status.
5. Open Inbox → Instagram tab. Send a DM from a second Instagram account to the connected account.
6. In the CRM Inbox, select that conversation and send a reply. Confirm the reply appears in Instagram.
7. Messaging is user-initiated only; we do not send bulk or unsolicited messages.`;

const testTelegramForm = useForm({ bot_token: '' });
const testTelegram = () => {
    testTelegramForm.bot_token = telegramForm.bot_token || '';
    testTelegramForm.post(route('settings.telegram.test'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {},
        onError: (errors) => {
            console.error('Telegram Test Error:', errors);
        },
    });
};

const testSmtpForm = useForm({
    test_email: '',
});

const testSmtp = () => {
    if (!testEmail.value) {
        alert('Please enter a test email address');
        return;
    }

    testSmtpForm.test_email = testEmail.value;
    
    testSmtpForm.post(route('settings.smtp.test'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Success/error message is handled by flash
        },
        onError: (errors) => {
            console.error('SMTP Test Error:', errors);
        },
    });
};

const testPhone = ref('');
const testMessage = ref('');

const testRonibotForm = useForm({
    test_phone: '',
    test_message: '',
});

const testRonibot = () => {
    if (!testPhone.value) {
        alert('Please enter a test phone number');
        return;
    }

    testRonibotForm.test_phone = testPhone.value;
    testRonibotForm.test_message = testMessage.value || '';
    
    testRonibotForm.post(route('settings.ronibot.test'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            // Success/error message is handled by flash
        },
        onError: (errors) => {
            console.error('Ronibot Test Error:', errors);
        },
    });
};

// User Management Functions
const editUser = (user) => {
    editingUser.value = user;
    userForm.name = user.name;
    userForm.username = user.username;
    userForm.email = user.email;
    userForm.password = '';
    userForm.password_confirmation = '';
    userForm.roles = user.roles || [];
    showEditUserModal.value = true;
};

const deleteUser = (user) => {
    if (confirm(`Are you sure you want to delete user "${user.name}"?`)) {
        router.delete(route('settings.users.destroy', user.id), {
            preserveState: true,
            preserveScroll: true,
        });
    }
};

const saveUser = () => {
    if (showEditUserModal.value && editingUser.value) {
        userForm.put(route('settings.users.update', editingUser.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeUserModal();
            },
        });
    } else {
        userForm.post(route('settings.users.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeUserModal();
            },
        });
    }
};

const closeUserModal = () => {
    showCreateUserModal.value = false;
    showEditUserModal.value = false;
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
};

const editOrganization = (organization) => {
    editingOrganization.value = organization;
    organizationForm.name = organization.name;
    organizationForm.slug = organization.slug;
    organizationForm.is_active = !!organization.is_active;
};

const resetOrganizationForm = () => {
    editingOrganization.value = null;
    organizationForm.reset();
    organizationForm.is_active = true;
    organizationForm.clearErrors();
};

const saveOrganization = () => {
    if (editingOrganization.value) {
        organizationForm.put(route('settings.organizations.update', editingOrganization.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                resetOrganizationForm();
            },
        });
        return;
    }

    organizationForm.post(route('settings.organizations.store'), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            resetOrganizationForm();
        },
    });
};

const deleteOrganization = (organization) => {
    if (organization.slug === 'roni-plus') {
        return;
    }

    if (!confirm(`Delete organization "${organization.name}"?`)) {
        return;
    }

    router.delete(route('settings.organizations.destroy', organization.id), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            if (editingOrganization.value?.id === organization.id) {
                resetOrganizationForm();
            }
        },
    });
};

const openMembersModal = (organization) => {
    selectedOrganizationForMembers.value = organization;
    selectedOrganizationMembers.value = Array.isArray(organization.members)
        ? organization.members.map((m) => ({ ...m }))
        : [];
    memberForm.reset();
    memberForm.role_in_org = 'org_agent';
    memberForm.status = 'active';
    memberForm.is_default = false;
    showMembersModal.value = true;
};

const closeMembersModal = () => {
    showMembersModal.value = false;
    selectedOrganizationForMembers.value = null;
    selectedOrganizationMembers.value = [];
    memberForm.reset();
};

const addOrganizationMember = () => {
    const organizationId = selectedOrganizationForMembers.value?.id;
    if (!organizationId) return;

    memberForm.post(route('settings.organizations.members.store', organizationId), {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                only: ['organizations', 'users', 'currentOrganization', 'currentOrganizationRole'],
                preserveState: true,
                onSuccess: () => {
                    const refreshed = props.organizations.find((o) => o.id === organizationId);
                    if (refreshed) {
                        openMembersModal(refreshed);
                    }
                },
            });
        },
    });
};

const updateOrganizationMember = (member) => {
    const organizationId = selectedOrganizationForMembers.value?.id;
    if (!organizationId) return;

    router.put(
        `/settings/organizations/${organizationId}/members/${member.id}`,
        {
            role_in_org: member.role_in_org,
            status: member.status,
            is_default: !!member.is_default,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['organizations', 'currentOrganization', 'currentOrganizationRole'], preserveState: true });
            },
        }
    );
};

const removeOrganizationMember = (member) => {
    const organizationId = selectedOrganizationForMembers.value?.id;
    if (!organizationId) return;

    if (!confirm(`Remove "${member.name}" from this organization?`)) return;

    router.delete(`/settings/organizations/${organizationId}/members/${member.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedOrganizationMembers.value = selectedOrganizationMembers.value.filter((m) => m.id !== member.id);
            router.reload({ only: ['organizations', 'currentOrganization', 'currentOrganizationRole'], preserveState: true });
        },
    });
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>
