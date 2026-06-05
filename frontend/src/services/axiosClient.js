// src/services/axiosClient.js
import axios from 'axios';
import { toast } from 'react-toastify';

const MUTATING_METHODS = ['post', 'put', 'patch', 'delete'];

const axiosClient = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

const getCsrfTokenFromCookie = () => {
  const name = 'XSRF-TOKEN';
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);

  if (parts.length !== 2) {
    return null;
  }

  try {
    return decodeURIComponent(parts.pop().split(';').shift());
  } catch {
    return null;
  }
};

const clearReadableXsrfCookies = () => {
  if (typeof document === 'undefined') return;

  document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/';
  document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/; domain=localhost';
  document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/; domain=127.0.0.1';
};

let csrfRequest = null;

export const getCsrfToken = async ({ force = false } = {}) => {
  if (force) {
    clearReadableXsrfCookies();
  }

  let token = force ? null : getCsrfTokenFromCookie();

  if (!token) {
    csrfRequest ??= axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    await csrfRequest.finally(() => {
      csrfRequest = null;
    });
    token = getCsrfTokenFromCookie();
  }

  return token;
};

axiosClient.interceptors.request.use(
  async (config) => {
    if (MUTATING_METHODS.includes(config.method?.toLowerCase())) {
      try {
        const token = await getCsrfToken();

        if (token) {
          config.headers = config.headers || {};
          config.headers['X-XSRF-TOKEN'] = token;
        }
      } catch (error) {
        console.error('Failed to get CSRF token:', error);
      }
    }

    return config;
  },
  (error) => Promise.reject(error)
);

axiosClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const status = error.response?.status;
    const originalRequest = error.config;

    if (
      status === 419 &&
      originalRequest &&
      MUTATING_METHODS.includes(originalRequest.method?.toLowerCase()) &&
      !originalRequest._csrfRetried
    ) {
      originalRequest._csrfRetried = true;
      const token = await getCsrfToken({ force: true });
      originalRequest.headers = {
        ...originalRequest.headers,
        'X-XSRF-TOKEN': token,
      };

      return axiosClient(originalRequest);
    }

    if (originalRequest?.skipGlobalErrorHandler) {
      return Promise.reject(error);
    }

    if (status === 401) {
      // Auth state is handled by AuthContext and route guards.
      // A hard redirect here causes reload loops on public auth pages.
      window.dispatchEvent(new CustomEvent('auth:unauthorized'));
    } else if (status === 403) {
      toast.error('Bạn không có quyền truy cập');
    } else if (status === 404) {
      toast.error('Không tìm thấy dữ liệu');
    } else if (status === 419) {
      toast.error('Phiên làm việc hết hạn. Vui lòng thử lại');
    } else if (status === 422) {
      const errors = error.response.data?.errors || {};
      const errorMessages = Object.values(errors).flat();
      if (errorMessages.length > 0) {
        toast.error(errorMessages[0]);
      }
    } else if (status >= 500) {
      toast.error('Lỗi máy chủ. Vui lòng thử lại sau');
    } else if (error.message === 'Network Error') {
      toast.error('Lỗi kết nối. Kiểm tra kết nối internet');
    }

    return Promise.reject(error);
  }
);

export default axiosClient;
