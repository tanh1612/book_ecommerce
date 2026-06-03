// src/components/UI/Chatbot.jsx
import { useState, useRef, useEffect } from 'react';
import { FiMessageCircle, FiX, FiSend, FiThumbsUp, FiThumbsDown } from 'react-icons/fi';
import { v4 as uuidv4 } from 'uuid';
import aiApi from '../../services/aiApi';
import { toast } from 'react-toastify';

const Chatbot = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const messagesEndRef = useRef(null);

  // Khởi tạo Session ID duy nhất cho phiên chat hiện tại
  const [sessionId] = useState(() => uuidv4());

  const [messages, setMessages] = useState([
    {
      id: 'welcome',
      type: 'bot',
      text: 'Chào bạn! Mình là trợ lý ảo AI của Bookify. Mình có thể giúp bạn tìm sách, tóm tắt nội dung, hoặc giải đáp thắc mắc. Bạn cần hỗ trợ gì ạ?',
      message_id: null,
      sources: []
    }
  ]);

  // Tự động cuộn xuống tin nhắn mới nhất
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isLoading]);

  const handleSend = async () => {
    const question = input.trim();
    if (!question) return;

    // 1. Thêm tin nhắn của User vào UI
    const newUserMsg = { id: Date.now(), type: 'user', text: question };
    setMessages(prev => [...prev, newUserMsg]);
    setInput('');
    setIsLoading(true);

    try {
      // 2. Gọi API lên Backend RAG
      const res = await aiApi.sendChat(sessionId, question);
      const data = res.data?.data || res.data;

      // 3. Cập nhật câu trả lời của Bot
      setMessages(prev => [...prev, {
        id: Date.now() + 1,
        type: 'bot',
        text: data.answer,
        message_id: data.message_id,
        sources: data.sources || [],
        feedback: null // Trạng thái đã vote: null | 'up' | 'down'
      }]);
    } catch (error) {
      setMessages(prev => [...prev, {
        id: Date.now() + 1,
        type: 'bot',
        text: 'Xin lỗi, hệ thống AI đang quá tải hoặc có lỗi xảy ra. Vui lòng thử lại sau giây lát!',
        message_id: null,
        isError: true
      }]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === 'Enter') {
      handleSend();
    }
  };

  const handleFeedback = async (msgIndex, messageId, rating) => {
    if (!messageId) return;

    // Cập nhật UI ngay lập tức
    const newMessages = [...messages];
    newMessages[msgIndex].feedback = rating;
    setMessages(newMessages);

    try {
      await aiApi.sendFeedback(messageId, sessionId, rating);
      toast.success(rating === 'up' ? "Cảm ơn bạn đã đánh giá tốt!" : "Cảm ơn bạn đã góp ý!");
    } catch (error) {
      toast.error("Lỗi khi gửi đánh giá.");
    }
  };

  // Hàm render văn bản có ngắt dòng
  const formatText = (text) => {
    return text.split('\n').map((str, idx) => (
      <span key={idx}>
        {str}
        <br />
      </span>
    ));
  };

  return (
    <div className="fixed bottom-6 right-6 z-50">
      {isOpen && (
        <div className="bg-white w-[350px] sm:w-[400px] h-[500px] rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden mb-4 transition-all duration-300 transform origin-bottom-right">
          
          {/* Header */}
          <div className="bg-[#157a2c] text-white p-4 flex justify-between items-center shadow-md z-10">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-white rounded-full flex justify-center items-center">
                <span className="text-[#157a2c] font-bold text-xl">B</span>
              </div>
              <div>
                <h3 className="font-bold tracking-wide">Bookify AI</h3>
                <div className="flex items-center gap-1.5 mt-0.5">
                  <span className="w-2 h-2 bg-green-300 rounded-full animate-pulse"></span>
                  <p className="text-[11px] text-green-100 uppercase tracking-wider">Trợ lý thông minh</p>
                </div>
              </div>
            </div>
            <button onClick={() => setIsOpen(false)} className="hover:bg-green-700 p-2 rounded-full transition">
              <FiX size={20} />
            </button>
          </div>

          {/* Khung Chat */}
          <div className="flex-grow p-4 bg-[#f8f9fa] overflow-y-auto custom-scrollbar flex flex-col gap-4">
            {messages.map((msg, index) => (
              <div key={msg.id} className={`flex flex-col ${msg.type === 'user' ? 'items-end' : 'items-start'}`}>
                
                {/* Bong bóng tin nhắn */}
                <div className={`max-w-[85%] p-3 text-sm shadow-sm ${
                  msg.type === 'user' 
                    ? 'bg-[#157a2c] text-white rounded-2xl rounded-tr-sm' 
                    : `bg-white border border-gray-100 text-gray-800 rounded-2xl rounded-tl-sm ${msg.isError ? 'border-red-300 text-red-600' : ''}`
                }`}>
                  {formatText(msg.text)}
                </div>

                {/* Phần hiển thị Nguồn sách gợi ý (Nếu có) */}
                {msg.type === 'bot' && msg.sources && msg.sources.length > 0 && (
                  <div className="max-w-[85%] mt-2 flex flex-wrap gap-2">
                    {msg.sources.map((source, idx) => (
                      <span key={idx} className="text-[11px] bg-green-50 text-[#157a2c] border border-green-200 px-2 py-1 rounded-md line-clamp-1">
                        📚 {source.name || source.title || "Sách gợi ý"}
                      </span>
                    ))}
                  </div>
                )}

                {/* Nút Feedback (Chỉ hiện cho Bot và có message_id) */}
                {msg.type === 'bot' && msg.message_id && (
                  <div className="flex gap-2 mt-2 ml-1">
                    <button 
                      onClick={() => handleFeedback(index, msg.message_id, 'up')}
                      className={`p-1.5 rounded transition ${msg.feedback === 'up' ? 'bg-green-100 text-[#157a2c]' : 'text-gray-400 hover:text-[#157a2c] hover:bg-green-50'}`}
                      title="Câu trả lời tốt"
                    >
                      <FiThumbsUp size={14} className={msg.feedback === 'up' ? 'fill-current' : ''} />
                    </button>
                    <button 
                      onClick={() => handleFeedback(index, msg.message_id, 'down')}
                      className={`p-1.5 rounded transition ${msg.feedback === 'down' ? 'bg-red-100 text-red-500' : 'text-gray-400 hover:text-red-500 hover:bg-red-50'}`}
                      title="Câu trả lời chưa tốt"
                    >
                      <FiThumbsDown size={14} className={msg.feedback === 'down' ? 'fill-current' : ''} />
                    </button>
                  </div>
                )}
              </div>
            ))}
            
            {/* Hiệu ứng gõ phím khi đang load */}
            {isLoading && (
              <div className="flex items-start">
                <div className="bg-white border border-gray-100 p-4 rounded-2xl rounded-tl-sm shadow-sm flex gap-1.5">
                  <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></span>
                  <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></span>
                  <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style={{ animationDelay: '0.4s' }}></span>
                </div>
              </div>
            )}
            
            <div ref={messagesEndRef} />
          </div>

          {/* Ô nhập liệu */}
          <div className="p-3 bg-white border-t border-gray-200 flex gap-2 items-center">
            <input 
              type="text" 
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              disabled={isLoading}
              placeholder={isLoading ? "AI đang suy nghĩ..." : "Nhập câu hỏi của bạn..."} 
              className="flex-grow border border-gray-300 rounded-full px-4 py-2.5 text-sm outline-none focus:border-[#157a2c] focus:ring-1 focus:ring-[#157a2c] disabled:bg-gray-100"
            />
            <button 
              onClick={handleSend}
              disabled={!input.trim() || isLoading}
              className="bg-[#157a2c] text-white p-3 rounded-full hover:bg-green-800 transition flex-shrink-0 disabled:bg-gray-300 disabled:cursor-not-allowed"
            >
              <FiSend size={18} />
            </button>
          </div>
        </div>
      )}

      {/* Nút Floating */}
      <button 
        onClick={() => setIsOpen(!isOpen)}
        className="bg-[#157a2c] text-white p-4 rounded-full shadow-xl hover:shadow-2xl hover:scale-110 transition-all flex items-center justify-center float-right relative"
      >
        {!isOpen && (
          <span className="absolute -top-1 -right-1 flex h-3 w-3">
            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span className="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
          </span>
        )}
        {isOpen ? <FiX size={28} /> : <FiMessageCircle size={28} />}
      </button>
    </div>
  );
};

export default Chatbot;