<template>
    <AppLayout>
        <template #header>
            {{ settingsPageTitle }}
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
                <p class="font-medium text-sm mb-2">{{ t('settings.google_sync_errors_title') }}</p>
                <ul class="list-disc list-inside text-sm space-y-1">
                    <li v-for="(err, i) in $page.props.flash.google_sync_errors" :key="i">{{ err }}</li>
                </ul>
            </div>

            <div class="bg-white rounded-lg shadow">
                <!-- Social Media Tab (moved to SuperAdmin menu) -->
                <div v-if="activeTab === 'social-media' && isAdmin" class="p-6 hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900">{{ t('superadmin.social_media_platforms.title') }}</h2>
                        <button
                            @click="showAddModal = true"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                        >
                            {{ t('superadmin.social_media_platforms.add_platform') }}
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
                                    <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                        <div v-if="type.icon" class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-100">
                                            <i :class="type.icon" class="text-xl text-gray-700"></i>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-900">{{ type.name }}</h3>
                                        <span
                                            v-if="type.is_active"
                                            class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full"
                                        >
                                            {{ t('common.active') }}
                                        </span>
                                        <span
                                            v-else
                                            class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full"
                                        >
                                            {{ t('common.inactive') }}
                                        </span>
                                    </div>
                                    <div class="mt-2 space-y-1">
                                        <p v-if="type.icon" class="text-sm text-gray-600">
                                            <span class="font-medium">{{ t('common.icon') }}:</span> <code class="bg-gray-100 px-1 rounded">{{ type.icon }}</code>
                                        </p>
                                        <p v-if="type.base_url" class="text-sm text-gray-600">
                                            <span class="font-medium">{{ t('common.base_url') }}:</span>
                                            <a :href="type.base_url" target="_blank" class="text-blue-600 hover:underline">
                                                {{ type.base_url }}
                                            </a>
                                        </p>
                                        <p class="text-sm text-gray-600">
                                            <span class="font-medium">{{ t('common.sort_order') }}:</span> {{ type.sort_order }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex space-x-2 rtl:space-x-reverse">
                                    <button
                                        @click="editSocialMediaType(type)"
                                        class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                    >
                                        {{ t('common.edit') }}
                                    </button>
                                    <button
                                        @click="deleteSocialMediaType(type)"
                                        class="px-3 py-1 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-if="socialMediaTypes.length === 0" class="text-center text-gray-500 py-8">
                            {{ t('superadmin.social_media_platforms.empty') }}
                        </p>
                    </div>
                </div>

                <!-- SMTP Settings Tab -->
                <div v-if="activeTab === 'smtp' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">{{ t('settings.smtp_email_settings') }}</h2>
                    
                    <form @submit.prevent="saveSmtpSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-4 rtl:space-x-reverse">
                                <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                    <input
                                        v-model="smtpForm.enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">{{ t('settings.enable_smtp') }}</span>
                                </label>
                                <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                    <input
                                        v-model="smtpForm.save_to_sent"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">{{ t('settings.save_to_sent_folder') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.smtp_host_required') }}</label>
                                <input
                                    v-model="smtpForm.host"
                                    type="text"
                                    required
                                    :placeholder="t('settings.smtp_host_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.smtp_port_required') }}</label>
                                <input
                                    v-model.number="smtpForm.port"
                                    type="number"
                                    required
                                    min="1"
                                    max="65535"
                                    :placeholder="t('settings.smtp_port_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.encryption_required') }}</label>
                                <select
                                    v-model="smtpForm.encryption"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="tls">{{ t('settings.encryption_tls') }}</option>
                                    <option value="ssl">{{ t('settings.encryption_ssl') }}</option>
                                    <option value="none">{{ t('settings.none') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.username_required') }}</label>
                                <input
                                    v-model="smtpForm.username"
                                    type="text"
                                    required
                                    :placeholder="t('settings.username_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('auth.password') }}</label>
                                <input
                                    v-model="smtpForm.password"
                                    type="password"
                                    :placeholder="t('settings.password_keep_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.password_keep_hint') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.from_email_required') }}</label>
                                <input
                                    v-model="smtpForm.from_address"
                                    type="email"
                                    required
                                    :placeholder="t('settings.from_email_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.from_name_required') }}</label>
                                <input
                                    v-model="smtpForm.from_name"
                                    type="text"
                                    required
                                    :placeholder="t('settings.from_name_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        <!-- IMAP Settings (for saving to Sent folder) -->
                        <div v-if="smtpForm.save_to_sent" class="border-t pt-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('settings.imap_settings_for_sent') }}</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.imap_host_required') }}</label>
                                    <input
                                        v-model="smtpForm.imap_host"
                                        type="text"
                                        :placeholder="t('settings.imap_host_placeholder')"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <p class="mt-1 text-xs text-amber-700">{{ t('settings.imap_host_help') }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.imap_port') }}</label>
                                    <input
                                        v-model.number="smtpForm.imap_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        :placeholder="t('settings.imap_port_placeholder')"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">{{ t('settings.imap_port_help') }}</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.imap_encryption') }}</label>
                                    <select
                                        v-model="smtpForm.imap_encryption"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="ssl">{{ t('settings.encryption_ssl') }}</option>
                                        <option value="tls">{{ t('settings.encryption_tls') }}</option>
                                        <option value="none">{{ t('settings.none') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                                <p class="text-sm text-yellow-800 mb-2">
                                    <strong>{{ t('settings.important') }}:</strong> {{ t('settings.imap_extension_required') }}
                                </p>
                                <p class="text-xs text-yellow-700 mb-2">
                                    <strong>{{ t('settings.for_laragon') }}:</strong>
                                </p>
                                <ol class="text-xs text-yellow-700 list-decimal list-inside space-y-1 mb-2">
                                    <li>{{ t('settings.laragon_step_1') }}</li>
                                    <li>{{ t('settings.laragon_step_2') }}</li>
                                    <li>{{ t('settings.laragon_step_3') }}</li>
                                    <li>{{ t('settings.laragon_step_4') }}</li>
                                    <li>{{ t('settings.laragon_step_5') }}</li>
                                </ol>
                                <p class="text-xs text-yellow-700">
                                    <strong>{{ t('common.note') }}:</strong> {{ t('settings.imap_fallback_note') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t">
                            <div class="flex items-center space-x-4 rtl:space-x-reverse">
                                <input
                                    v-model="testEmail"
                                    type="email"
                                    :placeholder="t('settings.test_email_placeholder')"
                                    :disabled="testSmtpForm.processing"
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                />
                                <button
                                    type="button"
                                    @click="testSmtp"
                                    :disabled="testSmtpForm.processing || !testEmail"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ testSmtpForm.processing ? t('settings.sending') : t('settings.send_test_email') }}
                                </button>
                            </div>
                            <button
                                type="submit"
                                :disabled="smtpForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ smtpForm.processing ? t('settings.saving') : t('settings.save_smtp_settings') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Languages Tab (Admin Only) -->
                <div v-if="activeTab === 'languages' && isAdmin" class="p-6 hidden">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-gray-900">{{ t('settings.languages_management') }}</h2>
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
                                <span class="text-sm text-gray-500 ltr:mr-3 rtl:ml-3">({{ lang.code }})</span>
                            </div>
                            <div class="flex gap-2">
                                <button @click="editLanguage(lang)" class="text-blue-600 hover:text-blue-800 text-sm">{{ t('common.edit') }}</button>
                                <button @click="deleteLanguage(lang)" class="text-red-600 hover:text-red-800 text-sm">{{ t('common.delete') }}</button>
                            </div>
                        </div>
                        <p v-if="!languages?.length" class="text-gray-500 py-4">{{ t('settings.no_languages_defined') }}</p>
                    </div>
                    <!-- Language Modal -->
                    <div
                        v-if="showLanguageModal"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                        @click.self="showLanguageModal = false"
                    >
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 p-6">
                            <h3 class="text-lg font-semibold mb-4">{{ editingLanguage ? t('settings.edit_language') : t('settings.add_language') }}</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.language_code_required') }}</label>
                                    <input v-model="languageForm.code" type="text" maxlength="10" class="w-full px-3 py-2 border rounded-md" :disabled="!!editingLanguage" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }} *</label>
                                    <input v-model="languageForm.name" type="text" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.sort_order') }}</label>
                                    <input v-model.number="languageForm.sort_order" type="number" min="0" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-6">
                                <button @click="showLanguageModal = false" class="px-4 py-2 border rounded-lg hover:bg-gray-50">{{ t('common.cancel') }}</button>
                                <button @click="saveLanguage" :disabled="languageSaving || !languageForm.code || !languageForm.name" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
                                    {{ languageSaving ? t('settings.saving') : t('common.save') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Organizations Tab (Super Admin Only) -->
                <div v-if="activeTab === 'organizations' && isSuperAdmin" class="p-6 hidden">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">{{ t('superadmin.organizations') }}</h2>
                            <p class="text-sm text-gray-500 mt-1">{{ t('settings.manage_organizations_help') }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="saveOrganization" class="mb-6 p-4 border border-gray-200 rounded-lg bg-gray-50 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }} *</label>
                                <input
                                    v-model="organizationForm.name"
                                    type="text"
                                    required
                                    :placeholder="t('settings.organization_name_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.slug') }} *</label>
                                <input
                                    v-model="organizationForm.slug"
                                    type="text"
                                    required
                                    :placeholder="t('settings.organization_slug_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div class="flex items-center pt-7">
                                <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                    <input
                                        v-model="organizationForm.is_active"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm text-gray-700">{{ t('common.active') }}</span>
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
                                {{ t('settings.cancel_edit') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="organizationForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm"
                            >
                                {{ organizationForm.processing ? t('settings.saving') : (editingOrganization ? t('settings.update_organization') : t('settings.create_organization')) }}
                            </button>
                        </div>
                    </form>

                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.name') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.slug') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.status') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('settings.members') }}</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
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
                                                {{ organization.is_active ? t('common.active') : t('common.inactive') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ organization.users_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                @click="editOrganization(organization)"
                                                class="text-blue-600 hover:text-blue-900 ltr:mr-4 rtl:ml-4"
                                            >
                                                {{ t('common.edit') }}
                                            </button>
                                            <button
                                                @click="openMembersModal(organization)"
                                                class="text-indigo-600 hover:text-indigo-900 ltr:mr-4 rtl:ml-4"
                                            >
                                                {{ t('settings.members') }}
                                            </button>
                                            <button
                                                @click="deleteOrganization(organization)"
                                                :disabled="organization.slug === 'roni-plus'"
                                                :class="[
                                                    'text-red-600 hover:text-red-900',
                                                    organization.slug === 'roni-plus' ? 'opacity-50 cursor-not-allowed' : ''
                                                ]"
                                            >
                                                {{ t('common.delete') }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="!organizations.length" class="px-6 py-8 text-center text-gray-500">
                            {{ t('superadmin.organizations.empty') }}
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
                                    {{ t('settings.manage_members') }} - {{ selectedOrganizationForMembers?.name }}
                                </h3>
                                <button @click="closeMembersModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                            </div>
                            <div class="p-6 space-y-5">
                                <form @submit.prevent="addOrganizationMember" class="p-4 border border-gray-200 rounded-lg bg-gray-50 space-y-4">
                                    <h4 class="text-sm font-semibold text-gray-800">{{ t('settings.add_member') }}</h4>
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('common.user') }} *</label>
                                            <select v-model="memberForm.user_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                                <option value="">{{ t('settings.select_user') }}</option>
                                                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('common.role') }} *</label>
                                            <select v-model="memberForm.role_in_org" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                                <option value="org_admin">{{ t('settings.org_admin') }}</option>
                                                <option value="org_manager">{{ t('settings.org_manager') }}</option>
                                                <option value="org_agent">{{ t('settings.org_agent') }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('common.status') }} *</label>
                                            <select v-model="memberForm.status" required class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                                                <option value="active">{{ t('common.active') }}</option>
                                                <option value="inactive">{{ t('common.inactive') }}</option>
                                            </select>
                                        </div>
                                        <div class="flex items-end">
                                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                                <input v-model="memberForm.is_default" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                {{ t('settings.default_org_for_user') }}
                                            </label>
                                        </div>
                                    </div>
                                    <div class="flex justify-end">
                                        <button type="submit" :disabled="memberForm.processing || !selectedOrganizationForMembers" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm">
                                            {{ memberForm.processing ? t('settings.adding') : t('settings.add_member') }}
                                        </button>
                                    </div>
                                </form>

                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.user') }}</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.email') }}</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.role') }}</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('common.status') }}</th>
                                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t('settings.default') }}</th>
                                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.actions') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 bg-white">
                                            <tr v-for="member in selectedOrganizationMembers" :key="member.id">
                                                <td class="px-4 py-2 text-sm text-gray-900">
                                                    <div class="flex items-center gap-2">
                                                        <div class="w-7 h-7 rounded-full overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
                                                            <img
                                                                v-if="member.avatar_url"
                                                                :src="member.avatar_url"
                                                                :alt="t('common.avatar')"
                                                                class="w-full h-full object-cover"
                                                            />
                                                            <span v-else class="text-[10px] font-semibold text-gray-500">{{ getInitials(member.name) }}</span>
                                                        </div>
                                                        <span>{{ member.name }}</span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-gray-500">{{ member.email }}</td>
                                                <td class="px-4 py-2">
                                                    <select v-model="member.role_in_org" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                                        <option value="org_admin">{{ t('settings.org_admin') }}</option>
                                                        <option value="org_manager">{{ t('settings.org_manager') }}</option>
                                                        <option value="org_agent">{{ t('settings.org_agent') }}</option>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <select v-model="member.status" class="px-2 py-1 border border-gray-300 rounded text-sm">
                                                        <option value="active">{{ t('common.active') }}</option>
                                                        <option value="inactive">{{ t('common.inactive') }}</option>
                                                    </select>
                                                </td>
                                                <td class="px-4 py-2">
                                                    <input v-model="member.is_default" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                                </td>
                                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                                <button @click="updateOrganizationMember(member)" class="text-blue-600 hover:text-blue-900 ltr:mr-3 rtl:ml-3 text-sm">{{ t('common.save') }}</button>
                                                    <button @click="removeOrganizationMember(member)" class="text-red-600 hover:text-red-900 text-sm">{{ t('customers.remove') }}</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div v-if="!selectedOrganizationMembers.length" class="px-4 py-6 text-center text-sm text-gray-500">
                                        {{ t('settings.no_members_in_organization') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ronibot Settings Tab -->
                <div v-if="activeTab === 'ronibot' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">{{ t('settings.ronibot_settings') }}</h2>

                    <!-- اتصال خودکار از طریق Partner API (RoniBot) — دو ستون: فرم / QR -->
                    <div class="mb-8 rounded-lg border border-blue-200 bg-blue-50/80 p-5">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ t('settings.ronibot_partner_section_title') }}</h3>
                        <p class="text-sm text-gray-600 mb-4">{{ t('settings.ronibot_partner_intro') }}</p>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start">
                            <!-- ستون فرم -->
                            <div class="space-y-4 min-w-0">
                                <p
                                    v-if="!ronibotPartnerDisplayWebhook"
                                    class="text-sm text-amber-900 bg-amber-50 border border-amber-200 rounded-md p-2"
                                >
                                    {{ t('settings.ronibot_partner_app_url_missing') }}
                                </p>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.ronibot_partner_phone_label') }}</label>
                                    <input
                                        v-model="ronibotPartnerPhone"
                                        type="tel"
                                        autocomplete="tel"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 bg-white"
                                        :placeholder="t('settings.ronibot_partner_phone_placeholder')"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.ronibot_partner_crm_password_label') }}</label>
                                    <input
                                        v-model="ronibotPartnerPassword"
                                        type="password"
                                        autocomplete="current-password"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 bg-white"
                                        :placeholder="t('settings.ronibot_partner_crm_password_placeholder')"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.webhook_url') }}</label>
                                    <div
                                        class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-800 text-sm break-all min-h-[2.5rem]"
                                    >
                                        {{ ronibotPartnerDisplayWebhook || '—' }}
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ t('settings.ronibot_webhook_readonly_hint') }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm"
                                        :disabled="
                                            ronibotPartnerLoading ||
                                            ronibotPartnerPolling ||
                                            !String(ronibotPartnerPhone || '').trim() ||
                                            !String(ronibotPartnerPassword || '').trim() ||
                                            !ronibotPartnerDisplayWebhook
                                        "
                                        @click="ronibotPartnerStartAutoFlow"
                                    >
                                        {{ t('settings.ronibot_partner_start_connection') }}
                                    </button>
                                    <button
                                        v-if="ronibotPartnerNeedsAppRetry"
                                        type="button"
                                        class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50 text-sm"
                                        :disabled="ronibotPartnerLoading"
                                        @click="ronibotPartnerRetryCreateApp"
                                    >
                                        {{ t('settings.ronibot_partner_retry_app') }}
                                    </button>
                                </div>
                                <p v-if="ronibotPartnerStepLabel" class="text-sm text-gray-800">{{ ronibotPartnerStepLabel }}</p>
                                <p v-if="ronibotPartnerError" class="text-sm text-red-600">{{ ronibotPartnerError }}</p>
                                <p v-if="ronibotPartnerMessage" class="text-sm text-green-700">{{ ronibotPartnerMessage }}</p>
                                <p v-if="ronibotPartnerPolling" class="text-sm text-blue-800">{{ t('settings.ronibot_partner_waiting_whatsapp') }}</p>
                            </div>

                            <!-- ستون QR -->
                            <div
                                class="min-w-0 rounded-xl border-2 border-dashed border-blue-200 bg-white/90 p-4 flex flex-col items-center justify-center min-h-[300px] lg:min-h-[360px] lg:sticky lg:top-4"
                            >
                                <template v-if="ronibotQrSrc && !ronibotPartnerSetupComplete">
                                    <p class="text-xs text-gray-600 mb-3 text-center w-full">{{ t('settings.ronibot_partner_qr_scan') }}</p>
                                    <img
                                        :src="ronibotQrSrc"
                                        alt="WhatsApp QR"
                                        class="max-w-[280px] w-full h-auto object-contain rounded-lg shadow-md"
                                    />
                                </template>
                                <div v-else-if="ronibotPartnerSetupComplete" class="text-center px-2">
                                    <p class="text-green-700 font-medium text-sm">{{ t('settings.ronibot_partner_qr_done_hint') }}</p>
                                </div>
                                <p v-else class="text-sm text-gray-400 text-center px-4">{{ t('settings.ronibot_partner_qr_placeholder') }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <form @submit.prevent="saveRonibotSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                    <input
                                        v-model="ronibotForm.enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">{{ t('settings.enable_ronibot') }}</span>
                                </label>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.api_url_required') }}</label>
                                <input
                                    v-model="ronibotForm.api_url"
                                    type="url"
                                    readonly
                                    tabindex="-1"
                                    :placeholder="t('settings.ronibot_api_url_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-800 cursor-default focus:outline-none"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.ronibot_managed_by_system') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.app_key_required') }}</label>
                                <input
                                    v-model="ronibotForm.appkey"
                                    type="text"
                                    :placeholder="t('settings.ronibot_app_key_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.ronibot_app_key_help') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.auth_key_required') }}</label>
                                <input
                                    v-model="ronibotForm.authkey"
                                    type="text"
                                    :placeholder="t('settings.ronibot_auth_key_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.ronibot_auth_key_help') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.webhook_url') }}</label>
                                <input
                                    v-model="ronibotForm.webhook_url"
                                    type="url"
                                    readonly
                                    tabindex="-1"
                                    :placeholder="t('settings.ronibot_webhook_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-200 rounded-md bg-gray-50 text-gray-800 cursor-default focus:outline-none"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.ronibot_managed_by_system') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.ronibot_line_phone_label') }}</label>
                                <input
                                    v-model="ronibotForm.line_phone"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="t('settings.ronibot_line_phone_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.ronibot_line_phone_help') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.ronibot_session_id_label') }}</label>
                                <input
                                    v-model="ronibotForm.wa_session_id"
                                    type="text"
                                    autocomplete="off"
                                    :placeholder="t('settings.ronibot_session_id_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.ronibot_session_id_help') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-4 border-t">
                            <div class="flex items-center space-x-4 rtl:space-x-reverse">
                                <input
                                    v-model="testPhone"
                                    type="text"
                                    :placeholder="t('settings.test_phone_placeholder')"
                                    :disabled="testRonibotForm.processing"
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                />
                                <input
                                    v-model="testMessage"
                                    type="text"
                                    :placeholder="t('settings.test_message_optional')"
                                    :disabled="testRonibotForm.processing"
                                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                                />
                                <button
                                    type="button"
                                    @click="testRonibot"
                                    :disabled="testRonibotForm.processing || !testPhone"
                                    class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    {{ testRonibotForm.processing ? t('settings.sending') : t('settings.send_test_message') }}
                                </button>
                            </div>
                            <button
                                type="submit"
                                :disabled="ronibotForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ ronibotForm.processing ? t('settings.saving') : t('settings.save_ronibot_settings') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Organization Notifications Tab -->
                <div v-if="activeTab === 'notifications' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">اعلان‌های سازمان</h2>
                    <p class="text-sm text-gray-600 mb-6">
                        در این بخش می‌توانید ارسال پیام‌های خودکار (مثلاً خوش‌آمدگویی هنگام افزودن مخاطب) را برای ایمیل و واتساپ مدیریت کنید.
                        متغیرهای قابل استفاده: <code class="bg-gray-100 px-1 rounded">{name}</code>،
                        <code class="bg-gray-100 px-1 rounded">{company}</code>،
                        <code class="bg-gray-100 px-1 rounded">{public_link}</code>،
                        <code class="bg-gray-100 px-1 rounded">{org_name}</code>
                    </p>

                    <form @submit.prevent="saveOrgNotifications" class="space-y-6">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">افزودن مخاطب جدید</p>
                                    <p class="text-xs text-gray-600">وقتی مخاطب جدید ساخته می‌شود (از فرم، اینباکس، وب‌هوک و ...)</p>
                                </div>
                                <label class="flex items-center gap-2">
                                    <input
                                        v-model="orgNotificationsForm.events.customer_created.enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">فعال</span>
                                </label>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="rounded-lg border border-gray-200 p-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-900">ایمیل</h3>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="orgNotificationsForm.events.customer_created.channels.email.enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="text-sm text-gray-700">ارسال</span>
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">موضوع ایمیل</label>
                                    <input
                                        v-model="orgNotificationsForm.events.customer_created.channels.email.subject"
                                        type="text"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                        placeholder="مثلاً: خوش آمدید {name}"
                                    />
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">متن ایمیل</label>
                                    <textarea
                                        v-model="orgNotificationsForm.events.customer_created.channels.email.body"
                                        rows="8"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                        placeholder="متن پیام..."
                                    ></textarea>
                                    <p class="mt-1 text-xs text-gray-500">می‌توانید متن ساده یا HTML وارد کنید.</p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4 space-y-4">
                                <div class="flex items-center justify-between">
                                    <h3 class="font-semibold text-gray-900">واتساپ (Ronibot)</h3>
                                    <label class="flex items-center gap-2">
                                        <input
                                            v-model="orgNotificationsForm.events.customer_created.channels.whatsapp.enabled"
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="text-sm text-gray-700">ارسال</span>
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">متن پیام واتساپ</label>
                                    <textarea
                                        v-model="orgNotificationsForm.events.customer_created.channels.whatsapp.body"
                                        rows="10"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
                                        placeholder="متن پیام..."
                                    ></textarea>
                                    <p class="mt-1 text-xs text-gray-500">حداکثر ۵۰۰۰ کاراکتر.</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-3">
                            <button
                                type="submit"
                                :disabled="orgNotificationsForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ orgNotificationsForm.processing ? t('settings.saving') : 'ذخیره تنظیمات اعلان‌ها' }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Telegram Settings Tab -->
                <div v-if="activeTab === 'telegram' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">{{ t('settings.tabs.telegram_inbox') }}</h2>

                    <!-- User Account Connection (for Inbox + Group Crawler) -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">{{ t('settings.telegram_user_account') }}</h3>
                        <p class="text-gray-600 text-sm mb-4">{{ t('settings.telegram_user_account_help') }}</p>

                        <!-- Not Connected -->
                        <div v-if="!telegramConnection" class="p-6 border border-gray-200 rounded-lg bg-gray-50">
                            <div v-if="telegramNeeds2fa" class="mb-4 p-4 border border-amber-200 bg-amber-50 rounded-lg">
                                <p class="text-sm text-amber-800 mb-3">
                                    {{ t('settings.telegram_2fa_help') }}
                                </p>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <input
                                        v-model="telegram2faPassword"
                                        type="password"
                                        :placeholder="t('settings.telegram_2fa_password_placeholder')"
                                        class="flex-1 px-3 py-2 border border-amber-300 rounded-md focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    />
                                    <button
                                        type="button"
                                        @click="submitTelegram2fa"
                                        :disabled="telegram2faLoading"
                                        class="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-50"
                                    >
                                        {{ telegram2faLoading ? t('settings.verifying') : t('settings.complete_2fa_login') }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="telegramQrSvg" class="mb-4">
                                <p class="text-sm text-gray-700 mb-2">{{ t('settings.telegram_scan_qr') }}</p>
                                <div class="inline-block p-4 bg-white rounded-lg" v-html="telegramQrSvg"></div>
                                <p class="text-xs text-amber-600 mt-2">{{ t('settings.telegram_userbot_warning') }}</p>
                            </div>
                            <div v-if="telegramQrError" class="text-red-600 text-sm mb-4">{{ telegramQrError }}</div>
                            <div v-if="!telegramQrSvg && !telegramNeeds2fa" class="mb-4">
                                <button
                                    type="button"
                                    @click="startTelegramQr"
                                    :disabled="telegramQrLoading"
                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                                >
                                    {{ telegramQrLoading ? t('settings.loading') : t('settings.telegram_connect_qr') }}
                                </button>
                            </div>
                            <div class="mt-5 pt-4 border-t border-gray-200">
                                <p class="text-sm text-gray-700 mb-2">{{ t('settings.telegram_or_phone_otp') }}</p>
                                <div class="flex flex-col sm:flex-row gap-2 mb-2">
                                    <input
                                        v-model="telegramPhoneNumber"
                                        type="text"
                                        :placeholder="t('settings.telegram_phone_placeholder')"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <button
                                        type="button"
                                        @click="startTelegramPhoneLogin"
                                        :disabled="telegramPhoneLoginLoading"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {{ telegramPhoneLoginLoading ? t('settings.sending') : t('settings.send_otp') }}
                                    </button>
                                </div>
                                <div v-if="telegramWaitingOtp" class="flex flex-col sm:flex-row gap-2">
                                    <input
                                        v-model="telegramOtpCode"
                                        type="text"
                                        :placeholder="t('settings.enter_otp_code')"
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                    <button
                                        type="button"
                                        @click="completeTelegramPhoneLogin"
                                        :disabled="telegramOtpLoginLoading"
                                        class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {{ telegramOtpLoginLoading ? t('settings.verifying') : t('settings.verify_otp') }}
                                    </button>
                                </div>
                            </div>
                            <p v-if="telegramQrPolling" class="text-sm text-gray-500">{{ t('settings.telegram_waiting_scan') }}</p>
                        </div>

                        <!-- Connected -->
                        <div v-else class="p-6 border border-gray-200 rounded-lg bg-white flex flex-wrap items-start gap-6">
                            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-2xl">
                                <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900">{{ telegramConnection.telegram_username ? '@' + telegramConnection.telegram_username : t('settings.telegram_account') }}</p>
                                <p class="text-sm text-gray-500" v-if="telegramConnection.phone">{{ telegramConnection.phone }}</p>
                                <p class="text-sm text-green-600 mt-1">{{ t('settings.connected') }}</p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        @click="resetTelegramSession"
                                        class="px-4 py-2 border border-amber-200 text-amber-700 rounded-lg hover:bg-amber-50 text-sm font-medium"
                                    >
                                        {{ t('settings.reset_session') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="disconnectTelegram"
                                        class="px-4 py-2 border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium"
                                    >
                                        {{ t('settings.disconnect') }}
                                    </button>
                                </div>
                                <p class="text-xs text-amber-600">{{ t('settings.telegram_lightstate_help') }}</p>
                            </div>
                        </div>
                        <p v-if="telegramConnection" class="text-sm mt-2">
                            <a :href="route('inbox.index', { channel: 'telegram' })" class="text-blue-600 hover:underline">{{ t('settings.open_inbox') }} →</a>
                            <span class="text-gray-500 mx-2">|</span>
                            <a :href="route('telegram-crawler.index')" class="text-blue-600 hover:underline">{{ t('settings.telegram_group_crawl') }} →</a>
                        </p>
                    </div>

                    <!-- Telegram Group Categories -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">{{ t('settings.telegram_group_categories') }}</h3>
                        <p class="text-sm text-gray-600 mb-4">{{ t('settings.telegram_group_categories_help') }}</p>
                        <div class="flex flex-wrap gap-3 items-center mb-4">
                            <input
                                v-model="newCategoryName"
                                type="text"
                                :placeholder="t('settings.new_category_name')"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm w-48"
                                @keyup.enter="addTelegramGroupCategory"
                            />
                            <button
                                type="button"
                                @click="addTelegramGroupCategory"
                                :disabled="!newCategoryName.trim() || categorySaving"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 text-sm"
                            >
                                {{ categorySaving ? t('settings.saving') : t('common.add') }}
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
                                        {{ t('common.edit') }}
                                    </button>
                                    <template v-else>
                                        <input
                                            v-model="editCategoryName"
                                            type="text"
                                            class="px-2 py-1 border border-gray-300 rounded text-sm w-40"
                                            @keyup.enter="saveEditCategory"
                                        />
                                        <button @click="saveEditCategory" class="text-green-600 hover:text-green-800 text-sm">{{ t('common.save') }}</button>
                                        <button @click="cancelEditCategory" class="text-gray-500 hover:text-gray-700 text-sm">{{ t('common.cancel') }}</button>
                                    </template>
                                    <button
                                        @click="deleteTelegramGroupCategory(cat)"
                                        class="text-red-600 hover:text-red-800 text-sm"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </div>
                            <p v-if="!telegramGroupCategories?.length" class="text-gray-500 text-sm py-2">{{ t('settings.no_categories_defined') }}</p>
                        </div>
                    </div>

                    <!-- Bot (legacy / optional) -->
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">{{ t('settings.telegram_bot_optional') }}</h3>
                        <p class="text-sm text-gray-500 mb-4">{{ t('settings.telegram_bot_help') }}</p>
                    </div>
                    <form @submit.prevent="saveTelegramSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-3 rtl:space-x-reverse">
                                <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                    <input
                                        v-model="telegramForm.enabled"
                                        type="checkbox"
                                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                    />
                                    <span class="text-sm font-medium text-gray-700">{{ t('settings.enable_telegram_inbox') }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.bot_token_required') }}</label>
                                <input
                                    v-model="telegramForm.bot_token"
                                    type="text"
                                    :placeholder="t('settings.bot_token_placeholder')"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                <p class="mt-1 text-xs text-gray-500">{{ t('settings.bot_token_help') }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.webhook_url') }}</label>
                                <p class="mt-1 text-xs text-gray-500 mb-2">{{ t('settings.telegram_webhook_help') }}</p>
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
                                        {{ webhookCopied ? t('settings.copied') : t('settings.copy') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="registerTelegramWebhook"
                                        class="px-4 py-2 bg-amber-500 text-white rounded-md hover:bg-amber-600 text-sm font-medium whitespace-nowrap"
                                    >
                                        {{ t('settings.register_webhook_now') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-4 border-t">
                            <p class="text-xs text-gray-500">{{ t('settings.telegram_test_hint') }}</p>
                            <button
                                type="button"
                                @click="testTelegram"
                                :disabled="telegramForm.processing || testTelegramForm.processing || !telegramForm.bot_token"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ testTelegramForm.processing ? t('settings.testing') : t('settings.test_bot_token') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="telegramForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ telegramForm.processing ? t('settings.saving') : t('settings.save_telegram_settings') }}
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Instagram Settings Tab -->
                <div v-if="activeTab === 'instagram' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">{{ t('settings.tabs.instagram_inbox') }}</h2>

                    <!-- Not Connected -->
                    <div v-if="!instagramConnection" class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-gray-700 mb-4">{{ t('settings.instagram_connect_help') }}</p>
                        <a :href="route('settings.instagram.connect')" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">
                            {{ t('settings.connect_instagram_business') }}
                        </a>
                    </div>

                    <!-- Connected -->
                    <div v-else class="mb-8 space-y-6">
                        <div class="p-6 border border-gray-200 rounded-lg bg-white flex flex-wrap items-start gap-6">
                            <img v-if="instagramConnection.ig_profile_pic_url" :src="instagramConnection.ig_profile_pic_url" alt="Profile" class="w-16 h-16 rounded-full object-cover" />
                            <div v-else class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-2xl font-bold">{{ (instagramConnection.ig_username || 'IG').charAt(0).toUpperCase() }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900">{{ instagramConnection.ig_username || t('settings.instagram_account') }}</p>
                                <p class="text-sm text-gray-500">{{ t('settings.ig_business_account_id') }}: {{ instagramConnection.ig_business_account_id }}</p>
                                <p v-if="instagramConnection.page_id" class="text-sm text-gray-500">{{ t('settings.page_id') }}: {{ instagramConnection.page_id }}</p>
                                <p class="text-sm mt-2">
                                    <span :class="instagramConnection.token_valid ? 'text-green-600' : 'text-amber-600'">{{ instagramConnection.token_valid ? t('settings.token_valid') : t('settings.token_expired') }}</span>
                                    <span v-if="instagramConnection.webhook_verified_at" class="text-gray-500 ltr:ml-2 rtl:mr-2"> · {{ t('settings.webhook_verified') }}</span>
                                    <span v-if="instagramConnection.last_webhook_event_at" class="text-gray-500 ltr:ml-2 rtl:mr-2"> · {{ t('settings.last_event') }}: {{ formatDate(instagramConnection.last_webhook_event_at) }}</span>
                                </p>
                            </div>
                            <form @submit.prevent="disconnectInstagram" class="inline">
                                <button type="submit" class="px-4 py-2 border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium">{{ t('settings.disconnect') }}</button>
                            </form>
                        </div>
                        <p class="text-sm">
                            <a :href="route('inbox.index', { channel: 'instagram' })" class="text-blue-600 hover:underline">{{ t('settings.open_inbox') }} →</a> {{ t('settings.instagram_open_inbox_help') }}
                        </p>
                    </div>

                    <!-- Webhook & legacy token (for App Dashboard setup and fallback) -->
                    <div class="mb-8 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                        <p class="font-medium mb-2">{{ t('settings.meta_app_setup') }}:</p>
                        <ul class="list-disc list-inside space-y-1 mb-2">
                            <li>{{ t('settings.meta_setup_step_1') }} <a href="https://developers.facebook.com" target="_blank" class="underline">Facebook Developers</a> {{ t('settings.meta_setup_step_1_tail') }}</li>
                            <li>{{ t('settings.meta_setup_step_2') }} <code class="bg-amber-100 px-1 rounded">instagram_business_basic</code>, <code class="bg-amber-100 px-1 rounded">instagram_business_manage_messages</code>.</li>
                            <li>{{ t('settings.meta_setup_step_3') }}</li>
                        </ul>
                    </div>
                    <form @submit.prevent="saveInstagramSettings" class="space-y-6">
                        <div class="flex items-center justify-between mb-4">
                            <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                <input v-model="instagramForm.enabled" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                                <span class="text-sm font-medium text-gray-700">{{ t('settings.enable_instagram_inbox_legacy') }}</span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.webhook_url_meta') }}</label>
                            <div class="flex gap-2">
                                <input :value="instagramWebhookUrl" type="text" readonly class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-gray-600 text-sm" />
                                <button type="button" @click="copyInstagramWebhookUrl" class="px-3 py-2 border border-gray-300 rounded-md bg-white hover:bg-gray-50 text-sm font-medium text-gray-700 whitespace-nowrap">{{ instagramWebhookCopied ? t('settings.copied') + '!' : t('settings.copy') }}</button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.webhook_verify_token') }}</label>
                            <input v-model="instagramForm.webhook_verify_token" type="text" :placeholder="t('settings.instagram_verify_token_placeholder')" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('settings.page_access_token_optional') }}</label>
                            <input v-model="instagramForm.access_token" type="password" :placeholder="t('settings.instagram_access_token_placeholder')" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" />
                            <p class="mt-1 text-xs text-gray-500">{{ t('settings.instagram_access_token_help') }}</p>
                        </div>
                        <button type="submit" :disabled="instagramForm.processing" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50">{{ instagramForm.processing ? t('settings.saving') : t('common.save') }}</button>
                    </form>

                    <!-- Developer Diagnostics (admin) -->
                    <div v-if="isAdmin && instagramConnection" class="mt-10 pt-8 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('settings.developer_diagnostics') }}</h3>
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600">{{ t('settings.scopes') }}</p>
                                <p class="text-sm text-gray-700">{{ (instagramConnection.scopes || []).join(', ') || '—' }}</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-600">{{ t('settings.token_status') }}</p>
                                <p class="text-sm">{{ instagramConnection.token_valid ? t('settings.valid') : t('settings.expired') }} <span v-if="instagramConnection.token_expires_at">({{ t('settings.expires') }} {{ formatDate(instagramConnection.token_expires_at) }})</span></p>
                            </div>
                            <form @submit.prevent="revalidateInstagram" class="inline">
                                <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-medium">{{ t('settings.revalidate_token') }}</button>
                            </form>
                            <div v-if="instagramWebhookEvents.length">
                                <p class="text-sm font-medium text-gray-600 mb-2">{{ t('settings.last_20_webhook_events') }}</p>
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-50"><tr><th class="px-3 py-2 text-left">{{ t('common.type') }}</th><th class="px-3 py-2 text-left">{{ t('settings.sender') }}</th><th class="px-3 py-2 text-left">{{ t('settings.time') }}</th></tr></thead>
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
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('settings.app_review_instructions') }}</h3>
                        <div class="p-4 bg-gray-100 rounded-lg text-sm text-gray-800 whitespace-pre-line">{{ reviewerInstructions }}</div>
                    </div>
                </div>

                <!-- TikTok (Login Kit + Inbox) -->
                <div v-if="activeTab === 'tiktok' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-6">{{ t('settings.tabs.tiktok_inbox') }}</h2>

                    <div v-if="!tiktokConnection" class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-gray-700 mb-4">{{ t('settings.tiktok_connect_help') }}</p>
                        <a :href="route('settings.tiktok.connect')" class="inline-flex items-center px-5 py-2.5 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800">
                            {{ t('settings.connect_tiktok_account') }}
                        </a>
                    </div>

                    <div v-else class="mb-8 p-6 border border-gray-200 rounded-lg bg-white">
                        <div class="flex flex-wrap items-start gap-4">
                            <img v-if="tiktokConnection.avatar_url" :src="tiktokConnection.avatar_url" alt="TikTok" class="w-16 h-16 rounded-full object-cover" />
                            <div v-else class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-2xl font-bold">{{ (tiktokConnection.display_name || 'TT').charAt(0).toUpperCase() }}</div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900">{{ tiktokConnection.display_name || t('settings.tiktok_account') }}</p>
                                <p class="text-sm text-gray-500">{{ t('settings.tiktok_open_id') }}: {{ tiktokConnection.open_id }}</p>
                                <p class="text-sm mt-1">
                                    <span :class="tiktokConnection.token_valid ? 'text-green-600' : 'text-amber-600'">{{ tiktokConnection.token_valid ? t('settings.token_valid') : t('settings.token_expired') }}</span>
                                    <span v-if="tiktokConnection.webhook_verified_at" class="text-gray-500 ltr:ml-2 rtl:mr-2"> · {{ t('settings.webhook_verified') }}</span>
                                    <span v-if="tiktokConnection.last_webhook_event_at" class="text-gray-500 ltr:ml-2 rtl:mr-2"> · {{ t('settings.last_event') }}: {{ formatDate(tiktokConnection.last_webhook_event_at) }}</span>
                                </p>
                            </div>
                            <form @submit.prevent="disconnectTiktok" class="inline">
                                <button type="submit" class="px-4 py-2 border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium">{{ t('settings.disconnect') }}</button>
                            </form>
                        </div>
                        <p class="mt-4 text-sm text-gray-600">
                            <a :href="route('inbox.index', { channel: 'tiktok' })" class="text-blue-600 hover:underline">{{ t('settings.open_inbox') }} →</a> {{ t('settings.tiktok_open_inbox_help') }}
                        </p>
                    </div>

                    <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 space-y-3">
                        <p class="font-medium">{{ t('settings.tiktok_webhook_url_label') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <input :value="tiktokWebhookUrl" type="text" readonly class="flex-1 min-w-[200px] px-3 py-2 border border-gray-300 rounded-md bg-white text-gray-600 text-sm" />
                            <button type="button" @click="copyTiktokWebhookUrl" class="px-3 py-2 border border-gray-300 rounded-md bg-white hover:bg-gray-50 text-sm font-medium text-gray-700 whitespace-nowrap">{{ tiktokWebhookCopied ? t('settings.copied') + '!' : t('settings.copy') }}</button>
                        </div>
                        <p class="text-xs text-gray-500">{{ t('settings.tiktok_webhook_help') }}</p>
                        <p class="text-xs text-gray-500">{{ t('settings.tiktok_messaging_env_hint') }}</p>
                    </div>
                </div>

                <!-- Google Contacts (CRM → Google, one-way) -->
                <div v-if="activeTab === 'google-contacts' && canManageOrganizationSettings" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">{{ t('settings.google_contacts_sync_title') }}</h2>
                    <p class="text-sm text-gray-600 mb-6 max-w-3xl">
                        {{ t('settings.google_contacts_sync_intro_1') }}
                        {{ t('settings.google_contacts_sync_intro_2') }} <strong>First / Middle / Last</strong> {{ t('settings.google_contacts_sync_intro_2_tail') }}
                        {{ t('settings.google_contacts_sync_intro_3') }} <strong>{{ t('common.avatar') }}</strong> {{ t('settings.google_contacts_sync_intro_3_tail') }} <code class="bg-gray-100 px-1 text-xs">updateContactPhoto</code> {{ t('settings.google_contacts_sync_intro_3_tail_2') }}
                        {{ t('settings.google_contacts_sync_intro_4') }}
                    </p>

                    <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-700 space-y-2">
                        <p><span class="font-medium">{{ t('settings.google_redirect_uri_exact') }}</span></p>
                        <code class="block bg-white border px-3 py-2 rounded text-xs break-all">{{ googleRedirectUriDisplay }}</code>
                        <p class="text-xs text-gray-500">{{ t('settings.google_redirect_env_hint') }}</p>
                    </div>

                    <div v-if="!googleContactsIntegration" class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
                        <p class="text-gray-700 mb-4">{{ t('settings.google_connect_first_help') }}</p>
                        <a
                            :href="route('settings.google-contacts.connect')"
                            class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700"
                        >
                            {{ t('settings.connect_google') }}
                        </a>
                    </div>

                    <div v-else class="mb-8 space-y-6">
                        <div class="p-6 border border-gray-200 rounded-lg bg-white flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <p class="font-semibold text-gray-900">{{ googleContactsIntegration.account_email || t('settings.google_account') }}</p>
                                <p v-if="googleContactsIntegration.connected_at" class="text-sm text-gray-500">{{ t('settings.connected_since') }}: {{ formatDate(googleContactsIntegration.connected_at) }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    :disabled="googleBulkSyncBusy"
                                    @click="startGoogleBulkSync"
                                    class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 text-sm font-medium"
                                >
                                    {{ googleBulkSyncBusy ? t('settings.syncing') : t('settings.sync_all_customers') }}
                                </button>
                                <button
                                    v-if="showGoogleBulkStop"
                                    type="button"
                                    @click="requestGoogleBulkStop"
                                    class="px-4 py-2 border border-amber-500 text-amber-900 bg-amber-50 rounded-lg hover:bg-amber-100 text-sm font-medium"
                                >
                                    {{ t('settings.sync_stop_to_google') }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="googleImportBulkBusy"
                                    @click="startGoogleImportBulk"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 text-sm font-medium"
                                >
                                    {{ googleImportBulkBusy ? t('settings.importing_from_google') : t('settings.import_from_google_contacts') }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="googlePhotoBulkBusy"
                                    @click="startGooglePhotoBulk"
                                    class="px-4 py-2 bg-teal-600 text-white rounded-lg hover:bg-teal-700 disabled:opacity-50 text-sm font-medium"
                                >
                                    {{ googlePhotoBulkBusy ? t('settings.updating_photos_from_google') : t('settings.update_photos_from_google') }}
                                </button>
                                <button
                                    v-if="showGooglePhotoStop"
                                    type="button"
                                    @click="requestGooglePhotoStop"
                                    class="px-4 py-2 border border-amber-500 text-amber-900 bg-amber-50 rounded-lg hover:bg-amber-100 text-sm font-medium"
                                >
                                    {{ t('settings.google_photo_sync_stop') }}
                                </button>
                                <form @submit.prevent="disconnectGoogleContacts">
                                    <button type="submit" class="px-4 py-2 border border-red-200 text-red-700 rounded-lg hover:bg-red-50 text-sm font-medium">
                                        {{ t('settings.disconnect') }}
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
                                    {{ t('common.status') }}:
                                    <strong>{{ googleBulkStatusLabel }}</strong>
                                </span>
                                <span v-if="googleBulkProgress.total != null">
                                    {{ googleBulkProgress.processed ?? 0 }} / {{ googleBulkProgress.total }}
                                    <span class="text-gray-500 ltr:mr-2 rtl:ml-2">{{ t('settings.success') }}: {{ googleBulkProgress.success ?? 0 }} · {{ t('settings.failed') }}: {{ googleBulkProgress.failed ?? 0 }}</span>
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
                            <p v-if="googleBulkProgress.last_tick_at" class="text-xs text-gray-600">
                                {{ t('settings.last_server_activity') }}:
                                <span class="font-mono ltr:inline-block">{{ formatGoogleTick(googleBulkProgress.last_tick_at) }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ t('settings.google_bulk_inline_hint') }}</p>
                            <p class="text-xs text-gray-500">
                                {{ t('settings.google_queue_worker_hint_1') }} <code class="bg-gray-100 px-1">database</code>/<code class="bg-gray-100 px-1">redis</code> {{ t('settings.google_queue_worker_hint_2') }} <code class="bg-gray-100 px-1">php artisan queue:work</code>. {{ t('settings.google_queue_worker_hint_3') }} <code class="bg-gray-100 px-1">sync</code> {{ t('settings.google_queue_worker_hint_4') }}
                            </p>
                        </div>

                        <div
                            v-if="googleImportBulkProgress && googleImportBulkProgress.status && googleImportBulkProgress.status !== 'idle'"
                            class="p-4 border border-indigo-100 rounded-lg bg-indigo-50/40 space-y-2"
                        >
                            <div class="flex justify-between text-sm text-gray-700">
                                <span>
                                    {{ t('settings.import_from_google_contacts') }} —
                                    <strong>{{ googleImportBulkStatusLabel }}</strong>
                                </span>
                                <span v-if="googleImportBulkProgress.total != null && googleImportBulkProgress.total > 0">
                                    {{ googleImportBulkProgress.processed ?? 0 }} / {{ googleImportBulkProgress.total }}
                                </span>
                                <span v-else class="text-gray-500">{{ googleImportBulkProgress.processed ?? 0 }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="bg-indigo-600 h-2.5 rounded-full transition-all duration-300"
                                    :style="{ width: googleImportBulkProgressPercent + '%' }"
                                ></div>
                            </div>
                            <p class="text-xs text-gray-600">
                                {{ t('settings.success') }}: {{ googleImportBulkProgress.imported ?? 0 }} ·
                                {{ t('settings.google_import_skipped_duplicate') }}: {{ googleImportBulkProgress.skipped_duplicate ?? 0 }} ·
                                {{ t('settings.google_import_skipped_empty') }}: {{ googleImportBulkProgress.skipped_empty ?? 0 }} ·
                                {{ t('settings.failed') }}: {{ googleImportBulkProgress.failed ?? 0 }}
                            </p>
                            <p v-if="googleImportBulkProgress.message" class="text-sm text-red-700">{{ googleImportBulkProgress.message }}</p>
                            <ul v-if="googleImportBulkProgress.errors && googleImportBulkProgress.errors.length" class="text-xs text-amber-900 list-disc list-inside max-h-32 overflow-y-auto">
                                <li v-for="(ie, ii) in googleImportBulkProgress.errors" :key="ii">{{ ie }}</li>
                            </ul>
                            <p v-if="googleImportBulkProgress.last_tick_at" class="text-xs text-gray-600">
                                {{ t('settings.last_server_activity') }}:
                                <span class="font-mono ltr:inline-block">{{ formatGoogleTick(googleImportBulkProgress.last_tick_at) }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ t('settings.import_from_google_intro') }}</p>
                        </div>

                        <div
                            v-if="googlePhotoBulkProgress && googlePhotoBulkProgress.status && googlePhotoBulkProgress.status !== 'idle'"
                            class="p-4 border border-teal-200 rounded-lg bg-teal-50/50 space-y-2"
                        >
                            <div class="flex justify-between text-sm text-gray-700">
                                <span>
                                    {{ t('settings.update_photos_from_google') }} —
                                    <strong>{{ googlePhotoBulkStatusLabel }}</strong>
                                </span>
                                <span v-if="googlePhotoBulkProgress.total != null && googlePhotoBulkProgress.total > 0">
                                    {{ googlePhotoBulkProgress.processed ?? 0 }} / {{ googlePhotoBulkProgress.total }}
                                </span>
                                <span v-else class="text-gray-500">{{ googlePhotoBulkProgress.processed ?? 0 }}</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="bg-teal-600 h-2.5 rounded-full transition-all duration-200"
                                    :style="{ width: googlePhotoBulkProgressPercent + '%' }"
                                ></div>
                            </div>
                            <p class="text-xs text-gray-600">
                                {{ t('settings.google_photos_updated_label') }}: {{ googlePhotoBulkProgress.updated ?? 0 }} ·
                                {{ t('settings.google_photos_skipped_label') }}: {{ googlePhotoBulkProgress.skipped ?? 0 }} ·
                                {{ t('settings.failed') }}: {{ googlePhotoBulkProgress.failed ?? 0 }}
                            </p>
                            <p v-if="googlePhotoBulkProgress.message" class="text-sm text-red-700">{{ googlePhotoBulkProgress.message }}</p>
                            <ul v-if="googlePhotoBulkProgress.errors && googlePhotoBulkProgress.errors.length" class="text-xs text-amber-900 list-disc list-inside max-h-32 overflow-y-auto">
                                <li v-for="(pe, pi) in googlePhotoBulkProgress.errors" :key="pi">{{ pe }}</li>
                            </ul>
                            <p v-if="googlePhotoBulkProgress.last_tick_at" class="text-xs text-gray-600">
                                {{ t('settings.last_server_activity') }}:
                                <span class="font-mono ltr:inline-block">{{ formatGoogleTick(googlePhotoBulkProgress.last_tick_at) }}</span>
                            </p>
                            <p class="text-xs text-gray-500">{{ t('settings.google_photo_sync_intro') }}</p>
                        </div>

                        <p class="text-xs text-gray-500">
                            {{ t('settings.google_contact_id_saved_hint') }}
                            {{ t('settings.google_console_command_hint') }} <code class="bg-gray-100 px-1">php artisan google:sync-contacts</code>
                        </p>
                    </div>
                </div>

                <!-- Organization profile + subscription -->
                <div v-if="activeTab === 'organization' && canManageOrganizationSettings && organizationProfile" class="p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-2">{{ t('settings.tabs.organization_subscription') }}</h2>
                    <p class="text-sm text-gray-500 mb-6">{{ t('settings.organization_subscription_help') }}</p>

                    <form @submit.prevent="saveOrganizationProfile" class="space-y-6 max-w-3xl">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.name') }} *</label>
                                <input v-model="organizationProfileForm.name" type="text" required class="w-full px-3 py-2 border rounded-md" />
                                <div v-if="organizationProfileForm.errors.name" class="mt-1 text-sm text-red-600">{{ organizationProfileForm.errors.name }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.legal_name') }}</label>
                                <input v-model="organizationProfileForm.legal_name" type="text" class="w-full px-3 py-2 border rounded-md" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('common.slug') }}</label>
                                <input :value="organizationProfile.slug" type="text" readonly class="w-full px-3 py-2 border rounded-md bg-gray-50 text-gray-600" />
                                <p class="text-xs text-gray-500 mt-1">{{ t('settings.organization_slug_readonly') }}</p>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ t('settings.organization_logo') }}</h3>
                            <div class="flex flex-wrap items-end gap-4">
                                <div v-if="organizationProfile.logo_url && !organizationProfileForm.remove_logo" class="h-16 w-16 rounded border border-gray-200 overflow-hidden bg-gray-50">
                                    <img :src="organizationProfile.logo_url" alt="" class="h-full w-full object-contain" />
                                </div>
                                <div>
                                    <input type="file" accept="image/*" class="text-sm" @change="onOrganizationLogoChange" />
                                </div>
                                <label v-if="organizationProfile.logo_url" class="flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="organizationProfileForm.remove_logo" type="checkbox" />
                                    {{ t('settings.remove_logo') }}
                                </label>
                            </div>
                            <div v-if="organizationProfileForm.errors.logo" class="mt-1 text-sm text-red-600">{{ organizationProfileForm.errors.logo }}</div>
                        </div>

                        <div class="border-t border-gray-100 pt-4 space-y-4">
                            <h3 class="text-sm font-semibold text-gray-800">{{ t('settings.organization_address') }}</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.address_line1') }}</label>
                                <textarea v-model="organizationProfileForm.address_line1" rows="2" class="w-full px-3 py-2 border rounded-md"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.address_line2') }}</label>
                                <input v-model="organizationProfileForm.address_line2" type="text" class="w-full px-3 py-2 border rounded-md" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.city') }}</label>
                                    <input v-model="organizationProfileForm.city" type="text" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.region') }}</label>
                                    <input v-model="organizationProfileForm.region" type="text" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.postal_code') }}</label>
                                    <input v-model="organizationProfileForm.postal_code" type="text" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.country') }}</label>
                                    <input v-model="organizationProfileForm.country" type="text" maxlength="2" class="w-full px-3 py-2 border rounded-md uppercase" placeholder="e.g. AE" />
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4">
                            <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ t('settings.organization_contact') }}</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.phone') }}</label>
                                    <input v-model="organizationProfileForm.phone" type="text" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.public_email') }}</label>
                                    <input v-model="organizationProfileForm.public_email" type="email" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('settings.website') }}</label>
                                    <input v-model="organizationProfileForm.website" type="url" class="w-full px-3 py-2 border rounded-md" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button
                                type="submit"
                                :disabled="organizationProfileForm.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ organizationProfileForm.processing ? t('settings.saving') : t('common.save') }}
                            </button>
                        </div>
                    </form>

                    <div class="border-t border-gray-200 mt-10 pt-8 max-w-3xl">
                        <h3 class="text-lg font-bold text-gray-900 mb-2">{{ t('settings.subscription') }}</h3>
                        <p class="text-sm text-gray-500 mb-6">{{ t('settings.subscription_description') }}</p>

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500">{{ t('settings.subscription_status') }}</span>
                                    <span class="font-medium ltr:ml-2 rtl:mr-2" :class="subscriptionStatusClass">{{ props.subscriptionSummary?.status || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">{{ t('settings.subscription_remaining_days') }}</span>
                                    <span class="font-medium ltr:ml-2 rtl:mr-2">{{ props.subscriptionSummary?.remaining_days ?? '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">{{ t('settings.subscription_plan') }}</span>
                                    <span class="font-medium ltr:ml-2 rtl:mr-2">{{ props.subscriptionSummary?.plan?.name || '-' }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500">{{ t('settings.subscription_ends_at') }}</span>
                                    <span class="font-medium ltr:ml-2 rtl:mr-2">{{ formatIsoDate(props.subscriptionSummary?.ends_at) || '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <input v-model.number="renewMonths" type="number" min="1" max="24" class="w-28 px-3 py-2 border rounded-md" />
                            <button
                                type="button"
                                @click="renewSubscription"
                                :disabled="renewingSubscription"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ renewingSubscription ? t('common.renewing') : t('settings.renew') }}
                            </button>
                        </div>
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
                        {{ editingType ? t('common.edit') : t('common.add') }} {{ t('superadmin.social_media_platforms') }}
                    </h3>

                    <form @submit.prevent="saveSocialMediaType" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.name') }} *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.icon') }}</label>
                            <input
                                v-model="form.icon"
                                type="text"
                                :placeholder="t('settings.icon_placeholder')"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('settings.icon_help') }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.base_url') }}</label>
                            <input
                                v-model="form.base_url"
                                type="url"
                                :placeholder="t('settings.base_url_placeholder')"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('settings.base_url_help') }}</p>
                        </div>

                        <div class="flex items-center space-x-4 rtl:space-x-reverse">
                            <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-gray-700">{{ t('common.active') }}</span>
                            </label>

                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.sort_order') }}</label>
                                <input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 rtl:space-x-reverse pt-4">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                {{ t('common.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? t('settings.saving') : t('common.save') }}
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
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    initialTab: {
        type: String,
        default: 'smtp',
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
    orgNotificationsSettings: {
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
    tiktokConnection: {
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
    subscriptionSummary: {
        type: Object,
        default: null,
    },
    organizationProfile: {
        type: Object,
        default: null,
    },
});

const activeTab = ref(props.initialTab || 'smtp');

watch(
    () => props.initialTab,
    (v) => {
        if (v) {
            activeTab.value = v;
        }
    }
);

const settingsPageTitle = computed(() => {
    const tab = activeTab.value;
    const map = {
        organization: () => t('settings.tabs.organization_subscription'),
        smtp: () => t('settings.tabs.smtp'),
        ronibot: () => t('settings.tabs.ronibot'),
        notifications: () => t('settings.tabs.notifications'),
        telegram: () => t('settings.tabs.telegram_inbox'),
        instagram: () => t('settings.tabs.instagram_inbox'),
        tiktok: () => t('settings.tabs.tiktok_inbox'),
        'google-contacts': () => t('settings.tabs.google_contacts'),
    };
    if (map[tab]) {
        return map[tab]();
    }
    return t('sidebar.settings');
});
const showAddModal = ref(false);
const page = usePage();
const isSuperAdmin = computed(() => {
    const roles = page.props.auth?.user?.roles || [];
    return roles.includes('super_admin');
});
const languages = computed(() => page.props.languages || []);
const telegramGroupCategories = computed(() => page.props.telegramGroupCategories || []);
const renewMonths = ref(1);
const renewingSubscription = ref(false);
const subscriptionStatusClass = computed(() => {
    const s = props.subscriptionSummary?.status;
    if (s === 'active') return 'text-emerald-700';
    if (s === 'trial') return 'text-blue-700';
    if (s === 'grace') return 'text-amber-700';
    if (s === 'expired') return 'text-red-700';
    return 'text-gray-700';
});

function formatIsoDate(v) {
    if (!v) return '';
    try {
        return new Date(v).toLocaleDateString();
    } catch {
        return '';
    }
}

async function renewSubscription() {
    renewingSubscription.value = true;
    try {
        await axios.post(route('settings.subscription.renew'), { months: renewMonths.value || 1 });
        router.reload({ preserveState: true, preserveScroll: true });
    } finally {
        renewingSubscription.value = false;
    }
}

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
        alert(e.response?.data?.message || t('common.error'));
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
    if (!confirm(t('common.confirm_delete_named').replace(':name', lang.name))) return;
    try {
        await axios.delete(route('settings.languages.destroy', lang.id));
        router.reload({ preserveState: true });
    } catch (e) {
        alert(e.response?.data?.message || t('common.error'));
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
        alert(e.response?.data?.message || t('common.error'));
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
        alert(e.response?.data?.message || t('common.error'));
    }
}
function cancelEditCategory() {
    editingCategoryId.value = null;
    editCategoryName.value = '';
}
async function deleteTelegramGroupCategory(cat) {
    if (!confirm(t('common.confirm_delete_named').replace(':name', cat.name))) return;
    try {
        await axios.delete(route('settings.telegram-group-categories.destroy', cat.id));
        router.reload({ preserveState: true });
    } catch (e) {
        alert(e.response?.data?.message || t('common.error'));
    }
}
const editingType = ref(null);
const testEmail = ref('');

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

const organizationProfileForm = useForm({
    name: props.organizationProfile?.name ?? '',
    legal_name: props.organizationProfile?.legal_name ?? '',
    address_line1: props.organizationProfile?.address_line1 ?? '',
    address_line2: props.organizationProfile?.address_line2 ?? '',
    city: props.organizationProfile?.city ?? '',
    region: props.organizationProfile?.region ?? '',
    postal_code: props.organizationProfile?.postal_code ?? '',
    country: props.organizationProfile?.country ?? '',
    phone: props.organizationProfile?.phone ?? '',
    public_email: props.organizationProfile?.public_email ?? '',
    website: props.organizationProfile?.website ?? '',
    logo: null,
    remove_logo: false,
});

watch(
    () => props.organizationProfile,
    (p) => {
        if (!p) {
            return;
        }
        organizationProfileForm.name = p.name ?? '';
        organizationProfileForm.legal_name = p.legal_name ?? '';
        organizationProfileForm.address_line1 = p.address_line1 ?? '';
        organizationProfileForm.address_line2 = p.address_line2 ?? '';
        organizationProfileForm.city = p.city ?? '';
        organizationProfileForm.region = p.region ?? '';
        organizationProfileForm.postal_code = p.postal_code ?? '';
        organizationProfileForm.country = p.country ?? '';
        organizationProfileForm.phone = p.phone ?? '';
        organizationProfileForm.public_email = p.public_email ?? '';
        organizationProfileForm.website = p.website ?? '';
        organizationProfileForm.logo = null;
        organizationProfileForm.remove_logo = false;
        organizationProfileForm.clearErrors();
    },
    { deep: true }
);

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
    webhook_url: props.ronibotSettings.webhook_url || 'https://ronicrm.com/wpwebhook',
    enabled: props.ronibotSettings.enabled || false,
    line_phone: props.ronibotSettings.line_phone || '',
    wa_session_id: props.ronibotSettings.wa_session_id || '',
    device_id: props.ronibotSettings.device_id || '',
    device_uuid: props.ronibotSettings.device_uuid || '',
    ronibot_user_id: props.ronibotSettings.ronibot_user_id || '',
});

