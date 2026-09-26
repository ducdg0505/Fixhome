<?php
final class AuthController
{
    public static function loginForm(): void { if ($u=current_user()) redirect(user_home($u)); render('auth/login',[],'Đăng nhập'); }
    public static function login(): void {
        verify_csrf();
        if (rate_limited('login_failed', (int)config('security.login_max_failures', 20), (int)config('security.login_window_minutes', 10))) {
            flash('error','Có quá nhiều lần đăng nhập thất bại từ kết nối này. Vui lòng thử lại sau.');
            redirect('login');
        }
        $email=post_string('email',180); $password=(string)($_POST['password']??'');
        if (Auth::attempt($email,$password)) { $u=current_user(); flash('success','Đăng nhập thành công.'); redirect(user_home($u)); }
        audit_public('login_failed','auth',null,'Đăng nhập thất bại');
        flash('error','Email hoặc mật khẩu không đúng, hoặc tài khoản chưa kích hoạt.'); redirect('login');
    }
    public static function registerForm(): void { if(current_user()) redirect('dashboard'); render('auth/register',[],'Đăng ký khách hàng'); }
    public static function register(): void {
        verify_csrf();
        if (rate_limited('register', (int)config('security.register_max_per_hour', 5), 60)) { flash('error','Đã tạo quá nhiều tài khoản từ kết nối này. Vui lòng thử lại sau.'); redirect('register'); }
        $name=post_string('name',150); $phone=post_phone_input(); $email=strtolower(post_string('email',180)); $password=(string)($_POST['password']??''); $password2=(string)($_POST['password2']??'');
        if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)){flash('error','Vui lòng nhập đúng và đủ thông tin đăng ký.');redirect('register');}
        $phone = PhonePolicy::normalize($phone);
        if($phone === null){flash('error',PhonePolicy::ERROR_MESSAGE);redirect('register');}
        if($passwordError=PasswordPolicy::validationError($password)){flash('error',$passwordError);redirect('register');}
        if($password!==$password2){flash('error','Xác nhận mật khẩu không khớp.');redirect('register');}
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $s=$pdo->prepare("INSERT INTO users(role,name,email,phone,password_hash,status) VALUES('customer',?,?,?,?, 'active')");
            $s->execute([$name,$email,$phone,password_hash($password,PASSWORD_DEFAULT)]);
            $newUserId=(int)$pdo->lastInsertId();
            ProfileService::initializeUserVersion($pdo,$newUserId,$newUserId);
            $pdo->commit();
            session_regenerate_id(true);$_SESSION['user_id']=$newUserId;
            audit('register','user',$newUserId,'Khách hàng tự đăng ký');
            flash('success','Tạo tài khoản thành công.');redirect('customer');
        } catch(Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error',$e instanceof PDOException && $e->getCode()==='23000'?'Email này đã tồn tại.':'Không thể tạo tài khoản.');redirect('register');
        }
    }
    public static function logout(): void { verify_csrf(); Auth::logout(); header('Location: /'); exit; }
    public static function account(): void {
        $user=require_auth();
        $companyProfile = null;
        $technicianCompanyName = null;
        $savedAddresses = [];
        if ($user['role'] === 'company' && $user['company_id']) {
            $stmt = db()->prepare('SELECT * FROM companies WHERE id=? LIMIT 1');
            $stmt->execute([$user['company_id']]);
            $companyProfile = $stmt->fetch() ?: null;
        } elseif ($user['role'] === 'technician' && $user['company_id']) {
            $stmt = db()->prepare('SELECT name FROM companies WHERE id=? LIMIT 1');
            $stmt->execute([$user['company_id']]);
            $technicianCompanyName = $stmt->fetchColumn() ?: null;
        } elseif ($user['role'] === 'customer') {
            $savedAddresses = CustomerAddressService::listForCustomer((int)$user['id']);
        }
        render('account',[
            'account' => $user,
            'companyProfile' => $companyProfile,
            'technicianCompanyName' => $technicianCompanyName,
            'savedAddresses' => $savedAddresses,
        ],'Tài khoản');
    }
    public static function updateProfile(): void {
        $user=require_auth(); verify_csrf();
        try {
            $changed = ProfileService::updateUserProfile((int)$user['id'],(int)$user['id'],post_string('name',150),post_phone_input());
            flash($changed ? 'success' : 'info',$changed ? 'Đã cập nhật tài khoản cá nhân.' : 'Thông tin tài khoản không thay đổi.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể cập nhật tài khoản lúc này.' : $e->getMessage());
        }
        redirect('account');
    }
    public static function updateCompanyProfile(): void {
        $user=require_role('company'); verify_csrf();
        if (!$user['company_id']) { flash('error','Tài khoản chưa liên kết doanh nghiệp.'); redirect('account'); }
        try {
            $changed = ProfileService::updateCompanyProfile(
                (int)$user['id'],(int)$user['company_id'],post_string('representative',150),post_phone_input(),
                post_string('email',180),post_string('address',255)
            );
            flash($changed ? 'success' : 'info',$changed ? 'Đã cập nhật hồ sơ doanh nghiệp.' : 'Hồ sơ doanh nghiệp không thay đổi.');
        } catch (Throwable $e) {
            $message = $e instanceof PDOException
                ? ($e->getCode()==='23000' ? 'Email doanh nghiệp này đã được sử dụng.' : 'Không thể cập nhật hồ sơ doanh nghiệp lúc này.')
                : $e->getMessage();
            flash('error',$message);
        }
        redirect('account');
    }
    public static function changePassword(): void {
        $user=require_auth(); verify_csrf(); $old=(string)($_POST['old_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['new_password2']??'');
        $s=db()->prepare('SELECT password_hash FROM users WHERE id=?');$s->execute([$user['id']]);$hash=(string)$s->fetchColumn();
        if(!PasswordPolicy::isSafeBcryptInput($old)||!password_verify($old,$hash)){flash('error','Mật khẩu hiện tại không đúng.');redirect('account');}
        if($passwordError=PasswordPolicy::validationError($new)){flash('error',$passwordError);redirect('account');}
        if($new!==$confirm){flash('error','Xác nhận mật khẩu mới không khớp.');redirect('account');}
        if(password_verify($new,$hash)){flash('error','Mật khẩu mới phải khác mật khẩu hiện tại.');redirect('account');}
        $wasMandatory = !empty($user['must_change_password']);
        $s=db()->prepare('UPDATE users SET password_hash=?,must_change_password=0 WHERE id=?');$s->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);session_regenerate_id(true);audit('change_password','user',(int)$user['id'],'Đổi mật khẩu');
        flash('success',$wasMandatory ? 'Đổi mật khẩu thành công. Tài khoản của bạn đã sẵn sàng sử dụng.' : 'Đổi mật khẩu thành công.');
        redirect($wasMandatory ? role_home((string)$user['role']) : 'account');
    }

    public static function addAddress(): void {
        $user=require_role('customer'); verify_csrf();
        try {
            CustomerAddressService::create(
                (int)$user['id'],
                self::textInput('label'),
                self::textInput('address'),
                isset($_POST['is_default'])
            );
            flash('success','Đã lưu địa chỉ mới.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể lưu địa chỉ lúc này.' : $e->getMessage());
        }
        redirect('account');
    }

    public static function editAddress(): void {
        $user=require_role('customer'); verify_csrf();
        try {
            $changed = CustomerAddressService::update(
                (int)$user['id'],
                post_int('address_id'),
                self::textInput('label'),
                self::textInput('address')
            );
            flash($changed ? 'success' : 'info',$changed ? 'Đã cập nhật địa chỉ.' : 'Địa chỉ không thay đổi.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể cập nhật địa chỉ lúc này.' : $e->getMessage());
        }
        redirect('account');
    }

    public static function deleteAddress(): void {
        $user=require_role('customer'); verify_csrf();
        try {
            CustomerAddressService::delete((int)$user['id'],post_int('address_id'));
            flash('success','Đã xóa địa chỉ.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể xóa địa chỉ lúc này.' : $e->getMessage());
        }
        redirect('account');
    }

    public static function setDefaultAddress(): void {
        $user=require_role('customer'); verify_csrf();
        try {
            CustomerAddressService::setDefault((int)$user['id'],post_int('address_id'));
            flash('success','Đã đặt địa chỉ mặc định.');
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể đổi địa chỉ mặc định lúc này.' : $e->getMessage());
        }
        redirect('account');
    }

    public static function deleteAccount(): void {
        $user=require_role('customer'); verify_csrf();
        $password=$_POST['current_password'] ?? '';
        $password=is_string($password) ? $password : '';
        try {
            $result=AccountMaintenanceService::deleteRealCustomerAccount((int)$user['id'],$password);
            $cleanupWarning=!empty($result['file_cleanup']['failures_remaining']);
            Auth::logout();
            header('Location: /?account_deleted=1' . ($cleanupWarning ? '&cleanup_warning=1' : ''),true,303);
            exit;
        } catch (Throwable $e) {
            flash('error',$e instanceof PDOException ? 'Không thể xóa tài khoản lúc này.' : $e->getMessage());
            redirect('account');
        }
    }

    private static function textInput(string $key): string {
        $value=$_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }
}
