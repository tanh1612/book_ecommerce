// src/components/Layout/Header.jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiSearch, FiShoppingCart, FiUser, FiHeart, FiMapPin, FiMenu, FiChevronRight } from 'react-icons/fi';
import { CATEGORIES_DB } from '../../utils/constants';
import { useAuth } from '../../context/AuthContext';
import { useCart } from '../../context/CartContext';
import { useWishlist } from '../../context/WishlistContext';

const Header = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const parentCategories = CATEGORIES_DB.filter(cat => cat.parent_id === null && cat.is_active);
  const [activeMenuId, setActiveMenuId] = useState(parentCategories[0]?.id || null);
  const subCategories = CATEGORIES_DB.filter(cat => cat.parent_id === activeMenuId && cat.is_active);

  const [searchTerm, setSearchTerm] = useState('');
  const navigate = useNavigate();

  // Dùng Context thay vì localStorage/Redux
  const { user, logout } = useAuth();
  const { totalQuantity } = useCart();
  const { wishlistItems } = useWishlist();

  const handleSearch = () => {
    if (searchTerm.trim() !== '') {
      navigate(`/search?keyword=${encodeURIComponent(searchTerm.trim())}`);
      setIsMenuOpen(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') handleSearch();
  };

  return (
    <header className="bg-white relative z-50">
      {/* TẦNG 1: TOP BAR */}
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

      {/* TẦNG 2: MAIN HEADER */}
      <div className="container mx-auto px-4 py-5 flex items-center justify-between gap-8 border-b border-gray-100">
        <Link to="/" className="flex-shrink-0 flex items-center gap-2 text-[#157a2c]">
          <div className="font-extrabold text-2xl tracking-tighter flex flex-col items-center leading-none">
            <span>Bookify</span>
          </div>
        </Link>
    
        <div className="flex-grow max-w-3xl relative">
          <input
            type="text"
            placeholder="Tên sách lên xu hướng/bestselling..."
            className="w-full border border-gray-300 rounded-md py-2.5 px-4 pr-12 outline-none focus:border-[#157a2c] transition-all text-sm"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            onKeyDown={handleKeyDown}
          />
          <button 
            onClick={handleSearch}
            className="absolute right-0 top-0 h-full w-12 text-gray-500 hover:text-[#157a2c] flex items-center justify-center rounded-r-md transition-colors"
          >
            <FiSearch size={20} />
          </button>
        </div>

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
        {/* NÚT YÊU THÍCH ĐÃ ĐƯỢC CẬP NHẬT */}
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
                  onClick={() => {
                    logout();
                    navigate('/'); 
                  }}
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

      {/* TẦNG 3: MENU ĐIỀU HƯỚNG & MEGA MENU */}
      <div className="border-b border-gray-200 relative">
        <div className="container mx-auto px-4 flex items-center gap-8">
          <div 
            className="relative"
            onMouseEnter={() => setIsMenuOpen(true)}
            onMouseLeave={() => setIsMenuOpen(false)}
          >
            <button className={`flex items-center gap-2 font-bold py-3 px-6 border-x border-gray-100 transition-colors ${isMenuOpen ? 'text-[#157a2c] bg-gray-50' : 'text-[#157a2c] bg-white'}`}>
              <FiMenu size={20} />
              DANH MỤC
            </button>

            {isMenuOpen && (
              <div className="absolute top-full left-0 w-[850px] flex shadow-2xl border border-gray-100 rounded-b-lg overflow-hidden bg-white">
                <div className="w-64 bg-white flex-shrink-0">
                  <ul className="flex flex-col">
                    {parentCategories.map((category) => (
                      <li 
                        key={category.id}
                        onMouseEnter={() => setActiveMenuId(category.id)}
                        className={`px-4 py-3 cursor-pointer border-b border-gray-50 flex justify-between items-center transition-colors ${
                          activeMenuId === category.id 
                            ? 'bg-[#157a2c] text-white font-medium' 
                            : 'text-gray-700 hover:bg-gray-100'
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
                          to={`/category/${sub.slug}`} 
                          className="bg-white px-3 py-2 text-sm text-gray-700 rounded border border-gray-100 shadow-sm hover:text-[#157a2c] hover:border-[#157a2c] transition-colors truncate"
                          title={sub.name}
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
            <li><Link to="/sach-moi" className="hover:text-[#157a2c] transition">Sách mới</Link></li>
            <li><Link to="/sach-ban-chay" className="hover:text-[#157a2c] transition">Sách bán chạy</Link></li>
            <li><Link to="/sach-xu-huong" className="text-[#157a2c] transition">Sách xu hướng</Link></li>
            <li><Link to="/an-pham-dac-biet" className="hover:text-[#157a2c] transition">Ấn phẩm đặc biệt</Link></li>
            <li><Link to="/sach-dat-truoc" className="hover:text-[#157a2c] transition">Sách Đặt Trước</Link></li>
          </ul>
        </div>
      </div>
    </header>
  );
};

export default Header;