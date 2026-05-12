// src/pages/ProductDetail/ProductDetailPage.jsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FiShoppingCart, FiHeart, FiChevronRight, FiMinus, FiPlus } from 'react-icons/fi';
import { formatCurrency } from '../../utils/formatters';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext'; // THÊM IMPORT

const mockBookDetail = {
  id: 1,
  name: "Túp lều bác Tom (TB 2026)",
  author: "Harriet Beecher Stowe",
  publisher: "Nhà xuất bản Hội Nhà văn",
  thumbnail: "https://placehold.co/400x600/EEE/31343C?text=Tup+Leu+Bac+Tom",
  images: [
    "https://placehold.co/400x600/EEE/31343C?text=Tup+Leu+Bac+Tom",
    "https://placehold.co/400x600/EEE/31343C?text=Mat+Sau",
    "https://placehold.co/400x600/EEE/31343C?text=Trang+Trong"
  ],
  originalPrice: 150000,
  salePrice: 120000,
  averageRating: 4.8,
  reviewCount: 125,
  inStock: 50,
  format: "Bìa mềm",
  dimensions: "14 x 20.5 cm",
  weight: "450g",
  numPages: 520,
  publicationYear: 2026,
  description: "Túp lều bác Tom là một cuốn tiểu thuyết chống chế độ nô lệ của nhà văn Mỹ Harriet Beecher Stowe. Tác phẩm đã có ảnh hưởng sâu sắc đến thái độ đối với người Mỹ gốc Phi và chế độ nô lệ ở Hoa Kỳ..."
};

