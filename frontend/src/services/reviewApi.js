import axiosClient from './axiosClient';

const reviewApi = {
  getBookReviews: (slug, params) => axiosClient.get(`/v1/books/${slug}/reviews`, { params }),

  getBookReviewEligibility: (slug) => axiosClient.get(
    `/v1/books/${slug}/review-eligibility`,
    { skipGlobalErrorHandler: true }
  ),

  submitOrderItemReview: (orderItemId, data) => axiosClient.post(
    `/v1/account/order-items/${orderItemId}/review`,
    data
  ),
};

export default reviewApi;
