import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { route as ziggyRoute } from 'ziggy-js';

// Force relative URLs by default.
// When `absolute` is omitted, some Ziggy builds may end up returning an absolute URL
// based on the `Ziggy.url` value (which can be ngrok/production). That breaks POSTs
// when the user is accessing the app on a different host.
window.route = function(name, params = null, absolute = false) {
    if (typeof window !== 'undefined' && window.Ziggy) {
        return ziggyRoute(name, params, absolute, window.Ziggy);
    }
    return ziggyRoute(name, params, absolute);
};
