<template>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 flex flex-col items-center justify-center px-4 py-10 sm:py-12">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl" />
            <div class="absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-indigo-500/15 blur-3xl" />
        </div>

        <div class="relative z-10 w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    {{ t('front.auth_reset_password_title') }}
                </h1>
                <p class="mt-2 text-sm text-blue-100/80">
                    {{ t('front.auth_reset_password_subtitle') }}
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/95 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                <form class="space-y-5" @submit.prevent="submit">
                    <input type="hidden" name="token" :value="token" />
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('auth.email') }}</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                        <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('front.auth_password') }}</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                        <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600">{{ form.errors.password }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ t('front.auth_password_confirmation') }}</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex w-full items-center justify-center rounded-xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ form.processing ? t('auth.saving') : t('auth.set_new_password') }}
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
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const { t } = useI18n();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>
