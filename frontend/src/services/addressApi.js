// src/services/addressApi.js
import axiosClient from "./axiosClient";

const addressApi = {
  // === QUẢN LÝ SỔ ĐỊA CHỈ ===
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

  // === LẤY DỮ LIỆU TỈNH/PHƯỜNG ===
  getProvinces() {
    return axiosClient.get('/v1/locations/provinces?limit=100');
  },
  
  getWards(provinceCode) {
    const code = String(provinceCode).padStart(2, '0');
    return axiosClient.get(`/v1/locations/provinces/${code}/wards`);
  }
};

export default addressApi;