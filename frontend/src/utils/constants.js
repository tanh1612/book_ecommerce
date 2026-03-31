// src/utils/constants.js

export const CATEGORIES_DB = [
  // --- CÁC DANH MỤC CHA (parent_id = null) ---
  { id: 1, name: 'Hư cấu', slug: 'hu-cau', parent_id: null, is_active: true },
  { id: 2, name: 'Phi hư cấu', slug: 'phi-hu-cau', parent_id: null, is_active: true },
  { id: 3, name: 'Thiếu nhi', slug: 'thieu-nhi', parent_id: null, is_active: true },
  { id: 4, name: 'Phân loại khác', slug: 'phan-loai-khac', parent_id: null, is_active: true },

  // --- CÁC DANH MỤC CON THUỘC "HƯ CẤU" (parent_id = 1) ---
  { id: 5, name: 'Văn học hiện đại', slug: 'van-hoc-hien-dai', parent_id: 1, is_active: true },
  { id: 6, name: 'Văn học kinh điển', slug: 'van-hoc-kinh-dien', parent_id: 1, is_active: true },
  { id: 7, name: 'Văn học thiếu nhi', slug: 'van-hoc-thieu-nhi', parent_id: 1, is_active: true },
  { id: 8, name: 'Trinh thám - Kinh dị', slug: 'trinh-tham-kinh-di', parent_id: 1, is_active: true },
  { id: 9, name: 'Khoa học Viễn tưởng', slug: 'khoa-hoc-vien-tuong', parent_id: 1, is_active: true },

  // --- CÁC DANH MỤC CON THUỘC "PHI HƯ CẤU" (parent_id = 2) ---
  { id: 10, name: 'Triết học', slug: 'triet-hoc', parent_id: 2, is_active: true },
  { id: 11, name: 'Sử học', slug: 'su-hoc', parent_id: 2, is_active: true },
  { id: 12, name: 'Kinh doanh', slug: 'kinh-doanh', parent_id: 2, is_active: true },
  { id: 13, name: 'Kỹ năng', slug: 'ky-nang', parent_id: 2, is_active: true },
  { id: 14, name: 'Tâm lý học', slug: 'tam-ly-hoc', parent_id: 2, is_active: true },

  // --- CÁC DANH MỤC CON THUỘC "THIẾU NHI" (parent_id = 3) ---
  { id: 15, name: '0-5 tuổi', slug: '0-5-tuoi', parent_id: 3, is_active: true },
  { id: 16, name: '6-8 tuổi', slug: '6-8-tuoi', parent_id: 3, is_active: true },
  { id: 17, name: '9-12 tuổi', slug: '9-12-tuoi', parent_id: 3, is_active: true },
];