import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { useI18n } from './composables/useI18n';

const appName = import.meta.env.VITE_APP_NAME || 'RoniCRM';

// Route helper function
const routeHelper = (name, params = null, absolute = false) => {
    if (typeof window !== 'undefined' && window.route) {
        try {
            return window.route(name, params, absolute);
        } catch (e) {
            // Fallback to direct URLs
        }
    }
    
    // Fallback routes
    const routes = {
        'front.welcome': '/',
        'front.privacy': '/privacy-policy',
        'front.terms': '/terms-and-conditions',
        'login': '/login',
        'dashboard': '/dashboard',
        'settings.instagram.connect': '/settings/instagram/connect',
        'settings.instagram.callback': '/settings/instagram/callback',
        'settings.instagram.disconnect': '/settings/instagram/disconnect',
        'settings.instagram.revalidate': '/settings/instagram/revalidate',
        'settings.google-contacts.connect': '/settings/google-contacts/connect',
        'settings.google-contacts.callback': '/settings/google-contacts/callback',
        'settings.google-contacts.disconnect': '/settings/google-contacts/disconnect',
        'settings.google-contacts.sync-start': '/settings/google-contacts/sync-start',
        'settings.google-contacts.sync-progress': '/settings/google-contacts/sync-progress',
        'inbox.index': (params) => params && Object.keys(params).length ? '/inbox?' + new URLSearchParams(params).toString() : '/inbox',
        'customers.index': '/customers',
        'customers.create': '/customers/create',
        'customers.show': (id) => `/customers/${id}`,
        'customers.edit': (id) => `/customers/${id}/edit`,
        'customers.update': (id) => `/customers/${id}`,
        'customers.store': '/customers',
        'customers.destroy': (id) => `/customers/${id}`,
        'customers.notes.store': (id) => `/customers/${id}/notes`,
        'customers.notes.destroy': (id) => `/customers/notes/${id}`,
        'customers.share-via-whatsapp': (id) => `/customers/${id}/share-via-whatsapp`,
        'customers.import': '/customers/import',
        'customers.import-preview': '/customers/import-preview',
        'campaigns.index': '/campaigns',
        'campaigns.create': '/campaigns/create',
        'campaigns.show': (id) => `/campaigns/${id}`,
        'campaigns.store': '/campaigns',
        'campaigns.start': (id) => `/campaigns/${id}/start`,
        'campaigns.status': (id) => `/campaigns/${id}/status`,
        'campaigns.destroy': (id) => `/campaigns/${id}`,
        'campaign-templates.index': '/campaign-templates',
        'campaign-templates.store': '/campaign-templates',
        'campaign-templates.update': (id) => `/campaign-templates/${id}`,
        'campaign-templates.destroy': (id) => `/campaign-templates/${id}`,
        'industries.index': '/industries',
        'industries.store': '/industries',
        'industries.update': (id) => `/industries/${id}`,
        'industries.destroy': (id) => `/industries/${id}`,
        'projects.index': '/projects',
        'projects.store': '/projects',
        'projects.update': (id) => `/projects/${id}`,
        'projects.destroy': (id) => `/projects/${id}`,
        'reports.index': '/reports',
        'public.customer.card': (shareKey) => `/c/${shareKey}`,
        'public.customer.share-via-whatsapp': (shareKey) => `/c/${shareKey}/share-via-whatsapp`,
        'settings.index': '/settings',
        'settings.smtp.update': '/settings/smtp',
        'settings.ronibot.update': '/settings/ronibot',
        'settings.smtp.test': '/settings/smtp/test',
        'settings.ronibot.test': '/settings/ronibot/test',
        'inbox.index': (params) => params && Object.keys(params).length ? '/inbox?' + new URLSearchParams(params).toString() : '/inbox',
        'inbox.send': '/inbox/send',
        'inbox.create-customer': '/inbox/create-customer',
        'inbox.assign-customer': '/inbox/assign-customer',
        'inbox.customers-for-assign': '/inbox/customers-for-assign',
        'ronibot.webhook': '/wpwebhook',
        'settings.social-media-types.store': '/settings/social-media-types',
        'settings.social-media-types.update': (id) => `/settings/social-media-types/${id}`,
        'settings.social-media-types.destroy': (id) => `/settings/social-media-types/${id}`,
        'settings.users.index': '/settings/users',
        'settings.users.store': '/settings/users',
        'settings.users.update': (id) => `/settings/users/${id}`,
        'settings.users.destroy': (id) => `/settings/users/${id}`,
        'superadmin.translations.index': '/superadmin/translations',
        'superadmin.social-media-platforms.index': '/superadmin/social-media-platforms',
        'superadmin.languages.index': '/superadmin/languages',
        'superadmin.organizations.index': '/superadmin/organizations',
        'superadmin.plans.index': '/superadmin/plans',
        'superadmin.subscriptions.index': '/superadmin/subscriptions',
        'organizations.current.update': '/organizations/current',
        'settings.organizations.store': '/settings/organizations',
        'settings.organizations.update': (id) => `/settings/organizations/${id}`,
        'settings.organizations.destroy': (id) => `/settings/organizations/${id}`,
        'settings.organizations.members.store': (id) => `/settings/organizations/${id}/members`,
        'settings.organizations.members.update': (organizationId, userId) => `/settings/organizations/${organizationId}/members/${userId}`,
        'settings.organizations.members.destroy': (organizationId, userId) => `/settings/organizations/${organizationId}/members/${userId}`,
        'settings.telegram.register-webhook': '/settings/telegram/register-webhook',
        'profile.index': '/profile',
        'profile.update': '/profile',
        'profile.password.update': '/profile/password',
    };
    
    if (typeof routes[name] === 'function') {
        return routes[name](params);
    }
    return routes[name] || '/';
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        const i18n = useI18n();
        const syncI18nFromPage = (page) => {
            const locale = page?.props?.i18n?.locale;
            const jsonUrl = page?.props?.i18n?.json_url;
            if (locale && jsonUrl) {
                i18n.load(locale, jsonUrl);
            }
        };

        syncI18nFromPage(props?.initialPage);
        router.on('navigate', (event) => {
            syncI18nFromPage(event?.detail?.page);
        });
        
        // Make route helper available globally in templates
        app.config.globalProperties.route = routeHelper;
        app.config.globalProperties.t = i18n.t;
        app.provide('i18n', i18n);
        
        return app.use(plugin).mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});