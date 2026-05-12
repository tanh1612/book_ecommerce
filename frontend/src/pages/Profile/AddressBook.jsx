// src/pages/Profile/AddressBook.jsx
import { useState, useEffect } from 'react';
import { FiMapPin, FiPlus, FiEdit2, FiTrash2, FiX } from 'react-icons/fi';

// Dữ liệu mẫu (Cập nhật để khớp với chuẩn có Mã Tỉnh, Mã Huyện, Mã Phường)
const MOCK_ADDRESSES = [
  { 
    id: 1, 
    recipientName: "Lê Minh Quân", 
    recipientPhone: "0337706769", 
    provinceCode: "01", provinceName: "Thành phố Hà Nội", 
    districtCode: "006", districtName: "Quận Đống Đa", 
    wardCode: "00220", wardName: "Phường Láng Thượng", 
    detailAddress: "25 Chùa Láng", 
    isDefault: true 
  }
];

const AddressBook = () => {
  const [addresses, setAddresses] = useState(MOCK_ADDRESSES);
  
  // State quản lý Modal form
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingId, setEditingId] = useState(null);
  
  // State lưu trữ dữ liệu từ API
  const [provinces, setProvinces] = useState([]);
  const [districts, setDistricts] = useState([]);
  const [wards, setWards] = useState([]);

  // State quản lý form đang nhập
  const [addressForm, setAddressForm] = useState({
    recipientName: '',
    recipientPhone: '',
    provinceCode: '', provinceName: '',
    districtCode: '', districtName: '',
    wardCode: '', wardName: '',
    detailAddress: '',
    isDefault: false
  });

  // Gọi API lấy danh sách Tỉnh/Thành phố khi trang vừa load
  useEffect(() => {
    fetch('https://provinces.open-api.vn/api/p/')
      .then(res => res.json())
      .then(data => setProvinces(data))
      .catch(err => console.error("Lỗi tải Tỉnh/Thành phố:", err));
  }, []);

  // Xử lý khi chọn Tỉnh/Thành phố -> Load Quận/Huyện
  const handleProvinceChange = (e) => {
    const pCode = e.target.value;
    const pName = e.target.options[e.target.selectedIndex].text;
    
    setAddressForm({ 
      ...addressForm, 
      provinceCode: pCode, provinceName: pName, 
      districtCode: '', districtName: '', 
      wardCode: '', wardName: '' 
    });

    if (pCode) {
      fetch(`https://provinces.open-api.vn/api/p/${pCode}?depth=2`)
        .then(res => res.json())
        .then(data => {
          setDistricts(data.districts || []);
          setWards([]); // Xóa list Phường/Xã cũ
        });
    } else {
      setDistricts([]); setWards([]);
    }
  };

  // Xử lý khi chọn Quận/Huyện -> Load Phường/Xã
  const handleDistrictChange = (e) => {
    const dCode = e.target.value;
    const dName = e.target.options[e.target.selectedIndex].text;

    setAddressForm({ 
      ...addressForm, 
      districtCode: dCode, districtName: dName, 
      wardCode: '', wardName: '' 
    });

    if (dCode) {
      fetch(`https://provinces.open-api.vn/api/d/${dCode}?depth=2`)
        .then(res => res.json())
        .then(data => setWards(data.wards || []));
    } else {
      setWards([]);
    }
  };

  // Xử lý khi chọn Phường/Xã
  const handleWardChange = (e) => {
    const wCode = e.target.value;
    const wName = e.target.options[e.target.selectedIndex].text;
    setAddressForm({ ...addressForm, wardCode: wCode, wardName: wName });
  };

  const handleAddNew = () => {
    setAddressForm({
      recipientName: '', recipientPhone: '', 
      provinceCode: '', provinceName: '', 
      districtCode: '', districtName: '', 
      wardCode: '', wardName: '', 
      detailAddress: '', isDefault: false
    });
    setDistricts([]); setWards([]);
    setEditingId(null);
    setIsModalOpen(true);
  };

  const handleEdit = async (address) => {
    setAddressForm(address);
    setEditingId(address.id);
    setIsModalOpen(true);

    // Khi ấn nút Sửa, phải gọi API load trước danh sách Huyện và Phường của địa chỉ đó
    if (address.provinceCode) {
      const resD = await fetch(`https://provinces.open-api.vn/api/p/${address.provinceCode}?depth=2`);
      const dataD = await resD.json();
      setDistricts(dataD.districts || []);
    }
    if (address.districtCode) {
      const resW = await fetch(`https://provinces.open-api.vn/api/d/${address.districtCode}?depth=2`);
      const dataW = await resW.json();
      setWards(dataW.wards || []);
    }
  };

  const handleSave = (e) => {
    e.preventDefault();
    if(!addressForm.provinceCode || !addressForm.districtCode || !addressForm.wardCode) {
      alert("Vui lòng chọn đầy đủ Tỉnh, Huyện và Xã!");
      return;
    }

    let updatedAddresses = [...addresses];

    if (addressForm.isDefault) {
      updatedAddresses = updatedAddresses.map(addr => ({ ...addr, isDefault: false }));
    } else if (addresses.length === 0) {
       addressForm.isDefault = true;
    }

    if (editingId) {
      updatedAddresses = updatedAddresses.map(addr => addr.id === editingId ? { ...addressForm, id: editingId } : addr);
      alert("Cập nhật địa chỉ thành công!");
    } else {
      const newAddress = { ...addressForm, id: Date.now() };
      updatedAddresses.push(newAddress);
      alert("Thêm địa chỉ mới thành công!");
    }

    updatedAddresses.sort((a, b) => (b.isDefault === true ? 1 : 0) - (a.isDefault === true ? 1 : 0));
    setAddresses(updatedAddresses);
    setIsModalOpen(false);
  };

  const handleDelete = (id) => {
    if (window.confirm("Bạn có chắc chắn muốn xóa địa chỉ này?")) {
      setAddresses(addresses.filter(addr => addr.id !== id));
    }
  };

  return (
    <div className="bg-white p-6 md:p-8 rounded-lg shadow-sm border border-gray-100">
      <div className="flex justify-between items-center mb-6 border-b pb-4">
        <h1 className="text-2xl font-bold text-gray-800">Sổ địa chỉ</h1>
        <button 
          onClick={handleAddNew}
          className="flex items-center gap-2 bg-[#157a2c] text-white px-4 py-2 rounded-md hover:bg-green-800 transition text-sm font-medium shadow-sm"
        >
          <FiPlus /> Thêm địa chỉ mới
        </button>
      </div>

      <div className="flex flex-col gap-4">
        {addresses.length === 0 ? (
          <div className="text-center py-8 text-gray-500">Bạn chưa lưu địa chỉ nào.</div>
        ) : (
          addresses.map((addr) => (
            <div key={addr.id} className={`border rounded-lg p-5 flex justify-between items-start transition ${addr.isDefault ? 'border-[#157a2c] bg-green-50/30' : 'border-gray-200 hover:border-[#157a2c]'}`}>
              <div className="flex flex-col gap-2">
                <div className="flex items-center gap-3">
                  <span className="font-bold text-gray-800">{addr.recipientName}</span>
                  {addr.isDefault && (
                    <span className="bg-green-100 text-[#157a2c] text-[10px] font-bold px-2 py-0.5 rounded uppercase flex items-center gap-1">
                      <FiMapPin size={10} /> Mặc định
                    </span>
                  )}
                </div>
                <div className="text-sm text-gray-600">
                  <span className="text-gray-500">Địa chỉ:</span> {addr.detailAddress}, {addr.wardName}, {addr.districtName}, {addr.provinceName}
                </div>
                <div className="text-sm text-gray-600">
                  <span className="text-gray-500">Điện thoại:</span> {addr.recipientPhone}
                </div>
              </div>
              
              <div className="flex flex-col items-end gap-3">
                <div className="flex gap-4">
                  <button onClick={() => handleEdit(addr)} className="text-blue-600 hover:underline text-sm flex items-center gap-1">
                    <FiEdit2 size={14}/> Sửa
                  </button>
                  {!addr.isDefault && (
                    <button onClick={() => handleDelete(addr.id)} className="text-red-500 hover:underline text-sm flex items-center gap-1">
                      <FiTrash2 size={14}/> Xóa
                    </button>
                  )}
                </div>
              </div>
            </div>
          ))
        )}
      </div>

      {/* === MODAL THÊM / SỬA ĐỊA CHỈ === */}
      {isModalOpen && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
            <div className="flex justify-between items-center p-5 border-b border-gray-100 bg-gray-50">
              <h2 className="text-lg font-bold text-gray-800">{editingId ? "Cập nhật địa chỉ" : "Thêm địa chỉ mới"}</h2>
              <button onClick={() => setIsModalOpen(false)} className="text-gray-400 hover:text-red-500 transition"><FiX size={24} /></button>
            </div>
            
            <form onSubmit={handleSave} className="p-6 flex flex-col gap-4">
              <div className="flex gap-4">
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Họ và tên</label>
                  <input type="text" required placeholder="Tên người nhận" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                    value={addressForm.recipientName} onChange={(e) => setAddressForm({...addressForm, recipientName: e.target.value})} 
                  />
                </div>
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Số điện thoại</label>
                  <input type="tel" required placeholder="Số điện thoại" className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                    value={addressForm.recipientPhone} onChange={(e) => setAddressForm({...addressForm, recipientPhone: e.target.value})} 
                  />
                </div>
              </div>

              {/* TỈNH / THÀNH PHỐ */}
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Tỉnh/Thành phố</label>
                <select 
                  required
                  className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c] bg-white cursor-pointer"
                  value={addressForm.provinceCode}
                  onChange={handleProvinceChange}
                >
                  <option value="" disabled>-- Chọn Tỉnh/Thành phố --</option>
                  {provinces.map(p => (
                    <option key={p.code} value={p.code}>{p.name}</option>
                  ))}
                </select>
              </div>

              <div className="flex gap-4">
                {/* QUẬN / HUYỆN */}
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Quận/Huyện</label>
                  <select 
                    required disabled={!addressForm.provinceCode}
                    className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c] bg-white cursor-pointer disabled:bg-gray-100"
                    value={addressForm.districtCode}
                    onChange={handleDistrictChange}
                  >
                    <option value="" disabled>-- Chọn Quận/Huyện --</option>
                    {districts.map(d => (
                      <option key={d.code} value={d.code}>{d.name}</option>
                    ))}
                  </select>
                </div>
                
                {/* PHƯỜNG / XÃ */}
                <div className="w-1/2">
                  <label className="block text-sm font-medium text-gray-700 mb-1">Phường/Xã</label>
                  <select 
                    required disabled={!addressForm.districtCode}
                    className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c] bg-white cursor-pointer disabled:bg-gray-100"
                    value={addressForm.wardCode}
                    onChange={handleWardChange}
                  >
                    <option value="" disabled>-- Chọn Phường/Xã --</option>
                    {wards.map(w => (
                      <option key={w.code} value={w.code}>{w.name}</option>
                    ))}
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Địa chỉ cụ thể</label>
                <input type="text" required placeholder="Số nhà, Tên đường..." className="w-full border border-gray-300 rounded py-2 px-3 outline-none focus:border-[#157a2c]" 
                  value={addressForm.detailAddress} onChange={(e) => setAddressForm({...addressForm, detailAddress: e.target.value})} 
                />
              </div>

              <label className="flex items-center gap-2 mt-2 cursor-pointer">
                <input type="checkbox" className="w-4 h-4 text-[#157a2c] rounded border-gray-300 focus:ring-[#157a2c]" 
                  checked={addressForm.isDefault} onChange={(e) => setAddressForm({...addressForm, isDefault: e.target.checked})} 
                />
                <span className="text-sm text-gray-700">Đặt làm địa chỉ mặc định</span>
              </label>

              <div className="flex gap-4 mt-4">
                <button type="button" onClick={() => setIsModalOpen(false)} className="w-1/2 border border-gray-300 text-gray-700 py-2.5 rounded font-medium hover:bg-gray-50 transition">Hủy</button>
                <button type="submit" className="w-1/2 bg-[#157a2c] text-white py-2.5 rounded font-medium hover:bg-green-800 transition">Hoàn thành</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default AddressBook;