// src/pages/Profile/MyOrders.jsx
import { useState } from 'react';
import { FiPackage, FiChevronRight } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';

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

const MyOrders = () => {
  const [selectedOrder, setSelectedOrder] = useState(null);

  const formatDateTime = (dateString) => {
    const date = new Date(dateString);
    return `${date.getHours()}:${date.getMinutes().toString().padStart(2, '0')} ${date.getDate()}/${date.getMonth() + 1}/${date.getFullYear()}`;
  };

  return (
    <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
      <h1 className="text-2xl font-bold text-gray-800 mb-6 border-b pb-4">Đơn hàng của tôi</h1>
      
      <div className="flex flex-col gap-6">
        {MOCK_ORDERS.map((order) => (
          <div key={order.id} className="border border-gray-200 rounded-lg overflow-hidden">
            <div className="bg-gray-50 p-4 border-b border-gray-200 flex justify-between items-center text-sm">
              <span className="font-bold text-gray-800">Mã đơn: #{order.id}</span>
              <span className="font-medium px-3 py-1 rounded-full text-xs bg-blue-100 text-blue-700">{order.currentStatus}</span>
            </div>
            <div className="p-4 flex flex-col gap-4">
              {order.items.map((item) => (
                <div key={item.id} className="flex gap-4 items-center">
                  <img src={item.thumbnail} alt={item.bookName} className="w-16 h-24 object-cover border border-gray-200 rounded" />
                  <div className="flex-grow">
                    <h3 className="font-medium text-gray-800 line-clamp-2">{item.bookName}</h3>
                    <div className="text-sm text-gray-500">Số lượng: x{item.quantity}</div>
                  </div>
                  <div className="font-bold text-[#157a2c]">{formatCurrency(item.price)}</div>
                </div>
              ))}
            </div>
            <div className="bg-gray-50 p-4 border-t border-gray-200 flex justify-between items-center">
              <div className="text-sm text-gray-600">
                Tổng tiền: <span className="text-lg font-bold text-[#ff424e]">{formatCurrency(order.finalAmount)}</span>
              </div>
              <button onClick={() => setSelectedOrder(order)} className="px-4 py-2 bg-[#157a2c] text-white rounded text-sm font-medium hover:bg-green-800 transition">Xem chi tiết</button>
            </div>
          </div>
        ))}
      </div>

      {/* Popup Chi tiết Đơn hàng */}
      {selectedOrder && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto flex flex-col">
            <div className="flex justify-between items-center p-5 border-b border-gray-100 sticky top-0 bg-white z-10">
              <h2 className="text-xl font-bold text-gray-800">Chi tiết đơn hàng #{selectedOrder.id}</h2>
              <button onClick={() => setSelectedOrder(null)} className="text-gray-400 hover:text-red-500 text-2xl font-bold">&times;</button>
            </div>
            <div className="p-6">
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
              {/* Thông tin giao hàng */}
              <div className="bg-gray-50 p-4 rounded-lg border border-gray-100 text-sm mb-6">
                <h3 className="font-bold text-gray-800 mb-2 uppercase text-xs">Địa chỉ nhận hàng</h3>
                <div className="font-medium">{selectedOrder.shippingName}</div>
                <div>{selectedOrder.shippingPhone}</div>
                <div>{selectedOrder.shippingAddress}</div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MyOrders;