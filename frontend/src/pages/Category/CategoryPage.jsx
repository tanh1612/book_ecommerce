// src/pages/Category/CategoryPage.jsx
import { useState, useEffect } from 'react';
import { FiChevronDown } from 'react-icons/fi';
import ProductCard from '../../components/Product/ProductCard';
import { useSearchParams } from 'react-router-dom';
import bookApi from '../../services/bookApi';
import { toast } from 'react-toastify';

const SORT_CONFIG = {
  relevance: {
    backendSort: 'relevance',
  },
  best_selling: {
    sortBy: 'sold_quantity',
    order: 'desc',
    backendSort: 'rating_desc',
  },
  newest: {
    sortBy: 'created_at',
    order: 'desc',
    backendSort: 'newest',
  },
  price_asc: {
    sortBy: 'price',
    order: 'asc',
    backendSort: 'price_asc',
  },
  price_desc: {
    sortBy: 'price',
    order: 'desc',
    backendSort: 'price_desc',
  },
};

const SORT_BY_TO_KEY = {
  sold_quantity_desc: 'best_selling',
  created_at_desc: 'newest',
  price_asc: 'price_asc',
  price_desc: 'price_desc',
};

const LEGACY_SORT_TO_KEY = {
  relevance: 'relevance',
  newest: 'newest',
  price_asc: 'price_asc',
  price_desc: 'price_desc',
  rating_desc: 'best_selling',
  best_selling: 'best_selling',
};

const resolveSortKey = (params, hasKeyword) => {
  const sortBy = params.get('sort_by');
  const order = params.get('order');
  const sortKeyFromPair = sortBy && order ? SORT_BY_TO_KEY[`${sortBy}_${order}`] : null;

  if (sortKeyFromPair) {
    return sortKeyFromPair;
  }

  const legacySort = params.get('sort');
  const sortKeyFromLegacy = legacySort ? LEGACY_SORT_TO_KEY[legacySort] : null;

  if (sortKeyFromLegacy === 'relevance' && !hasKeyword) {
    return 'newest';
  }

  return sortKeyFromLegacy || (hasKeyword ? 'relevance' : 'newest');
};

const applySortParams = (apiParams, sortKey, hasKeyword) => {
  const safeSortKey = SORT_CONFIG[sortKey] ? sortKey : (hasKeyword ? 'relevance' : 'newest');
  const config = SORT_CONFIG[safeSortKey];

  delete apiParams.sort_by;
  delete apiParams.order;

  if (safeSortKey === 'relevance' && hasKeyword) {
    apiParams.sort = 'relevance';
    return;
  }

  apiParams.sort = config.backendSort;
  if (config.sortBy && config.order) {
    apiParams.sort_by = config.sortBy;
    apiParams.order = config.order;
  }
};

const getSortUrlParams = (sortKey, hasKeyword) => {
  const safeSortKey = SORT_CONFIG[sortKey] ? sortKey : (hasKeyword ? 'relevance' : 'newest');
  const config = SORT_CONFIG[safeSortKey];

  if (safeSortKey === 'relevance' && hasKeyword) {
    return { sort: 'relevance', sort_by: '', order: '' };
  }

  return {
    sort: config.backendSort,
    sort_by: config.sortBy || '',
    order: config.order || '',
  };
};

