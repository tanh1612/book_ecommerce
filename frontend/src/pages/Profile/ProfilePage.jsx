// src/pages/Profile/ProfilePage.jsx
import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiUser, FiMapPin, FiPackage, FiLogOut, FiChevronRight } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';

// Giả lập Dữ liệu Đơn hàng
const MOCK_ORDERS = [
  {
    id: 1001,
    createdAt: "2026-03-25T10:30:00",
    totalAmount: 150000,
    shippingFee: 25000,
    finalAmount: 175000,
    currentStatus: "Đang giao hàng",
    shippingName: "Nguyễn Văn Nam",
    shippingPhone: "0901234567",
    shippingAddress: "25 Chùa Láng, Đống Đa, Hà Nội",
    paymentMethod: "COD",
    items: [
      { id: 1, bookName: "Túp lều bác Tom (TB 2026)", thumbnail: "https://placehold.co/80x120/EEE/31343C?text=Tup+Leu", price: 150000, quantity: 1 }
    ],
    timeline: [
      { status: "Chờ xác nhận", date: "2026-03-25T10:30:00", note: "Đơn hàng đã được tạo" },
      { status: "Đã xác nhận", date: "2026-03-25T14:00:00", note: "Người bán đang chuẩn bị hàng" },
      { status: "Đang giao hàng", date: "2026-03-26T08:15:00", note: "Đơn hàng đã được giao cho đơn vị vận chuyển" }
    ]
  }
];

const formatDateTime = (dateString) => {
  const date = new Date(dateString);
  return `${date.getHours()}:${date.getMinutes().toString().padStart(2, '0')} ${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};

const ProfilePage = () => {
  const navigate = useNavigate();
  const { user, logout } = useAuth(); // Hút dữ liệu user từ AuthContext
  const [activeTab, setActiveTab] = useState('profile'); 
  const [selectedOrder, setSelectedOrder] = useState(null);

  // Đổ dữ liệu từ user đang đăng nhập vào form
  const [formData, setFormData] = useState({
    email: user?.email || "Chưa có email",
    firstName: user?.firstName || "Nam",
    lastName: user?.lastName || "Nguyễn",
    phone: "0901234567",
    gender: "male",
    birthday: "1998-05-20"
  });

  const handleInputChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleUpdateProfile = (e) => {
    e.preventDefault();
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
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c] cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Quản lý tài khoản</span>
        </div>
      </div>

      <div className="container mx-auto px-4 flex flex-col md:flex-row gap-8">
        <div className="w-full md:w-1/4">
          <div className="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100">
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
              <li 
                className={`flex items-center gap-3 p-4 cursor-pointer hover:bg-gray-50 hover:text-[#157a2c] transition ${activeTab === 'profile' ? 'text-[#157a2c] font-medium border-l-4 border-[#157a2c] bg-gray-50' : 'border-l-4 border-transparent'}`}
                onClick={() => setActiveTab('profile')}
              >
                <FiUser size={18} /> Thông tin tài khoản
              </li>
              <li 
                className={`flex items-center gap-3 p-4 cursor-pointer hover:bg-gray-50 hover:text-[#157a2c] transition ${activeTab === 'orders' ? 'text-[#157a2c] font-medium border-l-4 border-[#157a2c] bg-gray-50' : 'border-l-4 border-transparent'}`}
                onClick={() => setActiveTab('orders')}
              >
                <FiPackage size={18} /> Đơn hàng của tôi
              </li>
              <li 
                className={`flex items-center gap-3 p-4 cursor-pointer hover:bg-gray-50 hover:text-[#157a2c] transition ${activeTab === 'addresses' ? 'text-[#157a2c] font-medium border-l-4 border-[#157a2c] bg-gray-50' : 'border-l-4 border-transparent'}`}
                onClick={() => setActiveTab('addresses')}
              >
                <FiMapPin size={18} /> Sổ địa chỉ
              </li>
              <li 
                className="flex items-center gap-3 p-4 cursor-pointer text-red-500 hover:bg-red-50 transition border-l-4 border-transparent"
                onClick={handleLogout}
              >
                <FiLogOut size={18} /> Đăng xuất
              </li>
            </ul>
          </div>
        </div>

        <div className="w-full md:w-3/4">
          {activeTab === 'profile' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
              <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Hồ sơ của tôi</h1>
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

                <div className="flex flex-col md:flex-row gap-2 md:gap-8 mt-8">
                  <div className="md:w-1/4"></div>
                  <div className="md:w-3/4">
                    <button type="submit" className="bg-[#157a2c] text-white px-8 py-2.5 rounded hover:bg-green-800 transition font-medium shadow-sm">Lưu Thay Đổi</button>
                  </div>
                </div>
              </form>
            </div>
          )}

          {activeTab === 'orders' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
              <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Đơn hàng của tôi</h1>
              <div className="flex flex-col gap-6">
                {MOCK_ORDERS.map((order) => (
                  <div key={order.id} className="border border-gray-200 rounded-lg overflow-hidden">
                    <div className="bg-gray-50 p-4 border-b border-gray-200 flex justify-between items-center text-sm">
                      <div className="flex gap-4">
                        <span className="font-bold text-gray-800">Mã đơn: #{order.id}</span>
                      </div>
                      <span className="font-medium px-3 py-1 rounded-full text-xs bg-blue-100 text-blue-700">{order.currentStatus}</span>
                    </div>
                    <div className="bg-gray-50 p-4 border-t border-gray-200 flex justify-between items-center">
                      <div className="text-sm text-gray-600">
                        Tổng tiền: <span className="text-lg font-bold text-[#ff424e]">{order.finalAmount.toLocaleString('vi-VN')} đ</span>
                      </div>
                      <button onClick={() => setSelectedOrder(order)} className="px-4 py-2 bg-[#157a2c] text-white rounded text-sm font-medium hover:bg-green-800 transition shadow-sm">Xem chi tiết</button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto flex flex-col">
            <div className="flex justify-between items-center p-5 border-b border-gray-100 sticky top-0 bg-white z-10">
              <h2 className="text-xl font-bold text-gray-800">Chi tiết đơn hàng #{selectedOrder.id}</h2>
              <button onClick={() => setSelectedOrder(null)} className="text-gray-400 hover:text-red-500 text-2xl leading-none font-bold">&times;</button>
            </div>
            <div className="p-6">
              {/* Nội dung modal giữ nguyên như cũ để không làm dài code */}
              <p className="text-gray-500 text-center">Giao diện chi tiết đơn hàng hiển thị tại đây.</p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ProfilePage;