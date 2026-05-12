// src/context/CartContext.jsx
import { createContext, useContext, useState, useMemo } from 'react';

const CartContext = createContext();

export const CartProvider = ({ children }) => {
  const [cartItems, setCartItems] = useState(() => {
    const saved = localStorage.getItem('cart');
    return saved ? JSON.parse(saved) : [];
  });

  const saveCart = (newItems) => {
    setCartItems(newItems);
    localStorage.setItem('cart', JSON.stringify(newItems));
  };

  const totalQuantity = useMemo(() => 
    cartItems.reduce((total, item) => total + item.quantity, 0), [cartItems]);

  const addToCart = (book, qty = 1) => {
    const existing = cartItems.find(item => item.bookId === book.id);
    if (existing) {
      saveCart(cartItems.map(item => 
        item.bookId === book.id ? { ...item, quantity: item.quantity + qty } : item
      ));
    } else {
      saveCart([...cartItems, {
        id: Date.now(),
        bookId: book.id,
        name: book.name,
        thumbnail: book.thumbnail,
        salePrice: book.selling_price || book.salePrice,
        originalPrice: book.original_price || book.originalPrice || book.salePrice,
        quantity: qty,
        selected: true,
        inStock: book.inStock || 99
      }]);
    }
  };

  const updateQuantity = (id, quantity) => {
    saveCart(cartItems.map(item => item.id === id ? { ...item, quantity } : item));
  };

  const removeFromCart = (id) => {
    saveCart(cartItems.filter(item => item.id !== id));
  };

  const toggleSelect = (id) => {
    saveCart(cartItems.map(item => item.id === id ? { ...item, selected: !item.selected } : item));
  };

  const toggleAll = (isSelected) => {
    saveCart(cartItems.map(item => ({ ...item, selected: isSelected })));
  };

  return (
    <CartContext.Provider value={{ 
      cartItems, totalQuantity, addToCart, updateQuantity, removeFromCart, toggleSelect, toggleAll 
    }}>
      {children}
    </CartContext.Provider>
  );
};

export const useCart = () => useContext(CartContext);