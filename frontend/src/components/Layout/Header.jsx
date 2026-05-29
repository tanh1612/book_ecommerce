// src/components/Layout/Header.jsx
import { useState, useEffect, useRef } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { FiSearch, FiShoppingCart, FiUser, FiHeart, FiMapPin, FiMenu, FiChevronRight } from 'react-icons/fi';
import { useAuth } from '../../context/AuthContext';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';
import bookApi from '../../services/bookApi';
import { formatCurrency } from '../../utils/formatters';

const Header = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [parentCategories, setParentCategories] = useState([]);
  const [activeMenuId, setActiveMenuId] = useState(null);
  const navigate = useNavigate();
  const location = useLocation();

  const { user, logout } = useAuth();
  const { totalQuantity } = useCart();
  const { wishlistItems } = useWishlist();

  // --- STATE TÌM KIẾM & GỢI Ý ---
  const [searchTerm, setSearchTerm] = useState('');
  const [suggestions, setSuggestions] = useState([]);
  const [isSuggestionOpen, setIsSuggestionOpen] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const searchRef = useRef(null);
  // THÊM DÒNG NÀY: Kho lưu trữ lịch sử tìm kiếm
  const searchCache = useRef({});

  // Lấy danh mục
  useEffect(() => {
    bookApi.getFilters().then(res => {
      const allCats = res.data?.data?.categories || res.data?.categories || [];
      const targetNames = ["Hư cấu", "Phi hư cấu", "Phân loại khác"];
      const filteredParents = allCats.filter(cat => targetNames.includes(cat.name));
      const displayParents = filteredParents.length > 0 ? filteredParents : allCats;
      
      setParentCategories(displayParents);
      if (displayParents.length > 0) setActiveMenuId(displayParents[0].id);
    }).catch(err => console.error("Lỗi tải danh mục:", err));
  }, []);

  const subCategories = parentCategories.find(cat => cat.id === activeMenuId)?.children || [];

  // 1. Xử lý Click ra ngoài thì đóng Dropdown
  useEffect(() => {
    const handleClickOutside = (event) => {
      if (searchRef.current && !searchRef.current.contains(event.target)) {
        setIsSuggestionOpen(false);
        setIsSearching(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);
  useEffect(() => {
    setIsSuggestionOpen(false);
    setIsSearching(false);
    // Nếu bạn muốn xóa luôn chữ đã gõ khi chuyển trang, bỏ comment dòng dưới:
    // setSearchTerm(''); 
  }, [location.pathname]);

  // 2. Xử lý gọi API Gợi ý (Nhanh như chớp với Local Cache)
  useEffect(() => {
    const keyword = searchTerm.trim();
    
    if (keyword.length === 0) {
      setSuggestions([]);
      setIsSuggestionOpen(false);
      return;
    }

    // NẾU TỪ KHÓA ĐÃ TỪNG TÌM: Lấy từ Cache ra dùng luôn, siêu nhanh!
    if (searchCache.current[keyword]) {
      setSuggestions(searchCache.current[keyword]);
      setIsSuggestionOpen(true);
      return;
    }

    let ignore = false;
    setIsSearching(true);

    // GIẢM ĐỘ TRỄ XUỐNG 250ms
    const delayDebounceFn = setTimeout(async () => {
      try {
        const res = await bookApi.getSuggestions(keyword);
        
        if (!ignore) {
          const data = res.data?.data || res.data || [];
          
          // LƯU VÀO CACHE để lần sau không phải gọi API nữa
          searchCache.current[keyword] = data; 
          
          setSuggestions(data);
          setIsSuggestionOpen(true);
        }
      } catch (error) {
        console.error("Lỗi lấy gợi ý tìm kiếm:", error);
      } finally {
        if (!ignore) {
          setIsSearching(false);
        }
      }
    }, 250); 

    return () => {
      ignore = true;
      clearTimeout(delayDebounceFn);
    };
  }, [searchTerm]);

  // Hành động khi nhấn Enter hoặc Bấm kính lúp
  const handleSearch = () => {
    if (searchTerm.trim() !== '') {
      navigate(`/catalog?keyword=${encodeURIComponent(searchTerm.trim())}`);
      setIsSuggestionOpen(false);
    }
  };

  return (
    <header className="bg-white relative z-50">
      <div className="bg-[#157a2c] text-white text-sm hidden md:block">
        <div className="container mx-auto px-4 py-1.5 flex justify-between items-center">
          <ul className="flex gap-6">
            <li className="flex items-center gap-1 hover:text-gray-200 cursor-pointer">
              <FiMapPin /> Hệ Thống Cửa Hàng
            </li>
            <li className="hover:text-gray-200 cursor-pointer">Về Bookify</li>
            <li className="hover:text-gray-200 cursor-pointer">Event</li>
          </ul>
          <div className="flex items-center gap-3">
            <span>Liên hệ:</span>
            <span className="font-bold">0337706769</span>
          </div>
        </div>
      </div>

      <div className="container mx-auto px-4 py-5 flex items-center justify-between gap-8 border-b border-gray-100">
        <Link to="/" className="flex-shrink-0 flex items-center gap-2 text-[#157a2c]">
          <div className="font-extrabold text-2xl tracking-tighter flex flex-col items-center leading-none">
            <span>Bookify</span>
          </div>
        </Link>
    
        {/* --- KHU VỰC THANH TÌM KIẾM --- */}
        <div className="flex-grow max-w-3xl relative" ref={searchRef}>
          <input
            type="text"
            placeholder="Tên sách lên xu hướng/bestselling..."
            className="w-full border border-gray-300 rounded-md py-2.5 px-4 pr-12 outline-none focus:border-[#157a2c] transition-all text-sm"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            onFocus={() => { if (searchTerm.trim().length > 0) setIsSuggestionOpen(true) }}
          />
          <button 
            onClick={handleSearch}
            className="absolute right-0 top-0 h-full w-12 text-gray-500 hover:text-[#157a2c] flex items-center justify-center rounded-r-md transition-colors"
          >
            {isSearching ? (
              <div className="w-5 h-5 border-2 border-gray-400 border-t-transparent rounded-full animate-spin"></div>
            ) : (
              <FiSearch size={20} />
            )}
          </button>

          {/* KHUNG DROPDOWN GỢI Ý */}
          {isSuggestionOpen && (
            <div className="absolute top-full mt-2 left-0 w-full bg-white border border-gray-200 rounded-lg shadow-xl z-50 max-h-[400px] overflow-y-auto">
              {suggestions.length > 0 ? (
                <div className="flex flex-col">
                  <div className="px-4 py-2 bg-gray-50 text-xs font-semibold text-gray-500 border-b border-gray-100">
                    Sách gợi ý
                  </div>
                  {suggestions.map((book) => (
                    <Link
                      key={book.id}
                      to={`/book/${book.slug}`}
                      onClick={() => setIsSuggestionOpen(false)}
                      className="flex items-center gap-3 p-3 hover:bg-green-50 border-b border-gray-100 last:border-0 transition-colors"
                    >
                      <img 
                        src={book.thumbnail_url || book.thumbnail || "https://placehold.co/40x60?text=No+Image"} 
                        alt={book.name || book.title} 
                        className="w-10 h-14 object-cover rounded shadow-sm border border-gray-200" 
                      />
                      <div className="flex flex-col justify-center">
                        <h4 className="text-sm font-medium text-gray-800 line-clamp-1">{book.name || book.title}</h4>
                        <div className="flex gap-2 items-center mt-1">
                          <span className="text-xs font-bold text-[#ff424e]">
                            {formatCurrency(book.selling_price || book.price || 0)}
                          </span>
                          {(book.original_price && book.original_price > book.selling_price) && (
                            <span className="text-[10px] text-gray-400 line-through">
                              {formatCurrency(book.original_price)}
                            </span>
                          )}
                        </div>
                      </div>
                    </Link>
                  ))}
                  <button 
                    onClick={handleSearch}
                    className="p-3 text-center text-sm text-[#157a2c] font-medium hover:bg-green-50 transition-colors"
                  >
                    Xem tất cả kết quả cho "{searchTerm}"
                  </button>
                </div>
              ) : (
                <div className="p-8 text-center flex flex-col items-center justify-center text-gray-500">
                  <FiSearch size={32} className="mb-2 text-gray-300" />
                  <span className="text-sm">Không tìm thấy sách nào khớp với "{searchTerm}"</span>
                </div>
              )}
            </div>
          )}
        </div>
        {/* ------------------------------ */}

        <div className="flex items-center gap-8 flex-shrink-0">
          <Link to="/cart" className="flex flex-col items-center text-gray-600 hover:text-[#157a2c] relative transition-colors">
            <FiShoppingCart size={22} strokeWidth={1.5} />
            <span className="text-[11px] mt-1">Giỏ hàng</span>
            {totalQuantity > 0 && (
              <span className="absolute -top-1.5 -right-2 bg-red-600 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">
                {totalQuantity}
              </span>
            )}
          </Link>
          
          <Link to="/wishlist" className="flex flex-col items-center text-gray-600 hover:text-[#157a2c] relative transition-colors">
            <FiHeart size={22} strokeWidth={1.5} />
            <span className="text-[11px] mt-1">Yêu thích</span>
            {wishlistItems.length > 0 && (
              <span className="absolute -top-1.5 -right-2 bg-red-600 text-white text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">
                {wishlistItems.length}
              </span>
            )}
          </Link>
          
          {user ? (
            <div className="flex flex-col items-center text-[#157a2c] relative group">
              <div 
                className="flex flex-col items-center cursor-pointer"
                onClick={() => navigate('/profile')}
              >
                <FiUser size={22} strokeWidth={1.5} />
                <span className="text-[11px] mt-1 font-bold whitespace-nowrap">
                  {user.lastName} {user.firstName}
                </span>
              </div>
              <div className="absolute top-full pt-2 right-0 hidden group-hover:block z-50">
                <button 
                  onClick={() => { logout(); navigate('/'); }}
                  className="bg-white border border-gray-200 shadow-lg rounded-md px-4 py-2 text-sm text-red-600 hover:bg-red-50 whitespace-nowrap"
                >
                  Đăng xuất
                </button>
              </div>
            </div>
          ) : (
            <Link to="/login" className="flex flex-col items-center text-gray-600 hover:text-[#157a2c] transition-colors">
              <FiUser size={22} strokeWidth={1.5} />
              <span className="text-[11px] mt-1">Tài khoản</span>
            </Link>
          )}
        </div>
      </div>

      <div className="border-b border-gray-200 relative">
        <div className="container mx-auto px-4 flex items-center gap-8">
          <div 
            className="relative"
            onMouseEnter={() => setIsMenuOpen(true)}
            onMouseLeave={() => setIsMenuOpen(false)}
          >
            <button className={`flex items-center gap-2 font-bold py-3 px-6 border-x border-gray-100 transition-colors ${isMenuOpen ? 'text-[#157a2c] bg-gray-50' : 'text-[#157a2c] bg-white'}`}>
              <FiMenu size={20} /> DANH MỤC
            </button>

            {isMenuOpen && (
              <div className="absolute top-full left-0 w-[850px] flex shadow-2xl border border-gray-100 rounded-b-lg overflow-hidden bg-white">
                <div className="w-64 bg-white flex-shrink-0">
                  <ul className="flex flex-col">
                    {parentCategories.map((category) => (
                      <li 
                        key={category.id}
                        onMouseEnter={() => setActiveMenuId(category.id)}
                        onClick={() => {
                          navigate(`/catalog?category=${category.slug}`);
                          setIsMenuOpen(false);
                        }}
                        className={`px-4 py-3 cursor-pointer border-b border-gray-50 flex justify-between items-center transition-colors ${
                          activeMenuId === category.id ? 'bg-[#157a2c] text-white font-medium' : 'text-gray-700 hover:bg-gray-100'
                        }`}
                      >
                        {category.name}
                        <FiChevronRight size={16} className={activeMenuId === category.id ? 'text-white' : 'text-gray-400'} />
                      </li>
                    ))}
                  </ul>
                </div>
                <div className="flex-grow bg-[#f3f4f6] p-6">
                  {subCategories.length > 0 ? (
                    <div className="grid grid-cols-3 gap-3">
                      {subCategories.map((sub) => (
                        <Link 
                          key={sub.id} 
                          to={`/catalog?category=${sub.slug}`} 
                          onClick={() => setIsMenuOpen(false)}
                          className="bg-white px-3 py-2 text-sm text-gray-700 rounded border border-gray-100 shadow-sm hover:text-[#157a2c] hover:border-[#157a2c] transition-colors truncate"
                        >
                          {sub.name}
                        </Link>
                      ))}
                    </div>
                  ) : (
                    <div className="text-gray-400 italic text-sm">Đang cập nhật danh mục...</div>
                  )}
                </div>
              </div>
            )}
          </div>

          <ul className="flex items-center gap-6 text-sm text-gray-800 font-medium">
            <li><Link to="/catalog?sort=newest" className="hover:text-[#157a2c] transition">Sách mới</Link></li>
            <li><Link to="/catalog?sort=best_selling" className="hover:text-[#157a2c] transition">Sách bán chạy</Link></li>
            <li><Link to="/catalog?sort=rating_desc" className="text-[#157a2c] transition">Sách xu hướng</Link></li>
          </ul>
        </div>
      </div>
    </header>
  );
};

export default Header;