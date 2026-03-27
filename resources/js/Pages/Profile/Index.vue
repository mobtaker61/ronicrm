<template>
    <AppLayout>
        <template #header>
            {{ t('common.profile') }}
        </template>

        <div class="space-y-6 max-w-3xl">
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.error }}
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('profile.account_information') }}</h2>
                <form @submit.prevent="saveProfile" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.avatar') }}</label>
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-full overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
                                <img
                                    v-if="currentAvatarUrl"
                                    :src="currentAvatarUrl"
                                    :alt="t('common.avatar')"
                                    class="w-full h-full object-cover"
                                />
                                <span v-else class="text-gray-400 text-xs">{{ t('profile.no_image') }}</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <input
                                    type="file"
                                    accept="image/*"
                                    @change="onAvatarSelected"
                                    class="block w-full text-sm text-gray-700 file:ltr:mr-3 file:rtl:ml-3 file:px-3 file:py-1.5 file:rounded-md file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                />
                                <button
                                    type="button"
                                    @click="clearAvatar"
                                    class="px-3 py-1.5 text-xs font-medium text-red-700 bg-red-50 rounded-md hover:bg-red-100"
                                >
                                    {{ t('profile.remove_avatar') }}
                                </button>
                            </div>
                        </div>
                        <p v-if="profileForm.errors.avatar" class="mt-1 text-sm text-red-600">{{ profileForm.errors.avatar }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.name') }}</label>
                        <input
                            v-model="profileForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="profileForm.errors.name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.username') }}</label>
                        <input
                            v-model="profileForm.username"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="profileForm.errors.username" class="mt-1 text-sm text-red-600">{{ profileForm.errors.username }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.email') }}</label>
                        <input
                            v-model="profileForm.email"
                            type="email"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ profileForm.processing ? t('profile.saving') : t('profile.save_profile') }}
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ t('profile.change_password') }}</h2>
                <form @submit.prevent="savePassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('profile.current_password') }}</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('profile.new_password') }}</label>
                        <input
                            v-model="passwordForm.password"
                            type="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('profile.confirm_new_password') }}</label>
                        <input
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="px-4 py-2 bg-emerald-600 text-white rounded-md hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ passwordForm.processing ? t('profile.updating') : t('profile.update_password') }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
});

const profileForm = useForm({
    name: props.profile.name || '',
    username: props.profile.username || '',
    email: props.profile.email || '',
    avatar: null,
    remove_avatar: false,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const avatarPreviewUrl = ref(null);
const currentAvatarUrl = computed(() => avatarPreviewUrl.value || props.profile.avatar_url || null);

const onAvatarSelected = (event) => {
    const file = event.target?.files?.[0] || null;
    profileForm.avatar = file;
    profileForm.remove_avatar = false;

    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
        avatarPreviewUrl.value = null;
    }
    if (file) {
        avatarPreviewUrl.value = URL.createObjectURL(file);
    }
};

const clearAvatar = () => {
    profileForm.avatar = null;
    profileForm.remove_avatar = true;
    if (avatarPreviewUrl.value) {
        URL.revokeObjectURL(avatarPreviewUrl.value);
        avatarPreviewUrl.value = null;
    }
};

const saveProfile = () => {
    profileForm
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('profile.update'), {
            forceFormData: true,
            onSuccess: () => {
                profileForm.avatar = null;
                profileForm.remove_avatar = false;
                if (avatarPreviewUrl.value) {
                    URL.revokeObjectURL(avatarPreviewUrl.value);
                    avatarPreviewUrl.value = null;
                }
            },
        });
};

const savePassword = () => {
    passwordForm.put(route('profile.password.update'), {
        onSuccess: () => {
            passwordForm.reset();
        },
    });
};
</script>

