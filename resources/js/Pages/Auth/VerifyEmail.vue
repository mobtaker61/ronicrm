<template>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 flex flex-col items-center justify-center px-4 py-10 sm:py-12">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl" />
            <div class="absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-indigo-500/15 blur-3xl" />
        </div>

        <div class="relative z-10 w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    {{ t('front.verify_email_title') }}
                </h1>
                <p class="mt-2 text-sm text-blue-100/80">
                    {{ t('front.verify_email_subtitle') }}
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/95 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <p class="text-sm text-slate-600 mb-4">
                    {{ t('front.verify_email_sent_to') }}
                    <span class="font-semibold text-slate-900">{{ email }}</span>
                </p>
                <p class="text-sm text-slate-600 mb-6">
                    {{ t('front.verify_email_inbox_hint') }}
                </p>

                <form @submit.prevent="resend">
                    <button
                        type="submit"
                        :disabled="resendForm.processing"
                        class="flex w-full items-center justify-center rounded-xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ resendForm.processing ? t('front.verify_email_resending') : t('front.verify_email_resend') }}
                    </button>
                </form>

                <form class="mt-4" @submit.prevent="logout">
                    <button
                        type="submit"
                        class="w-full text-center text-sm font-medium text-slate-500 hover:text-slate-800"
                    >
                        {{ t('front.verify_email_logout') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from '@/composables/useI18n';
import { useForm, router } from '@inertiajs/vue3';

defineProps({
    email: { type: String, default: '' },
});

const { t } = useI18n();

const resendForm = useForm({});

function resend() {
    resendForm.post('/email/verification-notification');
}

function logout() {
    router.post('/logout');
}
</script>
