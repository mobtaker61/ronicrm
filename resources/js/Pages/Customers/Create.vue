<template>
    <AppLayout>
        <template #header>
            Create Customer
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 space-y-6" enctype="multipart/form-data">
                <!-- Avatar Upload -->
                <div class="flex items-center space-x-6">
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
                        <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                        <input
                            type="file"
                            accept="image/*"
                            @change="handleAvatarChange"
                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Type *</label>
                        <select
                            v-model="form.type"
                            required
                            @change="handleTypeChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="person">Person</option>
                            <option value="company">Company</option>
                        </select>
                        <div v-if="form.errors.type" class="mt-1 text-sm text-red-600">
                            {{ form.errors.type }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
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

                    <div v-if="form.type === 'person'">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                        <select
                            v-model="form.gender"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Language</label>
                        <select
                            v-model="form.language"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">Select Language</option>
                            <option value="Persian">Persian</option>
                            <option value="English">English</option>
                            <option value="Kurdish">Kurdish</option>
                            <option value="Turkish">Turkish</option>
                            <option value="Arabic">Arabic</option>
                            <option value="Hindi">Hindi</option>
                            <option value="Urdu">Urdu</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Company Name</label>
                        <input
                            v-model="form.company_name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <textarea
                            v-model="form.address"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Industry</label>
                        <IndustrySelect
                            v-model="form.industry_id"
                            :industries="industries"
                            placeholder="Choose category..."
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                        <select
                            v-model="form.project_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">No project</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status *</label>
                        <select
                            v-model="form.status"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="lead">Lead</option>
                            <option value="prospect">Prospect</option>
                            <option value="customer">Customer</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <div v-if="form.errors.status" class="mt-1 text-sm text-red-600">
                            {{ form.errors.status }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Source *</label>
                        <select
                            v-model="form.source"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                            <option value="advertisement">Advertisement</option>
                            <option value="social_media">Social Media</option>
                            <option value="other">Other</option>
                        </select>
                        <div v-if="form.errors.source" class="mt-1 text-sm text-red-600">
                            {{ form.errors.source }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person</label>
                        <input
                            v-model="form.contact_person"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
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
                        <h3 class="text-lg font-semibold text-gray-900">Contact Methods</h3>
                        <button
                            type="button"
                            @click="addContact"
                            class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            Add Contact
                        </button>
                    </div>

                    <div v-if="form.contacts.length === 0" class="text-sm text-gray-500 text-center py-4">
                        No contact methods added. Click "Add Contact" to add one.
                    </div>

                    <div v-for="(contact, index) in form.contacts" :key="index" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                            <select
                                v-model="contact.type"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="phone">Phone</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="telegram">Telegram</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Value *</label>
                            <input
                                v-model="contact.value"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center space-x-2">
                                <input
                                    v-model="contact.is_primary"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-gray-700">Primary</span>
                            </label>
                        </div>

                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="removeContact(index)"
                                class="w-full px-3 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Social Media & Websites -->
                <div class="border-t pt-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Social Media & Websites</h3>
                        <button
                            type="button"
                            @click="addSocialMedia"
                            class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                        >
                            Add Social Media
                        </button>
                    </div>

                    <div v-if="form.social_media.length === 0" class="text-sm text-gray-500 text-center py-4">
                        No social media added. Click "Add Social Media" to add one.
                    </div>

                    <div v-for="(sm, index) in form.social_media" :key="index" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4 p-4 bg-gray-50 rounded-lg">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Platform *</label>
                            <select
                                v-model="sm.social_media_type_id"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="null">Select Platform</option>
                                <option v-for="type in socialMediaTypes" :key="type.id" :value="type.id">
                                    {{ type.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Handle/Username *</label>
                            <input
                                v-model="sm.handle"
                                type="text"
                                required
                                placeholder="@username or handle"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center space-x-2">
                                <input
                                    v-model="sm.is_primary"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                <span class="text-sm text-gray-700">Primary</span>
                            </label>
                        </div>

                        <div class="flex items-end">
                            <button
                                type="button"
                                @click="removeSocialMedia(index)"
                                class="w-full px-3 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3">
                    <Link
                        :href="route('customers.index')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Customer' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import IndustrySelect from '@/Components/IndustrySelect.vue';

const props = defineProps({
    industries: Array,
    projects: Array,
    socialMediaTypes: Array,
});

const form = useForm({
    name: '',
    type: 'person',
    gender: null,
    language: null,
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
