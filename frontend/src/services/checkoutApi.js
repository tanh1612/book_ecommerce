// src/services/checkoutApi.js
import axiosClient from './axiosClient';

const checkoutApi = {
  getShippingQuote: (data, config = {}) => axiosClient.post('/v1/shipping/quote', data, config),
  submitOrder: (data) => axiosClient.post('/v1/checkout', data),
};

export default checkoutApi;
