<?php
// Tên file: import_csv.php
require 'db.php';

echo "<h2>Script Tự động Import Dữ liệu Khách Hàng (Từ CSV)</h2>";

try {
    $pdo->beginTransaction();

    // 1. Xoá toàn bộ dữ liệu khách hàng và trả góp cũ (Khách ảo)
    $pdo->exec("DELETE FROM installments");
    $pdo->exec("DELETE FROM customers");
    
    // Đặt lại AUTO_INCREMENT
    $pdo->exec("ALTER TABLE installments AUTO_INCREMENT = 1");
    $pdo->exec("ALTER TABLE customers AUTO_INCREMENT = 1");
    
    echo "<p class='text-success'>✅ Đã xoá thành công toàn bộ dữ liệu Khách hàng & Đợt trả góp cũ (ảo).</p>";

    // Lấy mapping ID của User (Sale/Leader) để gán
    $stmt = $pdo->query("SELECT id, username FROM users WHERE role IN ('sale', 'leader')");
    $usersDB = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userMap = [];
    foreach ($usersDB as $u) {
        $userMap[strtolower(trim($u['username']))] = $u['id'];
    }

    // Đọc tất cả các file CSV trong thư mục Export_Data
    $dir = 'Export_Data';
    $files = glob($dir . '/*.csv');
    
    if (empty($files)) {
        throw new Exception("Không tìm thấy file CSV nào trong thư mục '$dir'.");
    }

    $allCustomers = []; // Mảng dùng để lọc TRÙNG LẶP theo Tên

    foreach ($files as $file) {
        echo "<hr><h4>Đang xử lý file: " . htmlspecialchars(basename($file)) . "</h4>";
        
        $handle = fopen($file, "r");
        if ($handle) {
            $header = fgetcsv($handle); // Đọc dòng header (Name, Sale, Currency, Total bill)
            
            while (($data = fgetcsv($handle)) !== false) {
                // Bỏ qua dòng trống
                if (empty($data[0])) continue;
                
                $raw_name = trim($data[0]);
                $raw_sale = trim($data[1] ?? '');
                $raw_currency = trim($data[2] ?? '');
                $raw_bill = trim($data[3] ?? '');
                $raw_initial = trim($data[4] ?? '');

                // Lọc trùng lặp TÊN (Lấy tên gốc ko phân biệt chữ hoa thường)
                $nameKey = strtolower($raw_name);
                
                // Nếu khách đã tồn tại trong mảng, ta kiểm tra xem bản ghi mới có "tốt" hơn ko (vd có bill khác 0)
                if (isset($allCustomers[$nameKey])) {
                    if (empty($allCustomers[$nameKey]['raw_bill']) && !empty($raw_bill)) {
                        // Ghi đè bằng dòng có nhiều data hơn
                    } else {
                        continue; // Bỏ qua vì đã có record tốt
                    }
                }
                
                // --- Xử lý SALE ---
                $sale_id = null;
                $searchSale = strtolower($raw_sale);
                // Clean up string like "Selena (James)" or "Selena + Mike" to just "Selena"
                $searchSale = preg_replace('/[^a-z0-9]/', '', explode(' ', explode('+', explode('(', $searchSale)[0])[0])[0]);
                
                foreach ($userMap as $uName => $uId) {
                    if ($searchSale === $uName || strpos($uName, $searchSale) !== false || strpos($searchSale, $uName) !== false) {
                        $sale_id = $uId;
                        break;
                    }
                }

                // --- Xử lý CURRENCY & PAYMENT TYPE ---
                $curr = 'AUD'; // default
                $payment_type = 'monthly';
                
                $upCurr = strtoupper($raw_currency);
                if (strpos($upCurr, 'USD') !== false) $curr = 'USD';
                elseif (strpos($upCurr, 'NZD') !== false) $curr = 'NZD';
                elseif (strpos($upCurr, 'VND') !== false) $curr = 'VND';
                
                if (strpos($upCurr, 'TRẢ VÀO TRIP') !== false || strpos($upCurr, 'TRIP 2') !== false || strpos($upCurr, 'TRIP THỨ 2') !== false) {
                    $payment_type = 'trip2';
                }

                // --- Xử lý TOTAL BILL ---
                // Xoá mọi dấu phẩy, chữ, dấu chấm, khoảng trống
                $clean_bill = preg_replace('/[^0-9]/', '', $raw_bill);
                $total_bill = empty($clean_bill) ? 0 : floatval($clean_bill);

                $clean_initial = preg_replace('/[^0-9]/', '', $raw_initial);
                $initial_debt = empty($clean_initial) ? 0 : floatval($clean_initial);

                // Lưu vào mảng để chờ insert (đã lọc trùng)
                $allCustomers[$nameKey] = [
                    'name' => $raw_name,
                    'sale_id' => $sale_id,
                    'currency' => $curr,
                    'payment_type' => $payment_type,
                    'total_bill' => $total_bill,
                    'initial_debt' => $initial_debt,
                    'remaining' => $initial_debt, // Khi khởi tạo chưa nợ đợt nào thì remaining = initial_debt
                    'debt_status' => 'in_progress',
                    'raw_bill' => $raw_bill
                ];
            }
            fclose($handle);
            echo "<p>Đã tìm thấy/cập nhật " . count($allCustomers) . " khách hàng duy nhất cho đến nay.</p>";
        }
    }

    echo "<hr><h3>Đang Lưu vào Database...</h3>";
    $ins = $pdo->prepare("INSERT INTO customers (name, gender, sale_id, currency, payment_type, total_bill, initial_debt, remaining, debt_status, created_at) VALUES (?, 'Khác', ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $countInsert = 0;
    foreach ($allCustomers as $c) {
        $ins->execute([
            $c['name'],
            $c['sale_id'],
            $c['currency'],
            $c['payment_type'],
            $c['total_bill'],
            $c['initial_debt'],
            $c['remaining'],
            $c['debt_status']
        ]);
        $countInsert++;
    }

    $pdo->commit();
    echo "<h3 style='color:green;'>🎉 Hoàn tất! Đã import tổng cộng <b>$countInsert</b> khách hàng thực tế vào hệ thống.</h3>";
    echo "<p><a href='index.php'>Quay về Dashboard</a></p>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("<h3 style='color:red;'>❌ Lỗi Import: " . $e->getMessage() . "</h3>");
}
?>
