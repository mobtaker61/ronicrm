export function useRoute() {
    const route = (name, params = null, absolute = false) => {
        if (typeof window !== 'undefined' && window.route) {
            try {
                return window.route(name, params, absolute);
            } catch (e) {
                // Fallback to direct URLs
            }
        }
        
        // Fallback routes
        const routes = {
            'dashboard': '/dashboard',
            'customers.index': '/customers',
            'customers.create': '/customers/create',
            'customers.show': (id) => `/customers/${id}`,
            'customers.edit': (id) => `/customers/${id}/edit`,
            'customers.update': (id) => `/customers/${id}`,
            'customers.store': '/customers',
            'customers.destroy': (id) => `/customers/${id}`,
            'customers.notes.store': (id) => `/customers/${id}/notes`,
            'customers.notes.destroy': (id) => `/customers/notes/${id}`,
            'campaigns.index': '/campaigns',
            'campaigns.create': '/campaigns/create',
            'campaigns.show': (id) => `/campaigns/${id}`,
            'campaigns.store': '/campaigns',
            'campaigns.destroy': (id) => `/campaigns/${id}`,
            'industries.index': '/industries',
            'industries.store': '/industries',
            'industries.update': (id) => `/industries/${id}`,
            'industries.destroy': (id) => `/industries/${id}`,
            'projects.index': '/projects',
            'projects.store': '/projects',
            'projects.update': (id) => `/projects/${id}`,
            'projects.destroy': (id) => `/projects/${id}`,
            'reports.index': '/reports',
        };
        
        if (typeof routes[name] === 'function') {
            return routes[name](params);
        }
        return routes[name] || '/';
    };
    
    return { route };
}
