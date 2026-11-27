import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF token from the page (there is no XSRF-TOKEN cookie; see ValidateCsrfTokenWithoutCookie).
const csrf = document.querySelector('meta[name="csrf-token"]');
if (csrf) window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrf.getAttribute('content');
