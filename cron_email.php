<?php
// Tên file: cron_email.php
// ================================================================
// CRON JOB - TỰ ĐỘNG GỬI EMAIL NHẮC NỢ CHO LEADER
// Quét tất cả đợt trả góp đến hạn/quá hạn, nhóm theo Leader và gửi email
// ================================================================
// CÁCH CHẠY:
// - Localhost (test): http://localhost:8888/tool-payment-debt/cron_email.php
// - Azdigi Cron: 0 8 * * * /usr/local/bin/php /home/user/public_html/tool-payment-debt/cron_email.php
// ================================================================

require 'db.php';
require 'email_config.php';

// Load PHPMailer (nếu dùng)
if (EMAIL_METHOD === 'phpmailer') {
    // Kiểm tra PHPMailer đã cài chưa
    $phpmailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';
    if (!file_exists($phpmailerPath)) {
        // Thử Composer autoload
        $composerPath = __DIR__ . '/vendor/autoload.php';
        if (file_exists($composerPath)) {
            require $composerPath;
        } else {
            die("⚠️ PHPMailer chưa được cài đặt!\n"
              . "Cách 1: Chạy 'composer require phpmailer/phpmailer' trong thư mục dự án\n"
              . "Cách 2: Tải PHPMailer từ GitHub và đặt folder PHPMailer/ ngang hàng với file này\n");
        }
    } else {
        require __DIR__ . '/PHPMailer/src/Exception.php';
        require __DIR__ . '/PHPMailer/src/PHPMailer.php';
        require __DIR__ . '/PHPMailer/src/SMTP.php';
    }
}

