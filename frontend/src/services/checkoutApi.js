// src/services/checkoutApi.js
import axiosClient from './axiosClient';

const checkoutApi = {
  // Lấy phí vận chuyển từ Backend
  getShippingQuote: (data) => axiosClient.post('/v1/shipping/quote', data),
  
  // Gửi đơn đặt hàng (COD & VNPay)
  submitOrder: (data) => axiosClient.post('/v1/checkout', data),
};

export default checkoutApi;