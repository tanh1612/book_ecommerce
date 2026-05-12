// src/pages/Auth/LoginPage.jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiChevronRight } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';

const LoginPage = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const navigate = useNavigate();
  const { login } = useAuth(); // Hút hàm login từ AuthContext

  const handleSubmit = (e) => {
    e.preventDefault();
    // Giả lập dữ liệu trả về từ API (khớp bảng user_profiles)
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
    <div className="bg-gray-50 min-h-screen pb-12">
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c] cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Đăng nhập</span>
        </div>
      </div>

      <div className="container mx-auto px-4 flex justify-center">
        <div className="bg-white p-8 rounded-lg shadow-sm border border-gray-100 w-full max-w-md">
          <h1 className="text-2xl font-bold text-gray-800 mb-6 text-center">ĐĂNG NHẬP</h1>
          
          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email của bạn <span className="text-red-500">*</span></label>
              <input 
                type="email" 
                required
                placeholder="Nhập email"
                className="w-full border border-gray-300 rounded-md py-2.5 px-4 outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c] transition-all"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Mật khẩu <span className="text-red-500">*</span></label>
              <input 
                type="password" 
                required
                placeholder="Nhập mật khẩu"
                className="w-full border border-gray-300 rounded-md py-2.5 px-4 outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c] transition-all"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
              />
            </div>

            <div className="flex justify-between items-center text-sm mt-2 mb-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" className="w-4 h-4 text-[#157a2c] rounded border-gray-300 focus:ring-[#157a2c]" />
                <span className="text-gray-600">Ghi nhớ đăng nhập</span>
              </label>
              <a href="#" className="text-[#157a2c] hover:underline font-medium">Quên mật khẩu?</a>
            </div>

            <button 
              type="submit" 
              className="w-full bg-[#157a2c] text-white font-bold py-3 rounded-md hover:bg-green-800 transition-colors shadow-sm"
            >
              ĐĂNG NHẬP
            </button>
          </form>

          <div className="mt-6 text-center text-sm text-gray-600 border-t border-gray-100 pt-6">
            Bạn chưa có tài khoản?{' '}
            <Link to="/register" className="text-[#157a2c] font-bold hover:underline">
              Đăng ký ngay
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default LoginPage;