try {
    $today = date('Y-m-d');
    
    // Quét các đợt trả góp PENDING đã đến hạn hoặc quá hạn
    // Chỉ gửi cho Leader có email
    $sql = "SELECT i.payment_number, i.amount, i.due_date, 
                   c.name as customer_name, c.currency, c.remaining, c.debt_status,
                   u.username as sale_name, 
                   leader.email as leader_email, leader.username as leader_name
            FROM installments i
            JOIN customers c ON i.customer_id = c.id
            JOIN users u ON c.sale_id = u.id
            JOIN users leader ON u.leader_id = leader.id
            WHERE i.status = 'pending' AND i.due_date <= ?
            AND leader.email IS NOT NULL AND leader.email != ''
            ORDER BY leader.id ASC, i.due_date ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);
    $dues = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($dues) === 0) {
        echo "✅ [" . date('Y-m-d H:i:s') . "] Hôm nay không có khoản trả góp nào cần thông báo.\n";
        exit;
    }

    // Nhóm khoản nợ theo email Leader
    $emails_to_send = [];
    foreach ($dues as $row) {
        $leader_email = $row['leader_email'];
        if (!$leader_email) continue;
        
        $emails_to_send[$leader_email]['leader_name'] = $row['leader_name'];
        $emails_to_send[$leader_email]['debts'][] = $row;
    }

    // Gửi Email cho từng Leader
    foreach ($emails_to_send as $email => $data) {
        
        $debtCount = count($data['debts']);
        $totalAmount = 0;
        foreach ($data['debts'] as $d) $totalAmount += $d['amount'];

        // ===== XÂY DỰNG NỘI DUNG EMAIL HTML =====
        $htmlContent = "
        <div style='font-family: Arial, sans-serif; max-width: 700px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #dc3545, #c82333); padding: 20px 30px; border-radius: 8px 8px 0 0;'>
                <h2 style='color: #fff; margin: 0;'>⚠️ CẢNH BÁO: CÓ KHÁCH HÀNG TỚI HẠN THANH TOÁN</h2>
            </div>
            <div style='background: #fff; padding: 25px 30px; border: 1px solid #e0e0e0;'>
                <p style='font-size: 16px;'>Chào Leader <strong>{$data['leader_name']}</strong>,</p>
                <p>Hệ thống ghi nhận có <strong style='color: #dc3545;'>{$debtCount} khoản</strong> (tổng: <strong>" . number_format($totalAmount, 0, ',', '.') . "</strong>) thuộc team bạn đã <strong>đến hạn hoặc quá hạn</strong> đóng tiền trả góp.</p>
                <p>Vui lòng nhắc nhở nhân viên Sale liên hệ khách hàng ngay:</p>
                
                <table border='0' cellpadding='10' cellspacing='0' style='width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px;'>
                    <tr style='background-color: #343a40; color: #fff;'>
                        <th style='text-align:left; padding:12px;'>Khách Hàng</th>
                        <th style='text-align:left; padding:12px;'>Sale</th>
                        <th style='text-align:center; padding:12px;'>Đợt</th>
                        <th style='text-align:right; padding:12px;'>Số Tiền</th>
                        <th style='text-align:center; padding:12px;'>Hạn Đóng</th>
                        <th style='text-align:center; padding:12px;'>Tình Trạng</th>
                    </tr>";

        $rowNum = 0;
        foreach ($data['debts'] as $debt) {
            $rowNum++;
            $is_late = (strtotime($debt['due_date']) < strtotime($today));
            $bgColor = ($rowNum % 2 == 0) ? '#f8f9fa' : '#fff';
            $dateStyle = $is_late ? 'color: #dc3545; font-weight: bold;' : '';
            $statusText = $is_late ? '🔴 QUÁ HẠN' : '🟡 Đến hạn';
            $dateFmt = date('d/m/Y', strtotime($debt['due_date']));
            $amountFmt = number_format($debt['amount'], 0, ',', '.');

            $htmlContent .= "
                    <tr style='background-color: {$bgColor};'>
                        <td style='padding:10px; border-bottom:1px solid #e0e0e0;'>{$debt['customer_name']}</td>
                        <td style='padding:10px; border-bottom:1px solid #e0e0e0;'>{$debt['sale_name']}</td>
                        <td style='padding:10px; border-bottom:1px solid #e0e0e0; text-align:center;'>{$debt['payment_number']}</td>
                        <td style='padding:10px; border-bottom:1px solid #e0e0e0; text-align:right; font-weight:bold;'>{$amountFmt} {$debt['currency']}</td>
                        <td style='padding:10px; border-bottom:1px solid #e0e0e0; text-align:center; {$dateStyle}'>{$dateFmt}</td>
                        <td style='padding:10px; border-bottom:1px solid #e0e0e0; text-align:center;'>{$statusText}</td>
                    </tr>";
        }

        $htmlContent .= "
                </table>
                
                <div style='margin-top: 25px; padding: 15px; background: #f0f7ff; border-radius: 6px; border-left: 4px solid #0d6efd;'>
                    <p style='margin: 0;'>📌 Truy cập <a href='" . SYSTEM_URL . "' style='color: #0d6efd; font-weight: bold;'>Dashboard Hệ Thống</a> để xem chi tiết và cập nhật thanh toán.</p>
                </div>
            </div>
            <div style='background: #f8f9fa; padding: 15px 30px; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; border-top: 0; font-size: 12px; color: #6c757d;'>
                Email tự động từ Hệ Thống Quản Lý Trả Góp · " . date('d/m/Y H:i') . "
            </div>
        </div>";

        $subject = "⚠️ [{$debtCount} khoản] Khách Hàng Tới Hạn Trả Góp - " . date('d/m/Y');

        // ===== GỬI EMAIL =====
        if (!EMAIL_ENABLED) {
            // Chế độ TEST: chỉ hiển thị preview, không gửi thật
            echo "<div style='border:2px dashed #ffc107; padding:15px; margin:10px 0; border-radius:8px;'>";
            echo "<h4 style='color:#856404;'>📧 [CHẾ ĐỘ TEST] Email sẽ gửi cho: <strong>$email</strong></h4>";
            echo "<p><strong>Tiêu đề:</strong> $subject</p>";
            echo "<hr>$htmlContent";
            echo "</div>";
        } else {
            // GỬI THẬT
            if (EMAIL_METHOD === 'phpmailer') {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USERNAME;
                    $mail->Password   = SMTP_PASSWORD;
                    $mail->SMTPSecure = SMTP_SECURE;
                    $mail->Port       = SMTP_PORT;
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $htmlContent;

                    $mail->send();
                    echo "✅ Đã gửi email cho Leader: $email ({$data['leader_name']})\n";
                } catch (Exception $e) {
                    echo "❌ Lỗi gửi mail cho $email: {$mail->ErrorInfo}\n";
                }
            } else {
                // Dùng hàm mail() mặc định
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
                
                if (mail($email, $subject, $htmlContent, $headers)) {
                    echo "✅ Đã gửi email (mail()) cho Leader: $email\n";
                } else {
                    echo "❌ Lỗi gửi mail() cho: $email\n";
                }
            }
        }
    }
    
    echo "\n✅ Done! Cron chạy thành công vào " . date('Y-m-d H:i:s') . "\n";

} catch (PDOException $e) {
    echo "❌ Lỗi CSDL: " . $e->getMessage() . "\n";
}
?>
