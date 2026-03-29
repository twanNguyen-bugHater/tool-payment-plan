<?php
// Tên file: email_config.php
// =====================================================================
// CẤU HÌNH EMAIL - CHỈ CẦN SỬA FILE NÀY KHI DEPLOY LÊN AZDIGI
// =====================================================================

// Đổi thành true khi đã deploy lên Azdigi và muốn bật gửi mail
define('EMAIL_ENABLED', false);

// Phương thức gửi email: 'phpmailer' hoặc 'mail' 
// 'phpmailer' = Dùng SMTP (khuyến nghị, không bị spam)
// 'mail'      = Dùng hàm mail() mặc định của host (có thể bị spam)
define('EMAIL_METHOD', 'phpmailer');

// ===================== SMTP SETTINGS (cho PHPMailer) =====================
// Khi deploy lên Azdigi, vào cPanel → Email Accounts → Tạo email
// rồi điền thông tin vào đây
define('SMTP_HOST', 'mail.yourdomain.com'); // Server mail Azdigi (vd: mail.sydneytopdental.com)
define('SMTP_PORT', 465); // Port SMTP SSL
define('SMTP_SECURE', 'ssl'); // 'ssl' hoặc 'tls'
define('SMTP_USERNAME', 'noreply@yourdomain.com'); // Email đã tạo trên Azdigi
define('SMTP_PASSWORD', 'your_email_password'); // Mật khẩu email
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');
define('SMTP_FROM_NAME', 'Hệ Thống Quản Lý Trả Góp');

// ===================== URL CỦA HỆ THỐNG =====================
// Đổi thành domain thật khi deploy
define('SYSTEM_URL', 'http://localhost:8888/tool-payment-debt');

// ===================== GHI CHÚ =====================
// Trên Azdigi, vào cPanel → Cron Jobs → Thêm Cron:
// Thời gian: Chạy mỗi ngày lúc 8h sáng → 0 8 * * *
// Lệnh: /usr/local/bin/php /home/yourusername/public_html/tool-payment-debt/cron_email.php
// Hoặc: curl -s https://yourdomain.com/tool-payment-debt/cron_email.php > /dev/null
?>