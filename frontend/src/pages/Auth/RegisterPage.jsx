// src/pages/Auth/RegisterPage.jsx
import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiEye, FiEyeOff, FiCheckCircle, FiAlertCircle } from 'react-icons/fi';
import { toast } from 'react-toastify';
import authApi from '../../services/authApi';

const RegisterPage = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({ email: '', otp: '', password: '' });
  const [emailError, setEmailError] = useState(''); // State lưu lỗi hiển thị trên ô Email
  const [registerToken, setRegisterToken] = useState('');
  const [isOtpVerified, setIsOtpVerified] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const [loading, setLoading] = useState({ otp: false, verify: false, register: false });

  // Đếm ngược 60s
  useEffect(() => {
    if (countdown > 0) {
      const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
      return () => clearTimeout(timer);
    }
  }, [countdown]);

  // Tự động kích hoạt kiểm tra OTP khi nhập đủ 6 số
  const handleChange = (e) => {
    const { name, value } = e.target;
    if (name === 'otp' && value.length > 6) return; // Giới hạn 6 số
    if (name === 'email') setEmailError(''); // Tự xóa chữ đỏ khi gõ lại email
    setFormData({ ...formData, [name]: value });
  };

  // Bước 1: Gọi API Gửi OTP
  const handleGetOTP = async () => {
    if (!formData.email) return setEmailError("Vui lòng nhập email!");
    try {
      setLoading({ ...loading, otp: true });
      setEmailError('');
      await authApi.sendOtp(formData.email);
      toast.success("Mã xác nhận đã được gửi!");
      setCountdown(60);
    } catch (err) {
      // HIỂN THỊ LỖI BẰNG CHỮ ĐỎ TRÊN Ô EMAIL (KHÔNG DÙNG ALERT)
      setEmailError(err.response?.data?.message || "Lỗi hệ thống hoặc Email đã tồn tại!");
    } finally {
      setLoading({ ...loading, otp: false });
    }
  };

  // Bước 2: Gọi API Xác nhận OTP
  const handleVerifyOTP = useCallback(async () => {
    try {
      setLoading(prev => ({ ...prev, verify: true }));
      const res = await authApi.verifyOtp(formData.email, formData.otp);
      setRegisterToken(res.data.register_token); // Giữ token để bước 3 dùng
      setIsOtpVerified(true);
      toast.success("Xác thực mã thành công!");
    } catch (err) {
      toast.error(err.response?.data?.message || "Mã OTP không chính xác!");
      setFormData(prev => ({ ...prev, otp: '' }));
    } finally {
      setLoading(prev => ({ ...prev, verify: false }));
    }
  }, [formData.email, formData.otp]);

  // Bước 3: Đăng ký
  useEffect(() => {
    if (formData.otp.length === 6 && !isOtpVerified) {
      void handleVerifyOTP();
    }
  }, [formData.otp, handleVerifyOTP, isOtpVerified]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const passRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
    if (!passRegex.test(formData.password)) {
      return toast.error("Mật khẩu phải từ 8 ký tự, gồm cả chữ và số!");
    }

    try {
      setLoading({ ...loading, register: true });
      await authApi.register({ 
        email: formData.email, 
        password: formData.password, 
        register_token: registerToken 
      });
      toast.success('Đăng ký thành công!');
      navigate('/profile'); 
    } catch (err) {
      toast.error(err.response?.data?.message || "Đăng ký thất bại");
    } finally {
      setLoading({ ...loading, register: false });
    }
  };

  return (
    <div className="bg-auth-surface min-h-screen py-10 flex justify-center px-4">
      <div className="bg-white rounded-lg shadow-sm w-full max-w-[450px] p-10 border border-gray-100 h-fit">
        <div className="flex mb-8 border-b border-gray-200">
          <Link to="/login" className="flex-1 text-center pb-3 text-gray-500 font-medium">Đăng nhập</Link>
          <div className="flex-1 text-center pb-3 text-primary font-medium border-b-2 border-primary">Đăng ký</div>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          
          {/* Ô EMAIL */}
          <div>
            <div className="flex justify-between items-end mb-1">
              <label className="text-sm text-gray-700">Email</label>
              {emailError && (
                <span className="text-red-500 text-[11px] font-medium flex items-center gap-1 animate-pulse">
                  <FiAlertCircle /> {emailError}
                </span>
              )}
            </div>
            <div className="relative">
              <input 
                type="email" name="email" value={formData.email} onChange={handleChange} 
                placeholder="Nhập email" disabled={isOtpVerified} 
                className={`w-full border rounded py-2.5 px-3 outline-none transition-colors ${emailError ? 'border-red-500 bg-red-50' : 'border-gray-300 focus:border-primary'}`} 
              />
              <button 
                type="button" onClick={handleGetOTP} 
                disabled={countdown > 0 || isOtpVerified || loading.otp} 
                className="absolute right-1 top-1/2 -translate-y-1/2 px-3 py-1.5 text-primary font-bold disabled:text-gray-400"
              >
                {loading.otp ? "..." : countdown > 0 ? `${countdown}s` : "Lấy mã"}
              </button>
            </div>
          </div>

          {/* Ô MÃ XÁC NHẬN */}
          <div>
            <label className="block text-sm text-gray-700 mb-1 flex justify-between">
              Mã xác nhận 
              {isOtpVerified && <span className="text-green-600 text-xs flex items-center gap-1"><FiCheckCircle /> Đã xác thực</span>}
            </label>
            <input 
              type="number" name="otp" value={formData.otp} onChange={handleChange} 
              placeholder={loading.verify ? "Đang kiểm tra..." : "Nhập 6 số"} 
              disabled={isOtpVerified || !formData.email} 
              className="w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-primary text-center tracking-[10px] font-bold" 
            />
          </div>

          {/* Ô MẬT KHẨU */}
          <div>
            <label className="block text-sm text-gray-700 mb-1">Mật khẩu</label>
            <div className="relative">
              <input 
                type={showPassword ? "text" : "password"} name="password" 
                value={formData.password} onChange={handleChange} 
                placeholder={isOtpVerified ? "Tối thiểu 8 ký tự (Chữ & Số)" : "Chờ xác thực mã xong mới được nhập"} 
                disabled={!isOtpVerified} 
                className={`w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-primary ${!isOtpVerified ? 'bg-gray-100 cursor-not-allowed' : ''}`} 
              />
              {isOtpVerified && (
                <button 
                  type="button" className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary" 
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? <FiEyeOff size={20} /> : <FiEye size={20} />}
                </button>
              )}
            </div>
          </div>

          <button 
            type="submit" disabled={!isOtpVerified || !formData.password || loading.register} 
            className={`w-full py-3 rounded font-bold mt-4 text-white transition-all ${(!isOtpVerified || !formData.password || loading.register) ? 'bg-gray-300 cursor-not-allowed' : 'bg-primary hover:bg-green-800'}`}
          >
            {loading.register ? "Đang xử lý..." : "HOÀN TẤT ĐĂNG KÝ"}
          </button>
        </form>
      </div>
    </div>
  );
};

export default RegisterPage;
