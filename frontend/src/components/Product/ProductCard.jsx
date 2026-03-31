// src/components/Product/ProductCard.jsx
import { Link } from 'react-router-dom';
import { FiShoppingCart } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';

const ProductCard = ({ book }) => {
  const discountPercent = book.originalPrice > book.salePrice 
    ? Math.round(((book.originalPrice - book.salePrice) / book.originalPrice) * 100) 
    : 0;

  return (
    <div className="bg-white rounded border border-gray-100 hover:shadow-lg transition-all duration-300 relative group flex flex-col h-full overflow-hidden p-4">
      
      {/* Hình ảnh sách */}
      <Link to={`/book/${book.slug}`} className="block overflow-hidden mb-4 flex-shrink-0">
        <img 
          src={book.thumbnail} 
          alt={book.name} 
          className="w-full h-56 object-contain group-hover:scale-105 transition-transform duration-500" 
        />
      </Link>

      {/* Thông tin sách (Canh trái chuẩn Nhã Nam) */}
      <div className="flex flex-col flex-grow text-left">
        <Link to={`/book/${book.slug}`}>
          <h3 className="text-[15px] text-gray-800 font-medium line-clamp-2 hover:text-[#157a2c] transition-colors mb-1">
            {book.name}
          </h3>
        </Link>
        
        {/* Tác giả */}
        <p className="text-sm text-gray-500 mb-3 line-clamp-1">{book.author || "Đang cập nhật"}</p>

        {/* Cụm Giá tiền & Badge giảm giá */}
        <div className="mt-auto flex items-center gap-2 flex-wrap">
          <span className="text-black font-bold text-[15px]">
            {formatCurrency(book.salePrice)}
          </span>
          {discountPercent > 0 && (
            <>
              <span className="text-gray-400 line-through text-sm">
                {formatCurrency(book.originalPrice)}
              </span>
              <span className="bg-[#ff424e] text-white text-[11px] font-bold px-1.5 py-0.5 rounded">
                -{discountPercent}%
              </span>
            </>
          )}
        </div>
      </div>

      {/* Nút bấm ẩn hiện khi hover (Chuẩn Nhã Nam) */}
      <div className="absolute inset-x-0 -bottom-16 group-hover:bottom-0 bg-white p-3 transition-all duration-300 flex justify-between gap-2 border-t border-gray-100">
        <button 
          className="bg-white border border-[#157a2c] text-[#157a2c] w-10 h-10 rounded flex items-center justify-center hover:bg-[#157a2c] hover:text-white transition-colors"
          title="Thêm vào giỏ"
        >
          <FiShoppingCart size={18} />
        </button>
        <button 
          className="flex-grow bg-[#157a2c] text-white h-10 rounded text-sm font-bold hover:bg-green-800 transition-colors"
        >
          Mua ngay
        </button>
      </div>
    </div>
  );
};

export default ProductCard;