const CategoryPage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const keyword = searchParams.get('keyword');

  const [books, setBooks] = useState([]);
  const [filtersData, setFiltersData] = useState({ categories: [], publishers: [], suppliers: [], suggested_price_ranges: [] });
  const [isLoading, setIsLoading] = useState(true);

  // 🔥 STATE PHÂN TRANG (MỚI)
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const currentCategory = searchParams.get('category') || '';
  const currentPublisher = searchParams.get('publisher') || '';
  const currentPriceMin = searchParams.get('price_min') || '';
  const currentPriceMax = searchParams.get('price_max') || '';
  const currentSupplier = searchParams.get('supplier') || '';
  const currentSort = resolveSortKey(searchParams, Boolean(keyword));
  
  useEffect(() => {
    bookApi.getFilters()
      .then(res => setFiltersData(res.data?.data || res.data || {}))
      .catch(err => console.error("Lỗi tải bộ lọc:", err));
  }, []);

  const safeCategories = Array.isArray(filtersData?.categories) ? filtersData.categories : [];
  const isRootCategory = safeCategories.some(c => c.slug === currentCategory);
  const showCategoryFilter = currentCategory === '' || isRootCategory;

  useEffect(() => {
    const abortController = new AbortController();

    const fetchBooks = async () => {
      setIsLoading(true);
      try {
        const apiParams = Object.fromEntries([...searchParams]);

        if (!apiParams.page) apiParams.page = 1;
        apiParams.per_page = apiParams.per_page || 40;

        applySortParams(apiParams, currentSort, Boolean(keyword));

        const res = await bookApi.getBooks(apiParams);

        if (abortController.signal.aborted) return;

        let booksArray = [];
        let metaData = null;
        
        // 🔥 GIẢI MÃ PHÂN TRANG TỪ BACKEND LARAVEL
        if (res.data?.meta) {
          booksArray = res.data.data;
          metaData = res.data.meta;
        } else if (res.data?.data?.meta) {
          booksArray = res.data.data.data;
          metaData = res.data.data.meta;
        } else if (Array.isArray(res.data)) {
          booksArray = res.data;
        } else if (res.data?.data && Array.isArray(res.data.data)) {
          booksArray = res.data.data;
        } else if (res.data?.data?.data && Array.isArray(res.data.data.data)) {
          booksArray = res.data.data.data;
        }
        
        setBooks(booksArray);

        // Lưu thông tin số trang
        if (metaData) {
          setCurrentPage(metaData.current_page || 1);
          setLastPage(metaData.last_page || 1);
        } else if (res.data?.last_page) {
          setCurrentPage(res.data.current_page || 1);
          setLastPage(res.data.last_page || 1);
        } else if (res.data?.data?.last_page) {
          setCurrentPage(res.data.data.current_page || 1);
          setLastPage(res.data.data.last_page || 1);
        } else {
          setCurrentPage(1);
          setLastPage(1);
        }

        // Tự động cuộn lên đầu trang sau khi tải xong sách
        window.scrollTo({ top: 0, behavior: 'smooth' });

      } catch (err) {
        if (err.name !== 'AbortError') {
          toast.error("Lỗi tải danh sách sách!");
          setBooks([]);
        }
      } finally {
        setIsLoading(false);
      }
    };
    fetchBooks();

    return () => { abortController.abort(); };
  }, [searchParams, keyword, currentSort]);

  // 🔥 ĐÃ CẬP NHẬT: Thêm cờ resetPage để phân biệt lúc Lọc và lúc Chuyển Trang
  const updateUrlParams = (newParams, resetPage = true) => {
    const params = Object.fromEntries([...searchParams]);
    const finalParams = { ...params, ...newParams };
    
    // Nếu thay đổi bộ lọc (Giá, NXB, Sort...) thì phải ép về trang 1
    if (resetPage) finalParams.page = 1;
    
    Object.keys(finalParams).forEach(key => (finalParams[key] === '' || finalParams[key] == null) && delete finalParams[key]);
    setSearchParams(finalParams);
  };

  const renderCategories = (categories, level = 0) => {
    if (!Array.isArray(categories) || categories.length === 0) return null;
    
    return categories.map(item => (
      <div key={item.id} className={level > 0 ? 'ml-6 mt-3' : 'mt-3'}>
        <label className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer hover:text-primary">
          <input
            type="radio"
            name="categoryFilter"
            checked={currentCategory === item.slug}
            onChange={() => updateUrlParams({ category: item.slug })}
            className="w-4 h-4 text-primary focus:ring-primary"
          />
          <span className={currentCategory === item.slug ? "font-bold text-primary" : ""}>
            {item.name}
          </span>
        </label>
        {item.children?.length > 0 && renderCategories(item.children, level + 1)}
      </div>
    ));
  };

  // 🔥 THUẬT TOÁN TẠO THANH PHÂN TRANG (Render Pagination)
  const renderPagination = () => {
    if (lastPage <= 1) return null; // Nếu chỉ có 1 trang thì ẩn luôn thanh chuyển trang
    
    let pages = [];
    for (let i = 1; i <= lastPage; i++) {
      // Chỉ hiển thị: Trang 1, Trang Cuối, và 1 Trang trước/sau Trang hiện tại
      if (i === 1 || i === lastPage || (i >= currentPage - 1 && i <= currentPage + 1)) {
        pages.push(
          <button
            key={i}
            onClick={() => updateUrlParams({ page: i }, false)}
            className={`px-3.5 py-1.5 min-w-[38px] border rounded transition-colors ${
              currentPage === i 
              ? 'bg-primary text-white border-primary font-bold shadow-sm' 
              : 'border-gray-300 text-gray-600 hover:bg-green-50 hover:text-primary hover:border-primary'
            }`}
          >
            {i}
          </button>
        );
      } else if (i === currentPage - 2 || i === currentPage + 2) {
        // Chèn dấu "..." để rút gọn nếu số trang quá nhiều
        pages.push(<span key={`dots-${i}`} className="px-2 text-gray-400">...</span>);
      }
    }
    
    return (
      <div className="flex justify-center items-center mt-12 mb-4 gap-2">
        <button 
          onClick={() => updateUrlParams({ page: currentPage - 1 }, false)}
          disabled={currentPage === 1}
          className="px-4 py-1.5 border border-gray-300 rounded text-gray-600 font-medium hover:bg-green-50 hover:text-primary hover:border-primary disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-gray-600 disabled:hover:border-gray-300 disabled:cursor-not-allowed transition-colors"
        >
          Trang trước
        </button>
        
        <div className="flex items-center gap-1.5 mx-2">
          {pages}
        </div>

        <button 
          onClick={() => updateUrlParams({ page: currentPage + 1 }, false)}
          disabled={currentPage === lastPage}
          className="px-4 py-1.5 border border-gray-300 rounded text-gray-600 font-medium hover:bg-green-50 hover:text-primary hover:border-primary disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-gray-600 disabled:hover:border-gray-300 disabled:cursor-not-allowed transition-colors"
        >
          Trang sau
        </button>
      </div>
    );
  };

  const safeBooks = Array.isArray(books) ? books : [];
  const displaySort = currentSort === 'relevance' && !keyword ? 'newest' : currentSort;
  const handleSortChange = (event) => {
    updateUrlParams(getSortUrlParams(event.target.value, Boolean(keyword)));
  };

  return (
    <div className="min-h-screen pb-10">
      <div className="container mx-auto px-8 lg:px-10 mt-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-100 p-6 flex flex-col md:flex-row gap-8">
        
        {/* SIDEBAR BỘ LỌC */}
        <aside className="w-full md:w-64 flex-shrink-0">
          <h2 className="font-bold text-primary text-lg mb-6 border-b pb-2">Bộ lọc tìm kiếm</h2>

          {showCategoryFilter ? (
            <div className="mb-8">
              <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Danh mục</h3>
              <div className="max-h-80 overflow-y-auto pr-2 custom-scrollbar">
                <label className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer hover:text-primary mb-3 border-b border-gray-100 pb-3">
                  <input
                    type="radio"
                    name="categoryFilter"
                    checked={currentCategory === ''}
                    onChange={() => updateUrlParams({ category: '' })}
                    className="w-4 h-4 text-primary focus:ring-primary"
                  />
                  <span className={currentCategory === '' ? "font-bold text-primary" : ""}>Tất cả danh mục</span>
                </label>
                {renderCategories(filtersData.categories)}
              </div>
            </div>
          ) : (
            <div className="mb-8">
              <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Danh mục đang chọn</h3>
              <div className="flex items-center justify-between bg-green-50 p-3 rounded border border-green-200">
                <span className="text-sm font-bold text-primary truncate pr-2">{currentCategory}</span>
                <button onClick={() => updateUrlParams({ category: '' })} className="text-gray-400 hover:text-red-500 text-xs font-bold whitespace-nowrap">✕ Xóa</button>
              </div>
            </div>
          )}

          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Khoảng giá</h3>
            <label className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer mb-3 hover:text-primary">
              <input
                type="radio" name="priceFilter"
                checked={currentPriceMin === '' && currentPriceMax === ''}
                onChange={() => updateUrlParams({ price_min: '', price_max: '' })}
                className="w-4 h-4 text-primary focus:ring-primary"
              />
              Tất cả mức giá
            </label>
            {Array.isArray(filtersData.suggested_price_ranges) && filtersData.suggested_price_ranges.map((range, idx) => (
              <label key={idx} className="flex items-center gap-3 text-sm text-gray-700 cursor-pointer mb-3 hover:text-primary">
                <input
                  type="radio" name="priceFilter"
                  checked={currentPriceMin == (range.min || '') && currentPriceMax == (range.max || '')}
                  onChange={() => updateUrlParams({ price_min: range.min || '', price_max: range.max || '' })}
                  className="w-4 h-4 text-primary focus:ring-primary"
                />
                {range.label}
              </label>
            ))}
          </div>

          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Nhà xuất bản</h3>
            <select
              className="w-full border border-gray-300 rounded p-2.5 text-sm outline-none focus:border-primary bg-white"
              value={currentPublisher}
              onChange={(e) => updateUrlParams({ publisher: e.target.value })}
            >
              <option value="">Tất cả NXB</option>
              {Array.isArray(filtersData.publishers) && filtersData.publishers.map(p => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
          </div>

          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Nhà cung cấp</h3>
            <select
              className="w-full border border-gray-300 rounded p-2.5 text-sm outline-none focus:border-primary bg-white"
              value={currentSupplier}
              onChange={(e) => updateUrlParams({ supplier: e.target.value })}
            >
              <option value="">Tất cả nhà cung cấp</option>
              {Array.isArray(filtersData.suppliers) && filtersData.suppliers.map(s => (
                <option key={s.id} value={s.id}>{s.name}</option>
              ))}
            </select>
          </div>
        </aside>

        {/* DANH SÁCH SÁCH BÊN PHẢI */}
        <div className="flex-grow flex flex-col min-h-full">
          <div className="flex justify-between items-center mb-6 border-b pb-4">
            <h1 className="text-xl font-bold text-gray-800">
              {keyword ? `Kết quả tìm kiếm cho: "${keyword}"` : 'Danh mục sách'}
            </h1>
            
            <div className="relative">
              <select
                className="appearance-none bg-primary text-white text-sm font-medium pl-4 pr-10 py-2 rounded outline-none cursor-pointer"
                value={displaySort}
                onChange={handleSortChange}
              >
                {keyword && <option value="relevance">Liên quan nhất</option>}
                <option value="best_selling">Bán chạy</option>
                <option value="newest">Mới cập nhật</option>
                <option value="price_asc">Giá từ thấp đến cao</option>
                <option value="price_desc">Giá từ cao đến thấp</option>
              </select>
              <FiChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 text-white pointer-events-none" />
            </div>
          </div>

          {isLoading ? (
            <div className="py-20 flex justify-center flex-grow"><div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin"></div></div>
          ) : safeBooks.length > 0 ? (
            <>
              <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 flex-grow content-start">
                {safeBooks.map((book) => (
                  <ProductCard key={book.id} book={book} />
                ))}
              </div>
              
              {/* THANH PHÂN TRANG */}
              {renderPagination()}
            </>
          ) : (
            <div className="py-10 text-center text-gray-500 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200 flex-grow">
              Không tìm thấy tựa sách nào phù hợp với bộ lọc của bạn.
            </div>
          )}
        </div>
        </div>
      </div>
    </div>
  );
};

export default CategoryPage;
