<template>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 flex flex-col items-center justify-center px-4 py-10 sm:py-12">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl" />
            <div class="absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-indigo-500/15 blur-3xl" />
        </div>

        <div class="relative z-10 w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    {{ t('front.auth_forgot_password_title') }}
                </h1>
                <p class="mt-2 text-sm text-blue-100/80">
                    {{ t('front.auth_forgot_password_subtitle') }}
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/95 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <form class="space-y-5" @submit.prevent="submit">
                    <div>
                        <label for="fp-email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ t('front.auth_email') }}
                        </label>
                        <input
                            id="fp-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">
                            {{ form.errors.email }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center rounded-xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? t('front.verify_email_resending') : t('front.auth_send_reset_link') }}
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">
                    <Link href="/login" class="font-semibold text-blue-600 hover:text-blue-700">{{ t('front.auth_back_to_login') }}</Link>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from '@/composables/useI18n';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const { t } = useI18n();
const page = usePage();

const form = useForm({
    email: typeof page.props.email === 'string' ? page.props.email : '',
});

function submit() {
    form.post('/forgot-password');
}
</script>
