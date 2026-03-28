<template>
    <AppLayout>
        <template #header>
            <h2 class="text-lg font-semibold text-gray-900">{{ t('superadmin.platform_notifications') }}</h2>
        </template>

        <div class="max-w-3xl space-y-6">
            <p v-if="envOwnerEmailsHint" class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                {{ t('superadmin.platform_notifications.env_hint') }}: <code class="text-xs">{{ envOwnerEmailsHint }}</code>
            </p>

            <form class="bg-white border border-gray-200 rounded-lg p-6 space-y-6" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('superadmin.platform_notifications.owner_emails') }}</label>
                    <textarea
                        v-model="ownerEmailsText"
                        rows="4"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        :placeholder="t('superadmin.platform_notifications.owner_emails_placeholder')"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('superadmin.platform_notifications.owner_emails_help') }}</p>
                </div>

                <div class="space-y-3">
                    <label class="flex items-center gap-2">
                        <input v-model="form.email_user_welcome" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                        <span class="text-sm text-gray-800">{{ t('superadmin.platform_notifications.email_user_welcome') }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="form.email_owner_new_registration" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                        <span class="text-sm text-gray-800">{{ t('superadmin.platform_notifications.email_owner_new_registration') }}</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input v-model="form.email_org_subscription_reminder" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                        <span class="text-sm text-gray-800">{{ t('superadmin.platform_notifications.email_org_subscription_reminder') }}</span>
                    </label>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('superadmin.platform_notifications.reminder_days') }}</label>
                    <input
                        v-model="reminderDaysText"
                        type="text"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                        placeholder="7, 3, 1"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('superadmin.platform_notifications.reminder_days_help') }}</p>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                    >
                        {{ form.processing ? t('common.saving') : t('common.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/composables/useI18n';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
    envOwnerEmailsHint: { type: String, default: '' },
});

const { t } = useI18n();

const ownerEmailsText = ref((props.settings.owner_emails || []).join('\n'));
const reminderDaysText = ref((props.settings.subscription_reminder_days || []).join(', '));

const form = useForm({
    owner_emails: props.settings.owner_emails || [],
    email_user_welcome: !!props.settings.email_user_welcome,
    email_owner_new_registration: !!props.settings.email_owner_new_registration,
    email_org_subscription_reminder: !!props.settings.email_org_subscription_reminder,
    subscription_reminder_days: props.settings.subscription_reminder_days || [7, 3, 1],
});

watch(ownerEmailsText, (v) => {
    const lines = String(v || '')
        .split(/[\n,;]+/)
        .map((s) => s.trim())
        .filter(Boolean);
    form.owner_emails = lines;
});

watch(reminderDaysText, (v) => {
    const parts = String(v || '')
        .split(/[,;\s]+/)
        .map((s) => s.trim())
        .filter(Boolean);
    const nums = parts.map((p) => parseInt(p, 10)).filter((n) => !Number.isNaN(n) && n >= 0);
    form.subscription_reminder_days = nums.length ? nums : [7, 3, 1];
});

function submit() {
    form.put('/superadmin/platform-notifications', { preserveScroll: true });
}
</script>
