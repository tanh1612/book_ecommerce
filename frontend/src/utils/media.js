const trimTrailingSlash = (value) => String(value || '').replace(/\/+$/, '');

const API_ORIGIN = trimTrailingSlash(
  import.meta.env.VITE_BACKEND_ORIGIN
    || import.meta.env.VITE_API_ORIGIN
    || 'http://127.0.0.1:8000'
);

export const resolveMediaUrl = (url, fallback = 'https://placehold.co/80x120?text=No+Image') => {
  if (!url) return fallback;

  const value = String(url).trim();
  if (!value) return fallback;

  if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) {
    return value;
  }

  if (value.startsWith('/')) {
    return `${API_ORIGIN}${value}`;
  }

  return value;
};
