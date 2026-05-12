// src/context/WishlistContext.jsx
import { createContext, useContext, useState } from 'react';

const WishlistContext = createContext();

export const WishlistProvider = ({ children }) => {
  // Lấy dữ liệu yêu thích từ localStorage nếu có
  const [wishlistItems, setWishlistItems] = useState(() => {
    const saved = localStorage.getItem('wishlist');
    return saved ? JSON.parse(saved) : [];
  });

  const saveWishlist = (items) => {
    setWishlistItems(items);
    localStorage.setItem('wishlist', JSON.stringify(items));
  };

  // Hàm thả tim (Nếu đã có thì xóa đi, nếu chưa có thì thêm vào)
  const toggleWishlist = (book) => {
    const exists = wishlistItems.find(item => item.id === book.id);
    if (exists) {
      saveWishlist(wishlistItems.filter(item => item.id !== book.id));
    } else {
      saveWishlist([...wishlistItems, book]);
    }
  };

  // Hàm xóa khỏi danh sách yêu thích
  const removeFromWishlist = (id) => {
    saveWishlist(wishlistItems.filter(item => item.id !== id));
  };

  // Hàm kiểm tra sách đã được thả tim chưa
  const isInWishlist = (id) => {
    return wishlistItems.some(item => item.id === id);
  };

  return (
    <WishlistContext.Provider value={{ wishlistItems, toggleWishlist, removeFromWishlist, isInWishlist }}>
      {children}
    </WishlistContext.Provider>
  );
};

export const useWishlist = () => useContext(WishlistContext);