// src/pages/Auth/LoginPage.jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiEye, FiEyeOff } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { useAuth } from '../../context/AuthContext';
import authApi from '../../services/authApi';

const LoginPage = () => {
  const navigate = useNavigate();
  const { login } = useAuth();
  
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email || !password) return toast.warning("Vui lòng nhập đầy đủ email và mật khẩu!");

    try {
      setIsSubmitting(true);
      // 1. Gọi API Login (Sanctum tự xử lý Cookie)
      await authApi.login({ email, password, remember: remember ? 1 : 0 });
      
      // 2. Lấy thông tin user
      const userRes = await authApi.getProfile();
      login(userRes.data);
      
      toast.success("Đăng nhập thành công!");
      navigate('/');
    } catch (err) {
      toast.error(err.response?.data?.message || "Tài khoản hoặc mật khẩu không chính xác!");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="bg-[#f0f0f0] min-h-screen py-10 flex justify-center px-4">
      <div className="bg-white rounded-lg shadow-sm w-full max-w-[450px] p-10 border border-gray-100 h-fit">
        <div className="flex mb-8 border-b border-gray-200">
          <div className="flex-1 text-center pb-3 text-[#157a2c] font-medium border-b-2 border-[#157a2c]">Đăng nhập</div>
          <Link to="/register" className="flex-1 text-center pb-3 text-gray-500 font-medium">Đăng ký</Link>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-5">
          <div>
            <label className="block text-sm text-gray-700 mb-1.5">Email</label>
            <input 
              type="email" placeholder="Nhập email"
              className="w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-[#157a2c]"
              value={email} onChange={(e) => setEmail(e.target.value)}
            />
          </div>

          <div>
            <label className="block text-sm text-gray-700 mb-1.5">Mật khẩu</label>
            <div className="relative">
              <input 
                type={showPassword ? "text" : "password"} placeholder="Nhập mật khẩu"
                className="w-full border border-gray-300 rounded py-2.5 px-3 outline-none focus:border-[#157a2c]"
                value={password} onChange={(e) => setPassword(e.target.value)}
              />
              <button 
                type="button" className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#157a2c]"
                onClick={() => setShowPassword(!showPassword)}
              >
                {showPassword ? <FiEyeOff size={20} /> : <FiEye size={20} />}
              </button>
            </div>
          </div>

          <div className="flex justify-between items-center text-sm">
            <label className="flex items-center gap-2 cursor-pointer text-gray-600">
              <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} className="accent-[#157a2c]" />
              Ghi nhớ đăng nhập
            </label>
            <Link to="/forgot-password" size="sm" className="text-[#157a2c] hover:underline">Quên mật khẩu?</Link>
          </div>

          <button 
            type="submit" disabled={isSubmitting}
            className={`w-full py-3 rounded font-bold text-white transition-all ${isSubmitting ? 'bg-gray-400' : 'bg-[#157a2c] hover:bg-green-800'}`}
          >
            {isSubmitting ? "ĐANG XỬ LÝ..." : "ĐĂNG NHẬP"}
          </button>
        </form>
      </div>
    </div>
  );
};

export default LoginPage;