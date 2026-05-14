// src/pages/Profile/ProfilePage.jsx
import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiUser, FiMapPin, FiPackage, FiLogOut, FiChevronRight, FiLock } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { useAuth } from '../../context/AuthContext';
import authApi from '../../services/authApi';

// IMPORT CÁC COMPONENT CON
import MyOrders from './MyOrders';
import AddressBook from './AddressBook';

const ProfilePage = () => {
  const navigate = useNavigate();
  const { user, login, logout } = useAuth();
  const [activeTab, setActiveTab] = useState('profile'); 
  const [loadingProfile, setLoadingProfile] = useState(false);
  const [loadingPassword, setLoadingPassword] = useState(false);

  // State lưu thông tin hồ sơ (Khớp với Key Backend: first_name, last_name...)
  const [formData, setFormData] = useState({
    email: user?.email || "",
    first_name: user?.first_name || user?.firstName || "",
    last_name: user?.last_name || user?.lastName || "",
    phone: user?.phone || "",
    gender: user?.gender || "male",
    birthday: user?.birthday || ""
  });

  // State lưu thông tin đổi mật khẩu
  const [passwordData, setPasswordData] = useState({
    current_password: '',
    password: '',
    password_confirmation: ''
  });

  // Cập nhật state nếu user context thay đổi (khi F5 trang)
  useEffect(() => {
    if (user) {
      setFormData({
        email: user.email || "",
        first_name: user.first_name || user.firstName || "",
        last_name: user.last_name || user.lastName || "",
        phone: user.phone || "",
        gender: user.gender || "male",
        birthday: user.birthday || ""
      });
    }
  }, [user]);

  const handleInputChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handlePasswordChange = (e) => {
    setPasswordData({ ...passwordData, [e.target.name]: e.target.value });
  };

  // GỌI API: Cập nhật thông tin cá nhân
  const handleUpdateProfile = async (e) => {
    e.preventDefault();
    try {
      setLoadingProfile(true);
      await authApi.updateProfile({
        first_name: formData.first_name,
        last_name: formData.last_name,
        phone: formData.phone,
        gender: formData.gender,
        birthday: formData.birthday
      });
      
      // Gọi lại API lấy user mới nhất để cập nhật trên Context
      const resUser = await authApi.getProfile();
      login(resUser.data);
      
      toast.success("Cập nhật thông tin tài khoản thành công!");
    } catch (err) {
      toast.error(err.response?.data?.message || "Lỗi cập nhật thông tin!");
    } finally {
      setLoadingProfile(false);
    }
  };

  // GỌI API: Đổi mật khẩu
  const handleSubmitPassword = async (e) => {
    e.preventDefault();
    const passRegex = /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/;
    
    if (!passRegex.test(passwordData.password)) {
      return toast.error("Mật khẩu mới phải từ 8 ký tự, gồm cả chữ và số!");
    }
    if (passwordData.password !== passwordData.password_confirmation) {
      return toast.error("Mật khẩu xác nhận không khớp!");
    }

    try {
      setLoadingPassword(true);
      await authApi.changePassword(passwordData);
      toast.success("Đổi mật khẩu thành công!");
      setPasswordData({ current_password: '', password: '', password_confirmation: '' });
    } catch (err) {
      toast.error(err.response?.data?.message || "Đổi mật khẩu thất bại, vui lòng kiểm tra lại mật khẩu cũ!");
    } finally {
      setLoadingPassword(false);
    }
  };

  const handleLogout = async () => {
    if(window.confirm("Bạn có chắc chắn muốn đăng xuất?")) {
      try {
        await authApi.logout();
        logout(); // Xóa khỏi context
        toast.success("Đăng xuất thành công");
        navigate('/login');
      } catch(err) {
        toast.error("Lỗi đăng xuất!");
      }
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
                {formData.first_name ? formData.first_name.charAt(0) : "U"}
              </div>
              <div>
                <div className="text-sm text-gray-500">Tài khoản của</div>
                <div className="font-bold text-gray-800">{formData.last_name} {formData.first_name}</div>
              </div>
            </div>
            <ul className="flex flex-col text-gray-700">
              <li onClick={() => setActiveTab('profile')} className={`p-4 cursor-pointer flex items-center gap-3 transition-colors ${activeTab === 'profile' ? 'text-[#157a2c] bg-gray-50 border-l-4 border-[#157a2c] font-medium' : 'border-l-4 border-transparent hover:text-[#157a2c]'}`}><FiUser size={18} /> Hồ sơ của tôi</li>
              
              {/* THÊM MENU ĐỔI MẬT KHẨU VÀO ĐÂY */}
              <li onClick={() => setActiveTab('password')} className={`p-4 cursor-pointer flex items-center gap-3 transition-colors ${activeTab === 'password' ? 'text-[#157a2c] bg-gray-50 border-l-4 border-[#157a2c] font-medium' : 'border-l-4 border-transparent hover:text-[#157a2c]'}`}><FiLock size={18} /> Đổi mật khẩu</li>

              <li onClick={() => setActiveTab('orders')} className={`p-4 cursor-pointer flex items-center gap-3 transition-colors ${activeTab === 'orders' ? 'text-[#157a2c] bg-gray-50 border-l-4 border-[#157a2c] font-medium' : 'border-l-4 border-transparent hover:text-[#157a2c]'}`}><FiPackage size={18} /> Đơn hàng của tôi</li>
              <li onClick={() => setActiveTab('addresses')} className={`p-4 cursor-pointer flex items-center gap-3 transition-colors ${activeTab === 'addresses' ? 'text-[#157a2c] bg-gray-50 border-l-4 border-[#157a2c] font-medium' : 'border-l-4 border-transparent hover:text-[#157a2c]'}`}><FiMapPin size={18} /> Sổ địa chỉ</li>
              <li onClick={handleLogout} className="p-4 cursor-pointer flex items-center gap-3 text-red-500 hover:bg-red-50 border-l-4 border-transparent"><FiLogOut size={18} /> Đăng xuất</li>
            </ul>
          </div>
        </div>

        {/* === NỘI DUNG CHÍNH === */}
        <div className="w-full md:w-3/4">
          
          {/* TAB 1: HỒ SƠ CÁ NHÂN */}
          {activeTab === 'profile' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100 animate-fade-in">
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
                    <input type="text" name="last_name" value={formData.last_name} onChange={handleInputChange} placeholder="Họ" className="w-1/2 border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                    <input type="text" name="first_name" value={formData.first_name} onChange={handleInputChange} placeholder="Tên" className="w-1/2 border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
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
                    <button type="submit" disabled={loadingProfile} className={`bg-[#157a2c] text-white px-8 py-2.5 rounded hover:bg-green-800 transition font-medium shadow-sm ${loadingProfile ? 'opacity-70 cursor-not-allowed' : ''}`}>
                      {loadingProfile ? "Đang lưu..." : "Lưu Thay Đổi"}
                    </button>
                  </div>
                </div>
              </form>
            </div>
          )}

          {/* TAB 2: ĐỔI MẬT KHẨU */}
          {activeTab === 'password' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100 animate-fade-in">
              <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Đổi mật khẩu</h1>
              <p className="text-sm text-gray-500 mb-8">Để bảo mật tài khoản, vui lòng không chia sẻ mật khẩu cho người khác</p>

              <form onSubmit={handleSubmitPassword} className="max-w-2xl">
                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/3 text-sm font-medium text-gray-700 md:text-right">Mật khẩu hiện tại</label>
                  <div className="md:w-2/3">
                    <input type="password" name="current_password" required value={passwordData.current_password} onChange={handlePasswordChange} placeholder="Nhập mật khẩu hiện tại" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/3 text-sm font-medium text-gray-700 md:text-right">Mật khẩu mới</label>
                  <div className="md:w-2/3">
                    <input type="password" name="password" required value={passwordData.password} onChange={handlePasswordChange} placeholder="Ít nhất 8 ký tự, gồm cả chữ và số" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-8">
                  <label className="md:w-1/3 text-sm font-medium text-gray-700 md:text-right">Xác nhận mật khẩu</label>
                  <div className="md:w-2/3">
                    <input type="password" name="password_confirmation" required value={passwordData.password_confirmation} onChange={handlePasswordChange} placeholder="Nhập lại mật khẩu mới" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row gap-2 md:gap-8">
                  <div className="md:w-1/3"></div>
                  <div className="md:w-2/3">
                    <button type="submit" disabled={loadingPassword} className={`bg-[#157a2c] text-white px-8 py-2.5 rounded hover:bg-green-800 transition font-medium shadow-sm ${loadingPassword ? 'opacity-70 cursor-not-allowed' : ''}`}>
                      {loadingPassword ? "Đang xử lý..." : "Đổi mật khẩu"}
                    </button>
                  </div>
                </div>
              </form>
            </div>
          )}

          {/* TAB 3 & 4 */}
          {activeTab === 'orders' && <div className="animate-fade-in"><MyOrders /></div>}
          {activeTab === 'addresses' && <div className="animate-fade-in"><AddressBook /></div>}
        </div>
      </div>
    </div>
  );
};

export default ProfilePage;