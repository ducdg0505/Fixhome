<?php
final class ServiceCatalog
{
    public static function categories(): array
    {
        return [
            'Điện lạnh' => ['code'=>'refrigeration','icon'=>'❄️','services'=>[
                ['code'=>'ac_clean_wall','name'=>'Vệ sinh máy lạnh treo tường','unit'=>'máy','min'=>150000,'max'=>250000,'description'=>'Vệ sinh dàn lạnh, dàn nóng, kiểm tra nước xả và vận hành cơ bản.','issues'=>['Máy lạnh yếu','Có mùi','Chảy nước','Bám bụi']],
                ['code'=>'ac_repair_general','name'=>'Sửa máy lạnh không lạnh / kém lạnh','unit'=>'lần','min'=>250000,'max'=>850000,'description'=>'Kiểm tra block, quạt, gas, board, cảm biến và báo giá trước khi sửa.','issues'=>['Không lạnh','Kém lạnh','Tự tắt','Báo lỗi']],
                ['code'=>'ac_gas_check','name'=>'Kiểm tra và nạp gas máy lạnh','unit'=>'máy','min'=>200000,'max'=>650000,'description'=>'Kiểm tra rò rỉ, áp suất gas và nạp gas theo tình trạng thực tế.','issues'=>['Thiếu gas','Dàn lạnh đóng tuyết','Lạnh yếu']],
                ['code'=>'ac_install','name'=>'Lắp đặt máy lạnh','unit'=>'máy','min'=>350000,'max'=>1200000,'description'=>'Lắp đặt máy lạnh, đi ống đồng, kiểm tra điện và vận hành thử.','issues'=>['Lắp mới','Dời vị trí','Thay máy']],
                ['code'=>'fridge_repair','name'=>'Sửa tủ lạnh','unit'=>'lần','min'=>250000,'max'=>1200000,'description'=>'Xử lý tủ không lạnh, đóng tuyết, kêu to, rò nước, hỏng ron hoặc board.','issues'=>['Không lạnh','Đóng tuyết','Kêu to','Rò nước']],
                ['code'=>'freezer_repair','name'=>'Sửa tủ đông / tủ mát','unit'=>'lần','min'=>300000,'max'=>1500000,'description'=>'Kiểm tra hệ thống làm lạnh, block, gas, thermostat, quạt và board.','issues'=>['Không đông','Tủ mát yếu','Hao điện','Kêu lớn']],
            ]],
            'Điện gia dụng' => ['code'=>'home_appliances','icon'=>'🔌','services'=>[
                ['code'=>'washer_repair','name'=>'Sửa máy giặt','unit'=>'lần','min'=>250000,'max'=>1000000,'description'=>'Kiểm tra lỗi không vắt, không xả, rung mạnh, báo lỗi hoặc không cấp nước.','issues'=>['Không vắt','Không xả','Rung mạnh','Báo lỗi']],
                ['code'=>'washer_clean','name'=>'Vệ sinh máy giặt','unit'=>'máy','min'=>250000,'max'=>450000,'description'=>'Vệ sinh lồng giặt, khử mùi, loại bỏ cặn bẩn và kiểm tra cơ bản.','issues'=>['Có mùi','Cặn bẩn','Giặt không sạch']],
                ['code'=>'water_heater_repair','name'=>'Sửa máy nước nóng','unit'=>'lần','min'=>250000,'max'=>900000,'description'=>'Kiểm tra nguồn điện, chống giật, thanh đốt, cảm biến nhiệt và rò nước.','issues'=>['Không nóng','Rò điện','Rò nước','Tự ngắt']],
                ['code'=>'microwave_repair','name'=>'Sửa lò vi sóng','unit'=>'lần','min'=>200000,'max'=>750000,'description'=>'Kiểm tra nguồn, mâm xoay, đèn, board điều khiển và hệ thống phát nhiệt.','issues'=>['Không nóng','Không quay','Mất nguồn','Tia lửa']],
                ['code'=>'rice_cooker_repair','name'=>'Sửa nồi cơm điện / nồi áp suất','unit'=>'lần','min'=>120000,'max'=>450000,'description'=>'Kiểm tra nguồn, mâm nhiệt, cảm biến, nắp, gioăng và bo điều khiển.','issues'=>['Không vào điện','Cơm sống','Không giữ ấm','Báo lỗi']],
                ['code'=>'fan_repair','name'=>'Sửa quạt điện / quạt trần','unit'=>'lần','min'=>100000,'max'=>450000,'description'=>'Kiểm tra tụ, motor, công tắc, điều khiển, bạc đạn và lắp đặt cơ bản.','issues'=>['Không quay','Quay yếu','Kêu lớn','Hỏng remote']],
            ]],
            'Điện - nước' => ['code'=>'electrical_plumbing','icon'=>'⚡','services'=>[
                ['code'=>'electric_repair','name'=>'Sửa điện dân dụng','unit'=>'lần','min'=>150000,'max'=>800000,'description'=>'Xử lý chập điện, mất điện cục bộ, ổ cắm, công tắc, đèn và aptomat.','issues'=>['Mất điện','Chập điện','Ổ cắm hỏng','Đèn không sáng']],
                ['code'=>'light_install','name'=>'Lắp đèn, quạt, công tắc, ổ cắm','unit'=>'hạng mục','min'=>120000,'max'=>600000,'description'=>'Lắp đặt thiết bị điện cơ bản trong gia đình, phòng trọ và cửa hàng nhỏ.','issues'=>['Lắp mới','Thay ổ cắm','Thay công tắc','Lắp quạt']],
                ['code'=>'water_repair','name'=>'Sửa nước dân dụng','unit'=>'lần','min'=>150000,'max'=>850000,'description'=>'Xử lý rò nước, nghẹt ống, thay vòi, bồn cầu, lavabo và bơm nước.','issues'=>['Rò nước','Nghẹt ống','Vòi hỏng','Bồn cầu lỗi']],
                ['code'=>'pump_repair','name'=>'Sửa máy bơm nước','unit'=>'lần','min'=>180000,'max'=>900000,'description'=>'Kiểm tra motor, tụ, cánh bơm, phao điện, đường nước và áp lực.','issues'=>['Không lên nước','Kêu lớn','Yếu áp','Tự ngắt']],
            ]],
            'Lắp đặt thiết bị' => ['code'=>'device_installation','icon'=>'🛠️','services'=>[
                ['code'=>'camera_install','name'=>'Lắp đặt camera an ninh','unit'=>'camera','min'=>250000,'max'=>1000000,'description'=>'Lắp camera, cấu hình xem qua điện thoại và kiểm tra góc quan sát.','issues'=>['Lắp mới','Mất hình','Không xem từ xa']],
                ['code'=>'smart_device_install','name'=>'Lắp thiết bị thông minh','unit'=>'thiết bị','min'=>150000,'max'=>650000,'description'=>'Lắp khóa thông minh, cảm biến, công tắc Wi-Fi, thiết bị điều khiển từ xa.','issues'=>['Cài app','Kết nối Wi-Fi','Lắp mới','Cấu hình']],
                ['code'=>'appliance_install','name'=>'Lắp đặt thiết bị gia dụng','unit'=>'thiết bị','min'=>180000,'max'=>850000,'description'=>'Lắp máy giặt, máy rửa chén, bếp điện, máy lọc nước và thiết bị nhà bếp.','issues'=>['Lắp máy giặt','Lắp bếp','Lắp máy lọc nước']],
            ]],
            'Không rõ lỗi' => ['code'=>'unknown_issue','icon'=>'❓','services'=>[
                ['code'=>'unknown_diagnosis','name'=>'Chẩn đoán thiết bị chưa rõ lỗi','unit'=>'yêu cầu','min'=>120000,'max'=>350000,'description'=>'Khách hàng gửi ảnh và mô tả để FixHome phân loại sơ bộ trước khi chuyển doanh nghiệp.','issues'=>['Không rõ lỗi','Cần kiểm tra','Cần báo giá']],
            ]],
        ];
    }

