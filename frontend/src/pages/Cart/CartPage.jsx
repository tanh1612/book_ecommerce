// src/pages/Cart/CartPage.jsx
import { useMemo } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiTrash2, FiMinus, FiPlus, FiChevronRight } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';
import { useCart } from '../../context/CartContext';

const CartPage = () => {
  const navigate = useNavigate();
  const { cartItems, updateQuantity, removeFromCart, toggleSelect, toggleAll } = useCart();

  const handleUpdateQuantity = (id, newQuantity, inStock) => {
    if (newQuantity >= 1 && newQuantity <= inStock) {
      updateQuantity(id, newQuantity);
    }
  };

  const isAllSelected = cartItems.length > 0 && cartItems.every(item => item.selected);
  
  const cartSummary = useMemo(() => {
    const selectedItems = cartItems.filter(item => item.selected);
    const totalItems = selectedItems.reduce((sum, item) => sum + item.quantity, 0);
    const totalPrice = selectedItems.reduce((sum, item) => sum + (item.salePrice * item.quantity), 0);
    return { totalItems, totalPrice };
  }, [cartItems]);

  return (
    <div className="bg-gray-50 min-h-screen pb-12">
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c] cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Giỏ hàng của bạn</span>
        </div>
      </div>

      <div className="container mx-auto px-4">
        <h1 className="text-2xl font-bold text-gray-800 mb-6 uppercase">Giỏ hàng ({cartItems.length} sản phẩm)</h1>

        {cartItems.length === 0 ? (
          <div className="bg-white p-10 rounded-lg shadow-sm text-center">
            <p className="text-gray-500 mb-4">Giỏ hàng của bạn đang trống.</p>
            <Link to="/" className="inline-block bg-[#157a2c] text-white px-6 py-2 rounded-md hover:bg-green-800 transition">Tiếp tục mua sắm</Link>
          </div>
        ) : (
          <div className="flex flex-col lg:flex-row gap-8">
            <div className="lg:w-2/3">
              <div className="bg-white p-4 rounded-lg shadow-sm flex items-center mb-4 text-sm font-medium text-gray-600">
                <div className="w-1/2 flex items-center gap-4">
                  <input 
                    type="checkbox" 
                    className="w-4 h-4 text-[#157a2c] rounded border-gray-300"
                    checked={isAllSelected}
                    onChange={() => toggleAll(!isAllSelected)}
                  />
                  <span>Chọn tất cả ({cartItems.length} sản phẩm)</span>
                </div>
                <div className="w-1/6 text-center">Số lượng</div>
                <div className="w-1/4 text-right">Thành tiền</div>
                <div className="w-1/12 text-center"><FiTrash2 size={18} className="mx-auto" /></div>
              </div>

              <div className="bg-white rounded-lg shadow-sm flex flex-col">
                {cartItems.map((item, index) => (
                  <div key={item.id} className={`p-4 flex items-center border-gray-100 ${index !== cartItems.length - 1 ? 'border-b' : ''}`}>
                    <div className="w-1/2 flex items-center gap-4">
                      <input 
                        type="checkbox" 
                        className="w-4 h-4 text-[#157a2c] rounded border-gray-300"
                        checked={item.selected}
                        onChange={() => toggleSelect(item.id)}
                      />
                      <img src={item.thumbnail} alt={item.name} className="w-20 h-28 object-cover border border-gray-200 rounded" />
                      <div className="flex flex-col">
                        <Link to={`/book/${item.bookId}`} className="text-[15px] font-medium text-gray-800 hover:text-[#157a2c] line-clamp-2">{item.name}</Link>
                        <div className="flex items-center gap-2 mt-2">
                          <span className="font-bold text-black">{formatCurrency(item.salePrice)}</span>
                          {item.originalPrice > item.salePrice && (
                            <span className="text-gray-400 line-through text-xs">{formatCurrency(item.originalPrice)}</span>
                          )}
                        </div>
                      </div>
                    </div>

                    <div className="w-1/6 flex justify-center">
                      <div className="flex items-center border border-gray-300 rounded overflow-hidden h-8 w-24">
                        <button onClick={() => handleUpdateQuantity(item.id, item.quantity - 1, item.inStock)} className="px-2 hover:bg-gray-100 text-gray-600 h-full transition flex items-center justify-center"><FiMinus size={14} /></button>
                        <input type="text" value={item.quantity} readOnly className="w-full text-center border-x border-gray-300 h-full text-sm font-medium outline-none" />
                        <button onClick={() => handleUpdateQuantity(item.id, item.quantity + 1, item.inStock)} className="px-2 hover:bg-gray-100 text-gray-600 h-full transition flex items-center justify-center"><FiPlus size={14} /></button>
                      </div>
                    </div>

                    <div className="w-1/4 text-right font-bold text-[#157a2c] text-lg">
                      {formatCurrency(item.salePrice * item.quantity)}
                    </div>

                    <div className="w-1/12 flex justify-center">
                      <button onClick={() => { if(window.confirm('Bỏ sản phẩm này?')) removeFromCart(item.id); }} className="text-gray-400 hover:text-red-500 transition p-2">
                        <FiTrash2 size={20} />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div className="lg:w-1/3">
              <div className="bg-white p-6 rounded-lg shadow-sm sticky top-4">
                <h2 className="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Tổng kết đơn hàng</h2>
                
                <div className="flex justify-between items-center mb-4 text-sm text-gray-600">
                  <span>Tổng sản phẩm chọn:</span>
                  <span className="font-medium">{cartSummary.totalItems}</span>
                </div>
                
                <div className="flex justify-between items-center mb-6">
                  <span className="text-gray-800 font-medium">Tổng tiền:</span>
                  <span className="text-2xl font-bold text-[#157a2c]">{formatCurrency(cartSummary.totalPrice)}</span>
                </div>

                <div className="text-xs text-gray-500 italic text-right mb-6">
                  (Chưa bao gồm phí vận chuyển)
                </div>

                <button 
                  className={`w-full py-3 rounded-md font-bold text-lg transition shadow-sm ${
                    cartSummary.totalItems > 0 
                      ? 'bg-[#157a2c] text-white hover:bg-green-800 cursor-pointer' 
                      : 'bg-gray-300 text-gray-500 cursor-not-allowed'
                  }`}
                  disabled={cartSummary.totalItems === 0}
                  onClick={() => navigate('/checkout')}
                >
                  THANH TOÁN
                </button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default CartPage;