# Hướng Dẫn Sử Dụng & Triển Khai Tool Quản Lý Trả Góp

Chúc mừng! Bạn đã sở hữu một Web App Quản Lý Trả Góp hoàn chỉnh được viết bằng PHP thuần. Dưới đây là luồng hoạt động chuẩn và cách để bạn đưa nó vào thực tế (Host Azdigi).

## 1. Tính Năng Đã Hoàn Thiện
*   **Hệ thống Phân quyền (Roles):** Admin (Quản trị toàn quyền), Leader (Xem team), Sale (Chỉ xem và thao tác khách của mình).
*   **Quản Lý Khách Hàng ([customers.php](file:///Applications/MAMP/htdocs/tool-payment-debt/customers.php)):** Lưu trữ hồ sơ, link Drive, theo dõi công nợ tổng.
*   **Giao Diện Thêm Mới Nâng Cao ([add_customer.php](file:///Applications/MAMP/htdocs/tool-payment-debt/add_customer.php)):** Tự động gen form điền số đợt trả góp, tính toán ngày và số tiền linh hoạt.
*   **Dashboard Nhắc Nợ ([index.php](file:///Applications/MAMP/htdocs/tool-payment-debt/index.php)):** Hiển thị những đợt (installments) đang chờ thu tiền (Pending), tự động highlight màu đỏ nếu quá hạn.
*   **Thao tác 1 Click ([update_payment.php](file:///Applications/MAMP/htdocs/tool-payment-debt/update_payment.php)):** Bấm "Mark as Paid", tiền nợ (remaining) của khách tự động giảm xuống, trạng thái đổi thành PAID.
*   **Bảo mật & Theo dõi ([logs.php](file:///Applications/MAMP/htdocs/tool-payment-debt/logs.php)):** Mọi thao tác (đăng nhập, thêm khách, xoá, sửa tiền) đều được lưu lại để Admin kiểm soát.
*   **Cronjob Gửi Email ([cron_email.php](file:///Applications/MAMP/htdocs/tool-payment-debt/cron_email.php)):** Tự động nhóm các khoản nợ theo báo cáo gửi về Email của từng Leader.

---

## 2. Hướng Dẫn Test Trên MAMP (Localhost)
Bạn có thể bắt đầu sử dụng tool ngay bây giờ theo luồng sau:
1.  **Đăng nhập Admin:** Truy cập [http://localhost:8888/tool-payment-debt/login.php](http://localhost:8888/tool-payment-debt/login.php) bằng tài khoản `admin` / `admin123` (Nhớ thay số cổng 8888 bằng cổng MAMP bạn đang dùng).
2.  **Tạo Nhân Sự:** Vào **Quản Lý Nhân Sự** tạo thử 1 tài khoản Leader và 1 tài khoản Sale (nhớ chọn Leader quản lý cho Sale đó).
3.  **Lên Hồ Sơ:** Vào **Khách Hàng** -> **Thêm Khách Hàng**, điền thông tin và bấm *Thêm đợt trả góp* để chia các kỳ. Bấm Lưu.
4.  **Kiểm tra Dashboard:** Ra trang chủ (Dashboard) bạn sẽ thấy Lịch trả góp hiển thị rõ ràng. Bấm thử "Mark as Paid".

---

## 3. Hướng Dẫn Đưa Lên Host (Azdigi)

Sau khi test chán chê trên MAMP và thấy ổn, bạn hãy làm theo các bước này để Public cho toàn công ty dùng:

### Bước 1: Upload Source Code & Database
*   **Export:** Vào `http://localhost/phpmyadmin` (trên máy Mac của bạn), chọn database `tra_gop_official` -> **Export** (Tải file .sql về).
*   **Import:** Vào hosting Azdigi -> cPanel -> **MySQL Database**. Tạo 1 database mới và 1 user mới. Sau đó vào phpMyAdmin của Azdigi và **Import** file .sql vừa tải lên.
*   **Sửa cấu hình:** Mở file [db.php](file:///Applications/MAMP/htdocs/tool-payment-debt/db.php) trên máy lên, đổi `$host, $dbname, $username, $password` thành thông tin Database trên Azdigi.
*   **Upload file:** Nén thư mục `tool-payment-debt` thành `tool.zip`. Lên cPanel -> **File Manager** -> **public_html** -> Upload và giải nén nó.

### Bước 2: Bật Cảnh Báo Email Tự Động (Cronjobs)
Đây là "vũ khí bí mật" thay thế việc phải dò Google Sheet mỗi ngày.

1.  Mở file [cron_email.php](file:///Applications/MAMP/htdocs/tool-payment-debt/cron_email.php).
2.  (Khuyên dùng): Tải thư viện **PHPMailer** vào cùng thư mục nếu bạn muốn gửi qua SMTP của Azdigi cho chuyên nghiệp. Nhập email/pass của email công ty vào trong đoạn code (phần tôi đã comment bằng dấu `/* ... */`).
3.  Vào cPanel của Azdigi -> Cuộn xuống tìm tính năng **Cron Jobs**.
4.  Cài đặt thời gian là **Once Per Day (0 8 * * *)** - Tức là 8h sáng mỗi ngày nó sẽ chạy 1 lần.
5.  Trong ô **Command**, điền lệnh sau (nhớ thay tên miền của bạn):
    `curl -s http://chuyendoi.tenmiencuaban.com/cron_email.php >/dev/null 2>&1`
6.  Xong! Cứ 8h sáng, Tool sẽ check xem hôm nay có khách nào tới hạn đóng tiền không, gộp danh sách lại và bắn 1 email nhắc nhở cực đẹp cho Leader.

> [!TIP]
> **Bảo mật:** Đừng bao giờ chia sẻ tài khoản Admin cho ai. Nếu phát hiện sai số tiền, hãy vào mục **Lịch Sử (Logs)** để tra soát xem ai bấm nhầm "Mark as Paid".
