// src/pages/Category/CategoryPage.jsx
import { useState, useMemo } from 'react';
import { FiChevronRight, FiChevronDown } from 'react-icons/fi';
import ProductCard from '../../components/Product/ProductCard';
import { useSearchParams } from 'react-router-dom';

// 1. Cập nhật Mock Data: Thêm trường format, language, category để test chức năng lọc
const MOCK_BOOKS = [
  { id: 1, name: "Túp lều bác Tom", author: "Harriet Beecher Stowe", slug: "tup-leu-bac-tom", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Tup+Leu", originalPrice: 150000, salePrice: 150000, category: "Văn học kinh điển", format: "Bìa mềm", language: "Mỹ" },
  { id: 2, name: "Ú òa", author: "Giuliano Ferri", slug: "sach-lat-u-oa", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=U+Oa", originalPrice: 85000, salePrice: 85000, category: "Văn học thiếu nhi", format: "Bìa cứng", language: "Việt Nam" },
  { id: 3, name: "Chuyện con chó tên là trung thành", author: "Luis Sepúlveda", slug: "chuyen-con-cho", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Trung+Thanh", originalPrice: 90000, salePrice: 90000, category: "Văn học hiện đại", format: "Bìa mềm", language: "Pháp" },
  { id: 4, name: "Tàn ngày để lại", author: "Kazuo Ishiguro", slug: "tan-ngay-de-lai", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Tan+Ngay", originalPrice: 120000, salePrice: 120000, category: "Văn học hiện đại", format: "Bìa mềm", language: "Nhật Bản" },
  { id: 5, name: "Kim các tự", author: "Mishima Yukio", slug: "kim-cac-tu", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Kim+Cac+Tu", originalPrice: 110000, salePrice: 110000, category: "Văn học kinh điển", format: "Bìa cứng", language: "Nhật Bản" },
  { id: 6, name: "Người tình Sputnik", author: "Haruki Murakami", slug: "nguoi-tinh-sputnik", thumbnail: "https://placehold.co/300x400/EEE/31343C?text=Sputnik", originalPrice: 96000, salePrice: 81600, category: "Văn học hiện đại", format: "Bìa mềm", language: "Nhật Bản" },
];

const FILTER_DATA = {
  categories: ['Văn học hiện đại', 'Văn học kinh điển', 'Văn học thiếu nhi', 'Lãng mạn', 'Trinh thám - Kinh dị'],
  formats: ['Bìa cứng', 'Bìa mềm', 'Giới hạn'],
  languages: ['Việt Nam', 'Nhật Bản', 'Mỹ', 'Pháp']
};

const CategoryPage = () => {
  const [searchParams] = useSearchParams();
  const keyword = searchParams.get('keyword');



  // 2. Khai báo State để lưu trữ những ô checkbox nào đang được người dùng tích chọn
  const [selectedCategories, setSelectedCategories] = useState([]);
  const [selectedFormats, setSelectedFormats] = useState([]);
  const [selectedLanguages, setSelectedLanguages] = useState([]);
  const [sortOption, setSortOption] = useState('default');
  // Hàm xử lý chung khi người dùng tích/bỏ tích checkbox
  const handleCheckboxChange = (value, state, setState) => {
    if (state.includes(value)) {
      // Nếu đã có trong mảng -> Bỏ tích -> Lọc bỏ nó ra khỏi mảng
      setState(state.filter(item => item !== value));
    } else {
      // Nếu chưa có -> Tích vào -> Thêm nó vào mảng
      setState([...state, value]);
    }
  };

  // 3. Logic Lọc dữ liệu (Tự động chạy lại mỗi khi người dùng tích vào checkbox)
  const filteredAndSortedBooks = useMemo(() => {
    // BƯỚC 1: LỌC (Filter)
    let result = MOCK_BOOKS.filter((book) => {
      const matchCategory = selectedCategories.length === 0 || selectedCategories.includes(book.category);
      const matchFormat = selectedFormats.length === 0 || selectedFormats.includes(book.format);
      const matchLanguage = selectedLanguages.length === 0 || selectedLanguages.includes(book.language);
      const matchKeyword = keyword 
        ? book.name.toLowerCase().includes(keyword.toLowerCase()) || book.author.toLowerCase().includes(keyword.toLowerCase())
        : true;

      return matchCategory && matchFormat && matchLanguage && matchKeyword;
    });

    // BƯỚC 2: SẮP XẾP (Sort) dựa trên kết quả đã lọc
    if (sortOption === 'price_asc') {
      result.sort((a, b) => a.salePrice - b.salePrice); // Giá thấp đến cao
    } else if (sortOption === 'price_desc') {
      result.sort((a, b) => b.salePrice - a.salePrice); // Giá cao xuống thấp
    }
    // Nếu là 'default' thì giữ nguyên thứ tự ban đầu

    return result;
  }, [selectedCategories, selectedFormats, selectedLanguages, sortOption]); // Thêm sortOption vào đây

  return (
    <div className="bg-white min-h-screen pb-10">
      
      {/* BREADCRUMB */}
      <div className="bg-[#f0f9f3] py-3 border-b border-green-100">
        <div className="container mx-auto px-4 text-sm text-gray-600 flex items-center gap-2">
          <span className="hover:text-[#157a2c] cursor-pointer">Trang chủ</span>
          <FiChevronRight size={14} className="text-gray-400" />
          <span className="text-[#157a2c] font-medium">Hư cấu</span>
        </div>
      </div>

      <div className="container mx-auto px-4 mt-6 flex flex-col md:flex-row gap-8">
        
        {/* === CỘT TRÁI: SIDEBAR BỘ LỌC === */}
        <aside className="w-full md:w-64 flex-shrink-0">
          <h2 className="font-bold text-[#157a2c] text-lg mb-4 border-b pb-2">Bộ lọc tìm kiếm</h2>
          
          {/* Lọc: Danh mục */}
          <div className="mb-6">
            <h3 className="font-medium text-gray-800 mb-3">Danh mục</h3>
            <div className="max-h-60 overflow-y-auto pr-2 custom-scrollbar flex flex-col gap-2">
              {FILTER_DATA.categories.map((item, idx) => (
                <label key={idx} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:text-[#157a2c]">
                  <input 
                    type="checkbox" 
                    className="w-4 h-4 text-[#157a2c] rounded border-gray-300 focus:ring-[#157a2c]"
                    checked={selectedCategories.includes(item)}
                    onChange={() => handleCheckboxChange(item, selectedCategories, setSelectedCategories)}
                  />
                  {item}
                </label>
              ))}
            </div>
          </div>

          {/* Lọc: Ấn bản */}
          <div className="mb-6">
            <h3 className="font-medium text-gray-800 mb-3">Ấn bản</h3>
            <div className="flex flex-col gap-2">
              {FILTER_DATA.formats.map((item, idx) => (
                <label key={idx} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:text-[#157a2c]">
                  <input 
                    type="checkbox" 
                    className="w-4 h-4 text-[#157a2c] rounded border-gray-300 focus:ring-[#157a2c]"
                    checked={selectedFormats.includes(item)}
                    onChange={() => handleCheckboxChange(item, selectedFormats, setSelectedFormats)}
                  />
                  {item}
                </label>
              ))}
            </div>
          </div>

          {/* Lọc: Quốc gia */}
          <div className="mb-6">
            <h3 className="font-medium text-gray-800 mb-3">Quốc gia</h3>
            <div className="flex flex-col gap-2">
              {FILTER_DATA.languages.map((item, idx) => (
                <label key={idx} className="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:text-[#157a2c]">
                  <input 
                    type="checkbox" 
                    className="w-4 h-4 text-[#157a2c] rounded border-gray-300 focus:ring-[#157a2c]"
                    checked={selectedLanguages.includes(item)}
                    onChange={() => handleCheckboxChange(item, selectedLanguages, setSelectedLanguages)}
                  />
                  {item}
                </label>
              ))}
            </div>
          </div>
        </aside>

{/* === CỘT PHẢI: LƯỚI SẢN PHẨM === */}
        <div className="flex-grow">
          <div className="flex justify-between items-center mb-6 border-b pb-2">
            <h1 className="text-2xl font-bold text-[#157a2c]">
    {keyword ? `Kết quả tìm kiếm cho: "${keyword}"` : 'Danh mục sách'}
  </h1>
            <div className="flex items-center gap-2">
              <span className="text-sm font-medium text-gray-700">Sắp xếp theo</span>
              <div className="relative">
                {/* GẮN SỰ KIỆN ONCHANGE VÀO ĐÂY */}
                <select 
                  className="appearance-none bg-[#0e5c1f] text-white text-sm font-medium pl-4 pr-10 py-2 rounded outline-none cursor-pointer"
                  value={sortOption}
                  onChange={(e) => setSortOption(e.target.value)}
                >
                  <option value="default">Mặc định</option>
                  <option value="price_asc">Giá từ thấp tới cao</option>
                  <option value="price_desc">Giá từ cao tới thấp</option>
                </select>
                <FiChevronDown className="absolute right-3 top-1/2 -translate-y-1/2 text-white pointer-events-none" />
              </div>
            </div>
          </div>

          {/* RENDER MẢNG ĐÃ VỪA LỌC VỪA SẮP XẾP */}
          {filteredAndSortedBooks.length > 0 ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {filteredAndSortedBooks.map((book) => (
                <ProductCard key={book.id} book={book} />
              ))}
            </div>
          ) : (
            <div className="py-10 text-center text-gray-500 bg-gray-50 rounded-lg">
              Không tìm thấy tựa sách nào phù hợp với bộ lọc của bạn.
            </div>
          )}
        </div>

      </div>
    </div>
  );
};

export default CategoryPage;