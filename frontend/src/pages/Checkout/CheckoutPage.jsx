// src/pages/Checkout/CheckoutPage.jsx
import { useState, useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiChevronRight, FiMapPin, FiTruck, FiCreditCard } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';
import { useCart } from '../../context/CartContext';

const SHIPPING_METHODS = [
  { id: 1, name: "Giao hàng tiêu chuẩn", fee: 25000, time: "Dự kiến giao trong 3-5 ngày" },
  { id: 2, name: "Giao hàng hỏa tốc", fee: 50000, time: "Dự kiến giao trong 24h" }
];

const CheckoutPage = () => {
  const navigate = useNavigate();
  const { cartItems } = useCart();
  
  // Lọc ra các sản phẩm đã được tích chọn trong giỏ hàng
  const selectedItems = useMemo(() => cartItems.filter(item => item.selected), [cartItems]);

  const [shippingInfo, setShippingInfo] = useState({
    fullName: '',
    phone: '',
    address: '',
    note: ''
  });

  const [selectedShipping, setSelectedShipping] = useState(SHIPPING_METHODS[0]);
  const [paymentMethod, setPaymentMethod] = useState('COD');

  const handleInputChange = (e) => {
    setShippingInfo({ ...shippingInfo, [e.target.name]: e.target.value });
  };

  const orderSummary = useMemo(() => {
    const subTotal = selectedItems.reduce((sum, item) => sum + (item.salePrice * item.quantity), 0);
    const shippingFee = selectedShipping.fee;
    const total = subTotal + shippingFee;
    return { subTotal, shippingFee, total };
  }, [selectedItems, selectedShipping]);

  const handlePlaceOrder = (e) => {
    e.preventDefault();
    if(selectedItems.length === 0) {
      alert("Bạn chưa chọn sản phẩm nào để thanh toán!");
      return;
    }
    
    const newOrder = {
      orderId: `ORD-${Math.floor(Math.random() * 10000)}`,
      ...shippingInfo,
      shippingMethod: selectedShipping.name,
      paymentMethod: paymentMethod,
      totalAmount: orderSummary.total,
      items: selectedItems,
      createdAt: new Date().toISOString()
    };
    
    const existingOrders = JSON.parse(localStorage.getItem('mockOrders')) || [];
    existingOrders.push(newOrder);
    localStorage.setItem('mockOrders', JSON.stringify(existingOrders));

    alert(`🎉 Đặt hàng thành công! Mã đơn của bạn là: ${newOrder.orderId}`);
    navigate('/'); 
  };

  return (
    <div className="bg-gray-50 min-h-screen pb-12">
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/cart" className="hover:text-[#157a2c] cursor-pointer">Giỏ hàng</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Thanh toán</span>
        </div>
      </div>

      <div className="container mx-auto px-4">
        <form onSubmit={handlePlaceOrder} className="flex flex-col lg:flex-row gap-8">
          
          <div className="lg:w-2/3 flex flex-col gap-6">
            <div className="bg-white p-6 rounded-lg shadow-sm">
              <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 border-b pb-2">
                <FiMapPin className="text-[#157a2c]" /> Địa chỉ giao hàng
              </h2>
              <div className="grid grid-cols-2 gap-4">
                <div className="col-span-2 md:col-span-1">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Họ tên người nhận <span className="text-red-500">*</span></label>
                  <input type="text" name="fullName" required placeholder="Nhập họ và tên" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" onChange={handleInputChange} />
                </div>
                <div className="col-span-2 md:col-span-1">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Số điện thoại <span className="text-red-500">*</span></label>
                  <input type="tel" name="phone" required placeholder="Nhập số điện thoại" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" onChange={handleInputChange} />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Địa chỉ nhận hàng <span className="text-red-500">*</span></label>
                  <input type="text" name="address" required placeholder="Ví dụ: 25 Chùa Láng, Đống Đa, Hà Nội" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" onChange={handleInputChange} />
                </div>
                <div className="col-span-2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Ghi chú đơn hàng (Tùy chọn)</label>
                  <textarea name="note" rows="2" placeholder="Ghi chú về thời gian hoặc địa điểm giao hàng..." className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" onChange={handleInputChange}></textarea>
                </div>
              </div>
            </div>

            <div className="bg-white p-6 rounded-lg shadow-sm">
              <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 border-b pb-2">
                <FiTruck className="text-[#157a2c]" /> Phương thức vận chuyển
              </h2>
              <div className="flex flex-col gap-3">
                {SHIPPING_METHODS.map(method => (
                  <label key={method.id} className={`flex items-start gap-3 p-3 border rounded-lg cursor-pointer transition ${selectedShipping.id === method.id ? 'border-[#157a2c] bg-green-50' : 'border-gray-200 hover:border-green-200'}`}>
                    <input 
                      type="radio" 
                      name="shipping" 
                      className="mt-1 w-4 h-4 text-[#157a2c]" 
                      checked={selectedShipping.id === method.id}
                      onChange={() => setSelectedShipping(method)}
                    />
                    <div className="flex-grow">
                      <div className="font-medium text-gray-800">{method.name}</div>
                      <div className="text-sm text-gray-500">{method.time}</div>
                    </div>
                    <div className="font-bold text-[#157a2c]">{formatCurrency(method.fee)}</div>
                  </label>
                ))}
              </div>
            </div>

            <div className="bg-white p-6 rounded-lg shadow-sm">
              <h2 className="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2 border-b pb-2">
                <FiCreditCard className="text-[#157a2c]" /> Phương thức thanh toán
              </h2>
              <div className="flex flex-col gap-3">
                <label className={`flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition ${paymentMethod === 'COD' ? 'border-[#157a2c] bg-green-50' : 'border-gray-200 hover:border-green-200'}`}>
                  <input type="radio" name="payment" value="COD" checked={paymentMethod === 'COD'} onChange={(e) => setPaymentMethod(e.target.value)} className="w-4 h-4 text-[#157a2c]" />
                  <span className="font-medium text-gray-800">Thanh toán khi nhận hàng (COD)</span>
                </label>
                <label className={`flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition ${paymentMethod === 'VNPay' ? 'border-[#157a2c] bg-green-50' : 'border-gray-200 hover:border-green-200'}`}>
                  <input type="radio" name="payment" value="VNPay" checked={paymentMethod === 'VNPay'} onChange={(e) => setPaymentMethod(e.target.value)} className="w-4 h-4 text-[#157a2c]" />
                  <span className="font-medium text-gray-800">Thanh toán qua VNPay</span>
                </label>
              </div>
            </div>
          </div>

          <div className="lg:w-1/3">
            <div className="bg-white p-6 rounded-lg shadow-sm sticky top-4">
              <h2 className="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Đơn hàng của bạn</h2>
              
              <div className="flex flex-col gap-4 mb-4 border-b border-gray-100 pb-4 max-h-60 overflow-y-auto">
                {selectedItems.length === 0 ? (
                  <div className="text-sm text-gray-500 text-center py-4">Chưa có sản phẩm nào được chọn.</div>
                ) : (
                  selectedItems.map(item => (
                    <div key={item.id} className="flex gap-3 items-center text-sm">
                      <img src={item.thumbnail} alt={item.name} className="w-12 h-16 object-cover border rounded" />
                      <div className="flex-grow">
                        <div className="font-medium text-gray-800 line-clamp-2">{item.name}</div>
                        <div className="text-gray-500">SL: x{item.quantity}</div>
                      </div>
                      <div className="font-bold text-gray-800">{formatCurrency(item.salePrice * item.quantity)}</div>
                    </div>
                  ))
                )}
              </div>

              <div className="flex flex-col gap-2 mb-4 text-sm text-gray-600 border-b border-gray-100 pb-4">
                <div className="flex justify-between">
                  <span>Tạm tính:</span>
                  <span className="font-medium text-gray-800">{formatCurrency(orderSummary.subTotal)}</span>
                </div>
                <div className="flex justify-between">
                  <span>Phí vận chuyển:</span>
                  <span className="font-medium text-gray-800">{formatCurrency(orderSummary.shippingFee)}</span>
                </div>
              </div>
              
              <div className="flex justify-between items-center mb-6">
                <span className="text-gray-800 font-bold">Tổng cộng:</span>
                <span className="text-2xl font-bold text-[#ff424e]">{formatCurrency(orderSummary.total)}</span>
              </div>

              <button type="submit" disabled={selectedItems.length === 0} className="w-full bg-[#157a2c] text-white py-3 rounded-md font-bold text-lg hover:bg-green-800 transition shadow-sm disabled:opacity-50">
                ĐẶT HÀNG
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};

export default CheckoutPage;