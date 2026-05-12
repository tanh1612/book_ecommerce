// src/pages/Profile/ProfilePage.jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiUser, FiMapPin, FiPackage, FiLogOut, FiChevronRight } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';

// IMPORT CÁC COMPONENT CON
import MyOrders from './MyOrders';
import AddressBook from './AddressBook';

const ProfilePage = () => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const [activeTab, setActiveTab] = useState('profile'); 

  // State lưu thông tin hồ sơ
  const [formData, setFormData] = useState({
    email: user?.email || "khachhang@bookify.vn",
    firstName: user?.firstName || "Quân",
    lastName: user?.lastName || "Lê Minh",
    phone: "0337706769",
    gender: "male",
    birthday: "2004-08-22"
  });

  const handleInputChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleUpdateProfile = (e) => {
    e.preventDefault();
    console.log("Cập nhật thông tin:", formData);
    alert("✅ Cập nhật thông tin tài khoản thành công!");
  };

  const handleLogout = () => {
    if(window.confirm("Bạn có chắc chắn muốn đăng xuất?")) {
      logout();
      navigate('/login');
    }
  };

  return (
    <div className="bg-gray-50 min-h-screen pb-12">
      {/* BREADCRUMB */}
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c] cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Tài khoản</span>
        </div>
      </div>

      <div className="container mx-auto px-4 flex flex-col md:flex-row gap-8">
        {/* === SIDEBAR MENU === */}
        <div className="w-full md:w-1/4">
          <div className="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
            <div className="p-6 border-b border-gray-100 flex items-center gap-4 bg-[#f0f9f3]">
              <div className="w-12 h-12 bg-[#157a2c] text-white rounded-full flex items-center justify-center font-bold text-xl uppercase">
                {formData.firstName.charAt(0)}
              </div>
              <div>
                <div className="text-sm text-gray-500">Tài khoản của</div>
                <div className="font-bold text-gray-800">{formData.lastName} {formData.firstName}</div>
              </div>
            </div>
            <ul className="flex flex-col text-gray-700">
              <li onClick={() => setActiveTab('profile')} className={`p-4 cursor-pointer flex items-center gap-3 transition-colors ${activeTab === 'profile' ? 'text-[#157a2c] bg-gray-50 border-l-4 border-[#157a2c] font-medium' : 'border-l-4 border-transparent hover:text-[#157a2c]'}`}><FiUser size={18} /> Hồ sơ của tôi</li>
              <li onClick={() => setActiveTab('orders')} className={`p-4 cursor-pointer flex items-center gap-3 transition-colors ${activeTab === 'orders' ? 'text-[#157a2c] bg-gray-50 border-l-4 border-[#157a2c] font-medium' : 'border-l-4 border-transparent hover:text-[#157a2c]'}`}><FiPackage size={18} /> Đơn hàng của tôi</li>
              <li onClick={() => setActiveTab('addresses')} className={`p-4 cursor-pointer flex items-center gap-3 transition-colors ${activeTab === 'addresses' ? 'text-[#157a2c] bg-gray-50 border-l-4 border-[#157a2c] font-medium' : 'border-l-4 border-transparent hover:text-[#157a2c]'}`}><FiMapPin size={18} /> Sổ địa chỉ</li>
              <li onClick={handleLogout} className="p-4 cursor-pointer flex items-center gap-3 text-red-500 hover:bg-red-50 border-l-4 border-transparent"><FiLogOut size={18} /> Đăng xuất</li>
            </ul>
          </div>
        </div>

        {/* === NỘI DUNG CHÍNH === */}
        <div className="w-full md:w-3/4">
          
          {/* TAB: HỒ SƠ CÁ NHÂN */}
          {activeTab === 'profile' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
              <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Hồ sơ của tôi</h1>
              <p className="text-sm text-gray-500 mb-8">Quản lý thông tin hồ sơ để bảo mật tài khoản</p>

              <form onSubmit={handleUpdateProfile} className="max-w-2xl">
                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Email đăng nhập</label>
                  <div className="md:w-3/4">
                    <input type="text" value={formData.email} disabled className="w-full border border-gray-200 bg-gray-100 rounded py-2 px-3 text-gray-500 cursor-not-allowed" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Họ & Tên</label>
                  <div className="md:w-3/4 flex gap-4">
                    <input type="text" name="lastName" value={formData.lastName} onChange={handleInputChange} placeholder="Họ" className="w-1/2 border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                    <input type="text" name="firstName" value={formData.firstName} onChange={handleInputChange} placeholder="Tên" className="w-1/2 border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Số điện thoại</label>
                  <div className="md:w-3/4">
                    <input type="tel" name="phone" value={formData.phone} onChange={handleInputChange} className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Giới tính</label>
                  <div className="md:w-3/4 flex gap-6">
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input type="radio" name="gender" value="male" checked={formData.gender === 'male'} onChange={handleInputChange} className="text-[#157a2c] focus:ring-[#157a2c]" /> Nam
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input type="radio" name="gender" value="female" checked={formData.gender === 'female'} onChange={handleInputChange} className="text-[#157a2c] focus:ring-[#157a2c]" /> Nữ
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer">
                      <input type="radio" name="gender" value="other" checked={formData.gender === 'other'} onChange={handleInputChange} className="text-[#157a2c] focus:ring-[#157a2c]" /> Khác
                    </label>
                  </div>
                </div>

                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-8">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Ngày sinh</label>
                  <div className="md:w-3/4">
                    <input type="date" name="birthday" value={formData.birthday} onChange={handleInputChange} className="border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row gap-2 md:gap-8">
                  <div className="md:w-1/4"></div>
                  <div className="md:w-3/4">
                    <button type="submit" className="bg-[#157a2c] text-white px-8 py-2.5 rounded hover:bg-green-800 transition font-medium shadow-sm">Lưu Thay Đổi</button>
                  </div>
                </div>
              </form>
            </div>
          )}

          {/* TAB: ĐƠN HÀNG VÀ ĐỊA CHỈ ĐƯỢC TÁCH RA FILE RIÊNG */}
          {activeTab === 'orders' && <MyOrders />}
          {activeTab === 'addresses' && <AddressBook />}
        </div>
      </div>
    </div>
  );
};

export default ProfilePage;