const orgNotificationsForm = useForm({
    events: {
        customer_created: {
            enabled: !!(props.orgNotificationsSettings?.events?.customer_created?.enabled ?? false),
            channels: {
                email: {
                    enabled: !!(props.orgNotificationsSettings?.events?.customer_created?.channels?.email?.enabled ?? false),
                    subject: props.orgNotificationsSettings?.events?.customer_created?.channels?.email?.subject ?? 'خوش آمدید {name}',
                    body: props.orgNotificationsSettings?.events?.customer_created?.channels?.email?.body ?? '',
                },
                whatsapp: {
                    enabled: !!(props.orgNotificationsSettings?.events?.customer_created?.channels?.whatsapp?.enabled ?? false),
                    body: props.orgNotificationsSettings?.events?.customer_created?.channels?.whatsapp?.body ?? '',
                },
            },
        },
    },
});

function syncOrgNotificationsFormFromProps() {
    const s = props.orgNotificationsSettings || {};
    const ev = s.events?.customer_created || {};
    orgNotificationsForm.events.customer_created.enabled = !!(ev.enabled ?? false);
    orgNotificationsForm.events.customer_created.channels.email.enabled = !!(ev.channels?.email?.enabled ?? false);
    orgNotificationsForm.events.customer_created.channels.email.subject = ev.channels?.email?.subject ?? 'خوش آمدید {name}';
    orgNotificationsForm.events.customer_created.channels.email.body = ev.channels?.email?.body ?? '';
    orgNotificationsForm.events.customer_created.channels.whatsapp.enabled = !!(ev.channels?.whatsapp?.enabled ?? false);
    orgNotificationsForm.events.customer_created.channels.whatsapp.body = ev.channels?.whatsapp?.body ?? '';
}

