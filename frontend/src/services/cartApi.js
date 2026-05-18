// src/services/cartApi.js
import axiosClient from './axiosClient'; 

const cartApi = {
  // Thêm /v1 vào trước tất cả các endpoint để khớp chuẩn với Backend
  getCart: () => axiosClient.get('/v1/cart'),
  
  addItem: (book_id, quantity = 1) => axiosClient.post('/v1/cart/items', { book_id, quantity }),
  
  updateItem: (cartItemId, data) => axiosClient.patch(`/v1/cart/items/${cartItemId}`, data),
  
  removeItem: (cartItemId) => axiosClient.delete(`/v1/cart/items/${cartItemId}`),
  
  updateSelection: (selected) => axiosClient.patch('/v1/cart/items/selection', { selected })
};

export default cartApi;