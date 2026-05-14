import axios from 'axios';

const axiosClient = axios.create({
  baseURL: '/api', // CHỈ ĐỂ '/api'
  withCredentials: true, 
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

export const getCsrfToken = () => {
  // CHỈ ĐỂ '/sanctum/csrf-cookie'
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true }); 
};

export default axiosClient;