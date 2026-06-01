
import axiosClient from './axiosClient';

const wishlistApi = {
  // Lấy danh sách yêu thích
  getWishlist: () => axiosClient.get('/v1/account/wishlist'),
  
  // Thêm sách vào yêu thích (truyền book_id)
  addToWishlist: (bookId) => axiosClient.post('/v1/account/wishlist/items', { book_id: bookId }),
  
  // Xóa sách khỏi yêu thích
  removeFromWishlist: (bookId) => axiosClient.delete(`/v1/account/wishlist/items/${bookId}`)
};

export default wishlistApi;