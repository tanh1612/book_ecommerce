// src/pages/Profile/AddressBook.jsx
import { useState, useEffect } from 'react';
import { FiMapPin, FiPlus, FiEdit2, FiTrash2, FiX } from 'react-icons/fi';
import { toast } from 'react-toastify';
import addressApi from '../../services/addressApi';

const AddressBook = () => {
  const [addresses, setAddresses] = useState([]);
  const [isLoading, setIsLoading] = useState(true);
  
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  
  const [provinces, setProvinces] = useState([]);
  const [wards, setWards] = useState([]);

  const [addressForm, setAddressForm] = useState({
    recipient_name: '',
    recipient_phone: '',
    province_code: '', 
    ward_code: '', 
    detail_address: '',
    is_default: false
  });

  const fetchAddresses = async () => {
    try {
      setIsLoading(true);
      const res = await addressApi.getAddresses();
      setAddresses(res.data?.data || res.data || []);
    } catch (err) {
      toast.error("Không thể tải danh sách địa chỉ!");
    } finally {
      setIsLoading(false);
    }
  };

  const fetchProvinces = async () => {
    try {
      const res = await addressApi.getProvinces();
      setProvinces(res.data?.data || res.data || []);
    } catch (err) {
      console.error("Lỗi tải tỉnh thành:", err);
    }
  };

  useEffect(() => {
    fetchAddresses();
    fetchProvinces();
  }, []);

  const handleOpenModal = async (address = null) => {
    if (address) {
      setEditingId(address.id);
      setAddressForm({
        recipient_name: address.recipient_name || '',
        recipient_phone: address.recipient_phone || '',
        province_code: address.province_code || '',
        ward_code: address.ward_code || '',
        detail_address: address.detail_address || '',
        is_default: address.is_default === 1 || address.is_default === true
      });

      if (address.province_code) {
        try {
          const res = await addressApi.getWards(address.province_code);
          setWards(res.data?.data || res.data || []);
        } catch (err) {
          console.error(err);
        }
      }
    } else {
      setEditingId(null);
      setAddressForm({
        recipient_name: '',
        recipient_phone: '',
        province_code: '',
        ward_code: '',
        detail_address: '',
        is_default: addresses.length === 0 // Nếu sổ địa chỉ trống, ép địa chỉ đầu tiên làm mặc định
      });
      setWards([]);
    }
    setIsModalOpen(true);
  };

  const handleProvinceChange = async (e) => {
    const provinceCode = e.target.value;
    setAddressForm(prev => ({ ...prev, province_code: provinceCode, ward_code: '' }));
    
    if (provinceCode) {
      try {
        const res = await addressApi.getWards(provinceCode);
        setWards(res.data?.data || res.data || []);
      } catch (err) {
        console.error(err);
      }
    } else {
      setWards([]);
    }
  };

  const handleSaveAddress = async (e) => {
    e.preventDefault();

    // 1. CHẶN LỖI CLIENT: Bắt buộc chọn Phường/Xã theo chuẩn `required` của BE
    if (!addressForm.recipient_name || !addressForm.recipient_phone || !addressForm.province_code || !addressForm.ward_code || !addressForm.detail_address) {
      return toast.error("Vui lòng điền và chọn đầy đủ thông tin (Tỉnh thành, Phường xã)!");
    }

    setIsSubmitting(true);
    try {
      // 2. CHUẨN HÓA PAYLOAD: Ép kiểu và thêm số 0 chống lỗi Validation
      const payload = {
        recipient_name: addressForm.recipient_name.trim(),
        recipient_phone: addressForm.recipient_phone.trim(),
        province_code: String(addressForm.province_code).padStart(2, '0'),
        ward_code: String(addressForm.ward_code).padStart(5, '0'),
        detail_address: addressForm.detail_address.trim(),
        is_default: addressForm.is_default ? 1 : 0 // Backend yêu cầu boolean hoặc 1/0
      };

      if (editingId) {
        await addressApi.updateAddress(editingId, payload);
        toast.success("Cập nhật địa chỉ thành công!");
      } else {
        await addressApi.createAddress(payload);
        toast.success("Thêm địa chỉ mới thành công!");
      }

      setIsModalOpen(false);
      fetchAddresses();
    } catch (error) {
      console.error("Chi tiết lỗi lưu địa chỉ:", error.response?.data);
      
      // 3. BẮT LỖI TỪ BACKEND: Hiển thị thông báo Validation trực tiếp
      if (error.response?.status === 422) {
        const validationErrors = error.response.data?.errors;
        if (validationErrors) {
          const firstErrorKey = Object.keys(validationErrors)[0];
          toast.error(validationErrors[firstErrorKey][0]); // In ra lỗi đầu tiên
        } else {
          toast.error(error.response.data?.message || "Dữ liệu nhập vào chưa hợp lệ!");
        }
      } else {
        toast.error("Đã xảy ra lỗi hệ thống khi lưu địa chỉ!");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteAddress = async (id) => {
    if (window.confirm("Bạn có chắc chắn muốn xóa địa chỉ này?")) {
      try {
        await addressApi.deleteAddress(id);
        toast.success("Đã xóa địa chỉ!");
        fetchAddresses();
      } catch (error) {
        toast.error("Lỗi khi xóa địa chỉ!");
      }
    }
  };

  return (
    <div className="bg-white rounded-lg shadow-sm border border-gray-100 h-full">
      <div className="p-4 md:p-6 flex justify-between items-center border-b">
        <h2 className="text-xl font-bold text-gray-800">Sổ địa chỉ</h2>
        <button 
          onClick={() => handleOpenModal()} 
          className="bg-[#157a2c] text-white px-4 py-2 rounded text-sm font-bold flex items-center gap-2 hover:bg-green-800 transition"
        >
          <FiPlus /> Thêm địa chỉ mới
        </button>
      </div>

      <div className="p-4 md:p-6">
        {isLoading ? (
          <div className="flex justify-center py-10"><div className="w-8 h-8 border-4 border-[#157a2c] border-t-transparent rounded-full animate-spin"></div></div>
        ) : addresses.length > 0 ? (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {addresses.map(address => (
              <div key={address.id} className={`border rounded-lg p-4 relative ${address.is_default ? 'border-[#157a2c] bg-green-50' : 'border-gray-200 bg-white'}`}>
                {address.is_default && (
                  <div className="absolute top-0 right-0 bg-[#157a2c] text-white text-[10px] font-bold px-2 py-1 rounded-bl-lg rounded-tr-lg flex items-center gap-1">
                    Mặc định
                  </div>
                )}
                
                <div className="flex items-start gap-3 mb-2 mt-2">
                  <FiMapPin className="text-[#157a2c] mt-1 flex-shrink-0" />
                  <div>
                    <h3 className="font-bold text-gray-800 flex items-center gap-2">
                      {address.recipient_name}
                      <span className="text-gray-400 font-normal">|</span>
                      <span className="text-gray-600 font-normal">{address.recipient_phone}</span>
                    </h3>
                  </div>
                </div>
                
                <p className="text-gray-600 text-sm ml-7 mb-4 line-clamp-2">
                  {address.detail_address}
                </p>

                <div className="flex gap-2 ml-7">
                  <button onClick={() => handleOpenModal(address)} className="text-sm font-medium text-blue-600 hover:text-blue-800 flex items-center gap-1">
                    <FiEdit2 size={14} /> Sửa
                  </button>
                  {!address.is_default && (
                    <button onClick={() => handleDeleteAddress(address.id)} className="text-sm font-medium text-red-500 hover:text-red-700 flex items-center gap-1 ml-4">
                      <FiTrash2 size={14} /> Xóa
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-12 bg-gray-50 rounded-lg border-2 border-dashed border-gray-200">
            <FiMapPin className="mx-auto text-4xl text-gray-300 mb-3" />
            <h3 className="text-lg font-bold text-gray-800 mb-1">Sổ địa chỉ trống</h3>
            <p className="text-gray-500 text-sm mb-4">Bạn chưa lưu địa chỉ nhận hàng nào</p>
            <button onClick={() => handleOpenModal()} className="text-[#157a2c] font-bold text-sm hover:underline">Thêm ngay</button>
          </div>
        )}
      </div>

      {/* MODAL THÊM / SỬA ĐỊA CHỈ */}
      {isModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 px-4">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-lg overflow-hidden animate-fade-in-up">
            <div className="flex justify-between items-center p-4 md:p-6 border-b">
              <h2 className="text-xl font-bold text-gray-800">{editingId ? 'Cập nhật địa chỉ' : 'Thêm địa chỉ mới'}</h2>
              <button onClick={() => setIsModalOpen(false)} className="text-gray-400 hover:text-gray-600 transition"><FiX size={24} /></button>
            </div>
            
            <form onSubmit={handleSaveAddress} className="p-4 md:p-6 flex flex-col gap-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Họ và tên *</label>
                  <input type="text" placeholder="Nhập họ và tên" required className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                    value={addressForm.recipient_name} onChange={(e) => setAddressForm({...addressForm, recipient_name: e.target.value})} 
                  />
                </div>
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Số điện thoại *</label>
                  <input type="tel" placeholder="Nhập số điện thoại" required className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                    value={addressForm.recipient_phone} onChange={(e) => setAddressForm({...addressForm, recipient_phone: e.target.value})} 
                  />
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Tỉnh/Thành phố *</label>
                  <select required className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c] bg-white" 
                    value={addressForm.province_code} onChange={handleProvinceChange}
                  >
                    <option value="">Chọn Tỉnh/Thành</option>
                    {provinces.map(p => <option key={p.code} value={p.code}>{p.name}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-sm text-gray-600 mb-1">Quận/Huyện/Xã *</label>
                  <select required disabled={!addressForm.province_code} className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c] bg-white" 
                    value={addressForm.ward_code} onChange={(e) => setAddressForm({...addressForm, ward_code: e.target.value})}
                  >
                    <option value="">Chọn Phường/Xã</option>
                    {wards.map(w => <option key={w.code} value={w.code}>{w.name}</option>)}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm text-gray-600 mb-1">Địa chỉ chi tiết (Số nhà, đường...) *</label>
                <input type="text" placeholder="Ví dụ: 123 Lê Lợi..." required className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                  value={addressForm.detail_address} onChange={(e) => setAddressForm({...addressForm, detail_address: e.target.value})} 
                />
              </div>

              <label className="flex items-center gap-2 mt-2 cursor-pointer w-fit">
                <input type="checkbox" className="w-4 h-4 text-[#157a2c] rounded border-gray-300 focus:ring-[#157a2c]" 
                  checked={addressForm.is_default} onChange={(e) => setAddressForm({...addressForm, is_default: e.target.checked})} 
                />
                <span className="text-sm font-medium text-gray-700">Đặt làm địa chỉ mặc định</span>
              </label>

              <div className="flex gap-4 mt-4">
                <button type="button" disabled={isSubmitting} onClick={() => setIsModalOpen(false)} className="w-1/2 border border-gray-300 text-gray-700 py-2.5 rounded font-medium hover:bg-gray-50 transition">Hủy</button>
                <button type="submit" disabled={isSubmitting} className={`w-1/2 bg-[#157a2c] text-white py-2.5 rounded font-medium transition flex justify-center items-center gap-2 ${isSubmitting ? 'opacity-70' : 'hover:bg-green-800'}`}>
                  {isSubmitting ? <div className="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></div> : 'Lưu địa chỉ'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AddressBook;