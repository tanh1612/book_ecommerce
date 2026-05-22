// src/pages/Profile/MyOrders.jsx
import { useState, useEffect } from 'react';
import { FiX, FiCheckCircle, FiAlertCircle } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { formatCurrency } from '../../utils/formatters';
import orderApi from '../../services/orderApi';

// Map trạng thái đơn hàng (Dựa theo OrderStatus.php)
const ORDER_STATUSES = {
  pending: { label: 'Chờ xử lý', color: 'text-yellow-600 bg-yellow-100' },
  confirmed: { label: 'Đã xác nhận', color: 'text-blue-600 bg-blue-100' },
  processing: { label: 'Đang xử lý', color: 'text-indigo-600 bg-indigo-100' },
  shipping: { label: 'Đang giao hàng', color: 'text-gray-600 bg-gray-200' },
  completed: { label: 'Hoàn tất', color: 'text-green-600 bg-green-100' },
  cancelled: { label: 'Đã hủy', color: 'text-red-600 bg-red-100' },
  refund_closed: { label: 'Đóng - Không hoàn tiền', color: 'text-gray-500 bg-gray-100' },
};

const MyOrders = () => {
  const [orders, setOrders] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  const [selectedOrder, setSelectedOrder] = useState(null); // Dùng cho Modal Chi tiết
  
  // Modal State
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [isRefundModalOpen, setIsRefundModalOpen] = useState(false);
  
  // Refund Form State
  const [refundBanks, setRefundBanks] = useState([]);
  const [refundData, setRefundData] = useState({
    bank_code: '',
    account_number: '',
    account_holder: ''
  });

  const fetchOrders = async () => {
    setIsLoading(true);
    try {
      const res = await orderApi.getOrders();
      // Laravel trả về data trong res.data.data
      setOrders(res.data?.data || []);
    } catch (error) {
      console.error("Lỗi tải đơn hàng:", error);
      toast.error("Không thể tải danh sách đơn hàng!");
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, []);

  // --- XỬ LÝ HỦY ĐƠN ---
  const handleCancelOrder = async () => {
    if (!selectedOrder) return;
    try {
      await orderApi.cancelOrder(selectedOrder.id);
      toast.success("Hủy đơn hàng thành công!");
      setIsCancelModalOpen(false);
      setSelectedOrder(null);
      fetchOrders(); // Tải lại danh sách
    } catch (error) {
      toast.error(error.response?.data?.message || "Lỗi khi hủy đơn hàng!");
    }
  };

  // --- XỬ LÝ HOÀN TIỀN ---
  const openRefundModal = async (order) => {
    setSelectedOrder(order);
    setIsRefundModalOpen(true);
    try {
      const res = await orderApi.getRefundBanks();
      setRefundBanks(res.data?.data || res.data || []);
    } catch (error) {
      toast.error("Không thể tải danh sách ngân hàng");
    }
  };

  const handleSubmitRefund = async (e) => {
    e.preventDefault();
    if (!refundData.bank_code || !refundData.account_number || !refundData.account_holder) {
      return toast.warning("Vui lòng điền đủ thông tin!");
    }
    
    try {
      await orderApi.submitRefundBank(selectedOrder.id, refundData);
      toast.success("Đã gửi thông tin nhận hoàn tiền thành công!");
      setIsRefundModalOpen(false);
      setRefundData({ bank_code: '', account_number: '', account_holder: '' });
      fetchOrders();
    } catch (error) {
      toast.error(error.response?.data?.message || "Lỗi gửi thông tin hoàn tiền!");
    }
  };

  // --- UI RENDERERS ---
  const renderOrderItems = (items) => {
    return items.map((item) => {
      const bookData = item.book || {};
      const bookName = bookData.name || bookData.title || "Sản phẩm không xác định";
      // Lấy ảnh đầu tiên trong mảng images, nếu không có thì lấy thumbnail_url
      const thumbnail = (bookData.images && bookData.images[0]?.url) || bookData.thumbnail_url || "https://placehold.co/80x120?text=No+Image";

      return (
        <div key={item.id} className="flex gap-4 items-center mb-4 last:mb-0">
          <img src={thumbnail} alt={bookName} className="w-16 h-24 object-cover border border-gray-200 rounded" />
          <div className="flex-grow">
            <h3 className="font-medium text-gray-800 line-clamp-2">{bookName}</h3>
            <div className="text-sm text-gray-500">Số lượng: x{item.quantity}</div>
          </div>
          <div className="font-bold text-[#157a2c]">{formatCurrency(item.price)}</div>
        </div>
      );
    });
  };

  if (isLoading) {
    return <div className="py-20 flex justify-center"><div className="w-8 h-8 border-4 border-[#157a2c] border-t-transparent rounded-full animate-spin"></div></div>;
  }

  return (
    <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
      <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Đơn hàng của tôi</h1>
      
      {orders.length === 0 ? (
        <div className="text-center py-10 text-gray-500">
          Bạn chưa có đơn hàng nào.
        </div>
      ) : (
        <div className="flex flex-col gap-6">
          {orders.map((order) => {
            const statusUi = ORDER_STATUSES[order.current_status] || { label: order.current_status, color: 'text-gray-600 bg-gray-100' };
            const isPaid = order.payment_status === 'paid' || order.payment_status === 'refunding';
            
            // Logic hiển thị nút Hoàn tiền: Bị hủy + Đã thanh toán (hoặc đang refunding)
            const canRefund = (order.current_status === 'cancelled' && isPaid) || order.payment_status === 'refunding';

            return (
              <div key={order.id} className="border border-gray-200 rounded-lg overflow-hidden">
                <div className="bg-gray-50 p-4 border-b border-gray-200 flex justify-between items-center text-sm">
                  <div className="flex gap-4 items-center">
                    <span className="font-bold text-gray-800">Mã đơn: #{order.id}</span>
                    <span className="text-gray-500">{new Date(order.created_at).toLocaleDateString('vi-VN')}</span>
                  </div>
                  <span className={`font-medium px-3 py-1 rounded-full text-xs ${statusUi.color}`}>
                    {statusUi.label}
                  </span>
                </div>
                
                <div className="p-4">
                  {renderOrderItems(order.items || [])}
                </div>

                <div className="bg-gray-50 p-4 border-t border-gray-200 flex flex-col sm:flex-row justify-between items-center gap-4">
                  <div className="text-sm text-gray-600">
                    Thành tiền: <span className="text-xl font-bold text-[#ff424e]">{formatCurrency(order.final_amount)}</span>
                  </div>
                  
                  <div className="flex gap-2 w-full sm:w-auto">
                    {/* NÚT HỦY ĐƠN (Chỉ hiện khi PENDING) */}
                    {order.current_status === 'pending' && (
                      <button 
                        onClick={() => { setSelectedOrder(order); setIsCancelModalOpen(true); }} 
                        className="px-4 py-2 border border-red-500 text-red-500 bg-white rounded text-sm font-medium hover:bg-red-50 transition w-full sm:w-auto"
                      >
                        Hủy đơn
                      </button>
                    )}

                    {/* NÚT HOÀN TIỀN */}
                    {canRefund && (
                      <button 
                        onClick={() => openRefundModal(order)} 
                        className="px-4 py-2 bg-blue-600 text-white rounded text-sm font-medium hover:bg-blue-700 transition w-full sm:w-auto flex items-center gap-2"
                      >
                        <FiAlertCircle /> Nhập TK Hoàn tiền
                      </button>
                    )}

                    {/* NÚT ĐÁNH GIÁ (Chỉ hiện khi COMPLETED) */}
                    {order.current_status === 'completed' && (
                      <button 
                        onClick={() => toast.info('Tính năng đánh giá đang được phát triển!')} 
                        className="px-4 py-2 border border-[#157a2c] text-[#157a2c] bg-white rounded text-sm font-medium hover:bg-green-50 transition w-full sm:w-auto"
                      >
                        Đánh giá
                      </button>
                    )}

                    <button 
                      onClick={() => toast.info('Tính năng xem chi tiết đang phát triển')} 
                      className="px-4 py-2 bg-[#157a2c] text-white rounded text-sm font-medium hover:bg-green-800 transition w-full sm:w-auto"
                    >
                      Chi tiết
                    </button>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* --- MODAL HỦY ĐƠN --- */}
      {isCancelModalOpen && selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
            <h2 className="text-xl font-bold text-gray-800 mb-4">Xác nhận hủy đơn</h2>
            <p className="text-gray-600 mb-6">Bạn có chắc chắn muốn hủy đơn hàng <strong>#{selectedOrder.id}</strong> không? Hành động này không thể hoàn tác.</p>
            <div className="flex gap-4 justify-end">
              <button onClick={() => setIsCancelModalOpen(false)} className="px-4 py-2 bg-gray-200 text-gray-800 rounded font-medium hover:bg-gray-300">Quay lại</button>
              <button onClick={handleCancelOrder} className="px-4 py-2 bg-red-600 text-white rounded font-medium hover:bg-red-700">Đồng ý Hủy</button>
            </div>
          </div>
        </div>
      )}

      {/* --- MODAL NHẬP THÔNG TIN HOÀN TIỀN --- */}
      {isRefundModalOpen && selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-lg shadow-2xl w-full max-w-md p-6">
            <div className="flex justify-between items-center mb-4">
              <h2 className="text-xl font-bold text-gray-800">Nhập thông tin hoàn tiền</h2>
              <button onClick={() => setIsRefundModalOpen(false)} className="text-gray-400 hover:text-red-500"><FiX size={24}/></button>
            </div>
            
            <p className="text-sm text-gray-600 mb-4 bg-blue-50 p-3 rounded border border-blue-100">
              Đơn hàng <strong>#{selectedOrder.id}</strong> của bạn đã được thanh toán nhưng giao không thành công / bị hủy. Vui lòng cung cấp STK để chúng tôi hoàn lại số tiền <strong>{formatCurrency(selectedOrder.final_amount)}</strong>.
            </p>

            <form onSubmit={handleSubmitRefund} className="flex flex-col gap-4">
              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Ngân hàng *</label>
                <select 
                  className="w-full border border-gray-300 rounded p-2 focus:border-[#157a2c] outline-none"
                  value={refundData.bank_code}
                  onChange={(e) => setRefundData({...refundData, bank_code: e.target.value})}
                  required
                >
                  <option value="">-- Chọn ngân hàng --</option>
                  {refundBanks.map(bank => (
                    <option key={bank.code} value={bank.code}>{bank.short_name} - {bank.name}</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Số tài khoản *</label>
                <input 
                  type="text" 
                  className="w-full border border-gray-300 rounded p-2 focus:border-[#157a2c] outline-none"
                  value={refundData.account_number}
                  onChange={(e) => setRefundData({...refundData, account_number: e.target.value})}
                  placeholder="Nhập số tài khoản..."
                  required
                />
              </div>

              <div>
                <label className="block text-sm text-gray-700 mb-1 font-medium">Tên chủ tài khoản *</label>
                <input 
                  type="text" 
                  className="w-full border border-gray-300 rounded p-2 focus:border-[#157a2c] outline-none uppercase"
                  value={refundData.account_holder}
                  onChange={(e) => setRefundData({...refundData, account_holder: e.target.value.toUpperCase()})}
                  placeholder="NGUYEN VAN A"
                  required
                />
              </div>

              <button type="submit" className="w-full bg-[#157a2c] text-white rounded p-3 font-bold hover:bg-green-800 transition mt-2">
                GỬI THÔNG TIN HOÀN TIỀN
              </button>
            </form>
          </div>
        </div>
      )}

    </div>
  );
};

export default MyOrders;