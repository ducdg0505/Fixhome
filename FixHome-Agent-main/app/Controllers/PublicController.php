<?php
final class PublicController
{
    public static function home(): void {
        if ($user = current_user()) redirect(user_home($user));
        if ((string)($_GET['account_deleted'] ?? '') === '1') {
            flash('success','Tài khoản đã được xóa và thông tin cá nhân đã được ẩn danh.');
            if ((string)($_GET['cleanup_warning'] ?? '') === '1') {
                flash('warning','Dữ liệu tài khoản đã được xử lý, nhưng một số tệp ảnh cần được hệ thống dọn dẹp thêm.');
            }
        }
        $categories=db()->query("SELECT c.*,COUNT(s.id) service_count FROM service_categories c LEFT JOIN services s ON s.category_id=c.id AND s.active=1 GROUP BY c.id ORDER BY c.id")->fetchAll();
        $services=db()->query("SELECT s.*,c.name category_name,c.icon FROM services s JOIN service_categories c ON c.id=s.category_id WHERE s.active=1 ORDER BY c.id,s.id")->fetchAll();
        ServiceCatalog::attachCommonIssues(db(), $services);
        render('public/home',compact('categories','services'),'FixHome - Sửa chữa gia dụng');
    }
    public static function applyPartner(): void {
        verify_csrf();
        if (rate_limited('partner_apply', (int)config('security.partner_apply_max_per_hour', 5), 60)) { flash('error','Đã gửi quá nhiều hồ sơ từ kết nối này. Vui lòng thử lại sau.'); redirect(''); }
        $name=post_string('company_name',180);$tax=post_string('tax_code',60);$rep=post_string('representative',150);$phone=post_phone_input();$email=strtolower(post_string('email',180));$address=post_string('address',255);$note=post_string('legal_note',3000);$categories=array_map('intval',$_POST['categories']??[]);
        if(!$name||!$tax||!$rep||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$address||!$categories){flash('error','Vui lòng nhập đầy đủ hồ sơ đối tác và chọn ít nhất một nhóm dịch vụ.');redirect('');}
        $phone=PhonePolicy::normalize($phone);
        if($phone===null){flash('error',PhonePolicy::ERROR_MESSAGE);redirect('');}
        try{db()->beginTransaction();$s=db()->prepare('INSERT INTO partner_applications(company_name,tax_code,representative,phone,email,address,legal_note) VALUES(?,?,?,?,?,?,?)');$s->execute([$name,$tax,$rep,$phone,$email,$address,$note]);$id=(int)db()->lastInsertId();$i=db()->prepare('INSERT INTO partner_application_categories(application_id,category_id) VALUES(?,?)');foreach(array_unique($categories) as $c)$i->execute([$id,$c]);notify_role('admin','Hồ sơ đối tác mới',$name.' vừa gửi hồ sơ hợp tác.','partner_application');db()->commit();audit_public('partner_apply','partner_application',$id,'Gửi hồ sơ đối tác');flash('success','Đã gửi hồ sơ. FixHome sẽ kiểm tra pháp lý trước khi cấp tài khoản.');}
        catch(Throwable $e){if(db()->inTransaction())db()->rollBack();flash('error','Không thể gửi hồ sơ. Mã số thuế có thể đã được đăng ký hoặc dữ liệu chưa hợp lệ.');}
        redirect('');
    }
}
