<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu Quản trị Bookify</title>
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 24px 16px;">
    <p style="margin: 0 0 16px; font-size: 1.125rem; font-weight: 600; color: #111;">
        Bookify
    </p>

    <p>Xin chào{{ filled($recipientName) ? ' '.$recipientName : '' }},</p>

    <p>
        Bạn nhận email này vì chúng tôi vừa nhận yêu cầu đặt lại mật khẩu cho tài khoản
        <strong>Quản trị Bookify</strong> của bạn.
    </p>

    <p style="margin: 24px 0;">
        <a
            href="{{ $url }}"
            style="display: inline-block; padding: 12px 24px; background-color: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600;"
        >
            Đặt lại mật khẩu
        </a>
    </p>

    <p>
        Liên kết có hiệu lực trong <strong>{{ $expireMinutes }} phút</strong>.
        Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này.
    </p>

    <p style="margin-top: 24px; font-size: 0.875rem; color: #666;">
        Nếu nút không hoạt động, hãy sao chép và dán liên kết sau vào trình duyệt:<br>
        <a href="{{ $url }}" style="color: #4f46e5; word-break: break-all;">{{ $url }}</a>
    </p>

    <p style="margin-top: 32px; color: #666;">
        Trân trọng,<br>
        Bookify
    </p>
</body>
</html>
