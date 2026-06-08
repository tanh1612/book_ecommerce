// src/pages/Auth/LoginPage.jsx
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FiEye, FiEyeOff } from 'react-icons/fi';
import { toast } from 'react-toastify';
import { useAuth } from '../../context/AuthContext';
import authApi from '../../services/authApi';
import { validateEmail } from '../../utils/validation';

const LoginPage = () => {
  const navigate = useNavigate();
  const { login } = useAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState({});

  const validateForm = () => {
    const newErrors = {};
    if (!email) newErrors.email = 'Email không được để trống';
    else if (!validateEmail(email)) newErrors.email = 'Email không hợp lệ';
    if (!password) newErrors.password = 'Mật khẩu không được để trống';
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!validateForm()) return;

    try {
      setIsSubmitting(true);
      const userRes = await authApi.login({ email, password, remember: remember ? 1 : 0 });
      login(userRes.data);
      toast.success("Đăng nhập thành công!");
      navigate('/');
    } catch {
      // axiosClient.js đã tự động toast lỗi từ server, không cần toast thêm ở đây để tránh lặp
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="bg-auth-surface min-h-screen py-10 flex justify-center px-4">
      <div className="bg-white rounded-lg shadow-sm w-full max-w-[450px] p-10 border border-gray-100 h-fit">
        <div className="flex mb-8 border-b border-gray-200">
          <div className="flex-1 text-center pb-3 text-primary font-medium border-b-2 border-primary">Đăng nhập</div>
          <Link to="/register" className="flex-1 text-center pb-3 text-gray-500 font-medium">Đăng ký</Link>
        </div>

        <form onSubmit={handleSubmit} className="flex flex-col gap-5">
          <div>
            <label className="block text-sm text-gray-700 mb-1.5">Email</label>
            <input
              type="email"
              placeholder="Nhập email"
              className={`w-full border rounded py-2.5 px-3 outline-none focus:border-primary ${errors.email ? 'border-red-500' : 'border-gray-300'}`}
              value={email}
              onChange={(e) => {
                setEmail(e.target.value);
                if (errors.email) setErrors({ ...errors, email: '' });
              }}
            />
            {errors.email && <p className="text-red-500 text-xs mt-1">{errors.email}</p>}
          </div>

          <div>
            <label className="block text-sm text-gray-700 mb-1.5">Mật khẩu</label>
            <div className="relative">
              <input
                type={showPassword ? "text" : "password"}
                placeholder="Nhập mật khẩu"
                className={`w-full border rounded py-2.5 px-3 outline-none focus:border-primary ${errors.password ? 'border-red-500' : 'border-gray-300'}`}
                value={password}
                onChange={(e) => {
                  setPassword(e.target.value);
                  if (errors.password) setErrors({ ...errors, password: '' });
                }}
              />
              <button
                type="button"
                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-primary"
                onClick={() => setShowPassword(!showPassword)}
              >
                {showPassword ? <FiEyeOff size={20} /> : <FiEye size={20} />}
              </button>
            </div>
            {errors.password && <p className="text-red-500 text-xs mt-1">{errors.password}</p>}
          </div>

          <div className="flex justify-between items-center text-sm">
            <label className="flex items-center gap-2 cursor-pointer text-gray-600">
              <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} className="accent-primary" />
              Ghi nhớ đăng nhập
            </label>
            <Link to="/forgot-password" className="text-primary hover:underline">Quên mật khẩu?</Link>
          </div>

          <button
            type="submit"
            disabled={isSubmitting}
            className={`w-full py-3 rounded font-bold text-white transition-all ${isSubmitting ? 'bg-gray-400' : 'bg-primary hover:bg-green-800'}`}
          >
            {isSubmitting ? "ĐANG XỬ LÝ..." : "ĐĂNG NHẬP"}
          </button>
        </form>
      </div>
    </div>
  );
};

export default LoginPage;
