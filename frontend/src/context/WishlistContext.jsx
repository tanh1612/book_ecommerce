// src/context/WishlistContext.jsx
import { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { toast } from 'react-toastify';
import wishlistApi from '../services/wishlistApi';
import { useAuth } from './AuthContext'; // Import AuthContext để check đăng nhập

const WishlistContext = createContext();

export const WishlistProvider = ({ children }) => {
  const [wishlistItems, setWishlistItems] = useState([]);
  const [isLoadingWishlist, setIsLoadingWishlist] = useState(false);
  
  // Lấy trạng thái user từ AuthContext
  const { user } = useAuth();

  // 1. HÀM TẢI DANH SÁCH YÊU THÍCH TỪ BACKEND
  const fetchWishlist = useCallback(async () => {
    if (!user) {
      setWishlistItems([]); // Khách vãng lai thì làm rỗng danh sách
      return;
    }
    
    setIsLoadingWishlist(true);
    try {
      const res = await wishlistApi.getWishlist();
      setWishlistItems(res.data?.data || res.data || []);
    } catch (error) {
      console.error("Lỗi tải danh sách yêu thích:", error);
    } finally {
      setIsLoadingWishlist(false);
    }
  }, [user]);

  // Tự động tải lại Wishlist mỗi khi trạng thái đăng nhập (user) thay đổi
  useEffect(() => {
    fetchWishlist();
  }, [fetchWishlist]);

  // 2. HÀM THÊM VÀO YÊU THÍCH
  const addToWishlist = async (book) => {
    if (!user) {
      toast.warning("Vui lòng đăng nhập để lưu sách yêu thích!");
      return;
    }

    // Kiểm tra xem sách đã có trong danh sách chưa
    const isExist = wishlistItems.some(item => item.id === book.id);
    if (isExist) {
      toast.info("Cuốn sách này đã có trong danh sách yêu thích!");
      return;
    }

    // Tối ưu hóa UI: Cập nhật state ngay lập tức cho người dùng (Optimistic Update)
    const previousItems = [...wishlistItems];
    setWishlistItems(prev => [...prev, book]);

    try {
      const res = await wishlistApi.addToWishlist(book.id);
      // Update state từ response thay vì fetchWishlist
      const payload = res.data?.data || res.data || null;
      if (payload) {
        if (Array.isArray(payload)) {
          setWishlistItems(payload);
        } else if (payload.id) {
          setWishlistItems(prev => prev.map(item => item.id === book.id ? payload : item));
        }
      }
      toast.success(`Đã thả tim "${book.name || book.title}"!`);
    } catch (error) {
      // Nếu API lỗi, khôi phục lại dữ liệu cũ
      setWishlistItems(previousItems);
      console.error("Lỗi thêm vào yêu thích:", error);
    }
  };

  // 3. HÀM XÓA KHỎI YÊU THÍCH
  const removeFromWishlist = async (bookId) => {
    if (!user) return;
    
    // Tối ưu hóa UI: Cập nhật state ngay lập tức cho người dùng đỡ phải chờ (Optimistic Update)
    const previousItems = [...wishlistItems];
    setWishlistItems(prev => prev.filter(item => item.id !== bookId));

    try {
      await wishlistApi.removeFromWishlist(bookId);
      toast.success("Đã xóa khỏi danh sách yêu thích.");
    } catch (error) {
      // Nếu API lỗi, khôi phục lại dữ liệu cũ
      setWishlistItems(previousItems);
      console.error("Lỗi xóa khỏi yêu thích:", error);
    }
  };

  // 4. HÀM KIỂM TRA TRẠNG THÁI (Dùng cho icon Trái tim sáng/tối ở trang Chi tiết)
  const checkInWishlist = (bookId) => {
    return wishlistItems.some(item => item.id === bookId);
  };

  return (
    <WishlistContext.Provider value={{
      wishlistItems,
      isLoadingWishlist,
      fetchWishlist,
      addToWishlist,
      removeFromWishlist,
      checkInWishlist
    }}>
      {children}
    </WishlistContext.Provider>
  );
};

export const useWishlist = () => useContext(WishlistContext);
