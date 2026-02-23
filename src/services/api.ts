import axios from "axios";

/**
 * Determine the correct base URL for the Seamless AMS API.
 *
 * Priority (highest → lowest):
 *  1. WordPress mode  – `window.seamlessReactConfig.clientDomain` provided by
 *     the PHP plugin via wp_localize_script().
 *  2. Dev / Vite proxy mode – Vite proxies "/api" to the AMS domain.
 *  3. Explicit env var fallback.
 */
const getBaseURL = () => {
  if (typeof window !== 'undefined') {
    const cfg = (window as any).seamlessReactConfig;
    if (cfg?.clientDomain) {
      return cfg.clientDomain + '/api';
    }
  }
  if (import.meta.env.DEV) return '/api';
  return import.meta.env.VITE_API_BASE_URL || '/api';
};

const api = axios.create({
  baseURL: getBaseURL(),
  timeout: 15000,
  // ⚠️ withCredentials MUST be false — the AMS API responds with
  // `Access-Control-Allow-Origin: *`, and browsers block credentialed
  // cross-origin requests when the server uses a wildcard origin.
  // Authentication is handled via Bearer token headers instead.
  withCredentials: false,
});

// ─── Request interceptor: attach the current access token ───────────────────
api.interceptors.request.use((config) => {
  const token = getAccessToken();
  if (token) {
    if (!config.headers) config.headers = {} as any;
    // Axios v1+ strongly types AxiosHeaders but setting via bracket notation mostly works.
    // Adding via set() for maximum compatibility.
    if (typeof config.headers.set === 'function') {
      config.headers.set('Authorization', `Bearer ${token}`);
    } else {
      config.headers['Authorization'] = `Bearer ${token}`;
    }
  }
  if (typeof config.headers.set === 'function') {
    config.headers.set('Accept', 'application/json');
  } else {
    config.headers['Accept'] = 'application/json';
  }
  return config;
});

// ─── Response interceptor: auto-refresh token on 401, then retry ───────────
let isRefreshing = false;
let refreshQueue: Array<(token: string) => void> = [];

const processQueue = (newToken: string) => {
  refreshQueue.forEach(resolve => resolve(newToken));
  refreshQueue = [];
};

api.interceptors.response.use(
  response => response,
  async (error) => {
    const originalRequest = error.config;

    // Only attempt refresh on 401 from AMS, and only once per request
    if (error.response?.status !== 401 || originalRequest._retried) {
      return Promise.reject(error);
    }

    // In dev mode (no WordPress), just reject — no AJAX endpoint available
    if (import.meta.env.DEV && !(window as any).seamlessReactConfig?.ajaxUrl) {
      return Promise.reject(error);
    }

    originalRequest._retried = true;

    if (isRefreshing) {
      // Queue up while refresh is already in flight
      return new Promise<string>((resolve) => {
        refreshQueue.push(resolve);
      }).then((newToken) => {
        originalRequest.headers['Authorization'] = `Bearer ${newToken}`;
        return api(originalRequest);
      });
    }

    isRefreshing = true;

    try {
      const newToken = await refreshAccessToken();
      if (!newToken) throw new Error('Token refresh returned empty token.');

      // Persist the fresh token for subsequent requests
      storeAccessToken(newToken);

      processQueue(newToken);
      originalRequest.headers['Authorization'] = `Bearer ${newToken}`;
      return api(originalRequest);
    } catch (refreshError) {
      refreshQueue = [];
      console.error('[Seamless] Token refresh failed:', refreshError);

      // If refresh explicitly failed and we are in the browser, redirect to SSO login
      if (typeof window !== 'undefined') {
        const cfg = (window as any).seamlessReactConfig;
        const siteUrl = cfg?.siteUrl || window.location.origin;
        const currentUrl = encodeURIComponent(window.location.href);
        // Using query parameter trigger to bypass potential WordPress permalink issues
        window.location.href = `${siteUrl}/?sso_login_redirect=1&return_to=${currentUrl}`;
      }

      return Promise.reject(error);
    } finally {
      isRefreshing = false;
    }
  }
);

// ─── Token helpers ───────────────────────────────────────────────────────────

function getCookie(name: string): string {
  if (typeof document === 'undefined') return '';
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop()?.split(';').shift() || '';
  return '';
}

/**
 * Get the best available access token.
 *  1. Cookies (bypasses full-page caching like Flywheel)
 *  2. window.seamlessReactConfig.accessToken  (set on page load by PHP)
 *  3. localStorage                             (updated after a refresh)
 */
export function getAccessToken(): string {
  if (typeof window !== 'undefined') {
    // 1. Try to get it from our dedicated cookie
    const cookieToken = getCookie('seamless_token_js');
    if (cookieToken) return cookieToken;

    // 2. Fallback to injected config
    const cfg = (window as any).seamlessReactConfig;
    if (cfg?.accessToken) return cfg.accessToken;

    // 3. Fallback: localStorage
    const stored = localStorage.getItem('seamless_user_token');
    if (stored) return stored;
  }
  return '';
}

/**
 * Persist a refreshed token so it is used for future requests.
 */
function storeAccessToken(token: string): void {
  localStorage.setItem('seamless_user_token', token);
  // Also update the in-memory config so other code reading it stays in sync
  if (typeof window !== 'undefined') {
    const cfg = (window as any).seamlessReactConfig;
    if (cfg) cfg.accessToken = token;
  }
}

/**
 * Call the WordPress AJAX endpoint that uses SeamlessSSO::seamless_refresh_token_if_needed().
 * Returns the new token, or null if refresh failed.
 */
async function refreshAccessToken(): Promise<string | null> {
  const cfg = (window as any).seamlessReactConfig;
  const ajaxUrl: string = cfg?.ajaxUrl || '/wp-admin/admin-ajax.php';
  const nonce: string = cfg?.ajaxNonce || '';

  const body = new URLSearchParams({
    action: 'seamless_refresh_token',
    nonce,
  });

  const response = await fetch(ajaxUrl, {
    method: 'POST',
    credentials: 'include', // Ensure cookies are sent even if subdomains differ
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: body.toString(),
  });

  if (!response.ok) return null;

  const data = await response.json();
  if (data?.success && data?.data?.token) {
    return data.data.token;
  }
  return null;
}

export default api;
