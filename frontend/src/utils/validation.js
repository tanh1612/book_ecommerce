// Validation utils
export const validateEmail = (email) => {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailRegex.test(email);
};

export const validatePhoneNumber = (phone) => {
  // Validate Vietnamese phone number (10 digits, starts with 0)
  const phoneRegex = /^0\d{9}$/;
  return phoneRegex.test(phone.replace(/\s/g, ''));
};

export const validatePassword = (password) => {
  // At least 8 characters
  return password.length >= 8;
};

export const sanitizeInput = (input) => {
  if (typeof input !== 'string') return '';
  return input
    .trim()
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;')
    .replace(/\//g, '&#x2F;');
};

export const getValidationError = (field, value) => {
  switch (field) {
    case 'email':
      if (!value) return 'Email không được để trống';
      if (!validateEmail(value)) return 'Email không hợp lệ';
      return null;
    case 'phone':
      if (!value) return 'Số điện thoại không được để trống';
      if (!validatePhoneNumber(value)) return 'Số điện thoại phải là 10 chữ số (0xxxxxxxxx)';
      return null;
    case 'password':
      if (!value) return 'Mật khẩu không được để trống';
      if (!validatePassword(value)) return 'Mật khẩu ít nhất 8 ký tự';
      return null;
    case 'fullname':
      if (!value) return 'Họ tên không được để trống';
      if (value.length < 2) return 'Họ tên ít nhất 2 ký tự';
      return null;
    default:
      return null;
  }
};
