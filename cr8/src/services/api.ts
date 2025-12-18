import axios from 'axios';

// Determine the API base URL based on environment
const getBaseURL = () => {
  // Check if we're in production (on the school subdomain)
  if (window.location.hostname === 'cr8.dcism.org') {
    return 'https://cr8.dcism.org/api';
  }
  // In development, use relative path with Vite proxy
  return '/api';
};

const api = axios.create({
  baseURL: getBaseURL(),
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Request interceptor
api.interceptors.request.use(
  (config) => {
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      // Unauthorized - redirect to login only if not already there
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export default api;
