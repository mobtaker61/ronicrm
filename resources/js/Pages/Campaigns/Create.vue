<template>
    <AppLayout>
        <template #header>
            Create Campaign
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 space-y-6" enctype="multipart/form-data">
                <!-- Success/Error Messages -->
                <div v-if="form.errors" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Campaign Name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., Summer Sale 2024"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Campaign Type *</label>
                        <select
                            v-model="form.type"
                            required
                            @change="handleTypeChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">Select Type</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template</label>
                        <select
                            v-model="form.template_id"
                            @change="loadTemplate"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">No Template</option>
                            <option v-for="template in templates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Campaign description..."
                        ></textarea>
                    </div>
                </div>

                <!-- Email Subject (only for email campaigns) -->
                <div v-if="form.type === 'email'" class="border-t pt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email Subject *</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        :required="form.type === 'email'"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="e.g., Special Offer Just for You!"
                    />
                </div>

                <!-- File Upload (for WhatsApp) -->
                <div v-if="form.type === 'whatsapp'" class="border-t pt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Attachment (Optional)</label>
                    <div class="flex items-center space-x-4">
                        <div v-if="imagePreview || selectedFile" class="flex-shrink-0 relative">
                            <img
                                v-if="imagePreview && isImageFile(selectedFile)"
                                :src="imagePreview"
                                alt="Preview"
                                class="w-32 h-32 object-cover rounded-lg border border-gray-300"
                            />
                            <div
                                v-else-if="selectedFile"
                                class="w-32 h-32 bg-gray-100 rounded-lg border border-gray-300 flex items-center justify-center"
                            >
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <button
                                v-if="imagePreview || selectedFile"
                                type="button"
                                @click="clearImage"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                            >
                                ×
                            </button>
                        </div>
                        <div class="flex-1">
                            <input
                                type="file"
                                @change="handleImageChange"
                                class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                            <p class="mt-1 text-xs text-gray-500">Upload file for WhatsApp (PDF, Word, Excel, Images, etc. - Max 50MB)</p>
                            <p v-if="selectedFile" class="mt-1 text-xs text-gray-600">
                                Selected: {{ selectedFile.name }} ({{ formatFileSize(selectedFile.size) }})
                            </p>
                            <p v-if="form.template_id && selectedTemplate?.image" class="mt-1 text-xs text-blue-600">
                                Template has an attachment. Upload a new file to replace it.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="border-t pt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Message Content *</label>
                    
                    <!-- HTML Editor for Email -->
                    <div v-if="form.type === 'email'" class="space-y-2">
                        <div class="flex space-x-2 mb-2">
                            <button
                                type="button"
                                @click="editorMode = 'html'"
                                :class="[
                                    'px-3 py-1 text-sm rounded-md',
                                    editorMode === 'html' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'
                                ]"
                            >
                                HTML
                            </button>
                            <button
                                type="button"
                                @click="editorMode = 'preview'"
                                :class="[
                                    'px-3 py-1 text-sm rounded-md',
                                    editorMode === 'preview' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'
                                ]"
                            >
                                Preview
                            </button>
                        </div>
                        
                        <div v-if="editorMode === 'html'">
                            <!-- HTML Editor Toolbar -->
                            <div class="mb-2 flex flex-wrap gap-2 p-2 bg-gray-50 border border-gray-300 rounded-t-md">
                                <button
                                    type="button"
                                    @click="formatText('bold')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Bold"
                                >
                                    <strong>B</strong>
                                </button>
                                <button
                                    type="button"
                                    @click="formatText('italic')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Italic"
                                >
                                    <em>I</em>
                                </button>
                                <button
                                    type="button"
                                    @click="formatText('underline')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Underline"
                                >
                                    <u>U</u>
                                </button>
                                <div class="border-l border-gray-300 mx-1"></div>
                                <button
                                    type="button"
                                    @click="formatText('h1')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Heading 1"
                                >
                                    H1
                                </button>
                                <button
                                    type="button"
                                    @click="formatText('h2')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Heading 2"
                                >
                                    H2
                                </button>
                                <button
                                    type="button"
                                    @click="formatText('h3')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Heading 3"
                                >
                                    H3
                                </button>
                                <div class="border-l border-gray-300 mx-1"></div>
                                <button
                                    type="button"
                                    @click="formatText('ul')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Unordered List"
                                >
                                    • List
                                </button>
                                <button
                                    type="button"
                                    @click="formatText('ol')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Ordered List"
                                >
                                    1. List
                                </button>
                                <button
                                    type="button"
                                    @click="insertLink"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Insert Link"
                                >
                                    🔗 Link
                                </button>
                                <div class="border-l border-gray-300 mx-1"></div>
                                <button
                                    type="button"
                                    @click="insertVariable('name')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Insert Name Variable"
                                >
                                    {name}
                                </button>
                                <button
                                    type="button"
                                    @click="insertVariable('company')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Insert Company Variable"
                                >
                                    {company}
                                </button>
                                <button
                                    type="button"
                                    @click="insertVariable('email')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Insert Email Variable"
                                >
                                    {email}
                                </button>
                                <button
                                    type="button"
                                    @click="insertVariable('phone')"
                                    class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                    title="Insert Phone Variable"
                                >
                                    {phone}
                                </button>
                            </div>
                            <textarea
                                ref="htmlEditor"
                                v-model="form.content"
                                rows="12"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-b-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                                placeholder="Enter HTML content or use the toolbar above..."
                            ></textarea>
                        </div>
                        
                        <div v-else class="border border-gray-300 rounded-md bg-white min-h-[200px] overflow-auto">
                            <div 
                                class="p-4 email-preview" 
                                v-html="form.content || '<p class=&quot;text-gray-400 italic&quot;>No content to preview. Start typing HTML in the editor above.</p>'"
                            ></div>
                        </div>
                    </div>

                    <!-- Simple Textarea for WhatsApp -->
                    <div v-else>
                        <textarea
                            v-model="form.content"
                            rows="8"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="WhatsApp message content..."
                        ></textarea>
                    </div>

                    <p class="mt-1 text-xs text-gray-500">
                        You can use variables: {{ '{name}' }}, {{ '{company}' }}, {{ '{email}' }}, {{ '{phone}' }}
                    </p>
                </div>

                <!-- Recipients Selection -->
                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Select Recipients</h3>
                    
                    <!-- Campaign Type Warning -->
                    <div v-if="!form.type" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <strong>Note:</strong> Please select the campaign type (WhatsApp or Email) first. The recipient list will be filtered automatically based on the selected type.
                        </p>
                    </div>
                    
                    <!-- Filtered Count Info -->
                    <div v-if="form.type" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <strong>By campaign type:</strong>
                            <span class="font-semibold">{{ recipientEntries.length }}</span>
                            {{ form.type === 'whatsapp' ? 'WhatsApp contact(s)' : 'email(s)' }}
                            (one row per contact method; count is number of messages to be sent)
                        </p>
                    </div>
                    
                    <!-- Filter Options -->
                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Project</label>
                                <select
                                    v-model="recipientFilters.project_id"
                                    @change="filterRecipients"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">All Projects</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Industry</label>
                                <select
                                    v-model="recipientFilters.industry_id"
                                    @change="filterRecipients"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">All Industries</option>
                                    <option v-for="industry in allIndustries" :key="industry.id" :value="industry.id">
                                        {{ industry.full_path || industry.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Status</label>
                                <select
                                    v-model="recipientFilters.status"
                                    @change="filterRecipients"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="lead">Lead</option>
                                    <option value="prospect">Prospect</option>
                                    <option value="customer">Customer</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Filter by Type</label>
                                <select
                                    v-model="recipientFilters.type"
                                    @change="filterRecipients"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">All Types</option>
                                    <option value="person">Person</option>
                                    <option value="company">Company</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <button
                                    type="button"
                                    @click="selectAll"
                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                >
                                    Select All
                                </button>
                                <button
                                    type="button"
                                    @click="deselectAll"
                                    class="px-3 py-1 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700"
                                >
                                    Deselect All
                                </button>
                            </div>
                            <div class="text-sm text-gray-600">
                                Selected: <span class="font-semibold">{{ form.recipient_entries.length }}</span> (messages to send)
                            </div>
                        </div>
                    </div>

                    <!-- Recipients List (one row per contact method) -->
                    <div v-if="!form.type" class="p-8 text-center text-gray-500 border border-gray-200 rounded-lg">
                        <p>Please select the campaign type first to see available recipients.</p>
                    </div>
                    <div v-else-if="recipientEntries.length === 0" class="p-8 text-center text-gray-500 border border-gray-200 rounded-lg">
                        <p>No recipients found matching the selected filters.</p>
                        <p class="text-sm mt-2">Try adjusting your filters or check if customers have {{ form.type === 'whatsapp' ? 'WhatsApp' : 'email' }} contact(s).</p>
                    </div>
                    <div v-else class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        <input
                                            type="checkbox"
                                            :checked="allSelected"
                                            @change="toggleAll"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact (to send to)</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="entry in recipientEntries" :key="entry.key">
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="isEntrySelected(entry)"
                                            @change="toggleEntry(entry)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ entry.customer.name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                            :class="entry.customer.type === 'person' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                                        >
                                            {{ entry.customer.type || 'person' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span class="text-xs text-gray-400">{{ entry.contact ? entry.contact.type : 'email' }}:</span> {{ entry.contact ? entry.contact.value : entry.customer.email }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800': entry.customer.status === 'lead',
                                                'bg-blue-100 text-blue-800': entry.customer.status === 'prospect',
                                                'bg-green-100 text-green-800': entry.customer.status === 'customer',
                                            }"
                                        >
                                            {{ entry.customer.status }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Scheduling -->
                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Schedule Campaign</h3>
                    <div class="flex items-center space-x-4">
                        <label class="flex items-center space-x-2">
                            <input
                                v-model="scheduleNow"
                                type="radio"
                                :value="true"
                                @change="form.scheduled_at = null"
                                class="text-blue-600 focus:ring-blue-500"
                            />
                            <span>Send Now</span>
                        </label>
                        <label class="flex items-center space-x-2">
                            <input
                                v-model="scheduleNow"
                                type="radio"
                                :value="false"
                                class="text-blue-600 focus:ring-blue-500"
                            />
                            <span>Schedule Later</span>
                        </label>
                    </div>
                    <div v-if="!scheduleNow" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Scheduled Date & Time</label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3 border-t pt-6">
                    <Link
                        :href="route('campaigns.index')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || form.recipient_entries.length === 0"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Campaign' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    templates: Array,
    industries: Array,
    projects: Array,
    customers: Array,
});

// Flatten industries to include all parent and child industries
const allIndustries = computed(() => {
    const flatten = (items, prefix = '') => {
        let result = [];
        items.forEach(item => {
            const name = prefix ? `${prefix} > ${item.name}` : item.name;
            result.push({
                ...item,
                full_path: name
            });
            if (item.children && item.children.length > 0) {
                result = result.concat(flatten(item.children, name));
            }
        });
        return result;
    };
    return flatten(props.industries || []);
});

const form = useForm({
    name: '',
    description: '',
    type: '',
    template_id: null,
    subject: '',
    content: '',
    image: null,
    scheduled_at: null,
    recipient_entries: [],
});

const scheduleNow = ref(true);
const editorMode = ref('html');
const imagePreview = ref(null);
const selectedFile = ref(null);
const recipientFilters = ref({
    project_id: '',
    industry_id: '',
    status: '',
    type: '',
});

const filteredCustomers = computed(() => {
    let filtered = [...props.customers];

    // Filter by campaign type (WhatsApp or Email)
    if (form.type === 'whatsapp') {
        // Only customers with WhatsApp contact (whatsapp type in contacts)
        filtered = filtered.filter(c => {
            // Check if customer has whatsapp contact
            if (c.contacts && Array.isArray(c.contacts)) {
                return c.contacts.some(contact => contact.type === 'whatsapp');
            }
            
            return false;
        });
    } else if (form.type === 'email') {
        // Only customers with email (either in email field or contacts)
        filtered = filtered.filter(c => {
            // Check if customer has email in main field
            if (c.email) return true;
            
            // Check if customer has email in contacts
            if (c.contacts && Array.isArray(c.contacts)) {
                return c.contacts.some(contact => contact.type === 'email');
            }
            
            return false;
        });
    }

    // Filter by industry
    if (recipientFilters.value.industry_id) {
        // Also include customers from child industries
        const selectedIndustry = allIndustries.value.find(i => i.id == recipientFilters.value.industry_id);
        const industryIds = [parseInt(recipientFilters.value.industry_id)];
        
        // Get all child industry IDs recursively
        const getChildIds = (industry) => {
            if (industry.children && industry.children.length > 0) {
                industry.children.forEach(child => {
                    industryIds.push(child.id);
                    getChildIds(child);
                });
            }
        };
        
        if (selectedIndustry) {
            getChildIds(selectedIndustry);
        }
        
        filtered = filtered.filter(c => industryIds.includes(c.industry_id));
    }

    // Filter by status
    if (recipientFilters.value.status) {
        filtered = filtered.filter(c => c.status === recipientFilters.value.status);
    }

    // Filter by customer type
    if (recipientFilters.value.type) {
        filtered = filtered.filter(c => c.type === recipientFilters.value.type);
    }

    // Filter by project
    if (recipientFilters.value.project_id) {
        filtered = filtered.filter(c => (c.project_id || '') === recipientFilters.value.project_id || c.project_id === parseInt(recipientFilters.value.project_id));
    }

    return filtered;
});

// One row per contact method matching campaign type (WhatsApp -> whatsapp contacts, Email -> email contacts + main email)
const recipientEntries = computed(() => {
    const list = [];
    const custs = filteredCustomers.value;
    if (form.type === 'whatsapp') {
        custs.forEach(c => {
            if (c.contacts && Array.isArray(c.contacts)) {
                c.contacts.filter(contact => contact.type === 'whatsapp').forEach(contact => {
                    list.push({
                        key: `${c.id}-${contact.id}`,
                        customer: c,
                        contact,
                        customer_id: c.id,
                        customer_contact_id: contact.id,
                    });
                });
            }
        });
    } else if (form.type === 'email') {
        custs.forEach(c => {
            const emailContacts = (c.contacts && Array.isArray(c.contacts)) ? c.contacts.filter(contact => contact.type === 'email') : [];
            emailContacts.forEach(contact => {
                list.push({
                    key: `${c.id}-${contact.id}`,
                    customer: c,
                    contact,
                    customer_id: c.id,
                    customer_contact_id: contact.id,
                });
            });
            if (c.email && emailContacts.length === 0) {
                list.push({
                    key: `${c.id}-main`,
                    customer: c,
                    contact: null,
                    customer_id: c.id,
                    customer_contact_id: null,
                });
            }
        });
    }
    return list;
});

const allSelected = computed(() => {
    return recipientEntries.value.length > 0 &&
           recipientEntries.value.every(entry => isEntrySelected(entry));
});

function isEntrySelected(entry) {
    return form.recipient_entries.some(
        e => e.customer_id === entry.customer_id && (e.customer_contact_id || null) === (entry.customer_contact_id || null)
    );
}

function toggleEntry(entry) {
    const idx = form.recipient_entries.findIndex(
        e => e.customer_id === entry.customer_id && (e.customer_contact_id || null) === (entry.customer_contact_id || null)
    );
    if (idx >= 0) {
        form.recipient_entries.splice(idx, 1);
    } else {
        form.recipient_entries.push({
            customer_id: entry.customer_id,
            customer_contact_id: entry.customer_contact_id || null,
        });
    }
}

const handleTypeChange = () => {
    if (form.type === 'whatsapp') {
        form.subject = '';
    }
    editorMode.value = 'html';
    
    // Clear selected recipients when campaign type changes
    form.recipient_entries = [];
};

const handleImageChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file size (50MB max)
        if (file.size > 50 * 1024 * 1024) {
            alert('File size must be less than 50MB');
            event.target.value = '';
            return;
        }
        
        form.image = file;
        selectedFile.value = file;
        
        // Only create preview for image files
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.value = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            imagePreview.value = null;
        }
    }
};

