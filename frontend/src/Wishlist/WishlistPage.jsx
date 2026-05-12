// src/pages/Wishlist/WishlistPage.jsx
import { Link } from 'react-router-dom';
import { FiTrash2, FiShoppingCart, FiChevronRight } from 'react-icons/fi';
import { useWishlist } from '../context/WishlistContext';
import { useCart } from '../context/CartContext';
import { formatCurrency } from '../utils/formatters';

const WishlistPage = () => {
  const { wishlistItems, removeFromWishlist } = useWishlist();
  const { addToCart } = useCart();

  const handleAddToCart = (book) => {
    addToCart(book, 1);
    alert(`✅ Đã thêm cuốn "${book.name}" vào giỏ hàng!`);
  };

  return (
    <div className="bg-gray-50 min-h-screen pb-12">
      {/* BREADCRUMB */}
      <div className="bg-white py-3 border-b border-gray-200 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c] cursor-pointer">Trang chủ</Link>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Danh sách yêu thích</span>
        </div>
      </div>

      <div className="container mx-auto px-4">
        <h1 className="text-2xl font-bold text-gray-800 mb-6 uppercase">Sách Yêu Thích Của Bạn ({wishlistItems.length})</h1>

        {wishlistItems.length === 0 ? (
          <div className="bg-white p-10 rounded-lg shadow-sm text-center border border-gray-100">
            <p className="text-gray-500 mb-4">Bạn chưa thả tim cuốn sách nào.</p>
            <Link to="/" className="inline-block bg-[#157a2c] text-white px-6 py-2 rounded-md hover:bg-green-800 transition shadow-sm">
              Khám phá sách ngay
            </Link>
          </div>
        ) : (
          <div className="bg-white rounded-lg shadow-sm border border-gray-100 flex flex-col">
            {wishlistItems.map((item, index) => (
              <div key={item.id} className={`p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 border-gray-100 ${index !== wishlistItems.length - 1 ? 'border-b' : ''}`}>
                
                {/* Cột trái: Ảnh và Thông tin sách */}
                <div className="flex items-center gap-5 w-full md:w-1/2">
                  <Link to={`/book/${item.slug || item.id}`} className="flex-shrink-0">
                    <img src={item.thumbnail} alt={item.name} className="w-20 h-28 object-cover border border-gray-200 rounded hover:opacity-80 transition" />
                  </Link>
                  <div className="flex flex-col">
                    <Link to={`/book/${item.slug || item.id}`} className="text-base font-medium text-gray-800 hover:text-[#157a2c] line-clamp-2 mb-1">
                      {item.name}
                    </Link>
                    <div className="text-sm text-gray-500 mb-2">{item.author || "Tác giả đang cập nhật"}</div>
                    <div className="flex items-center gap-2">
                      <span className="font-bold text-[#157a2c] text-lg">{formatCurrency(item.salePrice)}</span>
                      {item.originalPrice > item.salePrice && (
                        <span className="text-gray-400 line-through text-sm">{formatCurrency(item.originalPrice)}</span>
                      )}
                    </div>
                  </div>
                </div>

                {/* Cột phải: Các nút hành động */}
                <div className="flex items-center gap-4 ml-25 md:ml-0">
                  <button 
                    onClick={() => handleAddToCart(item)}
                    className="flex-1 md:flex-none border-2 border-[#157a2c] text-[#157a2c] px-6 py-2.5 rounded font-bold hover:bg-green-50 transition flex justify-center items-center gap-2"
                  >
                    <FiShoppingCart size={18} /> Thêm vào giỏ
                  </button>
                  <button 
                    onClick={() => removeFromWishlist(item.id)} 
                    className="text-gray-400 hover:text-red-500 hover:bg-red-50 p-3 rounded-full transition"
                    title="Xóa khỏi yêu thích"
                  >
                    <FiTrash2 size={20} />
                  </button>
                </div>
                
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};

export default WishlistPage;