<?php
// Tên file: cron_email.php
// MÔ TẢ: Kịch bản dùng để hệ thống tự động quét và gửi báo cáo những khoản nợ/trả góp "Đến hạn" (hoặc quá hạn) cho các Leader.
// HƯỚNG DẪN AZDIGI: Vào cPanel -> Cronjobs -> Thêm Cron chạy mỗi ngày (0 8 * * *)
// Lệnh chạy: curl -s http://domain-cua-ban.com/cron_email.php > /dev/null

require 'db.php';

// Lưu ý: Để gửi email chuyên nghiệp và tránh bị vào Spam, bạn cần tải thư viện PHPMailer.
// Đặt folder PHPMailer ngang hàng với file này (hoặc dùng Composer)
/* Bỏ comment đoạn này khi bạn đã copy folder PHPMailer vào
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
*/

try {
    // 1. Quét các khoản phạt đến hạn (due_date là trước hoặc bằng ngày hôm nay) và đang PENDING
    $today = date('Y-m-d');
    $sql = "SELECT i.payment_number, i.amount, i.due_date, 
                   c.name as customer_name, c.currency,
                   u.username as sale_name, leader.email as leader_email, leader.username as leader_name
            FROM installments i
            JOIN customers c ON i.customer_id = c.id
            JOIN users u ON c.sale_id = u.id
            JOIN users leader ON u.leader_id = leader.id
            WHERE i.status = 'pending' AND i.due_date <= ?
            ORDER BY leader.id ASC, i.due_date ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);
    $dues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($dues) === 0) {
        die("Hôm nay không có khoản trả góp nào cần thông báo.");
    }

    // Nhóm các khoản nợ theo từng Email của Leader
    $emails_to_send = [];
    foreach ($dues as $row) {
        $leader_email = $row['leader_email'];
        if (!$leader_email) continue;
        
        $emails_to_send[$leader_email]['leader_name'] = $row['leader_name'];
        $emails_to_send[$leader_email]['debts'][] = $row;
    }

    // 2. Gửi Email cho từng Leader
    foreach ($emails_to_send as $email => $data) {
        
        // --- Xây dựng nội dung Email HTML đẹp mắt ---
        $htmlContent = "<div style='font-family: Arial, sans-serif; color: #333;'>";
        $htmlContent .= "<h2 style='color: #d9534f;'>CẢNH BÁO: CÓ KHÁCH HÀNG TỚI HẠN THANH TOÁN</h2>";
        $htmlContent .= "<p>Chào Leader <strong>{$data['leader_name']}</strong>,</p>";
        $htmlContent .= "<p>Hệ thống ghi nhận có các khách hàng (thuộc team bạn) đã đến hạn hoặc quá hạn đóng tiền trả góp. Vui lòng nhắc nhở Sale liên hệ khách hàng ngay:</p>";
        
        $htmlContent .= "<table border='1' cellpadding='10' cellspacing='0' style='width: 100%; border-collapse: collapse; margin-top: 15px;'>";
        $htmlContent .= "<tr style='background-color: #f8f9fa;'>
                            <th>Khách Hàng</th>
                            <th>Sale Phụ Trách</th>
                            <th>Đợt Số</th>
                            <th>Số Tiền</th>
                            <th>Tiền Tệ</th>
                            <th>Hạn Đóng</th>
                         </tr>";
                         
        foreach ($data['debts'] as $debt) {
            $is_late = (strtotime($debt['due_date']) < strtotime($today)) ? 'color: red; font-weight: bold;' : '';
            $dateFmt = date('d/m/Y', strtotime($debt['due_date']));
            $amountFmt = number_format($debt['amount'], 2);
            
            $htmlContent .= "<tr>
                                <td>{$debt['customer_name']}</td>
                                <td>{$debt['sale_name']}</td>
                                <td align='center'>{$debt['payment_number']}</td>
                                <td align='right' style='font-weight:bold;'>{$amountFmt}</td>
                                <td>{$debt['currency']}</td>
                                <td style='{$is_late}'>{$dateFmt}</td>
                             </tr>";
        }
        $htmlContent .= "</table>";
        $htmlContent .= "<p>Truy cập vào <a href='http://domain-cua-ban.com'>Dashboard Hệ Thống</a> để xem chi tiết.</p>";
        $htmlContent .= "</div>";

        // ----------- Gửi Email (Dùng mail() mặc định hoặc PHPMailer) --------
        
        // Cách 1: Dùng hàm mail() mặc định của host (Có thể bị vô spam)
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: No-Reply <noreply@domain-cua-ban.com>" . "\r\n";
        
        mail($email, "Thông Báo Khách Hàng Tới Hạn Trả Góp - " . date('d/m/Y'), $htmlContent, $headers);
        
        /* 
        // Cách 2: Dùng PHPMailer (Khuyến nghị chuẩn nhất)
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'mail.domain-cua-ban.com'; // SMTP AZDIGI
            $mail->SMTPAuth   = true;
            $mail->Username   = 'admin@domain-cua-ban.com';
            $mail->Password   = 'mat_khau_email_cua_ban';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            $mail->setFrom('admin@domain-cua-ban.com', 'Hệ Thống Trả Góp');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "Thông Báo Khách Hàng Tới Hạn Trả Góp - " . date('d/m/Y');
            $mail->Body    = $htmlContent;

            $mail->send();
        } catch (Exception $e) {
            echo "Lỗi gửi mail: {$mail->ErrorInfo}";
        }
        */
        
        echo "Đã gửi báo cáo cho Leader: $email <br>";
    }
    
    echo "Done! Chạy cron thành công vào " . date('Y-m-d H:i:s');

} catch (PDOException $e) {
    die("Lỗi CSDL: " . $e->getMessage());
}
?>
