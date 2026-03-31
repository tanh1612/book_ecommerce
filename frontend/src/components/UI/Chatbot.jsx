import { useState } from 'react';
import { FiMessageCircle, FiX, FiSend } from 'react-icons/fi';

const Chatbot = () => {
  // Quản lý trạng thái mở/đóng của cửa sổ chat
  const [isOpen, setIsOpen] = useState(false);

  return (
    <div className="fixed bottom-6 right-6 z-50">
      {/* Cửa sổ Chat (Chỉ hiện khi isOpen = true) */}
      {isOpen && (
        <div className="bg-white w-80 h-96 rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden mb-4 transition-all duration-300 transform origin-bottom-right">
          {/* Header Chatbot */}
          <div className="bg-[#C92127] text-white p-4 flex justify-between items-center">
            <div>
              <h3 className="font-bold">Fahasa Support</h3>
              <p className="text-xs text-red-100">Luôn sẵn sàng hỗ trợ bạn</p>
            </div>
            <button onClick={() => setIsOpen(false)} className="hover:bg-red-700 p-1 rounded-full transition">
              <FiX size={20} />
            </button>
          </div>

          {/* Nội dung tin nhắn */}
          <div className="flex-grow p-4 bg-gray-50 overflow-y-auto">
            <div className="bg-white border border-gray-200 p-3 rounded-xl rounded-tl-none w-3/4 text-sm text-gray-700 shadow-sm mb-3">
              Chào bạn! Mình là trợ lý ảo. Bạn đang tìm sách thể loại gì hay cần hỗ trợ đơn hàng ạ?
            </div>
            {/* Bạn có thể map() các tin nhắn ở đây sau này */}
          </div>

          {/* Ô nhập tin nhắn */}
          <div className="p-3 bg-white border-t border-gray-200 flex gap-2">
            <input 
              type="text" 
              placeholder="Nhập tin nhắn..." 
              className="flex-grow border border-gray-300 rounded-full px-4 py-2 text-sm outline-none focus:border-[#C92127]"
            />
            <button className="bg-[#C92127] text-white p-2 rounded-full hover:bg-red-800 transition flex-shrink-0">
              <FiSend size={18} />
            </button>
          </div>
        </div>
      )}

      {/* Nút bật/tắt Chatbot (Floating Button) */}
      <button 
        onClick={() => setIsOpen(!isOpen)}
        className="bg-[#C92127] text-white p-4 rounded-full shadow-lg hover:shadow-xl hover:scale-105 transition-all flex items-center justify-center float-right"
      >
        {isOpen ? <FiX size={28} /> : <FiMessageCircle size={28} />}
      </button>
    </div>
  );
};

export default Chatbot;