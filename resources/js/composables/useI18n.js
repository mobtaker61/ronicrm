import { ref } from 'vue';

const currentLocale = ref(null);
const messages = ref({});
const loading = ref(false);

export function useI18n() {
    /**
     * بارگذاری JSON ترجمه برای locale فعلی. همیشه از سرور تازه‌سازی می‌کند تا بعد از عوض کردن زبان بدون رفرش دستی، متن‌ها به‌روز شوند.
     */
    async function load(locale, jsonUrl) {
        if (! locale || ! jsonUrl) {
            return;
        }

        loading.value = true;
        try {
            const { data } = await window.axios.get(jsonUrl, {
                timeout: 15000,
                params: { _: Date.now() },
            });
            if (data && typeof data === 'object') {
                messages.value = data;
                currentLocale.value = locale;
                try {
                    localStorage.setItem(`i18n_${locale}`, JSON.stringify(data));
                } catch {
                    // ignore quota / private mode
                }
            }
        } finally {
            loading.value = false;
        }
    }

    function t(key, fallback = null) {
        if (! key) {
            return '';
        }
        const dict = messages.value || {};
        if (Object.prototype.hasOwnProperty.call(dict, key)) {
            return dict[key];
        }

        return fallback ?? key;
    }

    return {
        locale: currentLocale,
        messages,
        loading,
        load,
        t,
    };
}
