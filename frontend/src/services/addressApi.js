// src/services/addressApi.js
import axiosClient from "./axiosClient";

const wardCache = {}; // KHO LƯU TRỮ TẠM BỘ NHỚ RAM

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
    return axiosClient.get('/v1/locations/provinces?limit=100');
  },
  
  async getWards(provinceCode) {
    const code = String(provinceCode).padStart(2, '0');
    
    // NẾU ĐÃ CÓ TRONG KHO -> TRẢ VỀ NGAY LẬP TỨC 
    if (wardCache[code]) {
      return { data: { data: wardCache[code] } };
    }

    try {
      // 1. Gọi trang đầu tiên để lấy dữ liệu và lấy Tổng số trang (last_page)
      const res1 = await axiosClient.get(`/v1/locations/provinces/${code}/wards?limit=100&page=1`);
      let allWards = res1.data?.data || res1.data || [];
      const lastPage = res1.data?.meta?.last_page || res1.meta?.last_page || 1;

      // 2. Nếu có nhiều hơn 1 trang, GỌI SONG SONG tất cả các trang còn lại cùng 1 lúc!
      if (lastPage > 1) {
        const promises = [];
        for (let p = 2; p <= lastPage; p++) {
          promises.push(axiosClient.get(`/v1/locations/provinces/${code}/wards?limit=100&page=${p}`));
        }
        
        const results = await Promise.all(promises);
        results.forEach(res => {
          const items = res.data?.data || res.data || [];
          allWards = [...allWards, ...items];
        });
      }

      wardCache[code] = allWards;
      return { data: { data: allWards } };
    } catch (error) {
      console.error("Lỗi lấy Phường/Xã:", error);
      return { data: { data: [] } };
    }
  }
};

export default addressApi;