watch(() => props.orgNotificationsSettings, () => syncOrgNotificationsFormFromProps(), { deep: true });

function saveOrgNotifications() {
    orgNotificationsForm.post(route('settings.notifications.update'), {
        preserveScroll: true,
    });
}

function syncRonibotFormFromProps() {
    const s = props.ronibotSettings;
    if (!s) return;
    ronibotForm.api_url = s.api_url || ronibotForm.api_url;
    ronibotForm.appkey = s.appkey ?? '';
    ronibotForm.authkey = s.authkey ?? '';
    ronibotForm.webhook_url = s.webhook_url || ronibotForm.webhook_url;
    ronibotForm.enabled = !!s.enabled;
    ronibotForm.line_phone = s.line_phone ?? '';
    ronibotForm.wa_session_id = s.wa_session_id ?? '';
    ronibotForm.device_id = s.device_id ?? '';
    ronibotForm.device_uuid = s.device_uuid ?? '';
    ronibotForm.ronibot_user_id = s.ronibot_user_id ?? '';
}

watch(() => props.ronibotSettings, () => syncRonibotFormFromProps(), { deep: true });

const ronibotPartnerPhone = ref(props.organizationProfile?.phone ?? '');
const ronibotPartnerPassword = ref('');
const ronibotPartnerLoading = ref(false);
const ronibotPartnerPolling = ref(false);
/** فقط پس از ساخت موفق App و ذخیرهٔ تنظیمات */
const ronibotPartnerSetupComplete = ref(false);
const ronibotPartnerNeedsAppRetry = ref(false);
const ronibotPartnerError = ref('');
const ronibotPartnerMessage = ref('');
const ronibotPartnerStepLabel = ref('');
const ronibotQrSrc = ref('');
let ronibotPartnerPollTimer = null;
const ronibotPartnerFinalizeStarted = ref(false);
/** دو poll پیاپی با همان پاسخ معتبر از سرور (کاهش false positive) */
const ronibotPartnerLinkConfirmCount = ref(0);

