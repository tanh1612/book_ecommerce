import axiosClient from "./axiosClient";

const bookApi = {
  getAll(params) {
    const url = '/books'; // Endpoint này do Tuấn Anh viết ở Backend
    return axiosClient.get(url, { params });
  },
  getDetail(id) {
    const url = `/books/${id}`;
    return axiosClient.get(url);
  }
};

export default bookApi;