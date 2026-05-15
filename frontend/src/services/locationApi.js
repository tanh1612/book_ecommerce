// src/services/locationApi.js
import axios from 'axios';

const locationApi = axios.create({
  baseURL: 'https://tinhthanhpho.com/api/v1',
  headers: {
    // Sử dụng đúng API Key mà BE đang dùng
    'Authorization': 'Bearer hvn_qbkwNSvDK8anuuq8BNQ0tuoWqeqhaKV3',
    'Accept': 'application/json'
  }
});

const api = {
  getProvinces: () => locationApi.get('/provinces?limit=100'),
  getDistricts: (provinceCode) => locationApi.get(`/provinces/${provinceCode}/districts?limit=100`),
  getWards: (districtCode) => locationApi.get(`/districts/${districtCode}/wards?limit=100`)
};

export default api;