const ProductDetailPage = () => {
  const navigate = useNavigate();
  const { addToCart } = useCart();
  const { toggleWishlist, isInWishlist } = useWishlist(); // KÉO CÔNG CỤ TỪ WISHLIST

  const [quantity, setQuantity] = useState(1);
  const [activeImage, setActiveImage] = useState(mockBookDetail.thumbnail);

  const discountPercent = Math.round(((mockBookDetail.originalPrice - mockBookDetail.salePrice) / mockBookDetail.originalPrice) * 100);

  const handleIncrease = () => setQuantity(prev => (prev < mockBookDetail.inStock ? prev + 1 : prev));
  const handleDecrease = () => setQuantity(prev => (prev > 1 ? prev - 1 : 1));

  const handleAddToCart = () => {
    addToCart(mockBookDetail, quantity);
    alert(`✅ Đã thêm ${quantity} cuốn "${mockBookDetail.name}" vào giỏ hàng!`);
  };

  const handleBuyNow = () => {
    handleAddToCart();
    navigate('/cart');
  };

  // Kiểm tra xem sách này đã được thả tim chưa
  const isFavorite = isInWishlist(mockBookDetail.id);

  return (
    <div className="bg-white min-h-screen pb-12">
      <div className="bg-[#f0f9f3] py-3 border-b border-green-100 mb-8">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <span className="hover:text-[#157a2c] cursor-pointer" onClick={() => navigate('/')}>Trang chủ</span>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="hover:text-[#157a2c] cursor-pointer">Hư cấu</span>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium truncate max-wxs">{mockBookDetail.name}</span>
        </div>
      </div>

      <div className="container mx-auto px-4">
        <div className="flex flex-col md:flex-row gap-10 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
          <div className="w-full md:w-5/12 flex flex-col gap-4">
            <div className="border border-gray-200 rounded-lg overflow-hidden p-2 flex justify-center bg-gray-50 h-[450px]">
              <img src={activeImage} alt={mockBookDetail.name} className="h-full w-auto object-contain" />
            </div>
            <div className="flex gap-4 overflow-x-auto py-2">
              {mockBookDetail.images.map((img, idx) => (
                <div 
                  key={idx} 
                  onClick={() => setActiveImage(img)}
                  className={`w-20 h-24 border-2 rounded cursor-pointer overflow-hidden flex-shrink-0 ${activeImage === img ? 'border-[#157a2c]' : 'border-gray-200 hover:border-gray-300'}`}
                >
                  <img src={img} alt={`Thumb ${idx}`} className="w-full h-full object-cover" />
                </div>
              ))}
            </div>
          </div>

          <div className="w-full md:w-7/12 flex flex-col">
            <h1 className="text-2xl font-bold text-gray-800 mb-2">{mockBookDetail.name}</h1>
            
            <div className="flex items-center gap-6 text-sm mb-4">
              <p>Tác giả: <span className="font-semibold text-[#157a2c]">{mockBookDetail.author}</span></p>
              <p>Nhà xuất bản: <span className="font-semibold text-gray-700">{mockBookDetail.publisher}</span></p>
            </div>

            <div className="flex items-center gap-2 mb-6">
              <span className="text-yellow-400 text-lg">★★★★★</span>
              <span className="text-gray-500 text-sm">({mockBookDetail.reviewCount} đánh giá)</span>
              <span className="border-l border-gray-300 h-4 mx-2"></span>
              <span className="text-[#157a2c] text-sm font-medium">Tình trạng: Còn hàng</span>
            </div>

            <div className="bg-gray-50 p-6 rounded-lg mb-8 flex items-end gap-4 border border-gray-100">
              <span className="text-3xl font-bold text-[#157a2c]">{formatCurrency(mockBookDetail.salePrice)}</span>
              {discountPercent > 0 && (
                <>
                  <span className="text-gray-400 line-through text-lg mb-1">{formatCurrency(mockBookDetail.originalPrice)}</span>
                  <span className="bg-[#ff424e] text-white text-sm font-bold px-2 py-1 rounded mb-1">-{discountPercent}%</span>
                </>
              )}
            </div>

            <div className="flex items-center gap-4 mb-8">
              <span className="text-gray-700 font-medium w-24">Số lượng:</span>
              <div className="flex items-center border border-gray-300 rounded overflow-hidden h-10">
                <button onClick={handleDecrease} className="px-3 hover:bg-gray-100 text-gray-600 h-full transition"><FiMinus /></button>
                <input type="text" value={quantity} readOnly className="w-full text-center border-x border-gray-300 h-full font-medium outline-none" />
                <button onClick={handleIncrease} className="px-3 hover:bg-gray-100 text-gray-600 h-full transition"><FiPlus /></button>
              </div>
            </div>

            <div className="flex gap-4 mt-auto">
              <button 
                onClick={handleAddToCart}
                className="flex-1 bg-white border-2 border-[#157a2c] text-[#157a2c] h-14 rounded-lg font-bold text-lg hover:bg-green-50 transition flex items-center justify-center gap-2 shadow-sm"
              >
                <FiShoppingCart size={22} /> Thêm vào giỏ hàng
              </button>
              <button 
                onClick={handleBuyNow}
                className="flex-1 bg-[#157a2c] text-white h-14 rounded-lg font-bold text-lg hover:bg-green-800 transition shadow-sm"
              >
                Mua ngay
              </button>
              {/* NÚT YÊU THÍCH SẼ ĐỔI MÀU NẾU ĐƯỢC CHỌN */}
              <button 
                onClick={() => toggleWishlist(mockBookDetail)}
                className={`w-14 h-14 border rounded-lg flex items-center justify-center transition shadow-sm ${
                  isFavorite 
                    ? 'bg-red-50 text-red-500 border-red-200 hover:bg-red-100' 
                    : 'bg-white text-gray-500 border-gray-200 hover:text-red-500 hover:border-red-200'
                }`} 
                title={isFavorite ? "Bỏ yêu thích" : "Thêm vào yêu thích"}
              >
                <FiHeart size={24} className={isFavorite ? 'fill-current' : ''} />
              </button>
            </div>
          </div>
        </div>

        <div className="mt-10 bg-white p-6 rounded-xl border border-gray-100 shadow-sm">
          <h2 className="text-xl font-bold text-gray-800 mb-6 uppercase border-b pb-3 border-gray-200">Thông tin chi tiết</h2>
          <div className="flex flex-col md:flex-row gap-10">
            <div className="w-full md:w-1/3">
              <ul className="flex flex-col border border-gray-200 rounded-lg overflow-hidden text-sm">
                <li className="flex border-b border-gray-200"><span className="w-1/3 bg-gray-50 p-3 text-gray-600 font-medium">Tác giả</span><span className="w-2/3 p-3 text-gray-800">{mockBookDetail.author}</span></li>
                <li className="flex border-b border-gray-200"><span className="w-1/3 bg-gray-50 p-3 text-gray-600 font-medium">Nhà xuất bản</span><span className="w-2/3 p-3 text-gray-800">{mockBookDetail.publisher}</span></li>
                <li className="flex border-b border-gray-200"><span className="w-1/3 bg-gray-50 p-3 text-gray-600 font-medium">Năm XB</span><span className="w-2/3 p-3 text-gray-800">{mockBookDetail.publicationYear}</span></li>
                <li className="flex border-b border-gray-200"><span className="w-1/3 bg-gray-50 p-3 text-gray-600 font-medium">Số trang</span><span className="w-2/3 p-3 text-gray-800">{mockBookDetail.numPages}</span></li>
                <li className="flex border-b border-gray-200"><span className="w-1/3 bg-gray-50 p-3 text-gray-600 font-medium">Kích thước</span><span className="w-2/3 p-3 text-gray-800">{mockBookDetail.dimensions}</span></li>
                <li className="flex border-b border-gray-200"><span className="w-1/3 bg-gray-50 p-3 text-gray-600 font-medium">Trọng lượng</span><span className="w-2/3 p-3 text-gray-800">{mockBookDetail.weight}</span></li>
                <li className="flex"><span className="w-1/3 bg-gray-50 p-3 text-gray-600 font-medium">Hình thức</span><span className="w-2/3 p-3 text-gray-800">{mockBookDetail.format}</span></li>
              </ul>
            </div>
            <div className="w-full md:w-2/3">
              <h3 className="text-lg font-bold text-gray-800 mb-4">Mô tả sản phẩm</h3>
              <p className="text-gray-700 leading-relaxed text-justify">
                {mockBookDetail.description}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductDetailPage;