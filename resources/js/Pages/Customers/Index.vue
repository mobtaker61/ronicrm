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
                <Link
                    :href="route('customers.create')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap"
                >
                    Add Customer
                </Link>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-lg shadow p-4 md:p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
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
                        <select
                            v-model="filters.industry_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Industries</option>
                            <option v-for="industry in industries" :key="industry.id" :value="industry.id">
                                {{ industry.name }}
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
                                    class="text-xs px-2 py-1 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    :style="{ backgroundColor: customer.industry ? customer.industry.color + '20' : '', color: customer.industry ? customer.industry.color : '' }"
                                >
                                    <option :value="null">-</option>
                                    <option v-for="industry in industries" :key="industry.id" :value="industry.id">
                                        {{ industry.name }}
                                    </option>
                                </select>
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
    </AppLayout>
</template>

<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { debounce } from 'lodash-es';

const props = defineProps({
    customers: Object,
    industries: Array,
    filters: Object,
});

const filters = ref({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    industry_id: props.filters?.industry_id || '',
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
