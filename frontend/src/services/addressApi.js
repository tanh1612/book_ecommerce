// src/services/addressApi.js
import axiosClient from "./axiosClient";

const addressApi = {
  // === 1. QUẢN LÝ SỔ ĐỊA CHỈ ===
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

  // === 2. LẤY DỮ LIỆU TỈNH/PHƯỜNG TỪ BACKEND ===
  // (Lấy dữ liệu chuẩn năm 2025 từ tinhthanhpho.com thông qua Backend)
// === TRONG FILE addressApi.js ===
  getProvinces() {
    return axiosClient.get('/v1/locations/provinces?limit=100');
  },
  
  getWards(provinceCode) {
    // Tự động đệm số 0 để luôn gửi chuẩn 2 ký tự (VD: '1' -> '01')
    const code = String(provinceCode).padStart(2, '0');
    // Bỏ limit=500 đi để Backend dùng limit mặc định (tránh lỗi 422)
    return axiosClient.get(`/v1/locations/provinces/${code}/wards?limit=100`); 
  }
};

export default addressApi;