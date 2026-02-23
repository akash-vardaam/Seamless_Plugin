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
    config.headers['Authorization'] = `Bearer ${token}`;
  }
  config.headers['Accept'] = 'application/json';
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
      
      // If refresh explicitly failed and we are in the browser, redirect to login
      if (typeof window !== 'undefined') {
          const cfg = (window as any).seamlessReactConfig;
          const siteUrl = cfg?.siteUrl || window.location.origin;
          window.location.href = (window as any).seamless_logout_url || `${siteUrl}/wp-login.php`;
      }
      
      return Promise.reject(error);
    } finally {
      isRefreshing = false;
    }
  }
);

// ─── Token helpers ───────────────────────────────────────────────────────────

/**
 * Get the best available access token.
 *  1. window.seamlessReactConfig.accessToken  (set on page load by PHP)
 *  2. localStorage                             (updated after a refresh)
 */
export function getAccessToken(): string {
  if (typeof window !== 'undefined') {
    const cfg = (window as any).seamlessReactConfig;
    // After a mid-session refresh, localStorage will hold the newer token
    const stored = localStorage.getItem('seamless_user_token');
    if (stored) return stored;
    if (cfg?.accessToken) return cfg.accessToken;
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
  const nonce: string   = cfg?.ajaxNonce || '';

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
