// src/pages/Profile/ProfilePage.jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiUser, FiMapPin, FiPackage, FiLogOut, FiChevronRight } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';

// MOCK DATA: Kết hợp từ bảng accounts và user_profiles
const MOCK_USER_PROFILE = {
  email: "khachhang@nhanam.vn",
  firstName: "Nam",
  lastName: "Nguyễn Văn",
  phone: "0901234567",
  gender: "male", // male, female, other
  birthday: "1998-05-20"
};

// MOCK DATA: Kết hợp từ Order.orders, Order.order_items và Order.order_timelines
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
  },
  {
    id: 1002,
    createdAt: "2026-03-10T14:15:00",
    totalAmount: 201600,
    shippingFee: 56400,
    finalAmount: 258000,
    currentStatus: "Hoàn thành",
    shippingName: "Nguyễn Văn Nam",
    shippingPhone: "0901234567",
    shippingAddress: "Tòa nhà Bitexco, Quận 1, TP.HCM",
    paymentMethod: "VNPay",
    items: [
      { id: 2, bookName: "Người tình Sputnik", thumbnail: "https://placehold.co/80x120/EEE/31343C?text=Sputnik", price: 81600, quantity: 1 },
      { id: 3, bookName: "Tàn ngày để lại (TB 2026)", thumbnail: "https://placehold.co/80x120/EEE/31343C?text=Tan+Ngay", price: 120000, quantity: 1 }
    ],
    timeline: [
      { status: "Chờ xác nhận", date: "2026-03-10T14:15:00", note: "Đơn hàng đã được tạo" },
      { status: "Đã xác nhận", date: "2026-03-10T15:00:00", note: "Người bán đang chuẩn bị hàng" },
      { status: "Đang giao hàng", date: "2026-03-11T09:00:00", note: "Đơn hàng đã được giao cho đơn vị vận chuyển" },
      { status: "Hoàn thành", date: "2026-03-13T16:30:00", note: "Giao hàng thành công" }
    ]
  }
];

