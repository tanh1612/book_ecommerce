// src/pages/Auth/RegisterPage.jsx
import { useState } from 'react';
import { Link } from 'react-router-dom';
import { FiChevronRight } from 'react-icons/fi';

const RegisterPage = () => {
  // Bám sát DB: accounts (email, password) + user_profiles (first_name, last_name)
  const [formData, setFormData] = useState({
    firstName: '',
    lastName: '',
    email: '',
    password: '',
    confirmPassword: ''
  });

  const handleChange = (e) => {
    setFormData({
      ...formData,
      [e.target.name]: e.target.value
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (formData.password !== formData.confirmPassword) {
      alert("Mật khẩu xác nhận không khớp!");
      return;
    }
    console.log('Đang xử lý đăng ký với:', formData);
    // Báo cáo khóa luận: Chỗ này sẽ gọi API Axios (POST /api/register)
  };

  return (
    <div className="bg-gray-50 min-h-screen pb-12">
      {/* BREADCRUMB */}
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c] cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Đăng ký tài khoản</span>
        </div>
      </div>

      <div className="container mx-auto px-4 flex justify-center">
        <div className="bg-white p-8 rounded-lg shadow-sm border border-gray-100 w-full max-w-lg">
          <h1 className="text-2xl font-bold text-gray-800 mb-6 text-center">TẠO TÀI KHOẢN MỚI</h1>
          
          <form onSubmit={handleSubmit} className="flex flex-col gap-4">
            
            <div className="flex gap-4">
              <div className="w-1/2">
                <label className="block text-sm font-medium text-gray-700 mb-1">Họ <span className="text-red-500">*</span></label>
                <input type="text" name="lastName" required placeholder="Nhập họ" className="w-full border border-gray-300 rounded-md py-2.5 px-4 outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c]" value={formData.lastName} onChange={handleChange} />
              </div>
              <div className="w-1/2">
                <label className="block text-sm font-medium text-gray-700 mb-1">Tên <span className="text-red-500">*</span></label>
                <input type="text" name="firstName" required placeholder="Nhập tên" className="w-full border border-gray-300 rounded-md py-2.5 px-4 outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c]" value={formData.firstName} onChange={handleChange} />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Email <span className="text-red-500">*</span></label>
              <input type="email" name="email" required placeholder="Nhập email hợp lệ" className="w-full border border-gray-300 rounded-md py-2.5 px-4 outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c]" value={formData.email} onChange={handleChange} />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Mật khẩu <span className="text-red-500">*</span></label>
              <input type="password" name="password" required placeholder="Tối thiểu 6 ký tự" className="w-full border border-gray-300 rounded-md py-2.5 px-4 outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c]" value={formData.password} onChange={handleChange} />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Xác nhận mật khẩu <span className="text-red-500">*</span></label>
              <input type="password" name="confirmPassword" required placeholder="Nhập lại mật khẩu" className="w-full border border-gray-300 rounded-md py-2.5 px-4 outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c]" value={formData.confirmPassword} onChange={handleChange} />
            </div>

            <button type="submit" className="w-full bg-[#157a2c] text-white font-bold py-3 mt-4 rounded-md hover:bg-green-800 transition-colors shadow-sm">
              ĐĂNG KÝ
            </button>
          </form>

          <div className="mt-6 text-center text-sm text-gray-600 border-t border-gray-100 pt-6">
            Đã có tài khoản?{' '}
            <Link to="/login" className="text-[#157a2c] font-bold hover:underline">
              Đăng nhập tại đây
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
};

export default RegisterPage;