import axios from "axios";

const getBaseURL = () => {
  if (typeof window !== 'undefined' && (window as any).seamlessReactConfig?.siteUrl) {
    return (window as any).seamlessReactConfig.siteUrl + '/wp-json/seamless/v1';
  }

  if (import.meta.env.DEV) {
    return '/api';
  }

  return import.meta.env.VITE_API_BASE_URL || '/wp-json/seamless/v1';
};

const baseURL = getBaseURL();

const api = axios.create({
  baseURL,
  timeout: 10000,
  withCredentials: true
});

api.interceptors.request.use((config) => {
  // Check if we have an SSO user token loaded securely in local storage
  const token = localStorage.getItem('seamless_user_token');
  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`;
  }
  config.headers['Accept'] = 'application/json';
  return config;
});

export default api;
