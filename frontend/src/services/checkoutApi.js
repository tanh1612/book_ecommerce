// src/services/checkoutApi.js
import axiosClient from './axiosClient';

const checkoutApi = {
  getShippingMethods: () => axiosClient.get('/v1/shipping/methods'),
  getShippingQuote: (data) => axiosClient.post('/v1/shipping/quote', data),
  submitOrder: (data) => axiosClient.post('/v1/checkout', data),
};

export default checkoutApi;
