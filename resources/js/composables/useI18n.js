import { ref } from 'vue';

const currentLocale = ref(null);
const messages = ref({});
const loading = ref(false);
const revision = ref(0);

export function useI18n() {
    function setMessages(data) {
        if (data && typeof data === 'object') {
            messages.value = data;
            revision.value += 1;
        }
    }

    /**
     * بارگذاری JSON ترجمه برای locale فعلی.
     */
    async function load(locale, jsonUrl) {
        if (! locale || ! jsonUrl) {
            return;
        }

        loading.value = true;
        try {
            const { data } = await window.axios.get(jsonUrl, {
                timeout: 15000,
                headers: { Accept: 'application/json' },
                params: { _: Date.now() },
            });
            if (data && typeof data === 'object') {
                setMessages(data);
                currentLocale.value = locale;
                try {
                    localStorage.setItem(`i18n_${locale}`, JSON.stringify(data));
                } catch {
                    // ignore quota / private mode
                }
            }
        } catch (error) {
            console.warn('i18n load failed', locale, error?.response?.status || error?.message);
        } finally {
            loading.value = false;
        }
    }

    function t(key, fallback = null) {
        // وابستگی reactive تا بعد از load دوباره رندر شود
        revision.value;

        if (! key) {
            return '';
        }
        const dict = messages.value || {};
        if (Object.prototype.hasOwnProperty.call(dict, key)) {
            const v = dict[key];
            if (v !== null && v !== undefined) {
                const s = String(v).trim();
                if (s !== '') {
                    return String(v);
                }
            }
            return fallback ?? key;
        }

        return fallback ?? key;
    }

    return {
        locale: currentLocale,
        messages,
        loading,
        revision,
        setMessages,
        load,
        t,
    };
}
