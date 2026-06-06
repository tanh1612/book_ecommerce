// src/context/CartContext.jsx
import { createContext, useContext, useState, useEffect, useMemo, useCallback } from 'react';
import cartApi from '../services/cartApi';
import { toast } from 'react-toastify';
import { useAuth } from './AuthContext';

const CartContext = createContext();

export const CartProvider = ({ children }) => {
  const [cartItems, setCartItems] = useState([]);
  const [isLoadingCart, setIsLoadingCart] = useState(false);
  const { user } = useAuth();

  const fetchCart = useCallback(async () => {
    setIsLoadingCart(true);
    try {
      const res = await cartApi.getCart();
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
    } catch {
      toast.error("Không thể tải giỏ hàng. Vui lòng tải lại trang.");
    } finally {
      setIsLoadingCart(false);
    }
  }, []);

  useEffect(() => {
    fetchCart();
  }, [fetchCart, user]);

  const totalQuantity = useMemo(() =>
    cartItems.reduce((total, item) => total + (item.quantity || 1), 0)
  , [cartItems]);

  const addToCart = async (book, qty = 1) => {
    try {
      const res = await cartApi.addItem(book.id, qty);
      // Backend trả về cart data, set trực tiếp thay vì fetch lại
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
      toast.success(`Đã thêm ${qty} cuốn vào giỏ hàng`);
      return true;
    } catch (error) {
      toast.error(error.response?.data?.message || "Lỗi khi thêm vào giỏ hàng");
      return false;
    }
  };

  const buyNow = async (book, qty = 1, navigate) => {
    const isSuccess = await addToCart(book, qty);
    if (isSuccess) {
      navigate('/cart');
    }
  };

  const updateQuantity = async (cartItemId, quantity) => {
    const oldItems = cartItems;
    try {
      setCartItems(prev => prev.map(item => item.id === cartItemId ? { ...item, quantity } : item));
      const res = await cartApi.updateItem(cartItemId, { quantity });
      // Update state từ response
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
    } catch {
      toast.error("Lỗi cập nhật số lượng");
      setCartItems(oldItems);
    }
  };

  const removeFromCart = async (cartItemId) => {
    const oldItems = cartItems;
    try {
      setCartItems(prev => prev.filter(item => item.id !== cartItemId));
      const res = await cartApi.removeItem(cartItemId);
      // Update state từ response thay vì fetchCart
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
      toast.success("Đã xóa sản phẩm");
    } catch {
      toast.error("Lỗi khi xóa sản phẩm");
      setCartItems(oldItems);
    }
  };

  const toggleSelect = async (cartItemId, currentSelectedStatus) => {
    const oldItems = cartItems;
    try {
      const newSelected = !currentSelectedStatus;
      setCartItems(prev => prev.map(item => item.id === cartItemId ? { ...item, selected: newSelected } : item));
      const res = await cartApi.updateItem(cartItemId, { selected: newSelected ? 1 : 0 });
      // Update state từ response
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
    } catch {
      toast.error("Lỗi thao tác");
      setCartItems(oldItems);
    }
  };

  const toggleAll = async (isSelected) => {
    const oldItems = cartItems;
    try {
      setCartItems(prev => prev.map(item => ({ ...item, selected: isSelected })));
      const res = await cartApi.updateSelection(isSelected ? 1 : 0);
      // Update state từ response
      const items = res.data?.data?.items || res.data?.items || res.data || [];
      setCartItems(Array.isArray(items) ? items : []);
    } catch {
      toast.error("Lỗi thao tác");
      setCartItems(oldItems);
    }
  };

  return (
    <CartContext.Provider value={{
      cartItems,
      totalQuantity,
      isLoadingCart,
      fetchCart,
      addToCart,
      buyNow,
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
