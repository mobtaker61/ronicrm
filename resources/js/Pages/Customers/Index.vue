<template>
    <AppLayout>
        <template #header>
            Customers
        </template>

        <div class="space-y-6">
            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <!-- Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <h2 class="text-2xl font-bold text-gray-900">Customers</h2>
                <div class="flex gap-2">
                    <button
                        @click="showImportModal = true"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors whitespace-nowrap flex items-center space-x-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span>Import Customers</span>
                    </button>
                    <Link
                        :href="route('customers.create')"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap"
                    >
                        Add Customer
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4 md:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Search customers..."
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="applyFilters"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                        <select
                            v-model="filters.type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Types</option>
                            <option value="person">Person</option>
                            <option value="company">Company</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Industry</label>
                        <IndustrySelect
                            v-model="filters.industry_id"
                            :industries="industries"
                            placeholder="All industries"
                            @update:model-value="applyFilters"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project</label>
                        <select
                            v-model="filters.project_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Projects</option>
                            <option v-for="project in projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select
                            v-model="filters.status"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Statuses</option>
                            <option value="lead">Lead</option>
                            <option value="prospect">Prospect</option>
                            <option value="customer">Customer</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Source</label>
                        <select
                            v-model="filters.source"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Sources</option>
                            <option value="website">Website</option>
                            <option value="referral">Referral</option>
                            <option value="advertisement">Advertisement</option>
                            <option value="social_media">Social Media</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avatar</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Industry</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                <div class="text-sm text-gray-500">{{ customer.company_name || '-' }}</div>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap">
                                <select
                                    v-model="customer.industry_id"
                                    @change="updateIndustry(customer)"
                                    class="text-xs px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[140px]"
                                    :style="{ backgroundColor: customer.industry ? customer.industry.color + '20' : '', color: customer.industry ? customer.industry.color : '' }"
                                >
                                    <option :value="null">-</option>
                                    <optgroup v-for="p in industries" :key="p.id" :label="p.name">
                                        <option v-if="(p.children && p.children.length)" :value="p.id">— All this category —</option>
                                        <option v-for="c in (p.children || [])" :key="c.id" :value="c.id">{{ c.name }}</option>
                                        <option v-if="!(p.children || []).length" :value="p.id">—</option>
                                    </optgroup>
                                </select>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ customer.project ? customer.project.name : '-' }}
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
                                    <option value="lead">Lead</option>
                                    <option value="prospect">Prospect</option>
                                    <option value="customer">Customer</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </td>
                            <td class="px-3 md:px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <Link
                                    :href="route('customers.show', customer.id)"
                                    class="text-blue-600 hover:text-blue-900 mr-4"
                                >
                                    View
                                </Link>
                                <Link
                                    :href="route('customers.edit', customer.id)"
                                    class="text-indigo-600 hover:text-indigo-900 mr-4"
                                >
                                    Edit
                                </Link>
                                <button
                                    @click="deleteCustomer(customer)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-sm text-gray-700">
                            Showing {{ customers.from || 0 }} to {{ customers.to || 0 }} of {{ customers.total || 0 }} results
                        </div>
                        <div class="flex items-center space-x-2">
                            <!-- Previous Button -->
                            <Link
                                v-if="customers.prev_page_url"
                                :href="customers.prev_page_url"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Previous
                            </Link>
                            <span
                                v-else
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 cursor-not-allowed opacity-50"
                            >
                                Previous
                            </span>

                            <!-- Page Numbers -->
                            <div class="flex items-center space-x-1">
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
                                Next
                            </Link>
                            <span
                                v-else
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-400 cursor-not-allowed opacity-50"
                            >
                                Next
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
                    <h3 class="text-lg font-semibold text-gray-900">Import مخاطبان از فایل</h3>
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
                        <h4 class="font-medium text-blue-900 mb-2">فرمت فایل CSV:</h4>
                        <ul class="text-sm text-blue-800 space-y-1 list-disc list-inside">
                            <li>فایل باید فرمت CSV باشد (با encoding UTF-8)</li>
                            <li>ستون‌های اجباری: name, type (person/company)</li>
                            <li>ستون‌های اختیاری: company_name, email, phone, address, industry_name, status, source, gender, language, contact_person, notes</li>
                            <li>برای contacts: phone, email, whatsapp, telegram (مثال: phone:09123456789)</li>
                            <li>برای social_media: instagram, telegram, linkedin (مثال: instagram:username)</li>
                        </ul>
                        <p class="text-xs text-blue-700 mt-3">
                            <strong>نکته:</strong> اگر industry_name در فایل باشد، سیستم به صورت خودکار industry را پیدا می‌کند یا ایجاد می‌کند.
                        </p>
                    </div>

                    <!-- File Upload -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">انتخاب فایل CSV یا Excel</label>
                        <input
                            type="file"
                            ref="fileInput"
                            @change="handleFileSelect"
                            accept=".csv,.xlsx,.xls"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p class="mt-1 text-xs text-gray-500">فرمت‌های پشتیبانی شده: CSV, Excel (.xlsx, .xls)</p>
                    </div>

                    <!-- Preview Table -->
                    <div v-if="isLoadingPreview" class="mb-4 p-4 bg-gray-50 rounded-lg text-center">
                        <p class="text-sm text-gray-600">در حال خواندن فایل...</p>
                    </div>
                    
                    <div v-if="previewData && previewData.success" class="mb-4">
                        <h4 class="font-medium text-gray-900 mb-2">
                            پیش‌نمایش فایل ({{ previewData.preview_rows }} از {{ previewData.total_rows }} ردیف)
                        </h4>
                        <div class="overflow-x-auto border border-gray-300 rounded-lg max-h-96 overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200">#</th>
                                        <th
                                            v-for="(header, index) in previewData.headers"
                                            :key="index"
                                            class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r border-gray-200"
                                        >
                                            {{ header }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="(row, rowIndex) in previewData.rows" :key="rowIndex" class="hover:bg-gray-50">
                                        <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500 border-r border-gray-200">{{ rowIndex + 1 }}</td>
                                        <td
                                            v-for="(header, headerIndex) in previewData.headers"
                                            :key="headerIndex"
                                            class="px-3 py-2 text-xs text-gray-900 border-r border-gray-200"
                                        >
                                            {{ row[header] || '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Preview/Results -->
                    <div v-if="importResult" class="mt-6">
                        <h4 class="font-medium text-gray-900 mb-3">نتایج Import:</h4>
                        <div class="space-y-2">
                            <div v-if="importResult.success > 0" class="p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p class="text-sm text-green-800">
                                    <strong>موفق:</strong> {{ importResult.success }} مخاطب با موفقیت وارد شد
                                </p>
                            </div>
                            <div v-if="importResult.failed > 0" class="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-sm text-red-800">
                                    <strong>ناموفق:</strong> {{ importResult.failed }} مخاطب با خطا مواجه شد
                                </p>
                            </div>
                            <div v-if="importResult.errors && importResult.errors.length > 0" class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                                <p class="text-sm text-yellow-800 font-medium mb-2">خطاها:</p>
                                <ul class="text-xs text-yellow-700 space-y-1 list-disc list-inside max-h-40 overflow-y-auto">
                                    <li v-for="(error, index) in importResult.errors" :key="index">{{ error }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button
                        @click="closeImportModal"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50"
                    >
                        انصراف
                    </button>
                    <button
                        @click="importCustomers"
                        :disabled="!selectedFile || importForm.processing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ importForm.processing ? 'در حال Import...' : 'Import' }}
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
    if (confirm('Are you sure you want to delete this customer?')) {
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
            alert('خطا در خواندن فایل: ' + (data.message || 'خطای ناشناخته'));
        }
    } catch (error) {
        console.error('Preview error:', error);
        alert('خطا در بارگذاری preview: ' + error.message);
    } finally {
        isLoadingPreview.value = false;
    }
};

const importCustomers = () => {
    if (!selectedFile.value) {
        alert('لطفا ابتدا یک فایل انتخاب کنید');
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
                    errors: ['خطای ناشناخته در import - لطفا لاگ‌ها را بررسی کنید'],
                };
            }
        },
        onError: (errors) => {
            console.error('Import errors:', errors);
            importResult.value = {
                success: 0,
                failed: 0,
                errors: Object.values(errors).flat() || ['خطا در ارسال فایل'],
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