watch(
    () => props.organizationProfile?.phone,
    (p) => {
        if (p && !String(ronibotPartnerPhone.value || '').trim()) {
            ronibotPartnerPhone.value = p;
        }
    }
);

const ronibotPartnerDisplayWebhook = computed(() => {
    const w = String(ronibotForm.webhook_url || '').trim();
    if (w) {
        return w;
    }
    if (typeof window !== 'undefined' && window.location?.origin) {
        return `${window.location.origin}/wpwebhook`;
    }
    return '';
});

watch(ronibotPartnerPhone, (v) => {
    const s = String(v || '').trim();
    if (s) {
        ronibotForm.line_phone = s;
    }
});

function reloadRonibotSettingsOnly() {
    router.reload({
        only: ['ronibotSettings'],
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            syncRonibotFormFromProps();
        },
    });
}

function reloadRonibotSettingsOnlyAsync() {
    return new Promise((resolve) => {
        router.reload({
            only: ['ronibotSettings'],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                syncRonibotFormFromProps();
                resolve();
            },
        });
    });
}

function stopRonibotPartnerPolling() {
    ronibotPartnerPolling.value = false;
    if (ronibotPartnerPollTimer) {
        clearInterval(ronibotPartnerPollTimer);
        ronibotPartnerPollTimer = null;
    }
}

