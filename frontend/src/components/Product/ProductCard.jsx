// src/components/Product/ProductCard.jsx
import { Link, useNavigate } from 'react-router-dom';
import { FiShoppingCart } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';
import { resolveMediaUrl } from '../../utils/media';
import { useCart } from '../../context/CartContext';

const ProductCard = ({ book }) => {
  const navigate = useNavigate();
  const { addToCart, buyNow } = useCart();
  const bookInfo = book.book || book;

  const title = bookInfo.name || 'Đang cập nhật';
  const slug = bookInfo.slug || '';
  const thumbnail = resolveMediaUrl(
    bookInfo.thumbnail_url || bookInfo.thumbnail,
    'https://placehold.co/300x400/EEE/31343C?text=No+Image'
  );
  const author = Array.isArray(bookInfo.authors) && bookInfo.authors.length > 0
    ? bookInfo.authors.map((authorItem) => authorItem.name).join(', ')
    : 'Nhiều tác giả';
  const isInStock = bookInfo.in_stock !== false && Number(bookInfo.available_stock ?? 1) > 0;
  const averageRating = Number(bookInfo.average_rating || 0);
  const reviewCount = Number(bookInfo.review_count || 0);

  let originalPrice = Number(book.original_price || bookInfo.original_price || 0);
  let salePrice = Number(book.selling_price || bookInfo.selling_price || originalPrice);
  let discountPercent = 0;

  if (book.discount_percent > 0) {
    discountPercent = Number(book.discount_percent);
    salePrice = originalPrice - (originalPrice * (discountPercent / 100));
  } else {
    discountPercent = originalPrice > salePrice
      ? Math.round(((originalPrice - salePrice) / originalPrice) * 100)
      : 0;
  }

  const handleAddToCart = (event) => {
    event.preventDefault();
    if (!isInStock) return;
    addToCart(bookInfo, 1);
  };

  const handleBuyNow = (event) => {
    event.preventDefault();
    if (!isInStock) return;
    buyNow(bookInfo, 1, navigate);
  };

  return (
    <div className="bg-white rounded border border-gray-100 hover:shadow-lg transition-all duration-300 relative group flex flex-col h-full overflow-hidden p-4">
      {!isInStock && (
        <span className="absolute left-3 top-3 z-10 rounded bg-gray-800 px-2 py-1 text-[11px] font-bold text-white">
          Hết hàng
        </span>
      )}

      <Link to={`/book/${slug}`} className="block overflow-hidden mb-4 flex-shrink-0">
        <img
          src={thumbnail}
          alt={title}
          className="w-full h-52 object-contain group-hover:scale-105 transition-transform duration-500"
          onError={(event) => {
            event.currentTarget.src = 'https://placehold.co/300x400/EEE/31343C?text=No+Image';
          }}
        />
      </Link>

      <div className="flex flex-col flex-grow text-left">
        <Link to={`/book/${slug}`}>
          <h3 className="text-[14px] text-gray-800 font-medium line-clamp-2 hover:text-[#157a2c] transition-colors mb-1 min-h-[40px]">
            {title}
          </h3>
        </Link>
        <p className="text-xs text-gray-500 mb-2 line-clamp-1">{author}</p>
        {reviewCount > 0 && (
          <p className="text-xs text-amber-500 mb-2">
            {averageRating.toFixed(1)} sao ({reviewCount})
          </p>
        )}

        <div className="mt-auto flex items-center gap-2 flex-wrap">
          <span className="text-[#157a2c] font-bold text-[16px]">{formatCurrency(salePrice)}</span>
          {discountPercent > 0 && (
            <span className="bg-[#ff424e] text-white text-[10px] font-bold px-1.5 py-0.5 rounded">-{discountPercent}%</span>
          )}
        </div>
      </div>

      <div className="absolute inset-x-0 -bottom-16 group-hover:bottom-0 bg-white p-3 transition-all duration-300 flex justify-between gap-2 border-t border-gray-100">
        <button
          onClick={handleAddToCart}
          disabled={!isInStock}
          className="bg-white border border-[#157a2c] text-[#157a2c] w-10 h-10 rounded flex items-center justify-center hover:bg-[#157a2c] hover:text-white transition-colors disabled:border-gray-300 disabled:text-gray-300 disabled:hover:bg-white disabled:cursor-not-allowed"
          title="Thêm vào giỏ"
        >
          <FiShoppingCart size={18} />
        </button>
        <button
          onClick={handleBuyNow}
          disabled={!isInStock}
          className="flex-grow bg-[#157a2c] text-white h-10 rounded text-sm font-bold hover:bg-green-800 transition-colors disabled:bg-gray-300 disabled:cursor-not-allowed"
        >
          {isInStock ? 'Mua ngay' : 'Hết hàng'}
        </button>
      </div>
    </div>
  );
};

export default ProductCard;
