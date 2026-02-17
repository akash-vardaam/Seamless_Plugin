import axios from "axios";

// Get API endpoint from window object (set by WordPress plugin)
// Uses WordPress REST API proxy to avoid CORS issues with third-party APIs
const getBaseURL = () => {
  // Check if running in WordPress with config
  if (typeof window !== 'undefined' && (window as any).seamlessReactConfig?.siteUrl) {
    // Use WordPress REST API proxy endpoint
    return (window as any).seamlessReactConfig.siteUrl + '/wp-json/seamless/v1';
  }
  
  // Development with local proxy
  if (import.meta.env.DEV) {
    return '/api';
  }
  
  // Fallback - use environment variable or default
  return import.meta.env.VITE_API_BASE_URL || '/wp-json/seamless/v1';
};

const baseURL = getBaseURL();

const api = axios.create({
  baseURL,
  timeout: 10000,
});

export default api;