function ronibotQrToDataUrl(qrcode) {
    if (!qrcode) return '';
    const s = String(qrcode).trim();
    if (s.startsWith('data:')) return s;
    return `data:image/png;base64,${s}`;
}

function partnerErr(e) {
    const d = e.response?.data;
    return d?.errors?.password?.[0] || d?.errors?.phone?.[0] || d?.message || e.message || 'Error';
}

/**
 * هم‌راستا با RonibotPartnerController::status: هم connected و هم session باید تأیید شوند.
 */
function ronibotPartnerIsServerConnected(data) {
    if (!data || data.ok !== true) {
        return false;
    }
    if (data.connected !== true) {
        return false;
    }
    const s = String(data.session_status || '')
        .toLowerCase()
        .trim();
    return s === 'authenticated' || s === 'connected';
}

async function ronibotPartnerStartAutoFlow() {
    ronibotPartnerError.value = '';
    ronibotPartnerMessage.value = '';
    ronibotPartnerStepLabel.value = '';
    ronibotPartnerSetupComplete.value = false;
    ronibotPartnerNeedsAppRetry.value = false;
    ronibotQrSrc.value = '';
    ronibotPartnerFinalizeStarted.value = false;
    ronibotPartnerLinkConfirmCount.value = 0;
    stopRonibotPartnerPolling();

    const phone = String(ronibotPartnerPhone.value || '').trim();
    if (!phone) {
        ronibotPartnerError.value = t('settings.ronibot_partner_phone_required');
        return;
    }
    if (!String(ronibotPartnerPassword.value || '').trim()) {
        ronibotPartnerError.value = t('settings.ronibot_partner_password_required');
        return;
    }
    if (!ronibotPartnerDisplayWebhook.value) {
        ronibotPartnerError.value = t('settings.ronibot_partner_app_url_missing');
        return;
    }

    ronibotPartnerLoading.value = true;
    try {
        ronibotPartnerStepLabel.value = t('settings.ronibot_partner_step_register');
        const { data: d1 } = await axios.post(route('settings.ronibot.partner.register'), {
            phone,
            password: ronibotPartnerPassword.value,
        });
        if (!d1.ok) throw new Error(d1.message || 'Error');
        ronibotPartnerMessage.value = d1.message || '';
        await reloadRonibotSettingsOnlyAsync();

        ronibotPartnerStepLabel.value = t('settings.ronibot_partner_step_device');
        const { data: d2 } = await axios.post(route('settings.ronibot.partner.device'), { phone });
        if (!d2.ok) throw new Error(d2.message || 'Error');
        ronibotPartnerMessage.value = d2.message || '';
        await reloadRonibotSettingsOnlyAsync();

        ronibotPartnerStepLabel.value = t('settings.ronibot_partner_step_qr');
        const { data: d3 } = await axios.post(route('settings.ronibot.partner.qr'));
        if (!d3.ok) throw new Error(d3.message || 'Error');
        ronibotQrSrc.value = ronibotQrToDataUrl(d3.qrcode);
        ronibotPartnerMessage.value = t('settings.ronibot_partner_qr_ready');
        ronibotPartnerError.value = '';

        ronibotPartnerStepLabel.value = t('settings.ronibot_partner_step_waiting_scan');
        startRonibotPartnerStatusPolling();
    } catch (e) {
        ronibotPartnerError.value = partnerErr(e);
        ronibotPartnerStepLabel.value = '';
    } finally {
        ronibotPartnerLoading.value = false;
        ronibotPartnerPassword.value = '';
    }
}

