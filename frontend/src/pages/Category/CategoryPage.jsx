// src/pages/Category/CategoryPage.jsx
import { useState, useEffect } from 'react';
import { FiChevronDown } from 'react-icons/fi';
import ProductCard from '../../components/Product/ProductCard';
import { useSearchParams } from 'react-router-dom';
import bookApi from '../../services/bookApi';
import { toast } from 'react-toastify';

const CategoryPage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const keyword = searchParams.get('keyword');

  const [books, setBooks] = useState([]);
  const [filtersData, setFiltersData] = useState({ categories: [], publishers: [], suppliers: [], suggested_price_ranges: [] });
  const [isLoading, setIsLoading] = useState(true);

  const currentCategory = searchParams.get('category') || '';
  const currentPublisher = searchParams.get('publisher') || '';
  const currentPriceMin = searchParams.get('price_min') || '';
  const currentPriceMax = searchParams.get('price_max') || '';
  const currentSort = searchParams.get('sort') || 'newest';
  
  // Tải bộ lọc tĩnh (Danh mục, NXB, Mức giá)
  useEffect(() => {
    bookApi.getFilters()
      .then(res => setFiltersData(res.data?.data || res.data || {}))
      .catch(err => console.error("Lỗi tải bộ lọc:", err));
  }, []);

  // Kiểm tra an toàn trước khi gọi .some() để tránh crash web
  const safeCategories = Array.isArray(filtersData?.categories) ? filtersData.categories : [];
  const isRootCategory = safeCategories.some(c => c.slug === currentCategory);
  const showCategoryFilter = currentCategory === '' || isRootCategory;

  // Tải danh sách sách dựa theo URL params
  useEffect(() => {
    const fetchBooks = async () => {
      setIsLoading(true);
      try {
        const apiParams = Object.fromEntries([...searchParams]);
        
        // Đảm bảo sort hợp lệ, nếu không có thì mặc định là newest
        const validSorts = ['newest', 'price_asc', 'price_desc', 'rating_desc'];
        if (apiParams.sort && !validSorts.includes(apiParams.sort)) {
          apiParams.sort = 'newest'; 
        }

        const res = await bookApi.getBooks(apiParams);
        
        // BỘ LỌC THÔNG MINH: Xử lý dữ liệu phân trang từ Laravel
        let booksArray = [];
        if (Array.isArray(res.data)) {
          booksArray = res.data;
        } else if (res.data?.data && Array.isArray(res.data.data)) {
          booksArray = res.data.data;
        } else if (res.data?.data?.data && Array.isArray(res.data.data.data)) {
          booksArray = res.data.data.data;
        }
        
        setBooks(booksArray);
      } catch (err) {
        toast.error("Lỗi tải danh sách sách!");
        setBooks([]); // Set mảng rỗng để tránh crash khi lỗi
      } finally {
        setIsLoading(false);
      }
    };
    fetchBooks();
  }, [searchParams]);

  // Hàm cập nhật URL params mà không làm mất các param cũ
  const updateUrlParams = (newParams) => {
    const params = Object.fromEntries([...searchParams]);
    const finalParams = { ...params, ...newParams, page: 1 };
    
    // Xóa các param rỗng để URL sạch đẹp
    Object.keys(finalParams).forEach(key => (finalParams[key] === '' || finalParams[key] == null) && delete finalParams[key]);
    setSearchParams(finalParams);
  };

  // Hàm đệ quy in ra danh mục cha con an toàn
  const renderCategories = (categories, level = 0) => {
    if (!Array.isArray(categories) || categories.length === 0) return null;
    
    return categories.map(item => (
      <div key={item.id} className={level > 0 ? 'ml-6 mt-3' : 'mt-3'}>
        <label className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer hover:text-[#157a2c]">
          <input
            type="radio"
            name="categoryFilter"
            checked={currentCategory === item.slug}
            onChange={() => updateUrlParams({ category: item.slug })}
            className="w-4 h-4 text-[#157a2c] focus:ring-[#157a2c]"
          />
          <span className={currentCategory === item.slug ? "font-bold text-[#157a2c]" : ""}>
            {item.name}
          </span>
        </label>
        {item.children?.length > 0 && renderCategories(item.children, level + 1)}
      </div>
    ));
  };

  // Đảm bảo biến books luôn là Array
  const safeBooks = Array.isArray(books) ? books : [];

  return (
    <div className="bg-white min-h-screen pb-10">
      <div className="container mx-auto px-4 mt-6 flex flex-col md:flex-row gap-8">
        
        {/* SIDEBAR BỘ LỌC */}
        <aside className="w-full md:w-64 flex-shrink-0">
          <h2 className="font-bold text-[#157a2c] text-lg mb-6 border-b pb-2">Bộ lọc tìm kiếm</h2>

          {/* Khối Lọc Danh Mục */}
          {showCategoryFilter ? (
            <div className="mb-8">
              <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Danh mục</h3>
              <div className="max-h-80 overflow-y-auto pr-2 custom-scrollbar">
                <label className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer hover:text-[#157a2c] mb-3 border-b border-gray-100 pb-3">
                  <input
                    type="radio"
                    name="categoryFilter"
                    checked={currentCategory === ''}
                    onChange={() => updateUrlParams({ category: '' })}
                    className="w-4 h-4 text-[#157a2c] focus:ring-[#157a2c]"
                  />
                  <span className={currentCategory === '' ? "font-bold text-[#157a2c]" : ""}>Tất cả danh mục</span>
                </label>
                {renderCategories(filtersData.categories)}
              </div>
            </div>
          ) : (
            <div className="mb-8">
              <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Danh mục đang chọn</h3>
              <div className="flex items-center justify-between bg-green-50 p-3 rounded border border-green-200">
                <span className="text-sm font-bold text-[#157a2c] truncate pr-2">{currentCategory}</span>
                <button onClick={() => updateUrlParams({ category: '' })} className="text-gray-400 hover:text-red-500 text-xs font-bold whitespace-nowrap">✕ Xóa</button>
              </div>
            </div>
          )}

          {/* Khối Lọc Khoảng Giá */}
          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Khoảng giá</h3>
            <label className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer mb-3 hover:text-[#157a2c]">
              <input
                type="radio" name="priceFilter"
                checked={currentPriceMin === '' && currentPriceMax === ''}
                onChange={() => updateUrlParams({ price_min: '', price_max: '' })}
                className="w-4 h-4 text-[#157a2c] focus:ring-[#157a2c]"
              />
              Tất cả mức giá
            </label>
            {Array.isArray(filtersData.suggested_price_ranges) && filtersData.suggested_price_ranges.map((range, idx) => (
              <label key={idx} className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer mb-3 hover:text-[#157a2c]">
                <input
                  type="radio" name="priceFilter"
                  checked={currentPriceMin == (range.min || '') && currentPriceMax == (range.max || '')}
                  onChange={() => updateUrlParams({ price_min: range.min || '', price_max: range.max || '' })}
                  className="w-4 h-4 text-[#157a2c] focus:ring-[#157a2c]"
                />
                {range.label}
              </label>
            ))}
          </div>

          {/* Khối Lọc Nhà Xuất Bản */}
          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Nhà xuất bản</h3>
            <select
              className="w-full border border-gray-300 rounded p-2.5 text-sm outline-none focus:border-[#157a2c] bg-white"
              value={currentPublisher}
              onChange={(e) => updateUrlParams({ publisher: e.target.value })}
            >
              <option value="">Tất cả NXB</option>
              {Array.isArray(filtersData.publishers) && filtersData.publishers.map(p => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>
        </aside>

        {/* DANH SÁCH SÁCH BÊN PHẢI */}
        <div className="flex-grow">
          <div className="flex justify-between items-center mb-6 border-b pb-4">
            <h1 className="text-xl font-bold text-gray-800">
              {keyword ? `Kết quả tìm kiếm cho: "${keyword}"` : 'Danh mục sách'}
            </h1>
            
            {/* Thanh Sắp xếp */}
            <div className="relative">
              <select
                className="appearance-none bg-[#157a2c] text-white text-sm font-medium pl-4 pr-10 py-2 rounded outline-none cursor-pointer"
                value={currentSort}
                onChange={(e) => updateUrlParams({ sort: e.target.value })}
              >
                <option value="newest">Mới nhất</option>
                <option value="price_asc">Giá từ thấp tới cao</option>
                <option value="price_desc">Giá từ cao tới thấp</option>
                <option value="rating_desc">Đánh giá cao / Nổi bật</option>
              </select>
              <FiChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 text-white pointer-events-none" />
            </div>
          </div>

          {/* Vùng hiển thị sách */}
          {isLoading ? (
            <div className="py-20 flex justify-center"><div className="w-8 h-8 border-4 border-[#157a2c] border-t-transparent rounded-full animate-spin"></div></div>
          ) : safeBooks.length > 0 ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {safeBooks.map((book) => (
                <ProductCard key={book.id} book={book} />
              ))}
            </div>
          ) : (
            <div className="py-10 text-center text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
              Không tìm thấy tựa sách nào phù hợp với bộ lọc của bạn.
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CategoryPage;