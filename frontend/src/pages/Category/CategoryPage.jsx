// src/pages/Category/CategoryPage.jsx
import { useState, useEffect } from 'react';
import { FiChevronRight, FiChevronDown } from 'react-icons/fi';
import ProductCard from '../../components/Product/ProductCard';
import { useSearchParams } from 'react-router-dom';
import bookApi from '../../services/bookApi';
import { toast } from 'react-toastify';

const CategoryPage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const [books, setBooks] = useState([]);
  const [filtersData, setFiltersData] = useState({ categories: [], publishers: [], suggested_price_ranges: [] });
  const [isLoading, setIsLoading] = useState(true);

  const currentCategory = searchParams.get('category') || '';
  const currentPublisher = searchParams.get('publisher') || '';
  const currentPriceMin = searchParams.get('price_min') || '';
  const currentPriceMax = searchParams.get('price_max') || '';
  const currentSort = searchParams.get('sort') || 'newest';

  useEffect(() => {
    bookApi.getFilters()
      .then(res => setFiltersData(res.data.data || res.data))
      .catch(err => console.error("Lỗi tải bộ lọc", err));
  }, []);

  useEffect(() => {
    const fetchBooks = async () => {
      setIsLoading(true);
      try {
        const res = await bookApi.getBooks(Object.fromEntries([...searchParams]));
        setBooks(res.data.data || res.data || []);
      } catch (err) {
        toast.error("Lỗi tải danh sách sách!");
      } finally {
        setIsLoading(false);
      }
    };
    fetchBooks();
  }, [searchParams]);

  const updateUrlParams = (newParams) => {
    const params = Object.fromEntries([...searchParams]);
    const finalParams = { ...params, ...newParams, page: 1 };
    Object.keys(finalParams).forEach(key => (finalParams[key] === '' || finalParams[key] == null) && delete finalParams[key]);
    setSearchParams(finalParams);
  };

  const renderCategories = (categories, level = 0) => {
    return categories.map(item => (
      <div key={item.id} className={level > 0 ? 'ml-4 mt-2' : 'mt-2'}>
        <label className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:text-[#157a2c]">
          <input 
            type="radio" name="category"
            checked={currentCategory === item.slug}
            onChange={() => updateUrlParams({ category: item.slug })}
            className="w-4 h-4 text-[#157a2c]" 
          />
          <span className={currentCategory === item.slug ? "font-bold text-[#157a2c]" : ""}>{item.name}</span>
        </label>
        {item.children?.length > 0 && renderCategories(item.children, level + 1)}
      </div>
    ));
  };

  return (
    <div className="bg-white min-h-screen pb-10">
      <div className="container mx-auto px-4 mt-6 flex flex-col md:flex-row gap-8">
        <aside className="w-full md:w-64 flex-shrink-0">
          <h2 className="font-bold text-[#157a2c] text-lg mb-6 border-b pb-2">Bộ lọc tìm kiếm</h2>
          
          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Danh mục</h3>
            <div className="max-h-80 overflow-y-auto pr-2 custom-scrollbar">
              {renderCategories(filtersData.categories)}
            </div>
          </div>

          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Khoảng giá</h3>
            {filtersData.suggested_price_ranges?.map((range, idx) => (
              <label key={idx} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mb-2">
                <input 
                  type="radio" name="price"
                  checked={currentPriceMin == (range.min || '') && currentPriceMax == (range.max || '')}
                  onChange={() => updateUrlParams({ price_min: range.min || '', price_max: range.max || '' })}
                  className="w-4 h-4 text-[#157a2c]" 
                />
                {range.label}
              </label>
            ))}
          </div>

          <div className="mb-8">
            <h3 className="font-bold text-gray-800 mb-3 uppercase text-xs tracking-wider">Nhà xuất bản</h3>
            <select 
              className="w-full border rounded p-2 text-sm outline-none focus:border-[#157a2c]" 
              value={currentPublisher} 
              onChange={(e) => updateUrlParams({ publisher: e.target.value })}
            >
              <option value="">Tất cả NXB</option>
              {filtersData.publishers?.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
            </select>
          </div>
        </aside>

        <div className="flex-grow">
          <div className="flex justify-between items-center mb-6 border-b pb-4">
            <h1 className="text-xl font-bold text-gray-800">Sách đang bán</h1>
            <select 
              className="bg-[#157a2c] text-white px-4 py-2 rounded font-bold text-sm outline-none" 
              value={currentSort} 
              onChange={(e) => updateUrlParams({ sort: e.target.value })}
            >
              <option value="newest">Mới nhất</option>
              <option value="price_asc">Giá: Thấp đến Cao</option>
              <option value="price_desc">Giá: Cao đến Thấp</option>
            </select>
          </div>

          {isLoading ? (
            <div className="py-20 flex justify-center"><div className="w-10 h-10 border-4 border-[#157a2c] border-t-transparent rounded-full animate-spin"></div></div>
          ) : (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
              {books.map(book => <ProductCard key={book.id} book={book} />)}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default CategoryPage;