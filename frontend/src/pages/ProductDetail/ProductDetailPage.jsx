// src/pages/Product/ProductDetailPage.jsx
import { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { FiShoppingCart, FiMinus, FiPlus, FiChevronRight, FiHeart } from 'react-icons/fi';
import { toast } from 'react-toastify';
import bookApi from '../../services/bookApi';
import { formatCurrency } from '../../utils/formatters';
import { useCart } from '../../context/CartContext';

const ProductDetailPage = () => {
  const { slug } = useParams();
  const { addToCart } = useCart();
  const [book, setBook] = useState(null);
  const [quantity, setQuantity] = useState(1);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const fetchBook = async () => {
      try {
        setIsLoading(true);
        const res = await bookApi.getBookDetail(slug);
        setBook(res.data.data || res.data);
      } catch (err) {
        toast.error("Không tìm thấy thông tin sách!");
      } finally {
        setIsLoading(false);
      }
    };
    fetchBook();
    window.scrollTo(0, 0);
  }, [slug]);

  const handleAddToCart = () => {
    addToCart(book, quantity);
    toast.success(`Đã thêm ${quantity} cuốn vào giỏ hàng`);
  };

  if (isLoading) return (
    <div className="py-20 flex justify-center">
      <div className="w-10 h-10 border-4 border-[#157a2c] border-t-transparent rounded-full animate-spin"></div>
    </div>
  );
  
  if (!book) return <div className="py-20 text-center text-gray-500">Sách không tồn tại.</div>;

  return (
    <div className="bg-white min-h-screen pb-20">
      {/* BREADCRUMB - Giữ phong cách cũ */}
      <div className="bg-gray-50 py-3 border-b border-gray-200">
        <div className="container mx-auto px-4 text-sm text-gray-500 flex items-center gap-2">
          <Link to="/" className="hover:text-[#157a2c]">Trang chủ</Link>
          <FiChevronRight size={14} />
          <Link to="/catalog" className="hover:text-[#157a2c]">Danh mục sách</Link>
          <FiChevronRight size={14} />
          <span className="text-[#157a2c] font-medium truncate">{book.title}</span>
        </div>
      </div>

      <div className="container mx-auto px-4 mt-8">
        <div className="flex flex-col md:flex-row gap-8 lg:gap-16">
          
          {/* CỘT TRÁI: ẢNH SẢCH */}
          <div className="md:w-1/3 lg:w-2/5">
            <div className="sticky top-24">
              <div className="border border-gray-200 rounded-lg p-6 bg-white flex justify-center items-center shadow-sm">
                <img 
                  src={book.thumbnail || book.image_url} 
                  alt={book.title} 
                  className="max-w-full h-auto max-h-[500px] object-contain transition-transform hover:scale-105 duration-300" 
                />
              </div>
            </div>
          </div>

          {/* CỘT PHẢI: THÔNG TIN CHI TIẾT */}
          <div className="md:w-2/3 lg:w-3/5">
            <h1 className="text-2xl lg:text-3xl font-bold text-gray-800 mb-4 leading-tight">
              {book.title}
            </h1>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 mb-6 text-[15px]">
              <p><span className="text-gray-500">Tác giả:</span> <span className="font-bold text-[#157a2c]">{book.authors?.map(a => a.name).join(', ') || "Nhiều tác giả"}</span></p>
              <p><span className="text-gray-500">Nhà xuất bản:</span> <span className="font-bold">{book.publisher?.name}</span></p>
              <p><span className="text-gray-500">Nhà cung cấp:</span> <span className="font-bold text-[#157a2c]">{book.supplier?.name}</span></p>
              <p><span className="text-gray-500">Mã SKU:</span> <span className="font-bold">{book.sku}</span></p>
            </div>

            {/* GIÁ CẢ */}
            <div className="bg-[#f9f9f9] p-6 rounded-xl flex items-center gap-6 mb-8 border border-gray-100 shadow-sm">
              <span className="text-3xl font-bold text-[#157a2c]">
                {formatCurrency(book.price)}
              </span>
              {book.original_price > book.price && (
                <div className="flex items-center gap-3">
                  <span className="text-lg text-gray-400 line-through">
                    {formatCurrency(book.original_price)}
                  </span>
                  <span className="bg-[#ff424e] text-white px-2 py-0.5 rounded text-sm font-bold shadow-sm">
                    -{Math.round(((book.original_price - book.price) / book.original_price) * 100)}%
                  </span>
                </div>
              )}
            </div>

            {/* CHỌN SỐ LƯỢNG & NÚT BẤM */}
            <div className="flex flex-col gap-6">
              <div className="flex items-center gap-4">
                <span className="text-sm font-bold text-gray-700 uppercase tracking-wider">Số lượng:</span>
                <div className="flex items-center border-2 border-gray-200 rounded-lg overflow-hidden h-11 bg-white">
                  <button 
                    onClick={() => setQuantity(q => Math.max(1, q-1))} 
                    className="px-4 hover:bg-gray-100 transition h-full text-gray-600"
                  >
                    <FiMinus />
                  </button>
                  <input 
                    type="text" 
                    value={quantity} 
                    readOnly 
                    className="w-14 text-center font-bold text-lg outline-none text-gray-800" 
                  />
                  <button 
                    onClick={() => setQuantity(q => q + 1)} 
                    className="px-4 hover:bg-gray-100 transition h-full text-gray-600"
                  >
                    <FiPlus />
                  </button>
                </div>
              </div>

              <div className="flex flex-wrap gap-4">
                <button 
                  onClick={handleAddToCart}
                  className="flex-grow sm:flex-none sm:w-64 h-14 bg-white border-2 border-[#157a2c] text-[#157a2c] font-bold rounded-lg flex items-center justify-center gap-3 hover:bg-green-50 transition-all shadow-sm"
                >
                  <FiShoppingCart size={22} /> THÊM VÀO GIỎ HÀNG
                </button>
                <button className="flex-grow sm:flex-none sm:w-64 h-14 bg-[#157a2c] text-white font-bold rounded-lg hover:bg-green-800 transition-all shadow-md active:scale-95">
                  MUA NGAY
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* PHẦN THÔNG TIN CHI TIẾT - CODE THEO DATA THẬT */}
        <div className="mt-20">
          <div className="border-b border-gray-200 mb-8">
            <h2 className="text-xl font-bold text-gray-800 mb-[-2px] uppercase border-b-4 border-[#157a2c] pb-2 inline-block tracking-widest">
              Thông tin chi tiết
            </h2>
          </div>
          
          <div className="max-w-4xl">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-x-12">
              <table className="w-full text-[15px]">
                <tbody>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500 w-1/3">Nhà xuất bản</td>
                    <td className="py-4 font-semibold text-gray-800">{book.publisher?.name}</td>
                  </tr>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500">Năm xuất bản</td>
                    <td className="py-4 font-semibold text-gray-800">{book.publication_year}</td>
                  </tr>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500">Số trang</td>
                    <td className="py-4 font-semibold text-gray-800">{book.num_pages}</td>
                  </tr>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500">Hình thức</td>
                    <td className="py-4 font-semibold text-gray-800">{book.format}</td>
                  </tr>
                </tbody>
              </table>

              <table className="w-full text-[15px]">
                <tbody>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500 w-1/3">Kích thước</td>
                    <td className="py-4 font-semibold text-gray-800">{book.dimensions}</td>
                  </tr>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500">Trọng lượng</td>
                    <td className="py-4 font-semibold text-gray-800">{book.weight} gr</td>
                  </tr>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500">Ngôn ngữ</td>
                    <td className="py-4 font-semibold text-gray-800">{book.language}</td>
                  </tr>
                  <tr className="border-b border-gray-100">
                    <td className="py-4 text-gray-500">Dịch giả</td>
                    <td className="py-4 font-semibold text-gray-800">{book.translator || "Không có"}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        {/* PHẦN MÔ TẢ */}
        <div className="mt-16 bg-white">
          <div className="border-b border-gray-200 mb-8">
            <h2 className="text-xl font-bold text-gray-800 mb-[-2px] uppercase border-b-4 border-[#157a2c] pb-2 inline-block tracking-widest">
              Mô tả sản phẩm
            </h2>
          </div>
          <div className="text-gray-700 leading-8 whitespace-pre-line text-[16px] max-w-5xl prose prose-green">
            {book.description}
          </div>
        </div>
      </div>
    </div>
  );
};

export default ProductDetailPage;