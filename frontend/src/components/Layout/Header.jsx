import { useState, useEffect, useRef, useCallback } from "react";
import { Link, useNavigate, useLocation } from "react-router-dom";
import {
  FiSearch,
  FiShoppingCart,
  FiUser,
  FiHeart,
  FiMenu,
  FiChevronRight,
} from "react-icons/fi";
import { toast } from "react-toastify";
import { useAuth } from "../../context/AuthContext";
import { useCart } from "../../context/CartContext";
import { useWishlist } from "../../context/WishlistContext";
import bookApi from "../../services/bookApi";
import logo from "../../assets/logo.png";

const SEARCH_CACHE_TTL = 5 * 60 * 1000;
const SEARCH_SUGGESTION_LIMIT = 8;

const Header = () => {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [parentCategories, setParentCategories] = useState([]);
  const [activeMenuId, setActiveMenuId] = useState(null);
  const [headerHeight, setHeaderHeight] = useState(0);
  const navigate = useNavigate();
  const location = useLocation();

  const { user, logout } = useAuth();
  const { totalQuantity } = useCart();
  const { wishlistItems } = useWishlist();

  const [searchTerm, setSearchTerm] = useState("");
  const [suggestions, setSuggestions] = useState([]);
  const [isSuggestionOpen, setIsSuggestionOpen] = useState(false);
  const [isSearching, setIsSearching] = useState(false);
  const headerRef = useRef(null);
  const searchRef = useRef(null);
  const searchCache = useRef({});
  const isCategoryMenuIntent = useRef(false);

  const getCachedSuggestions = useCallback((keyword) => {
    const cached = searchCache.current[keyword];
    if (!cached) return null;

    const isExpired = Date.now() - cached.timestamp > SEARCH_CACHE_TTL;
    if (isExpired) {
      delete searchCache.current[keyword];
      return null;
    }

    return cached.data;
  }, []);

  const setCachedSuggestions = useCallback((keyword, data) => {
    searchCache.current[keyword] = { data, timestamp: Date.now() };
  }, []);

  useEffect(() => {
    bookApi
      .getFilters()
      .then((res) => {
        const allCategories =
          res.data?.data?.categories || res.data?.categories || [];
        const targetNames = ["Hư cấu", "Phi hư cấu", "Phân loại khác"];
        const filteredParents = allCategories.filter((category) =>
          targetNames.includes(category.name),
        );
        const displayParents =
          filteredParents.length > 0 ? filteredParents : allCategories;

        setParentCategories(displayParents);
        if (displayParents.length > 0) setActiveMenuId(displayParents[0].id);
      })
      .catch((error) => console.error("Lỗi tải danh mục:", error));
  }, []);

  const subCategories =
    parentCategories.find((category) => category.id === activeMenuId)
      ?.children || [];
  const visibleSuggestions = suggestions.slice(0, SEARCH_SUGGESTION_LIMIT);
  const isDropdownOpen = isMenuOpen || isSuggestionOpen;

  const closeSearchDropdowns = useCallback(() => {
    setIsMenuOpen(false);
    setIsSuggestionOpen(false);
    setIsSearching(false);
  }, []);

  useEffect(() => {
    const updateHeaderHeight = () => {
      setHeaderHeight(headerRef.current?.offsetHeight || 0);
    };

    updateHeaderHeight();
    window.addEventListener("resize", updateHeaderHeight);

    if (!window.ResizeObserver || !headerRef.current) {
      return () => window.removeEventListener("resize", updateHeaderHeight);
    }

    const resizeObserver = new ResizeObserver(updateHeaderHeight);
    resizeObserver.observe(headerRef.current);

    return () => {
      window.removeEventListener("resize", updateHeaderHeight);
      resizeObserver.disconnect();
    };
  }, []);

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (searchRef.current && !searchRef.current.contains(event.target)) {
        closeSearchDropdowns();
      }
    };

    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [closeSearchDropdowns]);

  useEffect(() => {
    closeSearchDropdowns();
  }, [location.pathname, closeSearchDropdowns]);

  useEffect(() => {
    const keyword = searchTerm.trim();

    if (keyword.length < 2) {
      setSuggestions([]);
      setIsSuggestionOpen(false);
      setIsSearching(false);
      return;
    }

    const cachedData = getCachedSuggestions(keyword);
    if (cachedData) {
      setSuggestions(cachedData);
      if (!isCategoryMenuIntent.current) {
        setIsMenuOpen(false);
        setIsSuggestionOpen(true);
      }
      return;
    }

    let ignore = false;
    setIsSearching(true);

    const delayDebounceFn = setTimeout(async () => {
      try {
        const res = await bookApi.getSuggestions(
          keyword,
          SEARCH_SUGGESTION_LIMIT,
        );

        if (!ignore) {
          const data = res.data?.data || res.data || [];
          setCachedSuggestions(keyword, data);
          setSuggestions(data);
          if (!isCategoryMenuIntent.current) {
            setIsMenuOpen(false);
            setIsSuggestionOpen(true);
          }
        }
      } catch (error) {
        if (!ignore && error.response?.status >= 500) {
          toast.error("Lỗi tải gợi ý tìm kiếm");
        }
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
  }, [searchTerm, getCachedSuggestions, setCachedSuggestions]);

  const handleSearch = () => {
    if (searchTerm.trim() !== "") {
      navigate(`/catalog?keyword=${encodeURIComponent(searchTerm.trim())}`);
      closeSearchDropdowns();
    }
  };

  const handleCategoryClick = (category) => {
    navigate(`/catalog?category=${category.slug}`);
    closeSearchDropdowns();
  };

  return (
    <header ref={headerRef} className="site-header sticky top-0 z-50 shadow-lg">
      {isDropdownOpen && (
        <div
          className="site-header-overlay fixed left-0 right-0 bottom-0 z-40"
          style={{ top: `${headerHeight}px` }}
          onMouseDown={closeSearchDropdowns}
          aria-hidden="true"
        />
      )}

      <div className="site-header-shell relative z-50 container mx-auto px-8 lg:px-10 py-5 flex items-center justify-between gap-8">
        <Link to="/" className="flex-shrink-0 flex items-center">
          <img
            src={logo}
            alt="Bookify"
            className="h-10 w-auto object-contain"
          />
        </Link>

        <div
          className="relative flex-grow max-w-2xl min-w-0 flex items-stretch"
          ref={searchRef}
        >
          <div className="relative flex-shrink-0">
            <button
              type="button"
              onClick={() => {
                isCategoryMenuIntent.current = true;
                setIsSuggestionOpen(false);
                setIsMenuOpen((isOpen) => !isOpen);
              }}
              className={`header-menu-button h-full w-14 border-r-0 rounded-l-md flex items-center justify-center ${
                isMenuOpen ? "is-open" : ""
              }`}
              title="Danh mục"
            >
              <FiMenu size={22} />
            </button>

            {isMenuOpen && (
              <div className="header-dropdown absolute top-full left-0 w-[42rem] max-w-[calc(100vw-4rem)] flex rounded-lg overflow-hidden z-[60] mt-2">
                <div className="w-48 flex-shrink-0">
                  <ul className="flex flex-col">
                    {parentCategories.map((category) => (
                      <li
                        key={category.id}
                        onMouseEnter={() => setActiveMenuId(category.id)}
                        onClick={() => handleCategoryClick(category)}
                        className={`header-category-item px-4 py-3 cursor-pointer flex justify-between items-center gap-3 ${
                          activeMenuId === category.id
                            ? "is-active font-medium"
                            : ""
                        }`}
                      >
                        <span className="min-w-0 truncate">
                          {category.name}
                        </span>
                        <FiChevronRight
                          size={16}
                          className={
                            activeMenuId === category.id
                              ? "text-white"
                              : "app-soft-text"
                          }
                        />
                      </li>
                    ))}
                  </ul>
                </div>
                <div className="header-dropdown-muted flex-grow p-4">
                  {subCategories.length > 0 ? (
                    <div className="grid grid-cols-2 gap-3">
                      {subCategories.map((subCategory) => (
                        <Link
                          key={subCategory.id}
                          to={`/catalog?category=${subCategory.slug}`}
                          onClick={closeSearchDropdowns}
                          className="header-subcategory-link px-3 py-2 text-sm rounded truncate"
                        >
                          {subCategory.name}
                        </Link>
                      ))}
                    </div>
                  ) : (
                    <div className="app-soft-text italic text-sm">
                      Đang cập nhật danh mục...
                    </div>
                  )}
                </div>
              </div>
            )}
          </div>

          <div className="relative flex-grow min-w-0">
            <input
              type="text"
              placeholder="Tên sách lên xu hướng/bestselling..."
              className="header-search-input w-full rounded-r-md py-2.5 px-4 pr-12 outline-none text-sm"
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              onKeyDown={(event) => event.key === "Enter" && handleSearch()}
              onFocus={() => {
                isCategoryMenuIntent.current = false;
                setIsMenuOpen(false);
                if (searchTerm.trim().length >= 2) setIsSuggestionOpen(true);
              }}
            />
            <button
              type="button"
              onClick={handleSearch}
              className="header-icon-link absolute right-0 top-0 h-full w-12 flex items-center justify-center rounded-r-md"
            >
              {isSearching ? (
                <div className="app-spinner w-5 h-5 border-2 rounded-full animate-spin" />
              ) : (
                <FiSearch size={20} />
              )}
            </button>

            {isSuggestionOpen && (
              <div className="header-dropdown absolute top-full mt-2 -left-14 w-[calc(100%+3.5rem)] rounded-lg z-[60] overflow-hidden">
                {suggestions.length > 0 ? (
                  <div className="flex flex-col">
                    <div className="header-dropdown-muted app-muted-text app-section-divider px-4 py-2 text-xs font-semibold">
                      Sách gợi ý
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 p-4">
                      {visibleSuggestions.map((book) => (
                        <Link
                          key={book.id}
                          to={`/book/${book.slug}`}
                          onClick={closeSearchDropdowns}
                          className="header-suggestion-item flex min-w-0 items-start gap-3 rounded-md p-1.5"
                        >
                          <img
                            src={
                              book.thumbnail_url ||
                              book.thumbnail ||
                              "https://placehold.co/40x60?text=No+Image"
                            }
                            alt={book.name || book.title}
                            className="app-media-frame w-10 h-14 flex-shrink-0 object-cover rounded shadow-sm"
                          />
                          <span className="min-w-0 text-sm font-medium leading-5 app-section-title line-clamp-2">
                            {book.name || book.title}
                          </span>
                        </Link>
                      ))}
                    </div>
                    <button
                      type="button"
                      onClick={handleSearch}
                      className="header-suggestion-item p-3 text-center text-sm text-primary font-medium"
                    >
                      Xem tất cả kết quả cho &quot;{searchTerm}&quot;
                    </button>
                  </div>
                ) : (
                  <div className="app-muted-text p-8 text-center flex flex-col items-center justify-center">
                    <FiSearch size={32} className="app-soft-text mb-2" />
                    <span className="text-sm">
                      Không tìm thấy sách nào khớp với &quot;{searchTerm}&quot;
                    </span>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        <div className="flex items-center gap-8 flex-shrink-0">
          <Link
            to="/cart"
            className="header-icon-link flex flex-col items-center relative"
          >
            <FiShoppingCart size={22} strokeWidth={1.5} />
            <span className="text-[11px] mt-1">Giỏ hàng</span>
            {totalQuantity > 0 && (
              <span className="app-badge-danger absolute -top-1.5 -right-2 text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">
                {totalQuantity}
              </span>
            )}
          </Link>

          <Link
            to="/wishlist"
            className="header-icon-link flex flex-col items-center relative"
          >
            <FiHeart size={22} strokeWidth={1.5} />
            <span className="text-[11px] mt-1">Yêu thích</span>
            {wishlistItems.length > 0 && (
              <span className="app-badge-danger absolute -top-1.5 -right-2 text-[10px] font-bold w-4 h-4 flex items-center justify-center rounded-full">
                {wishlistItems.length}
              </span>
            )}
          </Link>

          {user ? (
            <div className="flex flex-col items-center text-primary relative group">
              <div
                className="flex flex-col items-center cursor-pointer"
                onClick={() => navigate("/profile")}
              >
                <FiUser size={22} strokeWidth={1.5} />
                <span className="text-[11px] mt-1 font-bold whitespace-nowrap">
                  {user.lastName || user.profile?.last_name}{" "}
                  {user.firstName || user.profile?.first_name}
                </span>
              </div>
              <div className="absolute top-full pt-2 right-0 hidden group-hover:block z-50">
                <button
                  type="button"
                  onClick={() => {
                    searchCache.current = {};
                    logout();
                    navigate("/");
                  }}
                  className="app-card app-danger-link rounded-md px-4 py-2 text-sm whitespace-nowrap"
                >
                  Đăng xuất
                </button>
              </div>
            </div>
          ) : (
            <Link
              to="/login"
              className="header-icon-link flex flex-col items-center"
            >
              <FiUser size={22} strokeWidth={1.5} />
              <span className="text-[11px] mt-1">Tài khoản</span>
            </Link>
          )}
        </div>
      </div>
    </header>
  );
};

export default Header;