function startRonibotPartnerStatusPolling() {
    stopRonibotPartnerPolling();
    ronibotPartnerPolling.value = true;
    ronibotPartnerError.value = '';
    ronibotPartnerNeedsAppRetry.value = false;
    ronibotPartnerLinkConfirmCount.value = 0;

    const statusAxiosConfig = {
        validateStatus: (status) => status >= 200 && status < 500,
    };

    const tick = async () => {
        try {
            const res = await axios.post(route('settings.ronibot.partner.status'), {}, statusAxiosConfig);
            const data = res.data;
            if (!data || data.ok !== true) {
                ronibotPartnerLinkConfirmCount.value = 0;
                return;
            }
            if (!ronibotPartnerIsServerConnected(data)) {
                ronibotPartnerLinkConfirmCount.value = 0;
                return;
            }
            ronibotPartnerLinkConfirmCount.value += 1;
            if (ronibotPartnerLinkConfirmCount.value < 2) {
                return;
            }
            if (ronibotPartnerFinalizeStarted.value) {
                return;
            }
            ronibotPartnerFinalizeStarted.value = true;
            stopRonibotPartnerPolling();
            ronibotPartnerStepLabel.value = t('settings.ronibot_partner_step_finishing');
            try {
                await reloadRonibotSettingsOnlyAsync();
                const { data: appData } = await axios.post(route('settings.ronibot.partner.app'), {});
                if (!appData.ok) throw new Error(appData.message || 'Error');
                ronibotQrSrc.value = '';
                ronibotPartnerSetupComplete.value = true;
                ronibotPartnerMessage.value = appData.message || t('settings.ronibot_partner_connected_success');
                ronibotPartnerStepLabel.value = '';
                await reloadRonibotSettingsOnlyAsync();
            } catch (err) {
                ronibotPartnerFinalizeStarted.value = false;
                ronibotPartnerLinkConfirmCount.value = 0;
                ronibotPartnerNeedsAppRetry.value = true;
                ronibotPartnerError.value = partnerErr(err);
                ronibotPartnerStepLabel.value = '';
            }
        } catch {
            /* شبکه یا ۵۰۰ — QR را نگه دار */
        }
    };

    ronibotPartnerPollTimer = setInterval(tick, 3000);
    tick();
}

