import { createContext, useContext, useState, useEffect } from 'react';
import { toast } from 'react-toastify';
import authApi from '../services/authApi';

const AuthContext = createContext();

const normalizeUser = (payload) => {
  const account = payload?.data || payload;
  if (!account) return null;

  const profile = account.profile || {};

  return {
    ...account,
    profile,
    firstName: profile.first_name || account.first_name || '',
    lastName: profile.last_name || account.last_name || '',
  };
};

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const checkLoginStatus = async () => {
      try {
        setError(null);
        const res = await authApi.getProfile();
        setUser(normalizeUser(res.data));
      } catch (err) {
        // Nếu không phải 401 (unauthorized), show error
        if (err.response?.status !== 401) {
          setError(err.message);
        }
        setUser(null);
      } finally {
        setLoading(false);
      }
    };
    checkLoginStatus();
  }, []);

  useEffect(() => {
    const handleUnauthorized = () => {
      setUser(null);
    };

    window.addEventListener('auth:unauthorized', handleUnauthorized);
    return () => window.removeEventListener('auth:unauthorized', handleUnauthorized);
  }, []);

  const login = (userData) => {
    setUser(normalizeUser(userData));
    setError(null);
  };

  const logout = async () => {
    try {
      await authApi.logout();
    } catch {
      toast.error("Lỗi đăng xuất");
    } finally {
      setUser(null);
      setError(null);
    }
  };

  return (
    <AuthContext.Provider value={{ user, login, logout, loading, error }}>
      {!loading && children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
