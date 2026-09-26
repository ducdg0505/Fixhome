<?php
final class DiagnosisService
{
    private const RULES = [
        ['máy lạnh','Điện lạnh','Máy lạnh',['không lạnh','kém lạnh','chảy nước','báo lỗi','đóng tuyết','hôi','mùi']],
        ['điều hòa','Điện lạnh','Máy lạnh',['không lạnh','kém lạnh','chảy nước','báo lỗi','đóng tuyết','hôi','mùi']],
        ['tủ lạnh','Điện lạnh','Tủ lạnh',['không lạnh','đóng tuyết','rò nước','kêu','hôi','không đông']],
        ['tủ đông','Điện lạnh','Tủ đông / tủ mát',['không đông','không lạnh','kêu','hao điện']],
        ['máy giặt','Điện gia dụng','Máy giặt',['không vắt','không xả','rung','báo lỗi','không cấp nước']],
        ['máy nước nóng','Điện gia dụng','Máy nước nóng',['không nóng','rò điện','rò nước','tự ngắt']],
        ['lò vi sóng','Điện gia dụng','Lò vi sóng',['không nóng','không quay','mất nguồn','tia lửa']],
        ['nồi cơm','Điện gia dụng','Nồi cơm điện',['không chín','cơm sống','không giữ ấm','mất nguồn']],
        ['quạt','Điện gia dụng','Quạt điện',['không quay','quay yếu','kêu','hỏng điều khiển']],
        ['điện','Điện - nước','Hệ thống điện dân dụng',['chập','mất điện','ổ cắm','công tắc','aptomat','tia lửa']],
        ['nước','Điện - nước','Hệ thống nước dân dụng',['rò','nghẹt','bồn cầu','vòi','ống nước']],
        ['camera','Lắp đặt thiết bị','Camera an ninh',['mất hình','không xem','lắp','wifi']],
    ];
    private const DANGER = ['cháy','khét','tia lửa','rò điện','giật','nổ','bốc khói','chập điện'];

    public static function analyze(string $description, bool $hasImage = false): array
    {
        $raw = trim($description);
        $text = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
        $device = 'Thiết bị chưa xác định'; $category = 'Không rõ lỗi'; $issues = []; $score = 0.25;
        foreach (self::RULES as [$key,$cat,$dev,$ruleIssues]) {
            if ((function_exists('mb_strpos') ? mb_strpos($text,$key,0,'UTF-8') : strpos($text,$key)) !== false) {
                $device=$dev; $category=$cat; $score+=0.35;
                foreach ($ruleIssues as $issue) if ((function_exists('mb_strpos') ? mb_strpos($text,$issue,0,'UTF-8') : strpos($text,$issue))!==false) { $issues[]=$issue; $score+=0.08; }
                break;
            }
        }
        if (!$issues) {
            foreach (self::RULES as [$key,$cat,$dev,$ruleIssues]) {
                $hits=[]; foreach ($ruleIssues as $issue) if ((function_exists('mb_strpos') ? mb_strpos($text,$issue,0,'UTF-8') : strpos($text,$issue))!==false) $hits[]=$issue;
                if ($hits) { $device=$dev; $category=$cat; $issues=$hits; $score += 0.25 + 0.06*count($hits); break; }
            }
        }
        if ($hasImage) $score += 0.1;
        $risk='Trung bình'; $safety='Không tự tháo thiết bị nếu chưa ngắt nguồn điện và chưa có kỹ thuật viên kiểm tra.';
        foreach (self::DANGER as $danger) {
            if ((function_exists('mb_strpos') ? mb_strpos($text,$danger,0,'UTF-8') : strpos($text,$danger))!==false) { $risk='Cao'; $safety='Nên ngắt nguồn điện, không tiếp tục sử dụng thiết bị và chờ kỹ thuật viên kiểm tra.'; $score+=0.1; break; }
        }
        if ($text==='') $risk='Thấp';
        $issueGroup = $issues ? implode(', ', array_unique($issues)) : 'Cần kiểm tra trực tiếp';
        $summary = "Hệ thống ghi nhận thiết bị: {$device}. Nhóm lỗi sơ bộ: {$issueGroup}. Yêu cầu nên được chuyển đến nhóm dịch vụ: {$category}.";
        if ($hasImage) $summary .= ' Ảnh thiết bị đã được đính kèm để doanh nghiệp đối chiếu trước khi báo giá.';
        return ['device_type'=>$device,'category'=>$category,'issue_group'=>$issueGroup,'risk_level'=>$risk,'confidence'=>min($score,0.92),'summary'=>$summary,'safety_note'=>$safety,'model_name'=>'FixHome Rule-based Diagnosis PHP v1'];
    }
}