async function ronibotPartnerRetryCreateApp() {
    ronibotPartnerError.value = '';
    ronibotPartnerLoading.value = true;
    ronibotPartnerStepLabel.value = t('settings.ronibot_partner_step_finishing');
    try {
        await reloadRonibotSettingsOnlyAsync();
        const { data: appData } = await axios.post(route('settings.ronibot.partner.app'), {});
        if (!appData.ok) throw new Error(appData.message || 'Error');
        ronibotQrSrc.value = '';
        ronibotPartnerSetupComplete.value = true;
        ronibotPartnerNeedsAppRetry.value = false;
        ronibotPartnerMessage.value = appData.message || t('settings.ronibot_partner_connected_success');
        ronibotPartnerStepLabel.value = '';
        await reloadRonibotSettingsOnlyAsync();
    } catch (e) {
        ronibotPartnerError.value = partnerErr(e);
    } finally {
        ronibotPartnerLoading.value = false;
    }
}

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
            telegramQrError.value = t('settings.telegram_2fa_help');
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
        telegramQrError.value = e.message || t('settings.failed_to_load_qr');
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
        telegramQrError.value = t('settings.enter_phone_with_country_code');
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
            telegramQrError.value = t('settings.telegram_2fa_enabled_complete');
            return;
        }
        if (data.waiting_code || data.success) {
            telegramWaitingOtp.value = true;
            telegramQrError.value = '';
            return;
        }
        telegramQrError.value = data.error || t('settings.could_not_start_phone_login');
    } catch (e) {
        telegramQrError.value = e.message || t('settings.phone_login_request_failed');
    } finally {
        telegramPhoneLoginLoading.value = false;
    }
};

const completeTelegramPhoneLogin = async () => {
    const code = (telegramOtpCode.value || '').trim();
    if (!code) {
        telegramQrError.value = t('settings.enter_otp_code_error');
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
            telegramQrError.value = t('settings.telegram_2fa_enabled_complete');
            return;
        }
        telegramQrError.value = data.error || t('settings.otp_verification_failed');
    } catch (e) {
        telegramQrError.value = e.message || t('settings.otp_verification_request_failed');
    } finally {
        telegramOtpLoginLoading.value = false;
    }
};

const submitTelegram2fa = async () => {
    const password = (telegram2faPassword.value || '').trim();
    if (!password) {
        telegramQrError.value = t('settings.enter_telegram_2fa_password');
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
        telegramQrError.value = data.error || t('settings.verification_2fa_failed');
    } catch (e) {
        telegramQrError.value = e.message || t('settings.request_2fa_failed');
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
            telegramQrError.value = t('settings.telegram_2fa_help');
        } else if (data.qr_svg) {
            telegramQrSvg.value = data.qr_svg;
            if (data.conn_id) telegramQrConnId.value = data.conn_id;
        } else if (data.error) {
            telegramQrError.value = data.error;
        }
    } catch (e) {
        telegramQrError.value = e.message || t('settings.poll_failed_retry');
    } finally {
        telegramQrPollInFlight = false;
    }
};

