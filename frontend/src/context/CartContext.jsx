// src/context/CartContext.jsx
import { createContext, useContext, useState, useEffect, useMemo } from 'react';
import cartApi from '../services/cartApi';
import { toast } from 'react-toastify';
import { useAuth } from './AuthContext'; // Để biết user đã đăng nhập chưa (nếu có)

const CartContext = createContext();

export const CartProvider = ({ children }) => {
  const [cartItems, setCartItems] = useState([]);
  const [isLoadingCart, setIsLoadingCart] = useState(false);
  const { user } = useAuth(); // Theo dõi trạng thái đăng nhập

  // 1. Hàm lấy giỏ hàng từ Server
  const fetchCart = async () => {
    setIsLoadingCart(true);
    try {
      const res = await cartApi.getCart();
      // Giả định API trả về res.data.data chứa mảng items (bạn có thể log ra để chỉnh lại cho chuẩn với BE)
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
    } catch (error) {
      console.error("Lỗi lấy giỏ hàng:", error);
    } finally {
      setIsLoadingCart(false);
    }
  };

  // Tự động lấy giỏ hàng khi app chạy hoặc khi user thay đổi trạng thái đăng nhập
  useEffect(() => {
    fetchCart();
  }, [user]);

  // Tính tổng số lượng hiển thị trên Header (Chỉ đếm các item có trong giỏ)
  const totalQuantity = useMemo(() => 
    cartItems.reduce((total, item) => total + (item.quantity || 1), 0)
  , [cartItems]);

  // 2. Thêm vào giỏ hàng (Gọi API POST)
  const addToCart = async (book, qty = 1) => {
    try {
      await cartApi.addItem(book.id, qty);
      await fetchCart(); // Lấy lại giỏ hàng mới nhất từ server
      toast.success(`Đã thêm ${qty} cuốn vào giỏ hàng`);
    } catch (error) {
      toast.error(error.response?.data?.message || "Lỗi khi thêm vào giỏ hàng");
    }
  };

  // 3. Cập nhật số lượng (Gọi API PATCH)
  const updateQuantity = async (cartItemId, quantity) => {
    try {
      // Cập nhật state UI trước cho mượt (Optimistic Update)
      setCartItems(prev => prev.map(item => item.id === cartItemId ? { ...item, quantity } : item));
      await cartApi.updateItem(cartItemId, { quantity });
      // Lấy lại data chuẩn từ server (tùy chọn)
      // fetchCart(); 
    } catch (error) {
      toast.error("Lỗi cập nhật số lượng");
      fetchCart(); // Nếu lỗi thì rollback lại data cũ từ server
    }
  };

  // 4. Xóa khỏi giỏ (Gọi API DELETE)
  const removeFromCart = async (cartItemId) => {
    try {
      setCartItems(prev => prev.filter(item => item.id !== cartItemId));
      await cartApi.removeItem(cartItemId);
      toast.success("Đã xóa sản phẩm");
    } catch (error) {
      toast.error("Lỗi khi xóa sản phẩm");
      fetchCart();
    }
  };

  // 5. Tick chọn 1 item (Gọi API PATCH)
  const toggleSelect = async (cartItemId, currentSelectedStatus) => {
    try {
      const newSelected = !currentSelectedStatus;
      setCartItems(prev => prev.map(item => item.id === cartItemId ? { ...item, selected: newSelected } : item));
      await cartApi.updateItem(cartItemId, { selected: newSelected ? 1 : 0 });
    } catch (error) {
      toast.error("Lỗi thao tác");
      fetchCart();
    }
  };

  // 6. Tick chọn tất cả (Gọi API PATCH BULK)
  const toggleAll = async (isSelected) => {
    try {
      setCartItems(prev => prev.map(item => ({ ...item, selected: isSelected })));
      await cartApi.updateSelection(isSelected ? 1 : 0);
    } catch (error) {
      toast.error("Lỗi thao tác");
      fetchCart();
    }
  };

  return (
    <CartContext.Provider value={{ 
      cartItems, 
      totalQuantity, 
      isLoadingCart,
      fetchCart,
      addToCart, 
      updateQuantity, 
      removeFromCart, 
      toggleSelect, 
      toggleAll 
    }}>
      {children}
    </CartContext.Provider>
  );
};

export const useCart = () => useContext(CartContext);