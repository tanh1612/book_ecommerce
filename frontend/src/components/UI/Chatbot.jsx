// src/components/UI/Chatbot.jsx
import { useState, useRef, useEffect } from "react";
import { Link } from "react-router-dom";
import { FiX, FiSend, FiThumbsUp, FiThumbsDown } from "react-icons/fi";
import { v4 as uuidv4 } from "uuid";
import aiApi from "../../services/aiApi";
import { toast } from "react-toastify";
import chatbotIcon from "../../assets/bookify-favicon-512.png";
import chatbotButtonIcon from "../../assets/bookify-b-white-transparent.png";

const stripLinkArtifacts = (value) =>
  String(value || "")
    .replace(/\uD83D\uDCDA|\uD83D\uDD17/g, "")
    .replace(/\S*(?:\u0178|\u00c5\u00b8)\S*/g, "")
    .replace(/(?:đŸ\S+|ðŸ\S+)/g, "")
    .trim();

const buildBookPath = (href, source = {}) => {
  const sourceSlug = source.slug || source.book_slug || source.product_slug;
  if (sourceSlug) return `/book/${sourceSlug}`;

  const sourceId = source.book_id || source.product_id || source.id;
  const cleanHref = stripLinkArtifacts(href);

  if (!cleanHref && sourceId) return `/book/${sourceId}`;
  if (!cleanHref) return null;

  try {
    const parsedUrl = new URL(cleanHref, window.location.origin);
    const productMatch = parsedUrl.pathname.match(/\/(?:book|books|product|products)\/([^/?#]+)/i);
    if (productMatch?.[1]) {
      return `/book/${productMatch[1]}`;
    }

    const queryBookId = parsedUrl.searchParams.get("book_id") || parsedUrl.searchParams.get("id");
    if (queryBookId) return `/book/${queryBookId}`;
  } catch {
    const productMatch = cleanHref.match(/\/(?:book|books|product|products)\/([^/?#]+)/i);
    if (productMatch?.[1]) {
      return `/book/${productMatch[1]}`;
    }
  }

  return sourceId ? `/book/${sourceId}` : null;
};

const renderChatLink = (label, href, key) => {
  const cleanLabel = stripLinkArtifacts(label) || stripLinkArtifacts(href);
  const cleanHref = stripLinkArtifacts(href).replace(/[.,;!?]+$/, "");
  const bookPath = buildBookPath(cleanHref);

  if (bookPath) {
    return (
      <Link key={key} to={bookPath} className="chatbot-markdown-link">
        {cleanLabel}
      </Link>
    );
  }

  if (/^https?:\/\//i.test(cleanHref)) {
    return (
      <a
        key={key}
        href={cleanHref}
        className="chatbot-markdown-link"
        target="_blank"
        rel="noreferrer"
      >
        {cleanLabel}
      </a>
    );
  }

  return cleanLabel;
};

const renderInlineMarkdown = (text, keyPrefix) => {
  const cleanText = stripLinkArtifacts(text);
  const tokenPattern = /(\*\*[^*]+\*\*|\[[^\]]+\]\([^)]+\)|https?:\/\/[^\s)]+|\/(?:book|books|product|products)\/[^\s)]+)/g;
  const nodes = [];
  let lastIndex = 0;
  let tokenMatch;

  while ((tokenMatch = tokenPattern.exec(cleanText)) !== null) {
    const token = tokenMatch[0];
    const tokenIndex = tokenMatch.index;

    if (tokenIndex > lastIndex) {
      nodes.push(cleanText.slice(lastIndex, tokenIndex));
    }

    const markdownLink = token.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
    if (markdownLink) {
      nodes.push(renderChatLink(markdownLink[1], markdownLink[2], `${keyPrefix}-link-${tokenIndex}`));
    } else if (token.startsWith("**") && token.endsWith("**")) {
      nodes.push(
        <strong key={`${keyPrefix}-bold-${tokenIndex}`} className="font-bold">
          {token.slice(2, -2)}
        </strong>
      );
    } else {
      nodes.push(renderChatLink(token, token, `${keyPrefix}-url-${tokenIndex}`));
    }

    lastIndex = tokenIndex + token.length;
  }

  if (lastIndex < cleanText.length) {
    nodes.push(cleanText.slice(lastIndex));
  }

  return nodes;
};

const renderSourceChip = (source, idx) => {
  const sourceLabel = stripLinkArtifacts(source.name || source.title || "S\u00e1ch g\u1ee3i \u00fd");
  const sourcePath = buildBookPath(source.url || source.link || source.href, source);

  if (sourcePath) {
    return (
      <Link
        key={idx}
        to={sourcePath}
        className="chatbot-source-chip text-[11px] px-2 py-1 rounded-md line-clamp-1"
      >
        {sourceLabel}
      </Link>
    );
  }

  return (
    <span
      key={idx}
      className="chatbot-source-chip text-[11px] px-2 py-1 rounded-md line-clamp-1"
    >
      {sourceLabel}
    </span>
  );
};

const Chatbot = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [input, setInput] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const messagesEndRef = useRef(null);

  // Khởi tạo Session ID duy nhất cho phiên chat hiện tại
  const [sessionId] = useState(() => uuidv4());

  const [messages, setMessages] = useState([
    {
      id: "welcome",
      type: "bot",
      text: "Chào bạn! Mình là trợ lý ảo AI của Bookify. Mình có thể giúp bạn tìm sách, tóm tắt nội dung, hoặc giải đáp thắc mắc. Bạn cần hỗ trợ gì ạ?",
      message_id: null,
      sources: [],
    },
  ]);

  // Tự động cuộn xuống tin nhắn mới nhất
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages, isLoading]);

  const handleSend = async () => {
    const question = input.trim();
    if (!question) return;
    if (question.length < 2) {
      toast.error("Vui lòng nhập ít nhất 2 ký tự.");
      return;
    }

    // 1. Thêm tin nhắn của User vào UI
    const newUserMsg = { id: Date.now(), type: "user", text: question };
    setMessages((prev) => [...prev, newUserMsg]);
    setInput("");
    setIsLoading(true);

    try {
      // 2. Gọi API lên Backend RAG
      const res = await aiApi.sendChat(sessionId, question);
      const data = res.data?.data || res.data;

      // 3. Cập nhật câu trả lời của Bot
      setMessages((prev) => [
        ...prev,
        {
          id: Date.now() + 1,
          type: "bot",
          text: data.answer,
          message_id: data.message_id,
          sources: data.sources || [],
          feedback: null, // Trạng thái đã vote: null | 'up' | 'down'
        },
      ]);
    } catch (error) {
      let errorText =
        "Xin lỗi, hệ thống AI đang quá tải hoặc có lỗi xảy ra. Vui lòng thử lại sau giây lát!";

      if (error.code === "ECONNABORTED") {
        errorText = "AI phản hồi quá lâu. Vui lòng thử lại sau.";
      } else if (error.response?.status === 419) {
        errorText = "Phiên bảo mật đã hết hạn. Vui lòng gửi lại tin nhắn.";
      } else if (error.response?.status === 422) {
        errorText =
          error.response?.data?.message ||
          "Tin nhắn chưa hợp lệ. Vui lòng kiểm tra lại.";
      } else if (error.response?.status >= 500) {
        errorText =
          error.response?.data?.message ||
          "Dịch vụ AI đang bận. Vui lòng thử lại sau.";
      }

      setMessages((prev) => [
        ...prev,
        {
          id: Date.now() + 1,
          type: "bot",
          text: errorText,
          message_id: null,
          isError: true,
        },
      ]);
    } finally {
      setIsLoading(false);
    }
  };

  const handleKeyDown = (e) => {
    if (e.key === "Enter") {
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
      toast.success(
        rating === "up"
          ? "Cảm ơn bạn đã đánh giá tốt!"
          : "Cảm ơn bạn đã góp ý!",
      );
    } catch (error) {
      if (error.response?.status === 419) {
        toast.error("Phiên bảo mật đã hết hạn. Vui lòng thử lại.");
      } else if (error.code === "ECONNABORTED") {
        toast.error("Gửi đánh giá quá lâu. Vui lòng thử lại.");
      } else {
        toast.error("Lỗi khi gửi đánh giá.");
      }
    }
  };

  // Hàm render văn bản có ngắt dòng
  const formatText = (text) => {
    return String(text || "").split("\n").map((str, idx, lines) => (
      <span key={idx}>
        {renderInlineMarkdown(str, idx)}
        {idx < lines.length - 1 && <br />}
      </span>
    ));
  };

  return (
    <div className="fixed bottom-8 right-6 z-50">
      {isOpen && (
        <div className="chatbot-panel w-[350px] sm:w-[400px] h-[500px] rounded-2xl flex flex-col overflow-hidden mb-4 transition-all duration-300 transform origin-bottom-right">
          {/* Header */}
          <div className="chatbot-header p-4 flex justify-between items-center z-10">
            <div className="flex items-center gap-3">
              <div className="chatbot-avatar w-10 h-10 rounded-full flex justify-center items-center">
                <img
                  src={chatbotIcon}
                  alt="Bookify"
                  className="h-8 w-8 object-contain"
                />
              </div>
              <div>
                <h3 className="font-bold tracking-wide">Bookify AI</h3>
                <div className="flex items-center gap-1.5 mt-0.5">
                  <span className="chatbot-status-dot w-2 h-2 rounded-full animate-pulse"></span>
                  <p className="chatbot-status-text text-[11px] uppercase tracking-wider">
                    Trợ lý thông minh
                  </p>
                </div>
              </div>
            </div>
            <button
              onClick={() => setIsOpen(false)}
              className="chatbot-close p-2 rounded-full"
            >
              <FiX size={20} />
            </button>
          </div>

          {/* Khung Chat */}
          <div className="flex-grow p-4 bg-chat-surface overflow-y-auto custom-scrollbar flex flex-col gap-4">
            {messages.map((msg, index) => (
              <div
                key={msg.id}
                className={`flex flex-col ${msg.type === "user" ? "items-end" : "items-start"}`}
              >
                {/* Bong bóng tin nhắn */}
                <div
                  className={`max-w-[85%] p-3 text-sm shadow-sm ${
                    msg.type === "user"
                      ? "chatbot-user-bubble rounded-2xl rounded-tr-sm"
                      : `chatbot-bot-bubble rounded-2xl rounded-tl-sm ${msg.isError ? "border-red-300 text-red-600" : ""}`
                  }`}
                >
                  {formatText(msg.text)}
                </div>

                {/* Phần hiển thị Nguồn sách gợi ý (Nếu có) */}
                {msg.type === "bot" &&
                  msg.sources &&
                  msg.sources.length > 0 && (
                    <div className="max-w-[85%] mt-2 flex flex-wrap gap-2">
                      {msg.sources.map(renderSourceChip)}
                    </div>
                  )}

                {/* Nút Feedback (Chỉ hiện cho Bot và có message_id) */}
                {msg.type === "bot" && msg.message_id && (
                  <div className="flex gap-2 mt-2 ml-1">
                    <button
                      onClick={() =>
                        handleFeedback(index, msg.message_id, "up")
                      }
                      className={`chatbot-feedback-button p-1.5 rounded ${msg.feedback === "up" ? "is-up" : ""}`}
                      title="Câu trả lời tốt"
                    >
                      <FiThumbsUp
                        size={14}
                        className={msg.feedback === "up" ? "fill-current" : ""}
                      />
                    </button>
                    <button
                      onClick={() =>
                        handleFeedback(index, msg.message_id, "down")
                      }
                      className={`chatbot-feedback-button p-1.5 rounded ${msg.feedback === "down" ? "is-down" : ""}`}
                      title="Câu trả lời chưa tốt"
                    >
                      <FiThumbsDown
                        size={14}
                        className={
                          msg.feedback === "down" ? "fill-current" : ""
                        }
                      />
                    </button>
                  </div>
                )}
              </div>
            ))}

            {/* Hiệu ứng gõ phím khi đang load */}
            {isLoading && (
              <div className="flex items-start">
                <div className="chatbot-bot-bubble p-4 rounded-2xl rounded-tl-sm shadow-sm flex gap-1.5">
                  <span className="chatbot-typing-dot w-2 h-2 rounded-full animate-bounce"></span>
                  <span
                    className="chatbot-typing-dot w-2 h-2 rounded-full animate-bounce"
                    style={{ animationDelay: "0.2s" }}
                  ></span>
                  <span
                    className="chatbot-typing-dot w-2 h-2 rounded-full animate-bounce"
                    style={{ animationDelay: "0.4s" }}
                  ></span>
                </div>
              </div>
            )}

            <div ref={messagesEndRef} />
          </div>

          {/* Ô nhập liệu */}
          <div className="chatbot-input-bar p-3 flex gap-2 items-center">
            <input
              type="text"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={handleKeyDown}
              disabled={isLoading}
              placeholder={
                isLoading ? "AI đang suy nghĩ..." : "Nhập câu hỏi của bạn..."
              }
              className="chatbot-input flex-grow rounded-full px-4 py-2.5 text-sm outline-none disabled:bg-gray-100"
            />
            <button
              onClick={handleSend}
              disabled={!input.trim() || isLoading}
              className="app-primary-button p-3 rounded-full flex-shrink-0 disabled:bg-gray-300 disabled:cursor-not-allowed"
            >
              <FiSend size={18} />
            </button>
          </div>
        </div>
      )}

      {/* Nút Floating */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="chatbot-fab h-16 w-16 rounded-full flex items-center justify-center float-right relative"
        aria-label={
          isOpen ? "Đóng trợ lý Bookify" : "Mở trợ lý Bookify"
        }
      >
        {!isOpen && (
          <span className="absolute -top-1 -right-1 flex h-3 w-3">
            <span className="chatbot-notification-ping animate-ping absolute inline-flex h-full w-full rounded-full opacity-75"></span>
            <span className="chatbot-notification-dot relative inline-flex rounded-full h-3 w-3"></span>
          </span>
        )}
        {isOpen ? (
          <FiX size={28} />
        ) : (
          <img
            src={chatbotButtonIcon}
            alt=""
            className="h-10 w-10 object-contain"
            aria-hidden="true"
          />
        )}
      </button>
    </div>
  );
};

export default Chatbot;
