// src/services/axiosClient.js
import axios from 'axios';
import { toast } from 'react-toastify';

const MUTATING_METHODS = ['post', 'put', 'patch', 'delete'];
const CSRF_CACHE_TTL_MS = 10 * 60 * 1000;

const trimTrailingSlash = (value) => String(value || '').replace(/\/+$/, '');

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api';
const BACKEND_ORIGIN = trimTrailingSlash(
  import.meta.env.VITE_BACKEND_ORIGIN || import.meta.env.VITE_API_ORIGIN || ''
);
const CSRF_URL = BACKEND_ORIGIN
  ? `${BACKEND_ORIGIN}/sanctum/csrf-cookie`
  : '/sanctum/csrf-cookie';

const axiosClient = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  withXSRFToken: false,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

const csrfClient = axios.create({
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  withXSRFToken: false,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'Cache-Control': 'no-cache',
  },
});

const getCsrfTokenFromCookie = () => {
  const name = 'XSRF-TOKEN';
  const tokens = document.cookie
    .split(';')
    .map((cookie) => cookie.trim())
    .filter((cookie) => cookie.startsWith(`${name}=`))
    .map((cookie) => cookie.slice(name.length + 1))
    .filter(Boolean);

  if (tokens.length === 0) {
    return null;
  }

  try {
    return decodeURIComponent(tokens.at(-1));
  } catch {
    return null;
  }
};

const clearReadableXsrfCookies = () => {
  if (typeof document === 'undefined') return;

  const hostname = window.location.hostname;
  const domains = new Set([hostname, `.${hostname}`, 'localhost', '127.0.0.1']);
  const expiry = 'XSRF-TOKEN=; Max-Age=0; path=/';

  document.cookie = expiry;
  domains.forEach((domain) => {
    document.cookie = `${expiry}; domain=${domain}`;
  });
};

let csrfRequest = null;
let csrfTokenCache = null;
let csrfTokenCachedAt = 0;

const cacheCsrfToken = (token) => {
  csrfTokenCache = token || null;
  csrfTokenCachedAt = token ? Date.now() : 0;
  return csrfTokenCache;
};

const hasFreshCachedCsrfToken = () => {
  if (!csrfTokenCache) {
    return false;
  }

  if (Date.now() - csrfTokenCachedAt > CSRF_CACHE_TTL_MS) {
    return false;
  }

  return getCsrfTokenFromCookie() === csrfTokenCache;
};

const wait = (milliseconds) => new Promise((resolve) => {
  setTimeout(resolve, milliseconds);
});

const waitForReadableCsrfToken = async (previousToken = null) => {
  for (let attempt = 0; attempt < 8; attempt += 1) {
    const token = getCsrfTokenFromCookie();

    if (token && (!previousToken || token !== previousToken)) {
      return token;
    }

    await wait(25);
  }

  return getCsrfTokenFromCookie();
};

const refreshCsrfCookie = async () => {
  csrfRequest ??= csrfClient.get(CSRF_URL, {
    params: { _: Date.now() },
  });

  await csrfRequest.finally(() => {
    csrfRequest = null;
  });
};

export const getCsrfToken = async ({ force = false } = {}) => {
  if (!force && hasFreshCachedCsrfToken()) {
    return csrfTokenCache;
  }

  const previousToken = force ? getCsrfTokenFromCookie() : null;

  if (force) {
    clearReadableXsrfCookies();
    cacheCsrfToken(null);
  }

  let token = force ? null : getCsrfTokenFromCookie();

  if (!token) {
    await refreshCsrfCookie();
    token = await waitForReadableCsrfToken(previousToken);
  }

  return cacheCsrfToken(token);
};

const toPlainHeaders = (headers) => {
  const plainHeaders = headers?.toJSON
    ? headers.toJSON()
    : { ...(headers || {}) };

  Object.keys(plainHeaders).forEach((key) => {
    if (key.toLowerCase() === 'x-xsrf-token') {
      delete plainHeaders[key];
    }
  });

  return plainHeaders;
};

const setCsrfHeader = (config, token) => {
  config.headers = {
    ...toPlainHeaders(config.headers),
    'X-XSRF-TOKEN': token,
  };
};

const buildRetriedRequest = (originalRequest, token) => {
  const retriedRequest = {
    ...originalRequest,
    headers: toPlainHeaders(originalRequest.headers),
    _csrfRetried: true,
  };

  setCsrfHeader(retriedRequest, token);

  return retriedRequest;
};

axiosClient.interceptors.request.use(
  async (config) => {
    if (
      MUTATING_METHODS.includes(config.method?.toLowerCase()) &&
      !config._csrfRetried
    ) {
      try {
        const token = await getCsrfToken();
        if (token) {
          setCsrfHeader(config, token);
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
      try {
        const token = await getCsrfToken({ force: true });

        if (token) {
          return axiosClient(buildRetriedRequest(originalRequest, token));
        }
      } catch (csrfError) {
        console.error('Failed to refresh CSRF token for retry:', csrfError);
      }
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
      const errors = error.response?.data?.errors || {};
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
