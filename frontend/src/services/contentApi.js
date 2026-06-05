import axiosClient from './axiosClient';

const contentApi = {
  getHomeBanners: () => axiosClient.get('/v1/banners'),
};

export default contentApi;
