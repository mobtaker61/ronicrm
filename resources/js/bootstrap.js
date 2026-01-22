import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import { route as ziggyRoute } from 'ziggy-js';

window.route = function(name, params, absolute) {
    if (typeof window !== 'undefined' && window.Ziggy) {
        return ziggyRoute(name, params, absolute, window.Ziggy);
    }
    return ziggyRoute(name, params, absolute);
};