    public static function seed(PDO $pdo): void
    {
        foreach (self::categories() as $name => $group) {
            $stmt = $pdo->prepare('INSERT INTO service_categories(code,name,icon) VALUES(?,?,?) ON DUPLICATE KEY UPDATE code=VALUES(code),icon=VALUES(icon),active=1');
            $stmt->execute([$group['code'],$name,$group['icon']]);
            $catId = (int)$pdo->query('SELECT id FROM service_categories WHERE name=' . $pdo->quote($name))->fetchColumn();
            foreach ($group['services'] as $s) {
                $stmt = $pdo->prepare('INSERT INTO services(category_id,code,name,unit,min_price,max_price,description,active) VALUES(?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE category_id=VALUES(category_id),name=VALUES(name),unit=VALUES(unit),min_price=VALUES(min_price),max_price=VALUES(max_price),description=VALUES(description),active=1');
                $stmt->execute([$catId,$s['code'],$s['name'],$s['unit'],$s['min'],$s['max'],$s['description']]);
                $serviceStmt = $pdo->prepare('SELECT id FROM services WHERE code=? LIMIT 1');
                $serviceStmt->execute([$s['code']]);
                $serviceId = (int)$serviceStmt->fetchColumn();
                $pdo->prepare('DELETE FROM service_common_issues WHERE service_id=?')->execute([$serviceId]);
                $issueStmt = $pdo->prepare('INSERT INTO service_common_issues(service_id,issue_label,sort_order) VALUES(?,?,?)');
                foreach ($s['issues'] as $index => $issue) $issueStmt->execute([$serviceId,$issue,$index + 1]);
            }
        }
    }

    public static function attachCommonIssues(PDO $pdo, array &$services): void
    {
        if (!$services) return;
        $ids = array_map(static fn(array $service): int => (int)$service['id'], $services);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT service_id,issue_label FROM service_common_issues WHERE service_id IN ($placeholders) ORDER BY service_id,sort_order,id");
        $stmt->execute($ids);
        $issues = [];
        foreach ($stmt->fetchAll() as $row) $issues[(int)$row['service_id']][] = $row['issue_label'];
        foreach ($services as &$service) $service['common_issues'] = $issues[(int)$service['id']] ?? [];
        unset($service);
    }
}
