<template>
    <AppLayout>
        <template #header>
            {{ t('industries.management') }}
        </template>

        <div class="space-y-6">
            <!-- Success/Error Messages -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <!-- Header -->
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">{{ t('sidebar.industries') }}</h2>
                <button
                    @click="showCreateModal = true; form.parent_id = null"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    {{ t('industries.add_industry') }}
                </button>
            </div>

            <!-- Industries List (Hierarchical) -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="p-6">
                    <div v-for="industry in industries" :key="industry.id" class="mb-4">
                        <!-- Parent Industry -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg mb-2">
                            <div class="flex items-center space-x-4 flex-1">
                                <div
                                    class="w-6 h-6 rounded-full border border-gray-300 flex-shrink-0"
                                    :style="{ backgroundColor: industry.color }"
                                ></div>
                                <div class="flex-1">
                                    <div class="font-medium text-gray-900">{{ industry.name }}</div>
                                    <div v-if="industry.description" class="text-sm text-gray-500 mt-1">
                                        {{ industry.description }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button
                                    @click="addChild(industry)"
                                    class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800 border border-blue-300 rounded hover:bg-blue-50"
                                    :title="t('industries.add_sub_industry')"
                                >
                                    {{ t('industries.sub') }}
                                </button>
                                <button
                                    @click="editIndustry(industry)"
                                    class="px-3 py-1 text-sm text-blue-600 hover:text-blue-800"
                                >
                                    {{ t('common.edit') }}
                                </button>
                                <button
                                    @click="deleteIndustry(industry)"
                                    class="px-3 py-1 text-sm text-red-600 hover:text-red-800"
                                >
                                    {{ t('common.delete') }}
                                </button>
                            </div>
                        </div>

                        <!-- Children Industries -->
                        <div v-if="industry.children && industry.children.length > 0" class="ml-8 space-y-2">
                            <div
                                v-for="child in industry.children"
                                :key="child.id"
                                class="flex items-center justify-between p-3 bg-white border border-gray-200 rounded-lg"
                            >
                                <div class="flex items-center space-x-3 flex-1">
                                    <div class="w-4 h-4 border-l-2 border-b-2 border-gray-300 -ml-2 mb-2"></div>
                                    <div
                                        class="w-5 h-5 rounded-full border border-gray-300 flex-shrink-0"
                                        :style="{ backgroundColor: child.color }"
                                    ></div>
                                    <div class="flex-1">
                                        <div class="text-sm font-medium text-gray-900">{{ child.name }}</div>
                                        <div v-if="child.description" class="text-xs text-gray-500 mt-1">
                                            {{ child.description }}
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <button
                                        @click="editIndustry(child)"
                                        class="px-2 py-1 text-xs text-blue-600 hover:text-blue-800"
                                    >
                                        {{ t('common.edit') }}
                                    </button>
                                    <button
                                        @click="deleteIndustry(child)"
                                        class="px-2 py-1 text-xs text-red-600 hover:text-red-800"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="industries.length === 0" class="text-center py-8 text-gray-500">
                        {{ t('industries.empty') }}
                    </div>
                </div>
            </div>

            <!-- Create/Edit Modal -->
            <div
                v-if="showCreateModal || editingIndustry"
                class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
                @click.self="closeModal"
            >
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        {{ editingIndustry ? t('industries.edit_industry') : t('industries.create_industry') }}
                    </h3>
                    <form @submit.prevent="saveIndustry">
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('industries.parent_optional') }}</label>
                            <select
                                v-model="form.parent_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="null">{{ t('industries.none_top_level') }}</option>
                                <option
                                    v-for="parent in availableParents"
                                    :key="parent.id"
                                    :value="parent.id"
                                >
                                    {{ parent.full_path || parent.name }}
                                </option>
                            </select>
                            <div v-if="form.errors.parent_id" class="mt-1 text-sm text-red-600">
                                {{ form.errors.parent_id }}
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.name') }}</label>
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

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.color') }}</label>
                            <input
                                v-model="form.color"
                                type="color"
                                required
                                class="w-full h-10 border border-gray-300 rounded-md"
                            />
                            <div v-if="form.errors.color" class="mt-1 text-sm text-red-600">
                                {{ form.errors.color }}
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.description') }}</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            ></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.sort_order') }}</label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <p class="mt-1 text-xs text-gray-500">{{ t('industries.lower_numbers_first') }}</p>
                        </div>

                        <div class="flex justify-end space-x-3">
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
                                {{ form.processing ? t('common.saving') : t('common.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    industries: Array,
    allIndustries: Array,
});

const showCreateModal = ref(false);
const editingIndustry = ref(null);

const form = useForm({
    name: '',
    description: '',
    color: '#3B82F6',
    parent_id: null,
    sort_order: 0,
});

const availableParents = computed(() => {
    if (!props.allIndustries) return [];
    
    if (editingIndustry.value) {
        // Exclude current industry and its descendants
        return props.allIndustries.filter(industry => {
            if (industry.id === editingIndustry.value.id) return false;
            // Simple check - in a real app, you'd want to check all descendants
            return true;
        });
    }
    
    return props.allIndustries;
});

const addChild = (parent) => {
    showCreateModal.value = true;
    form.reset();
    form.parent_id = parent.id;
    form.color = parent.color; // Inherit color from parent
};

const editIndustry = (industry) => {
    editingIndustry.value = industry;
    form.name = industry.name;
    form.description = industry.description || '';
    form.color = industry.color;
    form.parent_id = industry.parent_id || null;
    form.sort_order = industry.sort_order || 0;
};

const deleteIndustry = (industry) => {
    if (confirm(t('industries.confirm_delete'))) {
        router.delete(route('industries.destroy', industry.id));
    }
};

const saveIndustry = () => {
    if (editingIndustry.value) {
        form.put(route('industries.update', editingIndustry.value.id), {
            onSuccess: () => {
                closeModal();
            },
        });
    } else {
        form.post(route('industries.store'), {
            onSuccess: () => {
                closeModal();
            },
        });
    }
};

const closeModal = () => {
    showCreateModal.value = false;
    editingIndustry.value = null;
    form.reset();
    form.color = '#3B82F6';
    form.sort_order = 0;
};
</script>