// Hàm format hiển thị ngày giờ chi tiết
const formatDateTime = (dateString) => {
  const date = new Date(dateString);
  return `${date.getHours()}:${date.getMinutes().toString().padStart(2, '0')} ${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};

// Hàm format ngày tháng (Thêm hàm này ở ngoài component ProfilePage)
const formatDate = (dateString) => {
  const date = new Date(dateString);
  return `${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
};


const ProfilePage = () => {
  const navigate = useNavigate();
  // State quản lý Tab đang mở (mặc định là 'profile')
  const [activeTab, setActiveTab] = useState('profile'); 
  
  // State quản lý dữ liệu form
  const [formData, setFormData] = useState(MOCK_USER_PROFILE);

  const [selectedOrder, setSelectedOrder] = useState(null);

  const handleInputChange = (e) => {
    setFormData({ ...formData, [e.target.name]: e.target.value });
  };

  const handleUpdateProfile = (e) => {
    e.preventDefault();
    console.log("Dữ liệu cập nhật gửi lên Backend (bảng user_profiles):", formData);
    alert("✅ Cập nhật thông tin tài khoản thành công!");
  };

  const handleLogout = () => {
    if(window.confirm("Bạn có chắc chắn muốn đăng xuất?")) {
      localStorage.removeItem('activeUser');
      navigate('/login');
      window.location.reload();
    }
  };

  return (
    <div className="bg-gray-50 min-h-screen pb-12">
      {/* BREADCRUMB */}
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c] cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Quản lý tài khoản</span>
        </div>
      </div>

      <div className="container mx-auto px-4 flex flex-col md:flex-row gap-8">
        
        {/* === CỘT TRÁI: SIDEBAR MENU === */}
        <div className="w-full md:w-1/4">
          <div className="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-100">
            {/* Avatar & Tên (Mock) */}
            <div className="p-6 border-b border-gray-100 flex items-center gap-4 bg-[#f0f9f3]">
              <div className="w-12 h-12 bg-[#157a2c] text-white rounded-full flex items-center justify-center font-bold text-xl">
                {formData.firstName.charAt(0)}
              </div>
              <div>
                <div className="text-sm text-gray-500">Tài khoản của</div>
                <div className="font-bold text-gray-800">{formData.lastName} {formData.firstName}</div>
              </div>
            </div>

            {/* Menu Tabs */}
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

        {/* === CỘT PHẢI: NỘI DUNG TƯƠNG ỨNG VỚI TAB === */}
        <div className="w-full md:w-3/4">
          
          {/* TAB 1: THÔNG TIN TÀI KHOẢN */}
          {activeTab === 'profile' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
              <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Hồ sơ của tôi</h1>
              <p className="text-sm text-gray-500 mb-8">Quản lý thông tin hồ sơ để bảo mật tài khoản</p>

              <form onSubmit={handleUpdateProfile} className="max-w-2xl">
                {/* Email - Bảng accounts (Chỉ đọc) */}
                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Email đăng nhập</label>
                  <div className="md:w-3/4">
                    <input type="text" value={formData.email} disabled className="w-full border border-gray-200 bg-gray-100 rounded py-2 px-3 text-gray-500 cursor-not-allowed" />
                  </div>
                </div>

                {/* Họ & Tên - Bảng user_profiles */}
                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Họ & Tên</label>
                  <div className="md:w-3/4 flex gap-4">
                    <input type="text" name="lastName" value={formData.lastName} onChange={handleInputChange} placeholder="Họ" className="w-1/2 border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                    <input type="text" name="firstName" value={formData.firstName} onChange={handleInputChange} placeholder="Tên" className="w-1/2 border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                {/* Số điện thoại */}
                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-6">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Số điện thoại</label>
                  <div className="md:w-3/4">
                    <input type="tel" name="phone" value={formData.phone} onChange={handleInputChange} className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                {/* Giới tính */}
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

                {/* Ngày sinh */}
                <div className="flex flex-col md:flex-row md:items-center gap-2 md:gap-8 mb-8">
                  <label className="md:w-1/4 text-sm font-medium text-gray-700 md:text-right">Ngày sinh</label>
                  <div className="md:w-3/4">
                    <input type="date" name="birthday" value={formData.birthday} onChange={handleInputChange} className="border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" />
                  </div>
                </div>

                <div className="flex flex-col md:flex-row gap-2 md:gap-8">
                  <div className="md:w-1/4"></div>
                  <div className="md:w-3/4">
                    <button type="submit" className="bg-[#157a2c] text-white px-8 py-2.5 rounded hover:bg-green-800 transition font-medium shadow-sm">
                      Lưu Thay Đổi
                    </button>
                  </div>
                </div>
              </form>
            </div>
          )}

 {/* TAB 2: ĐƠN HÀNG */}
          {activeTab === 'orders' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
              <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Đơn hàng của tôi</h1>
              
              {/* Thanh lọc trạng thái đơn hàng */}
              <div className="flex gap-6 border-b border-gray-200 mb-6 overflow-x-auto text-sm font-medium">
                <button className="pb-3 border-b-2 border-[#157a2c] text-[#157a2c] whitespace-nowrap">Tất cả đơn</button>
                <button className="pb-3 border-b-2 border-transparent text-gray-500 hover:text-[#157a2c] whitespace-nowrap">Chờ xác nhận</button>
                <button className="pb-3 border-b-2 border-transparent text-gray-500 hover:text-[#157a2c] whitespace-nowrap">Đang giao</button>
                <button className="pb-3 border-b-2 border-transparent text-gray-500 hover:text-[#157a2c] whitespace-nowrap">Hoàn thành</button>
                <button className="pb-3 border-b-2 border-transparent text-gray-500 hover:text-[#157a2c] whitespace-nowrap">Đã hủy</button>
              </div>

              {/* Danh sách đơn hàng */}
              <div className="flex flex-col gap-6">
                {MOCK_ORDERS.map((order) => (
                  <div key={order.id} className="border border-gray-200 rounded-lg overflow-hidden">
                    
                    {/* Header đơn hàng: Mã đơn, Trạng thái, Ngày đặt */}
                    <div className="bg-gray-50 p-4 border-b border-gray-200 flex justify-between items-center text-sm">
                      <div className="flex gap-4">
                        <span className="font-bold text-gray-800">Mã đơn: #{order.id}</span>
                        <span className="text-gray-500">Ngày đặt: {formatDate(order.createdAt)}</span>
                      </div>
                      <span className={`font-medium px-3 py-1 rounded-full text-xs ${
                        order.currentStatus === 'Hoàn thành' ? 'bg-green-100 text-green-700' : 
                        order.currentStatus === 'Đang giao hàng' ? 'bg-blue-100 text-blue-700' : 
                        'bg-orange-100 text-orange-700'
                      }`}>
                        {order.currentStatus}
                      </span>
                    </div>

                    {/* Danh sách sách trong đơn (Order Items) */}
                    <div className="p-4 flex flex-col gap-4">
                      {order.items.map((item) => (
                        <div key={item.id} className="flex gap-4 items-center border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                          <img src={item.thumbnail} alt={item.bookName} className="w-16 h-24 object-cover border border-gray-200 rounded" />
                          <div className="flex-grow">
                            <h3 className="font-medium text-gray-800 mb-1 line-clamp-2">{item.bookName}</h3>
                            <div className="text-sm text-gray-500">Số lượng: x{item.quantity}</div>
                          </div>
                          <div className="font-bold text-[#157a2c] text-right min-w-[100px]">
                            {/* Chú ý: import hàm formatCurrency hoặc dùng Intl.NumberFormat */}
                            {item.price.toLocaleString('vi-VN')} đ
                          </div>
                        </div>
                      ))}
                    </div>

                    {/* Footer đơn hàng: Tổng tiền & Nút thao tác */}
                    <div className="bg-gray-50 p-4 border-t border-gray-200 flex justify-between items-center">
                      <div className="text-sm text-gray-600">
                        Tổng tiền: <span className="text-lg font-bold text-[#ff424e]">{order.finalAmount.toLocaleString('vi-VN')} đ</span>
                      </div>
                      <div className="flex gap-3">
                        {order.currentStatus === 'Hoàn thành' && (
                          <button className="px-4 py-2 border border-[#157a2c] text-[#157a2c] rounded text-sm font-medium hover:bg-green-50 transition">
                            Mua lại
                          </button>
                        )}
                        <button 
                                onClick={() => setSelectedOrder(order)} 
                                className="px-4 py-2 bg-[#157a2c] text-white rounded text-sm font-medium hover:bg-green-800 transition shadow-sm"
                            >
                                Xem chi tiết
                            </button>
                      </div>
                    </div>

                  </div>
                ))}
              </div>
              
            </div>
          )}
          {/* TAB 3: SỔ ĐỊA CHỈ (Placeholder) */}
          {activeTab === 'addresses' && (
            <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
              <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4 flex justify-between items-center">
                Sổ địa chỉ
                <button className="text-sm bg-[#157a2c] text-white px-4 py-2 rounded font-normal hover:bg-green-800 transition">+ Thêm địa chỉ mới</button>
              </h1>
              <div className="text-center py-10 text-gray-500">
                Giao diện danh sách sổ địa chỉ sẽ được xây dựng ở phần sau...
              </div>
            </div>
          )}

        </div>
      </div>
      {/* ========================================= */}
      {/* POPUP (MODAL) CHI TIẾT ĐƠN HÀNG */}
      {/* ========================================= */}
      {selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto flex flex-col">
            
            {/* Header Popup */}
            <div className="flex justify-between items-center p-5 border-b border-gray-100 sticky top-0 bg-white z-10">
              <h2 className="text-xl font-bold text-gray-800">Chi tiết đơn hàng #{selectedOrder.id}</h2>
              <button 
                onClick={() => setSelectedOrder(null)}
                className="text-gray-400 hover:text-red-500 text-2xl leading-none font-bold"
              >
                &times;
              </button>
            </div>

            <div className="p-6">
              {/* Thanh tiến trình (Stepper) - Phô diễn bảng order_timelines */}
              <div className="mb-8">
                <h3 className="font-bold text-gray-800 mb-4 border-b pb-2">Lịch sử đơn hàng</h3>
                <div className="relative border-l-2 border-[#157a2c] ml-3 flex flex-col gap-6">
                  {selectedOrder.timeline.map((step, index) => (
                    <div key={index} className="relative pl-6">
                      <div className="absolute w-4 h-4 bg-[#157a2c] rounded-full -left-[9px] top-1 border-4 border-white"></div>
                      <div className="font-bold text-[#157a2c] text-sm">{step.status}</div>
                      <div className="text-gray-500 text-xs mb-1">{formatDateTime(step.date)}</div>
                      <div className="text-gray-700 text-sm">{step.note}</div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Khối Thông tin Giao hàng & Thanh toán */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 bg-gray-50 p-4 rounded-lg border border-gray-100 text-sm">
                <div>
                  <h3 className="font-bold text-gray-800 mb-2 uppercase text-xs">Địa chỉ nhận hàng</h3>
                  <div className="font-medium text-gray-800">{selectedOrder.shippingName}</div>
                  <div className="text-gray-600">SĐT: {selectedOrder.shippingPhone}</div>
                  <div className="text-gray-600">{selectedOrder.shippingAddress}</div>
                </div>
                <div>
                  <h3 className="font-bold text-gray-800 mb-2 uppercase text-xs">Hình thức thanh toán</h3>
                  <div className="text-gray-600">{selectedOrder.paymentMethod === 'COD' ? 'Thanh toán khi nhận hàng (COD)' : `Thanh toán qua ${selectedOrder.paymentMethod}`}</div>
                </div>
              </div>

              {/* Danh sách sản phẩm chi tiết */}
              <div className="mb-6">
                <h3 className="font-bold text-gray-800 mb-4 border-b pb-2">Sản phẩm đã mua</h3>
                <div className="flex flex-col gap-4">
                  {selectedOrder.items.map((item) => (
                    <div key={item.id} className="flex gap-4 items-center">
                      <img src={item.thumbnail} alt={item.bookName} className="w-16 h-24 object-cover border border-gray-200 rounded" />
                      <div className="flex-grow">
                        <h4 className="font-medium text-gray-800 line-clamp-2">{item.bookName}</h4>
                        <div className="text-sm text-gray-500">SL: x{item.quantity}</div>
                      </div>
                      <div className="font-bold text-gray-800">{(item.price * item.quantity).toLocaleString('vi-VN')} đ</div>
                    </div>
                  ))}
                </div>
              </div>

              {/* Tổng kết tiền */}
              <div className="border-t border-gray-200 pt-4 flex flex-col gap-2 text-sm text-gray-600 items-end">
                <div className="flex justify-between w-full max-w-xs">
                  <span>Tạm tính:</span>
                  <span className="font-medium text-gray-800">{selectedOrder.totalAmount.toLocaleString('vi-VN')} đ</span>
                </div>
                <div className="flex justify-between w-full max-w-xs">
                  <span>Phí vận chuyển:</span>
                  <span className="font-medium text-gray-800">{selectedOrder.shippingFee.toLocaleString('vi-VN')} đ</span>
                </div>
                <div className="flex justify-between w-full max-w-xs text-base mt-2">
                  <span className="font-bold text-gray-800">Tổng cộng:</span>
                  <span className="font-bold text-[#ff424e] text-xl">{selectedOrder.finalAmount.toLocaleString('vi-VN')} đ</span>
                </div>
              </div>

            </div>
          </div>
        </div>
      )}
      {/* ========================================= */}
    </div>
  );
};

export default ProfilePage;