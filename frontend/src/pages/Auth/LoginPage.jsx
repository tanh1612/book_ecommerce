// src/pages/Auth/LoginPage.jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';

const LoginPage = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const navigate = useNavigate();
  const { login } = useAuth();

  // Kiểm tra xem đã nhập đủ thông tin chưa để làm sáng nút Đăng nhập
  const isSubmitDisabled = !email || !password;

  const handleSubmit = (e) => {
    e.preventDefault();
    const mockUser = {
      id: 1,
      firstName: "Quân",
      lastName: "Lê Minh",
      email: email
    };
    login(mockUser);
    navigate('/');
  };

  return (
    <div className="bg-[#f0f0f0] min-h-screen py-10 flex justify-center px-4">
      {/* Khung Card chính */}
      <div className="bg-white rounded-lg shadow-sm w-full max-w-[450px] flex flex-col h-fit p-6 md:p-10 border border-gray-100">
        
        {/* Hệ thống Tabs */}
        <div className="flex mb-8 border-b border-gray-200">
          <div className="flex-1 text-center pb-3 text-[#157a2c] text-[17px] font-medium border-b-2 border-[#157a2c] cursor-default">
            Đăng nhập
          </div>
          <Link to="/register" className="flex-1 text-center pb-3 text-gray-500 text-[17px] font-medium hover:text-[#157a2c] transition-colors">
            Đăng ký
          </Link>
        </div>

        {/* Nội dung Form */}
        <form onSubmit={handleSubmit} className="flex flex-col gap-5">
          {/* Cột Email */}
          <div>
            <label className="block text-[14px] text-gray-700 mb-1.5">Email</label>
            <input 
              type="email" 
              placeholder="Nhập email của bạn"
              className="w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-[#157a2c] transition-all text-[15px]"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          {/* Cột Mật khẩu */}
          <div>
            <label className="block text-[14px] text-gray-700 mb-1.5">Mật khẩu</label>
            <div className="relative">
              <input 
                type={showPassword ? "text" : "password"} 
                placeholder="Nhập mật khẩu"
                className="w-full border border-gray-300 rounded py-2.5 px-3 pr-12 outline-none focus:border-[#157a2c] transition-all text-[15px]"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
              {/* Nút Hiện/Ẩn mật khẩu (màu xanh dương giống ảnh) */}
              <button 
                type="button"
                className="absolute right-3 top-1/2 -translate-y-1/2 text-[#2489F4] text-[14px] font-medium hover:text-blue-700"
                onClick={() => setShowPassword(!showPassword)}
              >
                {showPassword ? "Ẩn" : "Hiện"}
              </button>
            </div>
          </div>

          {/* Quên mật khẩu */}
          <div className="text-right mt-1">
            <a href="#" className="text-[#157a2c] text-[14px] hover:underline">Quên mật khẩu?</a>
          </div>

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
              Đăng nhập
            </button>
            
            <button 
              type="button" 
              onClick={() => navigate('/')}
              className="w-full py-2.5 rounded font-bold text-[16px] text-[#157a2c] bg-white border border-[#157a2c] hover:bg-green-50 transition-colors"
            >
              Bỏ qua
            </button>
          </div>
        </form>
        
      </div>
    </div>
  );
};

export default LoginPage;