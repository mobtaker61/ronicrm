<template>
    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950 flex flex-col items-center justify-center px-4 py-10 sm:py-12">
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-blue-500/20 blur-3xl" />
            <div class="absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-indigo-500/15 blur-3xl" />
        </div>

        <div class="relative z-10 w-full max-w-md">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-lg shadow-black/20 ring-1 ring-white/20">
                    <img
                        src="/brand/logo-icon-512px.png"
                        alt=""
                        class="h-12 w-12 object-contain"
                    />
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                    {{ activeTab === 'login' ? t('auth.welcome_back') : t('auth.tab_register') }}
                </h1>
                <p class="mt-2 text-sm text-blue-100/80">
                    {{ activeTab === 'login' ? t('auth.sign_in_subtitle') : t('auth.register_subtitle') }}
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/95 p-6 shadow-2xl shadow-black/20 backdrop-blur sm:p-8">
                <div class="mb-6 flex rounded-xl bg-slate-100/90 p-1">
                    <button
                        type="button"
                        class="flex-1 rounded-lg py-2.5 text-sm font-semibold transition-colors"
                        :class="activeTab === 'login' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="setTab('login')"
                    >
                        {{ t('auth.tab_login') }}
                    </button>
                    <button
                        type="button"
                        class="flex-1 rounded-lg py-2.5 text-sm font-semibold transition-colors"
                        :class="activeTab === 'register' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                        @click="setTab('register')"
                    >
                        {{ t('auth.tab_register') }}
                    </button>
                </div>

                <!-- Login -->
                <div v-show="activeTab === 'login'">
                    <div
                        v-if="$page.props.flash?.success"
                        class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                    >
                        {{ $page.props.flash.success }}
                    </div>
                    <form class="space-y-5" @submit.prevent="submitLogin">
                        <div>
                            <label for="login-email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.email_or_username') }}
                            </label>
                            <input
                                id="login-email"
                                v-model="loginForm.email"
                                type="text"
                                autocomplete="username"
                                required
                                class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                                :placeholder="t('auth.email_or_username')"
                            />
                            <p v-if="loginForm.errors.email" class="mt-1.5 text-sm text-red-600">
                                {{ loginForm.errors.email }}
                            </p>
                        </div>
                        <div>
                            <label for="login-password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.password') }}
                            </label>
                            <input
                                id="login-password"
                                v-model="loginForm.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <p v-if="loginForm.errors.password" class="mt-1.5 text-sm text-red-600">
                                {{ loginForm.errors.password }}
                            </p>
                            <p class="mt-2 text-right text-sm">
                                <a href="/forgot-password" class="font-medium text-blue-600 hover:text-blue-700">{{ t('front.auth_forgot_password_link') }}</a>
                            </p>
                        </div>
                        <div class="flex items-center">
                            <input
                                id="remember"
                                v-model="loginForm.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <label for="remember" class="ms-2 text-sm text-slate-600">
                                {{ t('auth.remember_me') }}
                            </label>
                        </div>
                        <button
                            type="submit"
                            :disabled="loginForm.processing"
                            class="flex w-full items-center justify-center rounded-xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            <span v-if="loginForm.processing">{{ t('auth.signing_in') }}</span>
                            <span v-else>{{ t('auth.sign_in') }}</span>
                        </button>
                    </form>
                    <p class="mt-6 text-center text-sm text-slate-500">
                        {{ t('auth.need_organization') }}
                        <button type="button" class="font-semibold text-blue-600 hover:text-blue-700" @click="setTab('register')">
                            {{ t('auth.tab_register') }}
                        </button>
                    </p>
                </div>

                <!-- Register organization -->
                <div v-show="activeTab === 'register'">
                    <form class="space-y-4" @submit.prevent="submitRegister">
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.organization_name') }}
                            </label>
                            <input
                                v-model="registerForm.organization_name"
                                type="text"
                                required
                                autocomplete="organization"
                                class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <p v-if="registerForm.errors.organization_name" class="mt-1.5 text-sm text-red-600">
                                {{ registerForm.errors.organization_name }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.owner_full_name') }}
                            </label>
                            <input
                                v-model="registerForm.name"
                                type="text"
                                required
                                autocomplete="name"
                                class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <p v-if="registerForm.errors.name" class="mt-1.5 text-sm text-red-600">
                                {{ registerForm.errors.name }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.username') }}
                            </label>
                            <input
                                v-model="registerForm.username"
                                type="text"
                                required
                                autocomplete="username"
                                class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <p v-if="registerForm.errors.username" class="mt-1.5 text-sm text-red-600">
                                {{ registerForm.errors.username }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.register_email') }}
                            </label>
                            <input
                                v-model="registerForm.email"
                                type="email"
                                required
                                autocomplete="email"
                                class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <p v-if="registerForm.errors.email" class="mt-1.5 text-sm text-red-600">
                                {{ registerForm.errors.email }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.password') }}
                            </label>
                            <input
                                v-model="registerForm.password"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                            <p v-if="registerForm.errors.password" class="mt-1.5 text-sm text-red-600">
                                {{ registerForm.errors.password }}
                            </p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                {{ t('auth.password_confirmation') }}
                            </label>
                            <input
                                v-model="registerForm.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="registerForm.processing"
                            class="mt-2 flex w-full items-center justify-center rounded-xl bg-blue-600 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition hover:bg-blue-700 disabled:opacity-50"
                        >
                            <span v-if="registerForm.processing">{{ t('auth.creating_account') }}</span>
                            <span v-else>{{ t('auth.create_account') }}</span>
                        </button>
                    </form>
                    <p class="mt-6 text-center text-sm text-slate-500">
                        {{ t('auth.have_account') }}
                        <button type="button" class="font-semibold text-blue-600 hover:text-blue-700" @click="setTab('login')">
                            {{ t('auth.tab_login') }}
                        </button>
                    </p>
                </div>
            </div>

            <p class="mt-8 text-center text-xs text-blue-200/60">
                <a href="https://ronicrm.com" target="_blank" class="font-semibold text-blue-600 hover:text-blue-700">RoniCRM</a>
            </p>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from '@/composables/useI18n';
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const props = defineProps({
    initialTab: { type: String, default: 'login' },
});

const { t } = useI18n();

const activeTab = ref(props.initialTab === 'register' ? 'register' : 'login');

const loginForm = useForm({
    email: '',
    password: '',
    remember: false,
});

const registerForm = useForm({
    organization_name: '',
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
    plan_id: null,
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'register') {
        activeTab.value = 'register';
    }
    const em = params.get('email');
    if (em) {
        registerForm.email = em;
        loginForm.email = em;
    }
    const plan = params.get('plan');
    if (plan && /^\d+$/.test(plan)) {
        registerForm.plan_id = Number(plan);
    }
});

function setTab(tab) {
    activeTab.value = tab;
    const url = new URL(window.location.href);
    if (tab === 'register') {
        url.searchParams.set('tab', 'register');
    } else {
        url.searchParams.delete('tab');
    }
    window.history.replaceState({}, '', url.pathname + url.search + url.hash);
}

function submitLogin() {
    loginForm.post('/login', {
        onFinish: () => loginForm.reset('password'),
    });
}

function submitRegister() {
    registerForm
        .transform((data) => {
            const copy = { ...data };
            if (copy.plan_id === null || copy.plan_id === '' || copy.plan_id === undefined) {
                delete copy.plan_id;
            }
            return copy;
        })
        .post('/register', {
            onFinish: () => registerForm.reset('password', 'password_confirmation'),
        });
}
</script>
