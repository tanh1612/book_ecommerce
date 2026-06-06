// src/pages/Product/ProductDetailPage.jsx
import { useState, useEffect } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { FiShoppingCart, FiMinus, FiPlus, FiChevronRight, FiHeart, FiChevronDown } from 'react-icons/fi';
import { toast } from 'react-toastify';
import bookApi from '../../services/bookApi';
import recommendationApi from '../../services/recommendationApi';
import reviewApi from '../../services/reviewApi';
import { formatCurrency } from '../../utils/formatters';
import { resolveMediaUrl } from '../../utils/media';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import { useAuth } from '../../context/AuthContext';

const ProductDetailPage = () => {
  const { slug } = useParams();
  const navigate = useNavigate();
  const { addToCart, buyNow } = useCart();
  const { user } = useAuth();
  
  // 🔥 ĐÃ ĐỒNG BỘ: Sử dụng các hàm chuẩn từ WishlistContext mới
  const { checkInWishlist, addToWishlist, removeFromWishlist } = useWishlist(); 

  const [book, setBook] = useState(null);
  const [quantity, setQuantity] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [mainImage, setMainImage] = useState('');
  const [isDescExpanded, setIsDescExpanded] = useState(false);
  const [reviews, setReviews] = useState([]);
  const [reviewMeta, setReviewMeta] = useState(null);
  const [reviewEligibility, setReviewEligibility] = useState(null);

  useEffect(() => {
    const fetchBook = async () => {
      try {
        setIsLoading(true);
        const res = await bookApi.getBookDetail(slug);
        const bookData = res.data?.data || res.data;
        setBook(bookData);
        setMainImage(resolveMediaUrl(bookData.thumbnail_url, 'https://placehold.co/450x600?text=No+Image')); 
      } catch {
        toast.error("Không tìm thấy thông tin sách!");
      } finally {
        setIsLoading(false);
      }
    };
    fetchBook();
    window.scrollTo(0, 0);
  }, [slug]);

  useEffect(() => {
    let ignore = false;

    const fetchReviews = async () => {
      try {
        const res = await reviewApi.getBookReviews(slug, { per_page: 5 });
        if (!ignore) {
          setReviews(res.data?.data || []);
          setReviewMeta(res.data?.meta || null);
        }
      } catch {
        if (!ignore) {
          setReviews([]);
          setReviewMeta(null);
        }
      }
    };

    fetchReviews();

    return () => {
      ignore = true;
    };
  }, [slug]);

  useEffect(() => {
    if (!user) {
      setReviewEligibility(null);
      return;
    }

    let ignore = false;

    reviewApi.getBookReviewEligibility(slug)
      .then((res) => {
        if (!ignore) setReviewEligibility(res.data?.data || res.data || null);
      })
      .catch(() => {
        if (!ignore) setReviewEligibility(null);
      });

    return () => {
      ignore = true;
    };
  }, [slug, user]);

  const stock = Number.isFinite(Number(book?.available_stock)) ? Number(book.available_stock) : 999;

  useEffect(() => {
    setQuantity(stock <= 0 ? 0 : 1);
  }, [stock]);

  useEffect(() => {
    if (!user || !book?.id) return;

    const storageKey = `book-view-tracked:${book.id}`;
    if (sessionStorage.getItem(storageKey)) return;

    recommendationApi.trackBookView(book.id, 'book_detail')
      .then(() => {
        sessionStorage.setItem(storageKey, '1');
      })
      .catch(() => {});
  }, [book?.id, user]);

  const handleAddToCart = () => {
    if (stock <= 0) return toast.error("Sản phẩm hiện đã hết hàng!");
    if (quantity > stock) return toast.error(`Chỉ còn ${stock} sản phẩm trong kho!`);
    addToCart(book, quantity);
  };

  const handleBuyNow = () => {
    if (stock <= 0) return toast.error("Sản phẩm hiện đã hết hàng!");
    if (quantity > stock) return toast.error(`Chỉ còn ${stock} sản phẩm trong kho!`);
    buyNow(book, quantity, navigate);
  };

  // 🔥 KIỂM TRA TRẠNG THÁI YÊU THÍCH XUYÊN SUỐT TOÀN APP
  const isFavorited = book ? checkInWishlist(book.id) : false;

  // 🔥 XỬ LÝ CLICK YÊU THÍCH MƯỢT MÀ
  const handleToggleWishlist = async () => {
    if (!book) return;
    
    if (isFavorited) {
      await removeFromWishlist(book.id);
    } else {
      await addToWishlist(book);
    }
  };

  if (isLoading) return <div className="py-20 flex justify-center"><div className="w-10 h-10 border-4 border-[#007b22] border-t-transparent rounded-full animate-spin"></div></div>;
  if (!book) return <div className="py-20 text-center text-gray-500">Sách không tồn tại.</div>;

  const title = book.name || "Đang cập nhật";
  
  let originalPrice = Number(book.original_price || 0);
  let sellingPrice = Number(book.selling_price || originalPrice);
  let discountPercent = 0;

  if (book.flash_sale) {
    const fsDiscount = Number(book.flash_sale.discount_percent || book.flash_sale.discount_value || 0);
    if (fsDiscount > 0) {
      discountPercent = fsDiscount;
      sellingPrice = originalPrice - (originalPrice * (discountPercent / 100));
    }
  } else {
    discountPercent = originalPrice > sellingPrice 
      ? Math.round(((originalPrice - sellingPrice) / originalPrice) * 100) 
      : 0;
  }
  
  const authorDisplay = Array.isArray(book.authors) ? book.authors.map(a => a.name).join(', ') : "Đang cập nhật";
  const publisherName = book.publisher?.name || "Đang cập nhật";

  const pubYear = book.detail?.publication_year || "Đang cập nhật";
  const numPages = book.detail?.num_pages || "Đang cập nhật";
  const format = book.detail?.format_label || book.detail?.format || "Đang cập nhật";
  const translator = book.detail?.translator || "Đang cập nhật";
  const dimensions = book.detail?.dimensions || "Đang cập nhật";
  const weight = book.detail?.weight || "Đang cập nhật";
  const description = book.detail?.description || "<p>Chưa có mô tả cho sản phẩm này.</p>";
  
  const averageRating = book.average_rating || 0;
  const reviewCount = book.review_count || 0;
  const galleryImages = book.images?.length > 0 ? book.images : [{ image_url: book.thumbnail_url }];

  return (
    <div className="bg-gray-50 min-h-screen pb-20 pt-4">
      <div className="container mx-auto px-4 mb-4 text-sm text-gray-500 flex items-center gap-2">
        <Link to="/" className="hover:text-[#007b22]">Trang chủ</Link>
        <FiChevronRight size={14} />
        <Link to="/catalog" className="hover:text-[#007b22]">Danh mục sách</Link>
        <FiChevronRight size={14} />
        <span className="text-gray-800 truncate">{title}</span>
      </div>

      <div className="container mx-auto px-4">
        <div className="bg-white rounded-lg shadow-sm p-6 mb-6 flex flex-col lg:flex-row gap-10">
          
          <div className="w-full lg:w-5/12 flex-shrink-0">
            <div className="w-full flex justify-center mb-4">
              <img src={mainImage} alt={title} className="max-w-full h-auto max-h-[450px] object-contain" />
            </div>
            
            {galleryImages.length > 1 && (
              <div className="flex gap-3 justify-center overflow-x-auto py-2 mb-6">
                {galleryImages.map((img, idx) => {
                  const imageUrl = resolveMediaUrl(img.image_url || img.url, 'https://placehold.co/80x120?text=No+Image');

                  return (
                    <div key={img.id || idx} onMouseEnter={() => setMainImage(imageUrl)} className={`w-16 h-16 border rounded cursor-pointer overflow-hidden ${mainImage === imageUrl ? 'border-[#007b22] border-2' : 'border-gray-200'}`}>
                      <img src={imageUrl} alt={`thumb-${idx}`} className="w-full h-full object-cover" />
                    </div>
                  );
                })}
              </div>
            )}

            <div className="flex items-center justify-end mt-8 text-sm text-gray-700 px-4">
              <button onClick={handleToggleWishlist} className={`flex items-center gap-2 font-medium transition-colors ${isFavorited ? 'text-red-500' : 'text-gray-600 hover:text-red-500'}`}>
                {isFavorited ? 'Đã yêu thích' : 'Thêm vào yêu thích'} 
                <FiHeart size={20} className={isFavorited ? 'fill-current' : ''} />
              </button>
            </div>
          </div>

          <div className="w-full lg:w-7/12">
            <h1 className="text-2xl font-semibold text-gray-800 mb-3 leading-snug">{title}</h1>
            
            <div className="flex items-center gap-4 text-sm mb-4">
              <div className="flex text-gray-300">
                {[1,2,3,4,5].map(star => (
                  <svg key={star} className={`w-4 h-4 ${star <= averageRating ? 'text-yellow-400' : 'text-gray-300'}`} fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                  </svg>
                ))}
              </div>
              <span className="text-yellow-500 font-medium">
                {averageRating > 0 ? averageRating : '(Chưa có đánh giá)'}
              </span>
              <span className="text-gray-400 border-l border-gray-300 pl-4">{reviewCount} lượt đánh giá</span>
            </div>

            <div className="text-[15px] mb-6">
              <span className="text-gray-500">Tác giả: </span>
              <span className="font-semibold text-gray-800 uppercase">{authorDisplay}</span>
            </div>

            <div className="flex items-end gap-4 mb-6">
              <span className="text-3xl font-bold text-[#007b22]">{formatCurrency(sellingPrice)}</span>
              {originalPrice > sellingPrice && (
                <>
                  <span className="text-lg text-gray-400 line-through mb-1">{formatCurrency(originalPrice)}</span>
                  <span className="bg-red-500 text-white text-sm font-semibold px-2 py-0.5 rounded mb-1.5">-{discountPercent}%</span>
                </>
              )}
            </div>

            <div className="flex items-center gap-6 mb-8">
              <div className="flex items-center border border-gray-300 rounded h-10 w-28 bg-white">
                <button onClick={() => setQuantity(q => Math.max(1, q - 1))} disabled={stock <= 0} className="w-1/3 h-full flex justify-center items-center text-gray-600 hover:bg-gray-100 disabled:opacity-50"><FiMinus /></button>
                <input type="text" value={quantity} readOnly className="w-1/3 h-full text-center border-x border-gray-300 font-medium text-gray-800 outline-none" />
                <button onClick={() => setQuantity(q => Math.min(stock, q + 1))} disabled={stock <= 0} className="w-1/3 h-full flex justify-center items-center text-gray-600 hover:bg-gray-100 disabled:opacity-50"><FiPlus /></button>
              </div>
              {stock > 0 ? (
                 <span className="text-sm text-gray-500">Sẵn sàng giao hàng</span>
              ) : (
                 <span className="text-sm text-red-500 font-medium">Hết hàng</span>
              )}
            </div>

            <div className="flex gap-4">
              <button 
                onClick={handleAddToCart}
                disabled={stock <= 0}
                className="flex items-center justify-center gap-2 w-56 h-12 bg-white border-2 border-[#007b22] text-[#007b22] font-semibold rounded hover:bg-green-50 transition-colors disabled:border-gray-300 disabled:text-gray-400"
              >
                Thêm vào giỏ hàng <FiShoppingCart size={18} />
              </button>
              <button 
                onClick={handleBuyNow}
                disabled={stock <= 0}
                className="w-56 h-12 bg-[#007b22] text-white font-semibold rounded hover:bg-green-800 transition-colors shadow-sm disabled:bg-gray-400"
              >
                Mua ngay
              </button>
            </div>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row gap-6 items-start">
          <div className="w-full lg:w-[60%] bg-white border border-gray-200 rounded-lg p-6">
            <h3 className="text-lg font-bold text-gray-800 mb-4 pb-4 border-b border-gray-100">Mô tả sản phẩm</h3>
            
            <div className={`relative ${!isDescExpanded ? 'max-h-64 overflow-hidden' : ''}`}>
              <div 
                className="text-[15px] text-gray-600 leading-relaxed prose prose-sm max-w-none prose-p:mb-3"
                dangerouslySetInnerHTML={{ __html: description }}
              />
              {!isDescExpanded && (
                <div className="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-white to-transparent"></div>
              )}
            </div>
            
            <div className="mt-4 flex justify-center">
              <button 
                onClick={() => setIsDescExpanded(!isDescExpanded)}
                className="flex items-center gap-1 border border-[#007b22] text-[#007b22] px-6 py-1.5 rounded text-sm font-medium hover:bg-green-50 transition"
              >
                {isDescExpanded ? 'Thu gọn' : 'Đọc thêm'} <FiChevronDown className={`transform transition-transform ${isDescExpanded ? 'rotate-180' : ''}`} />
              </button>
            </div>
          </div>

          <div className="w-full lg:w-[40%] bg-white border border-gray-200 rounded-lg p-6">
            <h3 className="text-lg font-bold text-gray-800 mb-4 pb-4 border-b border-gray-100">Thông tin chi tiết</h3>
            
            <div className="flex flex-col gap-4 text-[14px]">
              <div className="flex"><span className="w-1/3 text-gray-500">Tác giả</span><span className="w-2/3 text-gray-800 font-semibold">{authorDisplay}</span></div>
              <div className="flex"><span className="w-1/3 text-gray-500">Dịch giả</span><span className="w-2/3 text-gray-800 font-semibold">{translator}</span></div>
              <div className="flex"><span className="w-1/3 text-gray-500">Nhà xuất bản</span><span className="w-2/3 text-gray-800 font-semibold">{publisherName}</span></div>
              <div className="flex"><span className="w-1/3 text-gray-500">Kích thước</span><span className="w-2/3 text-gray-800 font-semibold">{dimensions}</span></div>
              <div className="flex"><span className="w-1/3 text-gray-500">Số trang</span><span className="w-2/3 text-gray-800 font-semibold">{numPages}</span></div>
              <div className="flex"><span className="w-1/3 text-gray-500">Khối lượng</span><span className="w-2/3 text-gray-800 font-semibold">{weight !== "Đang cập nhật" ? `${weight} g` : weight}</span></div>
              <div className="flex"><span className="w-1/3 text-gray-500">Ngày phát hành</span><span className="w-2/3 text-gray-800 font-semibold">{pubYear}</span></div>
              <div className="flex"><span className="w-1/3 text-gray-500">Hình thức</span><span className="w-2/3 text-gray-800 font-semibold">{format}</span></div>
            </div>
          </div>
        </div>

        <div className="bg-white border border-gray-200 rounded-lg p-6 mt-6">
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5 pb-4 border-b border-gray-100">
            <div>
              <h3 className="text-lg font-bold text-gray-800">Đánh giá sản phẩm</h3>
              <p className="text-sm text-gray-500 mt-1">
                {reviewMeta?.total ? `${reviewMeta.total} đánh giá đã được duyệt` : 'Các đánh giá từ khách hàng đã mua sách'}
              </p>
            </div>
            {reviewEligibility?.can_review && (
              <div className="text-sm font-medium text-[#157a2c] bg-green-50 border border-green-100 rounded px-3 py-2">
                Bạn có thể đánh giá sách này trong mục đơn hàng.
              </div>
            )}
          </div>

          {reviews.length > 0 ? (
            <div className="flex flex-col divide-y divide-gray-100">
              {reviews.map((review) => (
                <div key={review.id} className="py-4 first:pt-0 last:pb-0">
                  <div className="flex flex-wrap items-center gap-3 mb-2">
                    <span className="font-bold text-gray-800">{review.reviewer_name || 'Khách hàng'}</span>
                    <span className="text-amber-500 text-sm font-semibold">{Number(review.rating || 0).toFixed(1)} sao</span>
                    {review.created_at && (
                      <span className="text-xs text-gray-400">
                        {new Date(review.created_at).toLocaleDateString('vi-VN')}
                      </span>
                    )}
                  </div>
                  <p className="text-sm text-gray-600 leading-relaxed">
                    {review.comment || 'Khách hàng không để lại bình luận.'}
                  </p>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center text-gray-500 bg-gray-50 border border-dashed border-gray-200 rounded py-8">
              Chưa có đánh giá nào cho sản phẩm này.
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default ProductDetailPage;
