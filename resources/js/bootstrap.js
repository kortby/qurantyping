import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add locale header to all requests
window.axios.interceptors.request.use(function (config) {
    const locale = localStorage.getItem('lang') || 'en';
    config.headers['X-Locale'] = locale;
    return config;
});
