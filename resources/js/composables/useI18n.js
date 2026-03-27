import { ref } from 'vue';

const currentLocale = ref(null);
const messages = ref({});
const loading = ref(false);

export function useI18n() {
    async function load(locale, jsonUrl) {
        if (!locale || !jsonUrl) return;
        if (currentLocale.value === locale && Object.keys(messages.value || {}).length) return;

        loading.value = true;
        try {
            const cacheKey = `i18n_${locale}`;
            const cached = localStorage.getItem(cacheKey);
            if (cached) {
                messages.value = JSON.parse(cached) || {};
                currentLocale.value = locale;
            }

            const { data } = await window.axios.get(jsonUrl, { timeout: 15000 });
            if (data && typeof data === 'object') {
                messages.value = data;
                currentLocale.value = locale;
                localStorage.setItem(cacheKey, JSON.stringify(data));
            }
        } finally {
            loading.value = false;
        }
    }

    function t(key, fallback = null) {
        if (!key) return '';
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

