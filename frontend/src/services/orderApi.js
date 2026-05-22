import axiosClient from './axiosClient';

const orderApi = {
  // Lấy danh sách đơn hàng (hỗ trợ phân trang và lọc theo status)
  getOrders: (params) => axiosClient.get('/v1/account/orders', { params }),
  
  // Lấy chi tiết 1 đơn hàng
  getOrderDetail: (id) => axiosClient.get(`/v1/account/orders/${id}`),
  
  // Hủy đơn hàng
  cancelOrder: (id) => axiosClient.post(`/v1/account/orders/${id}/cancel`),
  
  // Lấy danh sách ngân hàng hỗ trợ hoàn tiền
  getRefundBanks: () => axiosClient.get('/v1/account/refund-banks'),
  
  // Gửi thông tin tài khoản ngân hàng để nhận hoàn tiền
  submitRefundBank: (id, data) => axiosClient.post(`/v1/account/orders/${id}/refund-bank-info`, data),
};

export default orderApi;