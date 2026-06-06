import axiosClient from './axiosClient';

const recommendationApi = {
  getRecommendations: (params) => axiosClient.get('/v1/recommendations', { params }),

  trackBookView: (bookId, source = 'book_detail') => axiosClient.post(
    `/v1/recommendations/interactions/books/${bookId}/view`,
    { source },
    { skipGlobalErrorHandler: true }
  ),
};

export default recommendationApi;
