<template>
    <AppLayout>
        <template #header>
            {{ t('sidebar.customers') }}
        </template>

        <div class="space-y-6">
            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="text-2xl font-bold text-gray-900">{{ t('sidebar.customers') }}</h2>
                <div class="flex gap-2">
                    <button
                        @click="showImportModal = true"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap flex items-center space-x-2 rtl:space-x-reverse"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span>{{ t('customers.import_customers') }}</span>
                    </button>
                    <Link
                        :href="route('customers.create')"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap"
                    >
                        {{ t('customers.add_customer') }}
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4 md:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.search') }}</label>
                        <input
                            v-model="filters.search"
                            type="text"
                            :placeholder="t('customers.search_placeholder')"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="applyFilters"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.type') }}</label>
                        <select
                            v-model="filters.type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">{{ t('customers.all_types') }}</option>
                            <option value="person">{{ t('customers.person') }}</option>
                            <option value="company">{{ t('customers.company') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('sidebar.industries') }}</label>
                        <IndustrySelect
                            v-model="filters.industry_id"
                            :industries="industries"
                            :placeholder="t('customers.all_industries')"
                            @update:model-value="applyFilters"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('sidebar.projects') }}</label>
                        <select
                            v-model="filters.project_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">{{ t('customers.all_projects') }}</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.status') }}</label>
                        <select
                            v-model="filters.status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">{{ t('customers.all_statuses') }}</option>
                            <option value="lead">{{ t('customers.lead') }}</option>
                            <option value="prospect">{{ t('customers.prospect') }}</option>
                            <option value="customer">{{ t('customers.customer') }}</option>
                            <option value="inactive">{{ t('customers.inactive') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.source') }}</label>
                        <select
                            v-model="filters.source"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">{{ t('customers.all_sources') }}</option>
                            <option value="website">{{ t('customers.source_website') }}</option>
                            <option value="referral">{{ t('customers.source_referral') }}</option>
                            <option value="advertisement">{{ t('customers.source_advertisement') }}</option>
                            <option value="social_media">{{ t('customers.source_social_media') }}</option>
                            <option value="crawl">{{ t('customers.source_crawl') }}</option>
                            <option value="exhibition">{{ t('customers.source_exhibition') }}</option>
                            <option value="direct">{{ t('customers.source_direct') }}</option>
                            <option value="whatsapp">{{ t('inbox.whatsapp') }}</option>
                            <option value="telegram">{{ t('sidebar.telegram') }}</option>
                            <option value="instagram">{{ t('settings.tabs.instagram_inbox') }}</option>
                            <option value="telegram_group_crawl">{{ t('customers.source_telegram_group_crawl') }}</option>
                            <option value="other">{{ t('customers.source_other') }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('customers.avatar') }}</th>
                            <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.name') }}</th>
                            <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('customers.company') }}</th>
                            <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('customers.industry') }}</th>
                            <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('sidebar.projects') }}</th>
                            <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.status') }}</th>
                            <th class="px-6 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="customer in customers.data" :key="customer.id">
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap">
                                <div class="relative inline-block">
                                    <!-- Avatar Image or Default Avatar -->
                                    <img
                                        v-if="customer.avatar"
                                        :src="`/storage/${customer.avatar}`"
                                        :alt="customer.name"
                                        class="w-12 h-12 rounded-full object-cover border-2 border-gray-200"
                                    />
                                    <div
                                        v-else
                                        class="w-12 h-12 rounded-full border-2 border-gray-200 flex items-center justify-center text-white font-semibold text-lg"
                                        :class="getAvatarClass(customer)"
                                    >
                                        {{ getAvatarInitials(customer) }}
                                    </div>
                                    
                                    <!-- Type Icon Overlay (bottom-right) -->
                                    <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-white border-2 border-gray-200 flex items-center justify-center shadow-sm">
                                        <svg
                                            v-if="customer.type === 'person'"
                                            class="w-3 h-3 text-blue-600"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                        </svg>
                                        <svg
                                            v-else
                                            class="w-3 h-3 text-purple-600"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ customer.name }}</div>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">{{ customer.company_name || t('common.dash') }}</div>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap">
                                <select
                                    v-model="customer.industry_id"
                                    @change="updateIndustry(customer)"
                                    class="text-xs px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[140px]"
                                    :style="{ backgroundColor: customer.industry ? customer.industry.color + '20' : '', color: customer.industry ? customer.industry.color : '' }"
                                >
                                    <option :value="null">{{ t('common.dash') }}</option>
                                    <optgroup v-for="p in industries" :key="p.id" :label="p.name">
                                        <option v-if="(p.children && p.children.length)" :value="p.id">{{ t('customers.all_this_category') }}</option>
                                        <option v-for="c in (p.children || [])" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        <option v-if="!(p.children || []).length" :value="p.id">{{ t('common.dash') }}</option>
                                    </optgroup>
                                </select>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ customer.project ? customer.project.name : t('common.dash') }}
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap">
                                <select
                                    v-model="customer.status"
                                    @change="updateStatus(customer)"
                                    class="text-xs px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': customer.status === 'lead',
                                        'bg-blue-100 text-blue-800': customer.status === 'prospect',
                                        'bg-green-100 text-green-800': customer.status === 'customer',
                                        'bg-gray-100 text-gray-800': customer.status === 'inactive',
                                    }"
                                >
                                    <option value="lead">{{ t('customers.lead') }}</option>
                                    <option value="prospect">{{ t('customers.prospect') }}</option>
                                    <option value="customer">{{ t('customers.customer') }}</option>
                                    <option value="inactive">{{ t('customers.inactive') }}</option>
                                </select>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <Link
                                    :href="route('customers.show', customer.id)"
                                    class="text-blue-600 hover:text-blue-900 ltr:mr-4 rtl:ml-4"
                                >
                                    {{ t('common.view') }}
                                </Link>
                                <Link
                                    :href="route('customers.edit', customer.id)"
                                    class="text-indigo-600 hover:text-indigo-900 ltr:mr-4 rtl:ml-4"
                                >
                                    {{ t('common.edit') }}
                                </Link>
                                <button
                                    @click="deleteCustomer(customer)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    {{ t('common.delete') }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-700">
                            {{ t('common.showing_range_of_results').replace(':from', customers.from || 0).replace(':to', customers.to || 0).replace(':total', customers.total || 0) }}
                        </div>
                        <div class="flex items-center space-x-2 rtl:space-x-reverse">
                            <!-- Previous Button -->
                            <Link
                                v-if="customers.prev_page_url"
                                :href="customers.prev_page_url"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ t('common.previous') }}
                            </Link>
                            <span
                                v-else
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 cursor-not-allowed opacity-50"
                            >
                                {{ t('common.previous') }}
                            </span>

                            <!-- Page Numbers -->
                            <div class="flex items-center space-x-1 rtl:space-x-reverse">
                                <span
                                    v-for="page in getPageNumbers()"
                                    :key="`page-${page}`"
                                >
                                    <Link
                                        v-if="page !== '...' && page !== customers.current_page"
                                        :href="getPageUrl(page)"
                                        class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                                    >
                                        {{ page }}
                                    </Link>
                                    <span
                                        v-else-if="page === '...'"
                                        class="px-3 py-2 text-sm text-gray-500"
                                    >
                                        {{ page }}
                                    </span>
                                    <span
                                        v-else
                                        class="px-3 py-2 border border-blue-500 bg-blue-50 rounded-md text-sm font-medium text-blue-700"
                                    >
                                        {{ page }}
                                    </span>
                                </span>
                            </div>

                            <!-- Next Button -->
                            <Link
                                v-if="customers.next_page_url"
                                :href="customers.next_page_url"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ t('common.next') }}
                            </Link>
                            <span
                                v-else
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 cursor-not-allowed opacity-50"
                            >
                                {{ t('common.next') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Import Modal -->
        <div v-if="showImportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">{{ t('customers.import_modal_title') }}</h3>
                    <button
                        @click="closeImportModal"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <div class="px-6 py-4 flex-1 overflow-y-auto">
                    <!-- Instructions -->
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <h4 class="font-medium text-blue-900 mb-2">{{ t('customers.import_csv_format_title') }}</h4>
                        <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                                    <li>{{ t('customers.import_csv_rule_utf8') }}</li>
                                    <li>{{ t('customers.import_csv_rule_required_columns') }}</li>
                                    <li>{{ t('customers.import_csv_rule_optional_columns') }}</li>
                                    <li>{{ t('customers.import_csv_rule_contacts') }}</li>
                                    <li>{{ t('customers.import_csv_rule_social_media') }}</li>
                        </ul>
                        <p class="text-xs text-blue-700 mt-3">
                                    <strong>{{ t('common.note') }}:</strong> {{ t('customers.import_csv_note_industry') }}
                        </p>
                    </div>

                    <!-- File Upload -->
                    <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('customers.import_choose_file_label') }}</label>
                        <input
                            type="file"
                            ref="fileInput"
                            @change="handleFileSelect"
                            accept=".csv,.xlsx,.xls"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                            <p class="mt-1 text-xs text-gray-500">{{ t('customers.import_supported_formats_help') }}</p>
                    </div>

                    <!-- Preview Table -->
                    <div v-if="isLoadingPreview" class="mb-4 p-4 bg-gray-50 rounded-lg text-center">
                            <p class="text-sm text-gray-600">{{ t('customers.import_reading_file') }}</p>
                    </div>
                    
                    <div v-if="previewData && previewData.success" class="mb-4">
                        <h4 class="font-medium text-gray-900 mb-2">
                                {{ t('customers.import_file_preview_title').replace(':shown', String(previewData.preview_rows)).replace(':total', String(previewData.total_rows)) }}
                        </h4>
                        <div class="overflow-x-auto border border-gray-300 rounded-lg max-h-96 overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 rtl:border-r-0 rtl:border-l">#</th>
                                        <th
                                            v-for="(header, index) in previewData.headers"
                                            :key="index"
                                            class="px-3 py-2 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200 rtl:border-r-0 rtl:border-l"
                                        >
                                            {{ header }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(row, rowIndex) in previewData.rows" :key="rowIndex" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 border-r border-gray-200 rtl:border-r-0 rtl:border-l">{{ rowIndex + 1 }}</td>
                                        <td
                                            v-for="(header, headerIndex) in previewData.headers"
                                            :key="headerIndex"
                                            class="px-3 py-2 text-xs text-gray-900 border-r border-gray-200 rtl:border-r-0 rtl:border-l"
                                        >
                                            {{ row[header] || t('common.dash') }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Preview/Results -->
                    <div v-if="importResult" class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-3">{{ t('customers.import_results_title') }}</h4>
                        <div class="space-y-2">
                            <div v-if="importResult.success > 0" class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">
                                    <strong>{{ t('common.success') }}:</strong>
                                    {{ t('customers.import_success_count').replace(':count', String(importResult.success)) }}
                                </p>
                            </div>
                            <div v-if="importResult.failed > 0" class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-sm text-red-800">
                                    <strong>{{ t('common.failed') }}:</strong>
                                    {{ t('customers.import_failed_count').replace(':count', String(importResult.failed)) }}
                                </p>
                            </div>
                            <div v-if="importResult.errors && importResult.errors.length > 0" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <p class="text-sm text-yellow-800 font-medium mb-2">{{ t('customers.import_errors_title') }}</p>
                                <ul class="text-xs text-yellow-700 space-y-1 list-disc list-inside max-h-40 overflow-y-auto">
                                    <li v-for="(error, index) in importResult.errors" :key="index">{{ error }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3 rtl:space-x-reverse">
                    <button
                        @click="closeImportModal"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                    >
                        {{ t('common.cancel') }}
                    </button>
                    <button
                        @click="importCustomers"
                        :disabled="!selectedFile || importForm.processing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ importForm.processing ? t('customers.import_processing') : t('customers.import') }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import IndustrySelect from '@/Components/IndustrySelect.vue';
import { debounce } from 'lodash-es';
import { useI18n } from '@/composables/useI18n';

const props = defineProps({
    customers: Object,
    industries: Array,
    projects: Array,
    filters: Object,
});

const showImportModal = ref(false);
const selectedFile = ref(null);
const fileInput = ref(null);
const importResult = ref(null);
const previewData = ref(null);
const isLoadingPreview = ref(false);

const importForm = useForm({
    file: null,
});

const page = usePage();
const { t } = useI18n();

const filters = ref({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    industry_id: props.filters?.industry_id || '',
    project_id: props.filters?.project_id || '',
    status: props.filters?.status || '',
    source: props.filters?.source || '',
});

const applyFilters = debounce(() => {
    router.get(route('customers.index'), filters.value, {
        preserveState: true,
        replace: true,
    });
}, 300);

const deleteCustomer = (customer) => {
    const msg = t('common.confirm_delete_named').replace(':name', customer.name || '');
    if (confirm(msg)) {
        router.delete(route('customers.destroy', customer.id));
    }
};

const updateStatus = (customer) => {
    router.patch(route('customers.quick-update', customer.id), {
        status: customer.status,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const handleFileSelect = async (event) => {
    const file = event.target.files[0];
    if (file) {
        selectedFile.value = file;
        importForm.file = file;
        importResult.value = null;
        previewData.value = null;
        
        // Load preview
        await loadPreview(file);
    }
};

const loadPreview = async (file) => {
    isLoadingPreview.value = true;
    const formData = new FormData();
    formData.append('file', file);
    
    try {
        // Get CSRF token from Inertia props
        const csrfToken = page.props.csrf_token || '';
        
        const response = await fetch(route('customers.import-preview'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: formData,
        });
        
        const data = await response.json();
        if (data.success) {
            previewData.value = data;
        } else {
            alert(
                t('customers.import_preview_read_error')
                    .replace(':message', data.message || t('common.unknown_error'))
            );
        }
    } catch (error) {
        console.error('Preview error:', error);
        alert(
            t('customers.import_preview_load_error')
                .replace(':message', error.message || t('common.unknown_error'))
        );
    } finally {
        isLoadingPreview.value = false;
    }
};

const importCustomers = () => {
    if (!selectedFile.value) {
        alert(t('customers.import_select_file_first'));
        return;
    }

        importForm.post(route('customers.import'), {
        preserveState: false, // Don't preserve state to ensure flash messages are loaded
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (page) => {
            console.log('Import success response:', page.props.flash);
            // Check for import result
            if (page.props.flash?.import_result) {
                importResult.value = page.props.flash.import_result;
                console.log('Import result:', importResult.value);
                if (importResult.value.success > 0) {
                    // Reload customers list after successful import
                    setTimeout(() => {
                        router.reload({ only: ['customers'] });
                    }, 2000);
                }
            } else if (page.props.flash?.error) {
                // Show error message
                importResult.value = {
                    success: 0,
                    failed: 0,
                    errors: [page.props.flash.error],
                };
            } else {
                // No result, show generic message with debug info
                console.warn('No import_result in flash:', page.props.flash);
                importResult.value = {
                    success: 0,
                    failed: 0,
                    errors: [t('customers.import_unknown_error_check_logs')],
                };
            }
        },
        onError: (errors) => {
            console.error('Import errors:', errors);
            importResult.value = {
                success: 0,
                failed: 0,
                errors: Object.values(errors).flat() || [t('customers.import_upload_error')],
            };
        },
    });
};

const closeImportModal = () => {
    showImportModal.value = false;
    selectedFile.value = null;
    importResult.value = null;
    previewData.value = null;
    isLoadingPreview.value = false;
    importForm.reset();
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const updateIndustry = (customer) => {
    router.patch(route('customers.quick-update', customer.id), {
        industry_id: customer.industry_id,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Avatar helper functions
const getAvatarClass = (customer) => {
    if (customer.type === 'company') {
        return 'bg-purple-500';
    }
    
    if (customer.gender === 'male') {
        return 'bg-blue-500';
    } else if (customer.gender === 'female') {
        return 'bg-pink-500';
    }
    
    // Default for person without gender
    return 'bg-gray-400';
};

const getAvatarInitials = (customer) => {
    if (customer.type === 'company' && customer.company_name) {
        return customer.company_name.substring(0, 2).toUpperCase();
    }
    if (customer.name) {
        const names = customer.name.trim().split(' ');
        if (names.length >= 2) {
            return (names[0].charAt(0) + names[names.length - 1].charAt(0)).toUpperCase();
        }
        return customer.name.substring(0, 2).toUpperCase();
    }
    return '??';
};

// Pagination helpers
const getPageNumbers = () => {
    const customersData = props.customers;
    if (!customersData || !customersData.last_page) {
        return [];
    }

    const current = customersData.current_page;
    const last = customersData.last_page;
    const pages = [];

    if (last <= 7) {
        // Show all pages if 7 or fewer
        for (let i = 1; i <= last; i++) {
            pages.push(i);
        }
    } else {
        // Always show first page
        pages.push(1);

        if (current > 3) {
            pages.push('...');
        }

        // Show pages around current
        const start = Math.max(2, current - 1);
        const end = Math.min(last - 1, current + 1);

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }

        if (current < last - 2) {
            pages.push('...');
        }

        // Always show last page
        pages.push(last);
    }

    return pages;
};

const getPageUrl = (page) => {
    const customersData = props.customers;
    if (!customersData || !customersData.first_page_url) {
        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        return url.pathname + url.search;
    }
    
    const url = new URL(customersData.first_page_url);
    url.searchParams.set('page', page);
    return url.pathname + url.search;
};
</script>
