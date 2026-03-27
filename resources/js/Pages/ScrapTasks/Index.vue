<template>
    <AppLayout>
        <template #header>
            {{ t('scrap_tasks.web_scraping_tasks') }}
        </template>

        <div class="space-y-6">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">{{ t('scrap_tasks.web_scraping') }}</h2>
                <Link
                    :href="route('scrap-tasks.create')"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    {{ t('scrap_tasks.new_task') }}
                </Link>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.name') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('customers.type') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.status') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('scrap_tasks.urls') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('scrap_tasks.fields_selector') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.created') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="task in tasks.data" :key="task.id">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900">{{ task.name }}</div>
                                <div v-if="task.description" class="text-sm text-gray-500">{{ task.description }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-violet-100 text-violet-800': task.type === 'list',
                                        'bg-slate-100 text-slate-800': task.type === 'detail',
                                    }"
                                >
                                    {{ task.type === 'list' ? t('scrap_tasks.list') : t('scrap_tasks.detail') }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-gray-100 text-gray-800': task.status === 'draft',
                                        'bg-blue-100 text-blue-800': task.status === 'running',
                                        'bg-green-100 text-green-800': task.status === 'completed',
                                        'bg-red-100 text-red-800': task.status === 'failed',
                                    }"
                                >
                                    {{ statusLabel(task.status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ task.urls?.length ?? 0 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <template v-if="task.type === 'list'">{{ t('scrap_tasks.list_selector') }}</template>
                                <template v-else>{{ t('scrap_tasks.n_fields').replace(':count', String(task.extract_params?.length ?? 0)) }}</template>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ new Date(task.created_at).toLocaleDateString('en-US') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        :href="route('scrap-tasks.show', task.id)"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        {{ t('scrap_tasks.view_report') }}
                                    </Link>
                                    <Link
                                        :href="route('scrap-tasks.edit', task.id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ t('common.edit') }}
                                    </Link>
                                    <button
                                        v-if="['draft', 'failed', 'completed'].includes(task.status)"
                                        type="button"
                                        @click="runTask(task)"
                                        class="text-green-600 hover:text-green-900"
                                    >
                                        {{ task.status === 'completed' ? t('scrap_tasks.re_run') : t('scrap_tasks.run') }}
                                    </button>
                                    <button
                                        v-if="task.status === 'running'"
                                        type="button"
                                        @click="resetTask(task)"
                                        class="text-amber-600 hover:text-amber-900"
                                    >
                                        {{ t('scrap_tasks.reset') }}
                                    </button>
                                    <button
                                        v-if="task.status === 'draft'"
                                        type="button"
                                        @click="deleteTask(task)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="tasks.data?.length === 0" class="p-8 text-center text-gray-500">
                    {{ t('scrap_tasks.no_tasks_yet') }}
                </div>

                <div v-if="tasks.data?.length > 0" class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-700">
                            {{ tasks.from }} to {{ tasks.to }} of {{ tasks.total }}
                        </div>
                        <div class="flex space-x-2">
                            <Link
                                v-if="tasks.prev_page_url"
                                :href="tasks.prev_page_url"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                {{ t('common.previous') }}
                            </Link>
                            <Link
                                v-if="tasks.next_page_url"
                                :href="tasks.next_page_url"
                                class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                {{ t('common.next') }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

defineProps({
    tasks: Object,
});

const statusLabel = (status) => {
    const map = {
        draft: t('scrap_tasks.draft'),
        running: t('scrap_tasks.running'),
        completed: t('scrap_tasks.completed'),
        failed: t('scrap_tasks.failed'),
    };
    return map[status] ?? status;
};

const runTask = (task) => {
    if (confirm(t('scrap_tasks.confirm_start').replace(':name', task.name))) {
        router.post(route('scrap-tasks.run', task.id));
    }
};

const resetTask = (task) => {
    if (confirm(t('scrap_tasks.confirm_reset').replace(':name', task.name))) {
        router.post(route('scrap-tasks.reset', task.id));
    }
};

const deleteTask = (task) => {
    if (confirm(t('scrap_tasks.confirm_delete').replace(':name', task.name))) {
        router.delete(route('scrap-tasks.destroy', task.id));
    }
};
</script>
