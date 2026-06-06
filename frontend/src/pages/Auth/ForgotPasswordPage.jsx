// src/pages/Auth/ForgotPasswordPage.jsx
import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiEye, FiEyeOff, FiCheckCircle, FiAlertCircle, FiArrowLeft } from 'react-icons/fi';
import { toast } from 'react-toastify';
import authApi from '../../services/authApi';

const ForgotPasswordPage = () => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({ email: '', otp: '', password: '' });
  const [emailError, setEmailError] = useState('');
  const [resetToken, setResetToken] = useState('');
  const [isOtpVerified, setIsOtpVerified] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [countdown, setCountdown] = useState(0);
  const [loading, setLoading] = useState({ otp: false, verify: false, reset: false });

  // Đếm ngược 60s
  useEffect(() => {
    if (countdown > 0) {
      const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
      return () => clearTimeout(timer);
    }
  }, [countdown]);

  // Tự động kiểm tra OTP khi nhập đủ 6 số
  const handleChange = (e) => {
    const { name, value } = e.target;
    if (name === 'otp' && value.length > 6) return; // Chỉ cho nhập 6 số
    if (name === 'email') setEmailError(''); // Ẩn lỗi khi người dùng gõ lại
    setFormData({ ...formData, [name]: value });
  };

  // 1. Gửi OTP quên mật khẩu
  const handleGetOTP = async () => {
    if (!formData.email) return setEmailError("Vui lòng nhập email!");
    try {
      setLoading({ ...loading, otp: true });
      setEmailError('');
      await authApi.sendOtpForgot(formData.email);
      toast.success("Mã xác nhận đã được gửi tới email của bạn!");
      setCountdown(60);
    } catch (err) {
      setEmailError(err.response?.data?.message || "Email không tồn tại trong hệ thống!");
    } finally {
      setLoading({ ...loading, otp: false });
    }
  };

  // 2. Xác thực OTP
  const handleVerifyOTP = useCallback(async () => {
    try {
      setLoading(prev => ({ ...prev, verify: true }));
      const res = await authApi.verifyOtpForgot(formData.email, formData.otp);
      // Lưu lại token reset mật khẩu từ Backend trả về
      setResetToken(res.data.reset_token); 
      setIsOtpVerified(true);
      toast.success("Xác thực thành công! Mời bạn nhập mật khẩu mới.");
    } catch (err) {
      toast.error(err.response?.data?.message || "Mã OTP không chính xác!");
      setFormData(prev => ({ ...prev, otp: '' }));
    } finally {
      setLoading(prev => ({ ...prev, verify: false }));
    }
  }, [formData.email, formData.otp]);

  useEffect(() => {
    if (formData.otp.length === 6 && !isOtpVerified) {
      void handleVerifyOTP();
    }
  }, [formData.otp, handleVerifyOTP, isOtpVerified]);

  // 3. Đặt lại mật khẩu mới
  const handleSubmit = async (e) => {
    e.preventDefault();
    const passRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
    if (!passRegex.test(formData.password)) {
      return toast.error("Mật khẩu mới phải từ 8 ký tự, gồm cả chữ và số!");
    }

    try {
      setLoading({ ...loading, reset: true });
      await authApi.resetPassword({ 
        email: formData.email, 
        password: formData.password, 
        reset_token: resetToken 
      });
      toast.success('🎉 Đổi mật khẩu thành công! Hãy đăng nhập lại.');
      navigate('/login'); 
    } catch (err) {
      toast.error(err.response?.data?.message || "Đổi mật khẩu thất bại!");
    } finally {
      setLoading({ ...loading, reset: false });
    }
  };

  return (
    <div className="bg-[#f0f0f0] min-h-screen py-10 flex justify-center px-4">
      <div className="bg-white rounded-lg shadow-sm w-full max-w-[450px] p-10 border border-gray-100 h-fit">
        
        <div className="mb-6">
          <Link to="/login" className="text-sm text-gray-500 hover:text-[#157a2c] flex items-center gap-1 font-medium w-fit">
            <FiArrowLeft /> Quay lại đăng nhập
          </Link>
        </div>

        <h2 className="text-2xl font-bold text-[#157a2c] mb-2">Khôi phục mật khẩu</h2>
        <p className="text-sm text-gray-500 mb-8">Vui lòng nhập email bạn đã đăng ký để nhận mã xác nhận.</p>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          
          {/* Ô EMAIL */}
          <div>
            <div className="flex justify-between items-end mb-1">
              <label className="text-sm font-medium text-gray-700">Email của bạn</label>
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
                className={`w-full border rounded py-2.5 px-3 outline-none transition-colors ${emailError ? 'border-red-500 bg-red-50' : 'border-gray-300 focus:border-[#157a2c]'}`} 
              />
              <button 
                type="button" onClick={handleGetOTP} 
                disabled={countdown > 0 || isOtpVerified || loading.otp} 
                className="absolute right-1 top-1/2 -translate-y-1/2 px-3 py-1.5 text-[#157a2c] font-bold disabled:text-gray-400"
              >
                {loading.otp ? "..." : countdown > 0 ? `${countdown}s` : "Lấy mã"}
              </button>
            </div>
          </div>

          {/* Ô MÃ XÁC NHẬN */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1 flex justify-between">
              Mã xác nhận 
              {isOtpVerified && <span className="text-green-600 text-xs flex items-center gap-1"><FiCheckCircle /> Đã xác thực</span>}
            </label>
            <input 
              type="number" name="otp" value={formData.otp} onChange={handleChange} 
              placeholder={loading.verify ? "Đang kiểm tra..." : "Nhập 6 số"} 
              disabled={isOtpVerified || !formData.email} 
              className="w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-[#157a2c] text-center tracking-[10px] font-bold" 
            />
          </div>

          {/* Ô MẬT KHẨU MỚI */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Mật khẩu mới</label>
            <div className="relative">
              <input 
                type={showPassword ? "text" : "password"} name="password" 
                value={formData.password} onChange={handleChange} 
                placeholder={isOtpVerified ? "Tối thiểu 8 ký tự (Chữ & Số)" : "Chờ xác thực mã xong mới được nhập"} 
                disabled={!isOtpVerified} 
                className={`w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-[#157a2c] ${!isOtpVerified ? 'bg-gray-100 cursor-not-allowed' : ''}`} 
              />
              {isOtpVerified && (
                <button 
                  type="button" className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#157a2c]" 
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? <FiEyeOff size={20} /> : <FiEye size={20} />}
                </button>
              )}
            </div>
          </div>

          <button 
            type="submit" disabled={!isOtpVerified || !formData.password || loading.reset} 
            className={`w-full py-3.5 rounded-lg font-bold mt-4 text-white transition-all ${(!isOtpVerified || !formData.password || loading.reset) ? 'bg-gray-300 cursor-not-allowed' : 'bg-[#157a2c] hover:bg-green-800 shadow-md'}`}
          >
            {loading.reset ? "ĐANG XỬ LÝ..." : "CẬP NHẬT MẬT KHẨU"}
          </button>
        </form>
      </div>
    </div>
  );
};

export default ForgotPasswordPage;
