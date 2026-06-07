// src/services/bookApi.js
import axiosClient from "./axiosClient";

const bookApi = {
  // Lấy các thuộc tính để đổ ra Sidebar bộ lọc (Danh mục, NXB, Cung cấp...)
  getFilters() {
    return axiosClient.get('/v1/books/filters');
  },
  
  // 🔥 THÊM HÀM MỚI NÀY VÀO: Lấy gợi ý tìm kiếm
  getSuggestions: (keyword, limit = 8) => axiosClient.get('/v1/books/suggestions', { params: { keyword, limit } }),
  // 🔥 THÊM HÀM NÀY: Gọi API Flash Sale thật từ Backend
  getActiveFlashSale: () => axiosClient.get('/v1/flash-sales/active'),
  // Lấy danh sách sách (kèm các tham số lọc như category, publisher, sort...)
  getBooks(params) {
    return axiosClient.get('/v1/books', { params });
  },
  
  // Lấy chi tiết 1 cuốn sách theo slug
  getBookDetail(slug) {
    return axiosClient.get(`/v1/books/${slug}`);
  }
};

export default bookApi;