const clearImage = () => {
    form.image = null;
    imagePreview.value = null;
    selectedFile.value = null;
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.value = '';
    }
};

const isImageFile = (file) => {
    if (!file) return false;
    return file.type && file.type.startsWith('image/');
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const selectedTemplate = computed(() => {
    if (form.template_id) {
        return props.templates.find(t => t.id === form.template_id);
    }
    return null;
});

const loadTemplate = () => {
    if (form.template_id) {
        const template = props.templates.find(t => t.id === form.template_id);
        if (template) {
            form.content = template.content || '';
            form.subject = template.subject || '';
            // Only show template image if type matches and no new image is uploaded
            if (template.image && form.type === 'whatsapp' && !form.image) {
                // Template image is already a full URL from backend
                imagePreview.value = template.image;
            } else if (!form.image) {
                imagePreview.value = null;
            }
        }
    } else {
        if (!form.image) {
            imagePreview.value = null;
        }
    }
};

const filterRecipients = () => {
    // Filtering is handled by computed property
};

const selectAll = () => {
    recipientEntries.value.forEach(entry => {
        if (!isEntrySelected(entry)) {
            form.recipient_entries.push({
                customer_id: entry.customer_id,
                customer_contact_id: entry.customer_contact_id || null,
            });
        }
    });
};

const deselectAll = () => {
    form.recipient_entries = [];
};

const toggleAll = (event) => {
    if (event.target.checked) {
        selectAll();
    } else {
        deselectAll();
    }
};

const htmlEditor = ref(null);

const formatText = (command) => {
    const textarea = htmlEditor.value;
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = form.content.substring(start, end);
    let replacement = '';
    
    switch (command) {
        case 'bold':
            replacement = `<strong>${selectedText || 'bold text'}</strong>`;
            break;
        case 'italic':
            replacement = `<em>${selectedText || 'italic text'}</em>`;
            break;
        case 'underline':
            replacement = `<u>${selectedText || 'underlined text'}</u>`;
            break;
        case 'h1':
            replacement = `<h1>${selectedText || 'Heading 1'}</h1>`;
            break;
        case 'h2':
            replacement = `<h2>${selectedText || 'Heading 2'}</h2>`;
            break;
        case 'h3':
            replacement = `<h3>${selectedText || 'Heading 3'}</h3>`;
            break;
        case 'ul':
            replacement = `<ul><li>${selectedText || 'List item'}</li></ul>`;
            break;
        case 'ol':
            replacement = `<ol><li>${selectedText || 'List item'}</li></ol>`;
            break;
    }
    
    form.content = form.content.substring(0, start) + replacement + form.content.substring(end);
    
    // Restore cursor position
    setTimeout(() => {
        textarea.focus();
        const newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
    }, 0);
};

const insertLink = () => {
    const textarea = htmlEditor.value;
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = form.content.substring(start, end);
    const linkText = selectedText || 'link text';
    const url = prompt('Enter URL:', 'https://');
    
    if (url) {
        const replacement = `<a href="${url}">${linkText}</a>`;
        form.content = form.content.substring(0, start) + replacement + form.content.substring(end);
        
        setTimeout(() => {
            textarea.focus();
            const newPos = start + replacement.length;
            textarea.setSelectionRange(newPos, newPos);
        }, 0);
    }
};

const insertVariable = (varName) => {
    const textarea = htmlEditor.value;
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const replacement = `{${varName}}`;
    
    form.content = form.content.substring(0, start) + replacement + form.content.substring(start);
    
    setTimeout(() => {
        textarea.focus();
        const newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
    }, 0);
};

const submit = () => {
    form.transform((data) => {
        const payload = { ...data };
        if (payload.recipient_entries && Array.isArray(payload.recipient_entries)) {
            payload.recipient_entries = JSON.stringify(payload.recipient_entries);
        }
        return payload;
    }).post(route('campaigns.store'), {
        forceFormData: true,
    });
};
</script>
