<template>
    <AppLayout>
        <template #header>
            {{ t('customers.create_customer') }}
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 space-y-6" enctype="multipart/form-data">
                <!-- Avatar Upload -->
                <div class="flex items-center space-x-6 rtl:space-x-reverse">
                    <div class="flex-shrink-0">
                        <img
                            v-if="avatarPreview"
                            :src="avatarPreview"
                            alt="Avatar Preview"
                            class="w-24 h-24 rounded-full object-cover border-2 border-gray-300"
                        />
                        <div
                            v-else
                            class="w-24 h-24 rounded-full bg-gray-200 flex items-center justify-center border-2 border-gray-300"
                        >
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.profile_picture') }}</label>
                        <input
                            type="file"
                            accept="image/*"
                            @change="handleAvatarChange"
                            class="block w-full text-sm text-gray-500 file:ltr:mr-4 file:rtl:ml-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <!-- Row 1: Contact Type 1/4 + Company Name 3/4 -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.contact_type_required') }}</label>
                        <select
                            v-model="form.type"
                            required
                            @change="handleTypeChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="person">{{ t('customers.person') }}</option>
                            <option value="company">{{ t('customers.company') }}</option>
                        </select>
                        <div v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.type }}
                        </div>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.company_name') }}</label>
                        <input
                            v-model="form.company_name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <!-- Row 2: Gender 1/4 (person only) + Name 3/4 (or Name full width for company) -->
                    <div v-if="form.type === 'person'" class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.gender') }}</label>
                        <select
                            v-model="form.gender"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">{{ t('customers.select_gender') }}</option>
                            <option value="male">{{ t('customers.gender_male') }}</option>
                            <option value="female">{{ t('customers.gender_female') }}</option>
                            <option value="other">{{ t('customers.gender_other') }}</option>
                        </select>
                    </div>

                    <div
                        class="md:col-span-3"
                        :class="{ 'md:col-span-4': form.type === 'company' }"
                    >
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.name_required') }}</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <div v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('superadmin.languages') }}</label>
                        <p class="text-xs text-gray-500 mb-3">{{ t('customers.select_languages_help') }}</p>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                            <label
                                v-for="lang in languageOptions"
                                :key="lang"
                                class="inline-flex items-center gap-2 cursor-pointer text-sm text-gray-700"
                            >
                                <input
                                    v-model="form.languages"
                                    type="checkbox"
                                    :value="lang"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span>{{ lang }}</span>
                            </label>
                        </div>
                        <div v-if="form.errors.languages" class="mt-1 text-sm text-red-600">
                            {{ form.errors.languages }}
                        </div>
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.address') }}</label>
                        <textarea
                            v-model="form.address"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('sidebar.industries') }}</label>
                        <IndustrySelect
                            v-model="form.industry_id"
                            :industries="industries"
                            :placeholder="t('customers.choose_category')"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('sidebar.projects') }}</label>
                        <select
                            v-model="form.project_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">{{ t('customers.no_project') }}</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.status_required') }}</label>
                        <select
                            v-model="form.status"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="lead">{{ t('customers.lead') }}</option>
                            <option value="prospect">{{ t('customers.prospect') }}</option>
                            <option value="customer">{{ t('customers.customer') }}</option>
                            <option value="inactive">{{ t('customers.inactive') }}</option>
                        </select>
                        <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                            {{ form.errors.status }}
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.source_required') }}</label>
                        <select
                            v-model="form.source"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="website">{{ t('customers.source_website') }}</option>
                            <option value="referral">{{ t('customers.source_referral') }}</option>
                            <option value="advertisement">{{ t('customers.source_advertisement') }}</option>
                            <option value="social_media">{{ t('customers.source_social_media') }}</option>
                            <option value="crawl">{{ t('customers.source_crawl') }}</option>
                            <option value="exhibition">{{ t('customers.source_exhibition') }}</option>
                            <option value="direct">{{ t('customers.source_direct') }}</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="telegram">Telegram</option>
                            <option value="instagram">Instagram</option>
                            <option value="telegram_group_crawl">{{ t('customers.source_telegram_group_crawl') }}</option>
                            <option value="other">{{ t('customers.source_other') }}</option>
                        </select>
                        <div v-if="form.errors.source" class="mt-1 text-sm text-red-600">
                            {{ form.errors.source }}
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.contact_person') }}</label>
                        <input
                            v-model="form.contact_person"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.notes') }}</label>
                        <textarea
                            v-model="form.notes"
                            rows="4"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>
                </div>

                <!-- Contact Methods -->
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('customers.contact_methods') }}</h3>
                        <button
                            type="button"
                            @click="addContact"
                            class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            {{ t('customers.add_contact') }}
                        </button>
                    </div>

                    <div v-if="form.contacts.length === 0" class="text-sm text-gray-500 text-center py-4">
                        {{ t('customers.no_contact_methods') }}
                    </div>

                    <div v-for="(contact, index) in form.contacts" :key="index" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.type_required') }}</label>
                            <select
                                v-model="contact.type"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="phone">{{ t('customers.phone') }}</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="telegram">Telegram</option>
                                <option value="instagram">Instagram</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.value_required') }}</label>
                            <input
                                v-model="contact.value"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                <input
                                    v-model="contact.is_primary"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-gray-700">{{ t('customers.primary') }}</span>
                            </label>
                        </div>

                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="removeContact(index)"
                                class="w-full px-3 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                                {{ t('customers.remove') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Social Media & Websites -->
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ t('customers.social_media_websites') }}</h3>
                        <button
                            type="button"
                            @click="addSocialMedia"
                            class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            {{ t('customers.add_social_media') }}
                        </button>
                    </div>

                    <div v-if="form.social_media.length === 0" class="text-sm text-gray-500 text-center py-4">
                        {{ t('customers.no_social_media') }}
                    </div>

                    <div v-for="(sm, index) in form.social_media" :key="index" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.platform_required') }}</label>
                            <select
                                v-model="sm.social_media_type_id"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="null">{{ t('customers.select_platform') }}</option>
                                <option v-for="type in socialMediaTypes" :key="type.id" :value="type.id">
                                    {{ type.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.handle_required') }}</label>
                            <input
                                v-model="sm.handle"
                                type="text"
                                required
                                :placeholder="t('customers.handle_placeholder')"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center space-x-2 rtl:space-x-reverse">
                                <input
                                    v-model="sm.is_primary"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-gray-700">{{ t('customers.primary') }}</span>
                            </label>
                        </div>

                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="removeSocialMedia(index)"
                                class="w-full px-3 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                                {{ t('customers.remove') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 rtl:space-x-reverse">
                    <Link
                        :href="route('customers.index')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? t('customers.creating') : t('customers.create_customer') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import IndustrySelect from '@/Components/IndustrySelect.vue';
import { CUSTOMER_LANGUAGE_OPTIONS } from '@/constants/customerLanguages.js';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const page = usePage();
const languageOptions = computed(() => {
    const langs = page.props.languages || [];
    return langs.length ? langs.map((l) => l.name) : CUSTOMER_LANGUAGE_OPTIONS;
});

const props = defineProps({
    industries: Array,
    projects: Array,
    socialMediaTypes: Array,
});

const form = useForm({
    name: '',
    type: 'person',
    gender: null,
    languages: [],
    avatar: null,
    company_name: '',
    address: '',
    industry_id: null,
    project_id: null,
    status: 'lead',
    source: 'other',
    contact_person: '',
    notes: '',
    contacts: [],
    social_media: [],
});

const avatarPreview = ref(null);

const handleAvatarChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.avatar = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            avatarPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const handleTypeChange = () => {
    if (form.type === 'company') {
        form.gender = null;
    }
};

const addContact = () => {
    form.contacts.push({
        type: 'phone',
        value: '',
        is_primary: false,
    });
};

const removeContact = (index) => {
    form.contacts.splice(index, 1);
};

const addSocialMedia = () => {
    form.social_media.push({
        social_media_type_id: null,
        handle: '',
        is_primary: false,
    });
};

const removeSocialMedia = (index) => {
    form.social_media.splice(index, 1);
};

const submit = () => {
    form.post(route('customers.store'));
};
</script>