const disconnectTelegram = () => {
    if (!confirm(t('settings.disconnect_telegram_confirm'))) return;
    router.post(route('settings.telegram.disconnect'), {}, {
        preserveScroll: true,
        preserveState: false, // Force fresh page props so telegramConnection updates
    });
};

const resetTelegramSession = () => {
    if (!confirm(t('settings.reset_session_confirm'))) return;
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
const tiktokWebhookUrl = typeof window !== 'undefined' && window.location?.origin
    ? `${window.location.origin}/tiktok-webhook`
    : 'https://yourdomain.com/tiktok-webhook';
const tiktokWebhookCopied = ref(false);
const copyTiktokWebhookUrl = () => {
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(tiktokWebhookUrl);
        tiktokWebhookCopied.value = true;
        setTimeout(() => { tiktokWebhookCopied.value = false; }, 2000);
    }
};
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
    if (confirm(t('common.confirm_delete_named').replace(':name', type.name))) {
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
    if (!confirm(t('settings.disconnect_instagram_confirm'))) return;
    router.post(route('settings.instagram.disconnect'), {}, { preserveScroll: true });
};

const disconnectTiktok = () => {
    if (!confirm(t('settings.disconnect_tiktok_confirm'))) return;
    router.post(route('settings.tiktok.disconnect'), {}, { preserveScroll: true });
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

const GOOGLE_PROGRESS_POLL_MS = 400;

const googleBulkProgress = ref(null);
const googleBulkPolling = ref(null);
const googleBulkSyncBusy = ref(false);

const showGoogleBulkStop = computed(() => {
    const s = googleBulkProgress.value?.status;
    return s === 'running' || s === 'queued';
});

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
        return t('settings.queued');
    }
    if (s === 'running') {
        return t('settings.processing');
    }
    if (s === 'done') {
        return t('settings.done');
    }
    if (s === 'failed') {
        return t('common.error');
    }
    if (s === 'cancelled') {
        return t('settings.cancelled');
    }
    return s || '—';
});

function formatGoogleTick(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return String(iso);
    }
}

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
        if (data.status === 'done' || data.status === 'failed' || data.status === 'cancelled') {
            googleBulkSyncBusy.value = false;
            stopGoogleBulkPolling();
        }
    } catch {
        /* ignore */
    }
}

async function requestGoogleBulkStop() {
    try {
        await window.axios.post(route('settings.google-contacts.sync-stop'));
    } catch {
        /* ignore */
    }
}

async function startGoogleBulkSync() {
    googleBulkSyncBusy.value = true;
    try {
        const { data } = await window.axios.post(route('settings.google-contacts.sync-start'));
        if (!data.ok) {
            throw new Error(data.message || t('settings.sync_start_failed'));
        }
        await pollGoogleBulkOnce();
        stopGoogleBulkPolling();
        googleBulkPolling.value = setInterval(pollGoogleBulkOnce, GOOGLE_PROGRESS_POLL_MS);
    } catch (e) {
        googleBulkSyncBusy.value = false;
        const msg = e.response?.data?.message || e.message || t('common.error');
        alert(msg);
    }
}

const googleImportBulkProgress = ref(null);
const googleImportBulkPolling = ref(null);
const googleImportBulkBusy = ref(false);

const googleImportBulkProgressPercent = computed(() => {
    const p = googleImportBulkProgress.value;
    if (!p || !p.total || p.total <= 0) {
        return 0;
    }
    const done = Math.min(p.processed ?? 0, p.total);
    return Math.round((done / p.total) * 100);
});

const googleImportBulkStatusLabel = computed(() => {
    const s = googleImportBulkProgress.value?.status;
    if (s === 'queued') {
        return t('settings.queued');
    }
    if (s === 'running') {
        return t('settings.processing');
    }
    if (s === 'done') {
        return t('settings.done');
    }
    if (s === 'failed') {
        return t('common.error');
    }
    if (s === 'cancelled') {
        return t('settings.cancelled');
    }
    return s || '—';
});

function stopGoogleImportBulkPolling() {
    if (googleImportBulkPolling.value) {
        clearInterval(googleImportBulkPolling.value);
        googleImportBulkPolling.value = null;
    }
}

async function pollGoogleImportOnce() {
    try {
        const { data } = await window.axios.get(route('settings.google-contacts.import-progress'));
        googleImportBulkProgress.value = data;
        if (data.status === 'done' || data.status === 'failed' || data.status === 'cancelled') {
            googleImportBulkBusy.value = false;
            stopGoogleImportBulkPolling();
        }
    } catch {
        /* ignore */
    }
}

async function startGoogleImportBulk() {
    googleImportBulkBusy.value = true;
    try {
        const { data } = await window.axios.post(route('settings.google-contacts.import-start'));
        if (!data.ok) {
            throw new Error(data.message || t('settings.sync_start_failed'));
        }
        await pollGoogleImportOnce();
        stopGoogleImportBulkPolling();
        googleImportBulkPolling.value = setInterval(pollGoogleImportOnce, GOOGLE_PROGRESS_POLL_MS);
    } catch (e) {
        googleImportBulkBusy.value = false;
        const msg = e.response?.data?.message || e.message || t('common.error');
        alert(msg);
    }
}

const googlePhotoBulkProgress = ref(null);
const googlePhotoBulkPolling = ref(null);
const googlePhotoBulkBusy = ref(false);

const showGooglePhotoStop = computed(() => {
    const s = googlePhotoBulkProgress.value?.status;
    return s === 'running' || s === 'queued';
});

const googlePhotoBulkProgressPercent = computed(() => {
    const p = googlePhotoBulkProgress.value;
    if (!p || !p.total || p.total <= 0) {
        return 0;
    }
    const done = Math.min(p.processed ?? 0, p.total);
    return Math.round((done / p.total) * 100);
});

const googlePhotoBulkStatusLabel = computed(() => {
    const s = googlePhotoBulkProgress.value?.status;
    if (s === 'queued') {
        return t('settings.queued');
    }
    if (s === 'running') {
        return t('settings.processing');
    }
    if (s === 'done') {
        return t('settings.done');
    }
    if (s === 'failed') {
        return t('common.error');
    }
    if (s === 'cancelled') {
        return t('settings.cancelled');
    }
    return s || '—';
});

function stopGooglePhotoBulkPolling() {
    if (googlePhotoBulkPolling.value) {
        clearInterval(googlePhotoBulkPolling.value);
        googlePhotoBulkPolling.value = null;
    }
}

async function pollGooglePhotoOnce() {
    try {
        const { data } = await window.axios.get(route('settings.google-contacts.photo-sync-progress'));
        googlePhotoBulkProgress.value = data;
        if (data.status === 'done' || data.status === 'failed' || data.status === 'cancelled') {
            googlePhotoBulkBusy.value = false;
            stopGooglePhotoBulkPolling();
        }
    } catch {
        /* ignore */
    }
}

async function requestGooglePhotoStop() {
    try {
        await window.axios.post(route('settings.google-contacts.photo-sync-stop'));
    } catch {
        /* ignore */
    }
}

async function startGooglePhotoBulk() {
    googlePhotoBulkBusy.value = true;
    try {
        const { data } = await window.axios.post(route('settings.google-contacts.photo-sync-start'));
        if (!data.ok) {
            throw new Error(data.message || t('settings.sync_start_failed'));
        }
        await pollGooglePhotoOnce();
        stopGooglePhotoBulkPolling();
        googlePhotoBulkPolling.value = setInterval(pollGooglePhotoOnce, GOOGLE_PROGRESS_POLL_MS);
    } catch (e) {
        googlePhotoBulkBusy.value = false;
        const msg = e.response?.data?.message || e.message || t('common.error');
        alert(msg);
    }
}

async function refreshGoogleContactsTabProgress() {
    await pollGoogleBulkOnce();
    await pollGoogleImportOnce();
    await pollGooglePhotoOnce();
    const st = googleBulkProgress.value?.status;
    if (st === 'running' || st === 'queued') {
        googleBulkSyncBusy.value = true;
        stopGoogleBulkPolling();
        googleBulkPolling.value = setInterval(pollGoogleBulkOnce, GOOGLE_PROGRESS_POLL_MS);
    } else {
        googleBulkSyncBusy.value = false;
    }
    const ist = googleImportBulkProgress.value?.status;
    if (ist === 'running' || ist === 'queued') {
        googleImportBulkBusy.value = true;
        stopGoogleImportBulkPolling();
        googleImportBulkPolling.value = setInterval(pollGoogleImportOnce, GOOGLE_PROGRESS_POLL_MS);
    } else {
        googleImportBulkBusy.value = false;
    }
    const pst = googlePhotoBulkProgress.value?.status;
    if (pst === 'running' || pst === 'queued') {
        googlePhotoBulkBusy.value = true;
        stopGooglePhotoBulkPolling();
        googlePhotoBulkPolling.value = setInterval(pollGooglePhotoOnce, GOOGLE_PROGRESS_POLL_MS);
    } else {
        googlePhotoBulkBusy.value = false;
    }
}

watch(
    () => activeTab.value,
    async (t) => {
        if (t !== 'google-contacts') {
            stopGoogleBulkPolling();
            stopGoogleImportBulkPolling();
            stopGooglePhotoBulkPolling();
            return;
        }
        await refreshGoogleContactsTabProgress();
    }
);

onMounted(async () => {
    if (activeTab.value === 'google-contacts') {
        await refreshGoogleContactsTabProgress();
    }
});

onUnmounted(() => {
    stopGoogleBulkPolling();
    stopGoogleImportBulkPolling();
    stopGooglePhotoBulkPolling();
    stopRonibotPartnerPolling();
});

const disconnectGoogleContacts = () => {
    if (!confirm(t('settings.disconnect_google_confirm'))) {
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
        alert(t('settings.enter_test_email_address'));
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
        alert(t('settings.enter_test_phone_number'));
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

function saveOrganizationProfile() {
    organizationProfileForm.post(route('settings.organization-profile.update'), {
        forceFormData: true,
        preserveScroll: true,
    });
}

function onOrganizationLogoChange(e) {
    const f = e.target.files?.[0];
    organizationProfileForm.logo = f || null;
    organizationProfileForm.remove_logo = false;
}

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

    if (!confirm(t('common.confirm_delete_named').replace(':name', organization.name))) {
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

    if (!confirm(t('settings.remove_member_from_org_confirm').replace(':name', member.name))) return;

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

const getInitials = (name) => {
    if (!name || typeof name !== 'string') return 'U';
    const parts = name.trim().split(/\s+/).filter(Boolean);
    const first = parts[0]?.[0] || '';
    const second = parts.length > 1 ? (parts[1]?.[0] || '') : '';
    return (first + second).toUpperCase() || 'U';
};
</script>
