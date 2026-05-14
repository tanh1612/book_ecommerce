// src/pages/Auth/RegisterPage.jsx
import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';

const RegisterPage = () => {
  const navigate = useNavigate();
  // Đã xóa lastName và firstName
  const [formData, setFormData] = useState({
    email: '',
    otp: '', 
    password: '',
  });
  
  const [showPassword, setShowPassword] = useState(false);
  const [countdown, setCountdown] = useState(0); 
  const [isSending, setIsSending] = useState(false);

  // Logic đếm ngược 60s sau khi bấm lấy mã
  useEffect(() => {
    if (countdown > 0) {
      const timer = setTimeout(() => setCountdown(countdown - 1), 1000);
      return () => clearTimeout(timer);
    }
  }, [countdown]);

  // Điều kiện để làm sáng nút Đăng ký (chỉ kiểm tra email, otp, password)
  const isSubmitDisabled = !formData.email || !formData.otp || !formData.password;

  const handleChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleGetOTP = () => {
    if (!formData.email) {
      alert("Vui lòng nhập email trước khi lấy mã!");
      return;
    }
    setIsSending(true);
    // Giả lập gọi API gửi mail
    setTimeout(() => {
      alert(`Mã xác nhận đã được gửi đến email: ${formData.email}`);
      setIsSending(false);
      setCountdown(60); 
    }, 1000);
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    alert('🎉 Đăng ký thành công! Vui lòng đăng nhập.');
    navigate('/login');
  };

  return (
    <div className="bg-[#f0f0f0] min-h-screen py-10 flex justify-center px-4">
      <div className="bg-white rounded-lg shadow-sm w-full max-w-[450px] flex flex-col h-fit p-6 md:p-10 border border-gray-100">
        
        {/* Hệ thống Tabs */}
        <div className="flex mb-8 border-b border-gray-200">
          <Link to="/login" className="flex-1 text-center pb-3 text-gray-500 text-[17px] font-medium hover:text-[#157a2c] transition-colors">
            Đăng nhập
          </Link>
          <div className="flex-1 text-center pb-3 text-[#157a2c] text-[17px] font-medium border-b-2 border-[#157a2c] cursor-default">
            Đăng ký
          </div>
        </div>

        {/* Nội dung Form */}
        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          
          {/* Nhập Email + Nút Lấy mã */}
          <div>
            <label className="block text-[14px] text-gray-700 mb-1.5">Email</label>
            <div className="relative">
              <input 
                type="email" name="email" 
                placeholder="Nhập email" 
                className="w-full border border-gray-300 rounded py-2.5 px-3 pr-24 outline-none focus:border-[#157a2c] transition-all text-[15px]" 
                value={formData.email} onChange={handleChange} 
              />
              <button 
                type="button"
                disabled={countdown > 0 || isSending}
                onClick={handleGetOTP}
                className={`absolute right-1 top-1/2 -translate-y-1/2 px-3 py-1.5 rounded text-[13px] font-bold transition-all ${
                  countdown > 0 || isSending 
                    ? 'text-gray-400 cursor-not-allowed' 
                    : 'text-[#157a2c] hover:bg-green-50'
                }`}
              >
                {isSending ? "Đang gửi..." : countdown > 0 ? `Gửi lại (${countdown}s)` : "Lấy mã"}
              </button>
            </div>
          </div>

          {/* Nhập mã OTP */}
          <div>
            <label className="block text-[14px] text-gray-700 mb-1.5">Mã xác nhận</label>
            <input 
              type="text" name="otp" 
              placeholder="Nhập mã 6 ký tự" 
              maxLength="6"
              className="w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-[#157a2c] transition-all text-[15px]" 
              value={formData.otp} onChange={handleChange} 
            />
          </div>

          {/* Nhập Mật khẩu (Chỉ hiển thị khi đã nhập OTP) */}
          {formData.otp.length > 0 && (
            <div className="animate-fade-in">
              <label className="block text-[14px] text-gray-700 mb-1.5">Mật khẩu</label>
              <div className="relative">
                <input 
                  type={showPassword ? "text" : "password"} name="password" 
                  placeholder="Nhập mật khẩu" 
                  className="w-full border border-gray-300 rounded py-2.5 px-3 pr-12 outline-none focus:border-[#157a2c] transition-all text-[15px]" 
                  value={formData.password} onChange={handleChange} 
                />
                <button 
                  type="button"
                  className="absolute right-3 top-1/2 -translate-y-1/2 text-[#2489F4] text-[14px] font-medium hover:text-blue-700"
                  onClick={() => setShowPassword(!showPassword)}
                >
                  {showPassword ? "Ẩn" : "Hiện"}
                </button>
              </div>
            </div>
          )}

          {/* Cụm Nút bấm */}
          <div className="flex flex-col gap-3 mt-4">
            <button 
              type="submit" 
              disabled={isSubmitDisabled}
              className={`w-full py-2.5 rounded font-bold text-[16px] transition-colors ${
                isSubmitDisabled 
                  ? 'bg-[#e0e0e0] text-gray-500 cursor-not-allowed' 
                  : 'bg-[#157a2c] text-white hover:bg-green-800 shadow-sm'
              }`}
            >
              Đăng ký
            </button>
            
            <button 
              type="button" 
              onClick={() => navigate('/')}
              className="w-full py-2.5 rounded font-bold text-[16px] text-[#157a2c] bg-white border border-[#157a2c] hover:bg-green-50 transition-colors"
            >
              Bỏ qua
            </button>
          </div>

          <div className="mt-2 text-center text-[13px] text-gray-500 leading-relaxed">
            Bằng việc đăng ký, bạn đã đồng ý với Bookify về<br />
            <a href="#" className="text-[#157a2c] hover:underline font-medium">Điều khoản dịch vụ</a> & <a href="#" className="text-[#157a2c] hover:underline font-medium">Chính sách bảo mật</a>
          </div>
        </form>
        
      </div>
    </div>
  );
};

export default RegisterPage;