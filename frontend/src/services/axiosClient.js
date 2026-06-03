// src/services/axiosClient.js
import axios from 'axios';

const axiosClient = axios.create({
  baseURL: '/api', 
  withCredentials: true, 
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

export const getCsrfToken = () => {
  return axios.get('/sanctum/csrf-cookie', { withCredentials: true }); 
};

export default axiosClient;