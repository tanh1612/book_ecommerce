// src/components/Product/ProductCard.jsx
import { Link, useNavigate } from 'react-router-dom';
import { FiShoppingCart } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';
import { useCart } from '../../context/CartContext';

const ProductCard = ({ book }) => {
  const navigate = useNavigate();
  const { addToCart, buyNow } = useCart();

  const title = book.name || "Đang cập nhật";
  const salePrice = Number(book.selling_price || 0); // Ép kiểu số
  const originalPrice = Number(book.original_price || salePrice);
  const thumbnail = book.thumbnail_url || "https://placehold.co/300x400/EEE/31343C?text=No+Image";
  
  const author = Array.isArray(book.authors) 
    ? book.authors.map(a => a.name).join(', ') 
    : "Nhiều tác giả";

  const discountPercent = originalPrice > salePrice 
    ? Math.round(((originalPrice - salePrice) / originalPrice) * 100) 
    : 0;

  const handleAddToCart = (e) => {
    e.preventDefault();
    addToCart(book, 1);
  };

  // 🔥 Xử lý sự kiện Mua ngay
  const handleBuyNow = (e) => {
    e.preventDefault();
    buyNow(book, 1, navigate);
  };

  return (
    <div className="bg-white rounded border border-gray-100 hover:shadow-lg transition-all duration-300 relative group flex flex-col h-full overflow-hidden p-4">
      <Link to={`/book/${book.slug}`} className="block overflow-hidden mb-4 flex-shrink-0">
        <img src={thumbnail} alt={title} className="w-full h-52 object-contain group-hover:scale-105 transition-transform duration-500" />
      </Link>

      <div className="flex flex-col flex-grow text-left">
        <Link to={`/book/${book.slug}`}>
          <h3 className="text-[14px] text-gray-800 font-medium line-clamp-2 hover:text-[#157a2c] transition-colors mb-1 min-h-[40px]">
            {title}
          </h3>
        </Link>
        <p className="text-xs text-gray-500 mb-3 line-clamp-1">{author}</p>

        <div className="mt-auto flex items-center gap-2 flex-wrap">
          <span className="text-[#157a2c] font-bold text-[16px]">{formatCurrency(salePrice)}</span>
          {discountPercent > 0 && (
            <span className="bg-[#ff424e] text-white text-[10px] font-bold px-1.5 py-0.5 rounded">-{discountPercent}%</span>
          )}
        </div>
      </div>

      <div className="absolute inset-x-0 -bottom-16 group-hover:bottom-0 bg-white p-3 transition-all duration-300 flex justify-between gap-2 border-t border-gray-100">
        <button onClick={handleAddToCart} className="bg-white border border-[#157a2c] text-[#157a2c] w-10 h-10 rounded flex items-center justify-center hover:bg-[#157a2c] hover:text-white transition-colors" title="Thêm vào giỏ">
          <FiShoppingCart size={18} />
        </button>
        <button onClick={handleBuyNow} className="flex-grow bg-[#157a2c] text-white h-10 rounded text-sm font-bold hover:bg-green-800 transition-colors">
          Mua ngay
        </button>
      </div>
    </div>
  );
};

export default ProductCard;