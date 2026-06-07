// src/pages/Cart/CartPage.jsx
import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiTrash2, FiMinus, FiPlus, FiChevronRight } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';
import { useCart } from '../../context/CartContext';
import CartItemSkeleton from '../../components/LoadingSkeletons/CartItemSkeleton';
import cartApi from '../../services/cartApi';

const CartPage = () => {
  const navigate = useNavigate();
  const { cartItems, isLoadingCart, updateQuantity, removeFromCart, toggleSelect, toggleAll, fetchCart } = useCart();
  
  // Lưu trữ dữ liệu gốc từ Backend
  const [cartInfo, setCartInfo] = useState(null);

  useEffect(() => {
    fetchCart();
  }, [fetchCart]);

  // ĐỒNG BỘ DATA BACKEND: Mỗi khi có sự thay đổi item trong giỏ, lấy lại data tổng
  useEffect(() => {
    let isMounted = true;
    if (cartItems.length > 0) {
      cartApi.getCart().then(res => {
        if (isMounted) setCartInfo(res.data?.data || res.data);
      }).catch(console.error);
    } else {
      setCartInfo(null);
    }
    return () => { isMounted = false; };
  }, [cartItems]);

  const handleUpdateQuantity = (cartItemId, newQuantity, inStock) => {
    if (newQuantity >= 1 && newQuantity <= (inStock || 999)) {
      updateQuantity(cartItemId, newQuantity);
    }
  };

  const isAllSelected = cartItems.length > 0 && cartItems.every(item => item.selected);
  
  // TỔNG TIỀN CHUẨN BACKEND: Không tự tính, lấy thẳng selected_subtotal_after_discount
  const totalItems = cartItems.filter(item => item.selected).reduce((sum, item) => sum + (item.quantity || 0), 0);
  const totalPrice = cartInfo?.selected_subtotal_after_discount || 0;

  if (isLoadingCart && cartItems.length === 0) {
    return (
      <div className="min-h-screen pb-12">
        <div className="bg-white py-3 border-b border-gray-200 mb-8">
          <div className="container mx-auto px-8 lg:px-10 text-sm text-gray-600 flex items-center gap-2">
            <Link to="/" className="hover:text-primary cursor-pointer">Trang chủ</Link>
            <FiChevronRight size={14} className="text-gray-400" />
            <span className="text-primary font-medium">Giỏ hàng của bạn</span>
          </div>
        </div>
        <div className="container mx-auto px-8 lg:px-10">
          <h1 className="text-2xl font-bold text-gray-800 mb-6 uppercase">Giỏ hàng</h1>
          <div className="bg-white rounded-lg shadow-sm">
            {[1, 2, 3].map(i => <CartItemSkeleton key={i} />)}
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen pb-12">
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-8 lg:px-10 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-primary cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-primary font-medium">Giỏ hàng của bạn</span>
        </div>
      </div>

      <div className="container mx-auto px-8 lg:px-10">
        <h1 className="text-2xl font-bold text-gray-800 mb-6 uppercase">Giỏ hàng ({cartItems.length} sản phẩm)</h1>

        {cartItems.length === 0 ? (
          <div className="bg-white p-10 rounded-lg shadow-sm text-center">
            <p className="text-gray-500 mb-4">Giỏ hàng của bạn đang trống.</p>
            <Link to="/catalog" className="inline-block bg-primary text-white px-6 py-2 rounded-md hover:bg-green-800 transition">Tiếp tục mua sắm</Link>
          </div>
        ) : (
          <div className="flex flex-col lg:flex-row gap-8">
            <div className="lg:w-2/3">
              <div className="bg-white p-4 rounded-lg shadow-sm flex items-center mb-4 text-sm font-medium text-gray-600">
                <div className="w-1/2 flex items-center gap-4">
                  <input type="checkbox" className="w-4 h-4 text-primary rounded border-gray-300" checked={isAllSelected} onChange={(e) => toggleAll(e.target.checked)} />
                  <span>Chọn tất cả ({cartItems.length} sản phẩm)</span>
                </div>
                <div className="w-1/6 text-center">Số lượng</div>
                <div className="w-1/4 text-right">Thành tiền</div>
                <div className="w-1/12 text-center"><FiTrash2 size={18} className="mx-auto" /></div>
              </div>

              <div className="bg-white rounded-lg shadow-sm flex flex-col">
                {cartItems.map((item, index) => {
                  const bookData = item.book || item;
                  
                  // 🔥 CẬP NHẬT CHUẨN KỸ SƯ: Sử dụng effective_unit_price từ Backend
                  const itemOriginal = Number(bookData.original_price || bookData.price || 0);
                  const itemPrice = Number(item.effective_unit_price ?? bookData.selling_price ?? itemOriginal);
                  const itemTotal = Number(item.line_total ?? (itemPrice * item.quantity));

                  const itemTitle = bookData.name || bookData.title || "Đang cập nhật";
                  const itemThumbnail = bookData.thumbnail_url || bookData.thumbnail || "https://placehold.co/100x150";
                  const availableStock = item.available_stock ?? bookData.available_stock ?? 999;

                  return (
                    <div key={item.id} className={`p-4 flex items-center border-gray-100 ${index !== cartItems.length - 1 ? 'border-b' : ''}`}>
                      <div className="w-1/2 flex items-center gap-4">
                        <input type="checkbox" className="w-4 h-4 text-primary rounded border-gray-300 cursor-pointer" checked={!!item.selected} onChange={() => toggleSelect(item.id, item.selected)} />
                        <img src={itemThumbnail} alt={itemTitle} className="w-20 h-28 object-cover border border-gray-200 rounded" />
                        <div className="flex flex-col">
                          <Link to={`/book/${bookData.slug}`} className="text-[15px] font-medium text-gray-800 hover:text-primary line-clamp-2">{itemTitle}</Link>
                          <div className="flex items-center gap-2 mt-2">
                            <span className="font-bold text-black">{formatCurrency(itemPrice)}</span>
                            {itemOriginal > itemPrice && (
                              <span className="text-gray-400 line-through text-xs">{formatCurrency(itemOriginal)}</span>
                            )}
                          </div>
                        </div>
                      </div>

                      <div className="w-1/6 flex justify-center">
                        <div className="flex items-center border border-gray-300 rounded overflow-hidden h-8 w-24">
                          <button onClick={() => handleUpdateQuantity(item.id, item.quantity - 1, availableStock)} className="px-2 hover:bg-gray-100 text-gray-600 h-full transition flex items-center justify-center"><FiMinus size={14} /></button>
                          <input type="text" value={item.quantity} readOnly className="w-full text-center border-x border-gray-300 h-full text-sm font-medium outline-none" />
                          <button onClick={() => handleUpdateQuantity(item.id, item.quantity + 1, availableStock)} className="px-2 hover:bg-gray-100 text-gray-600 h-full transition flex items-center justify-center"><FiPlus size={14} /></button>
                        </div>
                      </div>

                      <div className="w-1/4 text-right font-bold text-primary text-lg">
                        {/* 🔥 Dùng item.line_total Backend gửi về */}
                        {formatCurrency(itemTotal)}
                      </div>

                      <div className="w-1/12 flex justify-center">
                        <button onClick={() => { if(window.confirm('Bỏ sản phẩm này?')) removeFromCart(item.id); }} className="text-gray-400 hover:text-red-500 transition p-2"><FiTrash2 size={20} /></button>
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>

            <div className="lg:w-1/3">
              <div className="bg-white p-6 rounded-lg shadow-sm sticky top-4">
                <h2 className="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Tổng kết đơn hàng</h2>
                <div className="flex justify-between items-center mb-4 text-sm text-gray-600">
                  <span>Tổng sản phẩm chọn:</span><span className="font-medium">{totalItems}</span>
                </div>
                <div className="flex justify-between items-center mb-6">
                  <span className="text-gray-800 font-medium">Tổng tiền:</span>
                  <span className="text-2xl font-bold text-primary">{formatCurrency(totalPrice)}</span>
                </div>
                <div className="text-xs text-gray-500 italic text-right mb-6">(Chưa bao gồm phí vận chuyển)</div>
                <button 
                  className={`w-full py-3 rounded-md font-bold text-lg transition shadow-sm ${totalItems > 0 ? 'bg-primary text-white hover:bg-green-800 cursor-pointer' : 'bg-gray-300 text-gray-500 cursor-not-allowed'}`}
                  disabled={totalItems === 0}
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
