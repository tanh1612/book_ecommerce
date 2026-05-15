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
  
  // CHỈ CẦN TỈNH VÀ PHƯỜNG/XÃ (Tuân thủ UpdateAddressRequest.php)
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
      setAddresses(res.data.data || res.data || []);
    } catch (err) {
      toast.error("Không thể tải sổ địa chỉ!");
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchAddresses();
    // LẤY DỮ LIỆU TỈNH TỪ BACKEND ĐỂ CHUẨN VERSION 2025
    addressApi.getProvinces()
      .then(res => setProvinces(res.data.data || res.data || []))
      .catch(() => console.log("Lỗi tải Tỉnh thành từ Backend"));
  }, []);

// === TRONG FILE AddressBook.jsx ===
  const handleProvinceChange = async (e) => {
    // Ép kiểu ngay lập tức để State lưu đúng chuẩn (VD: '1' -> '01')
    const pCode = String(e.target.value).padStart(2, '0');
    
    setAddressForm({ ...addressForm, province_code: pCode, ward_code: '' });
    
    if (pCode) {
      try {
        const res = await addressApi.getWards(pCode);
        setWards(res.data.data || res.data || []);
      } catch (error) {
        toast.error("Lỗi tải Phường/Xã từ Backend!");
      }
    } else {
      setWards([]);
    }
  };

  const handleWardChange = (e) => {
    setAddressForm({ ...addressForm, ward_code: e.target.value });
  };

  const handleAddNew = () => {
    setAddressForm({
      recipient_name: '', recipient_phone: '', 
      province_code: '', ward_code: '', 
      detail_address: '', is_default: false
    });
    setWards([]);
    setEditingId(null);
    setIsModalOpen(true);
  };

  const handleEdit = async (addr) => {
    setEditingId(addr.id);
    setIsModalOpen(true);
    setWards([]);

    setAddressForm({
      recipient_name: addr.recipient_name,
      recipient_phone: addr.recipient_phone,
      province_code: addr.province_code,
      ward_code: addr.ward_code,
      detail_address: addr.detail_address,
      is_default: addr.is_default == 1 || addr.is_default === true
    });

    if (addr.province_code) {
      try {
        const res = await addressApi.getWards(addr.province_code);
        setWards(res.data.data || res.data || []);
      } catch(e) {}
    }
  };

  const handleSave = async (e) => {
    e.preventDefault();
    if(!addressForm.province_code || !addressForm.ward_code) {
      return toast.warning("Vui lòng chọn đầy đủ Tỉnh/Thành và Phường/Xã!");
    }

    // Đệm số 0 để luôn gửi đúng định dạng cho BE
    const finalProvinceCode = String(addressForm.province_code).padStart(2, '0');
    const finalWardCode = String(addressForm.ward_code).padStart(5, '0');

    const payload = {
      recipient_name: addressForm.recipient_name,
      recipient_phone: addressForm.recipient_phone,
      province_code: finalProvinceCode,
      ward_code: finalWardCode,
      detail_address: addressForm.detail_address,
      is_default: addressForm.is_default ? 1 : 0
    };

    try {
      setIsSubmitting(true);
      if (editingId) {
        await addressApi.updateAddress(editingId, payload);
        toast.success("Cập nhật địa chỉ thành công!");
      } else {
        await addressApi.createAddress(payload);
        toast.success("Thêm địa chỉ mới thành công!");
      }
      setIsModalOpen(false);
      fetchAddresses(); 
    } catch (err) {
      if (err.response?.status === 422 && err.response?.data?.errors) {
        const errorMessages = Object.values(err.response.data.errors).flat();
        errorMessages.forEach(msg => toast.error(msg));
      } else {
        toast.error(err.response?.data?.message || "Lỗi lưu địa chỉ!");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDelete = async (addr) => {
    if (addr.is_default == 1 || addr.is_default === true) {
      return toast.warning("Không thể xóa địa chỉ mặc định!");
    }
    if (window.confirm("Bạn có chắc chắn muốn xóa địa chỉ này?")) {
      try {
        await addressApi.deleteAddress(addr.id);
        toast.success("Đã xóa địa chỉ!");
        fetchAddresses();
      } catch (err) {
        toast.error(err.response?.data?.message || "Xóa thất bại!");
      }
    }
  };

  return (
    <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
      <div className="flex justify-between items-center mb-6 border-b pb-4">
        <h1 className="text-2xl font-bold text-gray-800">Sổ địa chỉ</h1>
        <button onClick={handleAddNew} className="flex items-center gap-2 bg-[#157a2c] text-white px-4 py-2 rounded-md hover:bg-green-800 transition text-sm font-medium shadow-sm">
          <FiPlus /> Thêm địa chỉ mới
        </button>
      </div>

      <div className="flex flex-col gap-4 relative min-h-[150px]">
        {isLoading && (
          <div className="absolute inset-0 bg-white/60 flex items-center justify-center z-10">
             <div className="w-8 h-8 border-4 border-[#157a2c] border-t-transparent rounded-full animate-spin"></div>
          </div>
        )}

        {!isLoading && addresses.length === 0 ? (
          <div className="text-center py-8 text-gray-500">Bạn chưa lưu địa chỉ nào.</div>
        ) : (
          addresses.map((addr) => {
            const isDefault = addr.is_default == 1 || addr.is_default === true;
            return (
              <div key={addr.id} className={`border rounded-lg p-5 flex justify-between items-start transition ${isDefault ? 'border-[#157a2c] bg-green-50/30' : 'border-gray-200 hover:border-[#157a2c]'}`}>
                <div className="flex flex-col gap-2">
                  <div className="flex items-center gap-3">
                    <span className="font-bold text-gray-800">{addr.recipient_name}</span>
                    {isDefault && (
                      <span className="bg-green-100 text-[#157a2c] text-[10px] font-bold px-2 py-0.5 rounded uppercase flex items-center gap-1">
                        <FiMapPin size={10} /> Mặc định
                      </span>
                    )}
                  </div>
                  <div className="text-sm text-gray-600">
                    <span className="text-gray-500">Địa chỉ:</span> {addr.full_address || addr.detail_address}
                  </div>
                  <div className="text-sm text-gray-600">
                    <span className="text-gray-500">Điện thoại:</span> {addr.recipient_phone}
                  </div>
                </div>
                
                <div className="flex flex-col items-end gap-3">
                  <div className="flex gap-4">
                    <button onClick={() => handleEdit(addr)} className="text-blue-600 hover:underline text-sm flex items-center gap-1"><FiEdit2 size={14}/> Sửa</button>
                    {!isDefault && (
                      <button onClick={() => handleDelete(addr)} className="text-red-500 hover:underline text-sm flex items-center gap-1"><FiTrash2 size={14}/> Xóa</button>
                    )}
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>

      {isModalOpen && (
        <div className="fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col animate-fade-in">
            <div className="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
              <h2 className="text-lg font-bold text-gray-800">{editingId ? "Cập nhật địa chỉ" : "Thêm địa chỉ mới"}</h2>
              <button onClick={() => setIsModalOpen(false)} className="text-gray-400 hover:text-red-500 transition"><FiX size={24} /></button>
            </div>
            
            <form onSubmit={handleSave} className="p-6 flex flex-col gap-4">
              <div className="flex gap-4">
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Họ và tên</label>
                  <input type="text" required placeholder="Tên người nhận" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                    value={addressForm.recipient_name} onChange={(e) => setAddressForm({...addressForm, recipient_name: e.target.value})} 
                  />
                </div>
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
                  <input type="tel" required placeholder="Số điện thoại" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                    value={addressForm.recipient_phone} onChange={(e) => setAddressForm({...addressForm, recipient_phone: e.target.value})} 
                  />
                </div>
              </div>

              {/* === FORM CHỈ CÒN 2 Ô TỈNH VÀ PHƯỜNG === */}
              <div className="flex gap-4">
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Tỉnh/Thành phố</label>
                  <select required className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c] bg-white cursor-pointer"
                    value={addressForm.province_code} onChange={handleProvinceChange}>
                    <option value="" disabled>-- Chọn Tỉnh --</option>
                    {provinces.map(p => (
                      <option key={p.code} value={p.code}>{p.name}</option>
                    ))}
                  </select>
                </div>
                
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Phường/Xã</label>
                  <select required disabled={!addressForm.province_code} className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c] bg-white cursor-pointer disabled:bg-gray-100"
                    value={addressForm.ward_code} onChange={handleWardChange}>
                    <option value="" disabled>-- Chọn Phường --</option>
                    {wards.map(w => (
                      <option key={w.code} value={w.code}>{w.name}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Địa chỉ cụ thể</label>
                <input type="text" required placeholder="Số nhà, Tên đường..." className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
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
                <button type="submit" disabled={isSubmitting} className={`w-1/2 bg-[#157a2c] text-white py-2.5 rounded font-medium transition ${isSubmitting ? 'opacity-70' : 'hover:bg-green-800'}`}>
                  {isSubmitting ? 'Đang lưu...' : 'Hoàn thành'}
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