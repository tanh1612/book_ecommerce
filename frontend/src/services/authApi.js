// src/services/authApi.js
import axiosClient, { getCsrfToken } from "./axiosClient";

const authApi = {
  // --- ĐĂNG KÝ (3 BƯỚC) ---
  sendOtp(email) {
    return axiosClient.post('/v1/auth/register/send-otp', { email });
  },
  verifyOtp(email, otp) {
    return axiosClient.post('/v1/auth/register/verify-otp', { email, otp });
  },
  register(data) {
    return axiosClient.post('/v1/auth/register', data);
  },

  // --- ĐĂNG NHẬP / ĐĂNG XUẤT (SPA AUTH) ---
  async login(data) {
    await getCsrfToken(); // Bước bắt buộc của Sanctum
    return axiosClient.post('/v1/auth/login', data);
  },
  logout() {
    return axiosClient.post('/v1/auth/logout');
  },
  getProfile() {
    return axiosClient.get('/v1/account/profile');
  },

  // --- QUÊN MẬT KHẨU ---
  sendOtpForgot(email) {
    return axiosClient.post('/v1/auth/password/forgot/send-otp', { email });
  },
  verifyOtpForgot(email, otp) {
    return axiosClient.post('/v1/auth/password/forgot/verify-otp', { email, otp });
  },
  resetPassword(data) {
    return axiosClient.post('/v1/auth/password/reset', data);
  },

  // === THÊM MỚI TỪ FILE BE CỦA BẠN ===
  // Cập nhật thông tin cá nhân
  updateProfile(data) {
    // data gồm: first_name, last_name, phone, birthday, gender
    return axiosClient.patch('/v1/account/profile', data);
  },
  // Đổi mật khẩu
  changePassword(data) {
    // data gồm: current_password, password, password_confirmation
    return axiosClient.patch('/v1/account/password', data);
  }
};

export default authApi;