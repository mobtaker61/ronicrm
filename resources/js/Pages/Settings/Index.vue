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

            <!-- Tabs -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b border-gray-200">
                    <nav class="flex -mb-px">
                        <button
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
                    </nav>
                </div>

                <!-- Social Media Tab -->
                <div v-if="activeTab === 'social-media'" class="p-6">
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
                <div v-if="activeTab === 'smtp'" class="p-6">
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

                <!-- Ronibot Settings Tab -->
                <div v-if="activeTab === 'ronibot'" class="p-6">
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
                <div v-if="activeTab === 'telegram'" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">Telegram (Inbox)</h2>
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
                                    required
                                    placeholder="123456:ABC-DEF..."
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">Create a bot via @BotFather and paste the token here.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Webhook URL</label>
                                <input
                                    v-model="telegramForm.webhook_url"
                                    type="url"
                                    :placeholder="telegramWebhookPlaceholder"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">URL for receiving incoming messages (e.g. https://yourdomain.com/telegram-webhook)</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-4 border-t">
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
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    isAdmin: {
        type: Boolean,
        default: false,
    },
    users: {
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
});

const activeTab = ref('social-media');
const showAddModal = ref(false);
const editingType = ref(null);
const testEmail = ref('');

// User Management
const showCreateUserModal = ref(false);
const showEditUserModal = ref(false);
const editingUser = ref(null);

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
    webhook_url: props.telegramSettings.webhook_url || '',
    enabled: props.telegramSettings.enabled || false,
});

const telegramWebhookPlaceholder = typeof window !== 'undefined' && window.location?.origin
    ? `${window.location.origin}/telegram-webhook`
    : 'https://yourdomain.com/telegram-webhook';

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

const testTelegramForm = useForm({});
const testTelegram = () => {
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
