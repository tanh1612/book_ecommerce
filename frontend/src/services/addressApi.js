// src/services/addressApi.js
import axiosClient from "./axiosClient";

let provinceCache = null;
let provinceRequest = null;
const wardCache = {};
const wardRequests = {};

const extractItems = (response) => response.data?.data || response.data || [];

const resolveLastPage = (response, fallbackLimit = 100) => {
  const metadata = response.data?.metadata || response.data?.meta || {};
  const total = Number(metadata.total || 0);
  const limit = Number(metadata.limit || fallbackLimit);

  if (Number.isFinite(total) && total > 0 && Number.isFinite(limit) && limit > 0) {
    return Math.max(1, Math.ceil(total / limit));
  }

  return Number(metadata.last_page || metadata.total_pages || 1);
};

const addressApi = {
  getAddresses() {
    return axiosClient.get('/v1/account/addresses');
  },

  createAddress(data) {
    return axiosClient.post('/v1/account/addresses', data);
  },

  updateAddress(id, data) {
    return axiosClient.patch(`/v1/account/addresses/${id}`, data);
  },

  deleteAddress(id) {
    return axiosClient.delete(`/v1/account/addresses/${id}`);
  },

  getProvinces() {
    if (provinceCache) {
      return Promise.resolve({ data: { data: provinceCache } });
    }

    provinceRequest ??= axiosClient.get('/v1/locations/provinces?limit=100')
      .then((res) => {
        provinceCache = extractItems(res);
        return { data: { data: provinceCache } };
      })
      .finally(() => {
        provinceRequest = null;
      });

    return provinceRequest;
  },

  async getWards(provinceCode) {
    const rawCode = String(provinceCode || '').trim();

    if (!rawCode) {
      return { data: { data: [] } };
    }

    const code = rawCode.padStart(2, '0');

    if (wardCache[code]) {
      return { data: { data: wardCache[code] } };
    }

    if (wardRequests[code]) {
      return wardRequests[code];
    }

    try {
      wardRequests[code] = (async () => {
        const limit = 100;
        const res1 = await axiosClient.get(`/v1/locations/provinces/${code}/wards?limit=${limit}&page=1`);
        let allWards = extractItems(res1);
        const lastPage = resolveLastPage(res1, limit);

        if (lastPage > 1) {
          const promises = [];
          for (let page = 2; page <= lastPage; page += 1) {
            promises.push(axiosClient.get(`/v1/locations/provinces/${code}/wards?limit=${limit}&page=${page}`));
          }

          const results = await Promise.all(promises);
          results.forEach((res) => {
            allWards = [...allWards, ...extractItems(res)];
          });
        }

        wardCache[code] = allWards;
        return { data: { data: allWards } };
      })();

      return await wardRequests[code];
    } catch (error) {
      console.error('Failed to load wards:', error);
      return { data: { data: [] } };
    } finally {
      delete wardRequests[code];
    }
  },
};

export default addressApi;
