// src/context/CartContext.jsx
import { createContext, useContext, useState, useEffect, useMemo } from 'react';
import cartApi from '../services/cartApi';
import { toast } from 'react-toastify';
import { useAuth } from './AuthContext';

const CartContext = createContext();

export const CartProvider = ({ children }) => {
  const [cartItems, setCartItems] = useState([]);
  const [isLoadingCart, setIsLoadingCart] = useState(false);
  const { user } = useAuth(); 

  const fetchCart = async () => {
    setIsLoadingCart(true);
    try {
      const res = await cartApi.getCart();
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
    } catch (error) {
      console.error("Lỗi lấy giỏ hàng:", error);
    } finally {
      setIsLoadingCart(false);
    }
  };

  useEffect(() => {
    fetchCart();
  }, [user]);

  const totalQuantity = useMemo(() => 
    cartItems.reduce((total, item) => total + (item.quantity || 1), 0)
  , [cartItems]);

  const addToCart = async (book, qty = 1) => {
    try {
      await cartApi.addItem(book.id, qty);
      await fetchCart(); 
      toast.success(`Đã thêm ${qty} cuốn vào giỏ hàng`);
      return true; // Trả về true để biết là thành công
    } catch (error) {
      toast.error(error.response?.data?.message || "Lỗi khi thêm vào giỏ hàng");
      return false;
    }
  };

  // 🔥 HÀM MỚI: Xử lý nút "Mua ngay"
  const buyNow = async (book, qty = 1, navigate) => {
    const isSuccess = await addToCart(book, qty);
    if (isSuccess) {
      navigate('/cart'); // Chuyển thẳng tới giỏ hàng nếu thêm thành công
    }
  };

  const updateQuantity = async (cartItemId, quantity) => {
    try {
      setCartItems(prev => prev.map(item => item.id === cartItemId ? { ...item, quantity } : item));
      await cartApi.updateItem(cartItemId, { quantity });
    } catch (error) {
      toast.error("Lỗi cập nhật số lượng");
      fetchCart(); 
    }
  };

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
      buyNow, // Export hàm buyNow
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