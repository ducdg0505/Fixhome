<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = '/' . trim($path, '/');
if ($path === '//') $path = '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($method==='GET' && $path==='/') PublicController::home();
    elseif ($method==='GET' && $path==='/login') AuthController::loginForm();
    elseif ($method==='POST' && $path==='/login') AuthController::login();
    elseif ($method==='GET' && $path==='/register') AuthController::registerForm();
    elseif ($method==='POST' && $path==='/register') AuthController::register();
    elseif ($method==='POST' && $path==='/logout') AuthController::logout();
    elseif ($method==='GET' && $path==='/dashboard') { $u=require_auth(); redirect(role_home($u['role'])); }
    elseif ($method==='GET' && $path==='/account') AuthController::account();
    elseif ($method==='POST' && $path==='/account/profile') AuthController::updateProfile();
    elseif ($method==='POST' && $path==='/account/company') AuthController::updateCompanyProfile();
    elseif ($method==='POST' && $path==='/account/password') AuthController::changePassword();
    elseif ($method==='POST' && $path==='/account/addresses/add') AuthController::addAddress();
    elseif ($method==='POST' && $path==='/account/addresses/edit') AuthController::editAddress();
    elseif ($method==='POST' && $path==='/account/addresses/delete') AuthController::deleteAddress();
    elseif ($method==='POST' && $path==='/account/addresses/default') AuthController::setDefaultAddress();
    elseif ($method==='POST' && $path==='/account/delete') AuthController::deleteAccount();
    elseif ($method==='GET' && $path==='/notifications') NotificationController::index();
    elseif ($method==='POST' && $path==='/notifications/read') NotificationController::markRead();
    elseif ($method==='POST' && $path==='/partner/apply') PublicController::applyPartner();
    elseif ($method==='GET' && $path==='/media/order-image') MediaController::orderImage();

    elseif ($method==='GET' && $path==='/customer') CustomerController::dashboard();
    elseif ($method==='GET' && $path==='/customer/book') CustomerController::bookingForm();
    elseif ($method==='POST' && $path==='/customer/book') CustomerController::createOrder();
    elseif ($method==='POST' && $path==='/customer/diagnosis/preview') CustomerController::previewDiagnosis();
    elseif ($method==='GET' && $path==='/customer/orders') CustomerController::orders();
    elseif ($method==='POST' && $path==='/customer/quote') CustomerController::quoteAction();
    elseif ($method==='POST' && $path==='/customer/orders/cancel') CustomerController::cancel();
    elseif ($method==='POST' && $path==='/customer/feedback') CustomerController::feedback();

    elseif ($method==='GET' && $path==='/company') CompanyController::dashboard();
    elseif ($method==='GET' && $path==='/company/orders') CompanyController::orders();
    elseif ($method==='POST' && $path==='/company/orders/quote') CompanyController::quote();
    elseif ($method==='POST' && $path==='/company/orders/decline') CompanyController::decline();
    elseif ($method==='POST' && $path==='/company/orders/assign') CompanyController::assignTechnician();
    elseif ($method==='POST' && $path==='/company/orders/complete') CompanyController::complete();
    elseif ($method==='GET' && $path==='/company/technicians') CompanyController::technicians();
    elseif ($method==='POST' && $path==='/company/technicians') CompanyController::createTechnician();
    elseif ($method==='POST' && $path==='/company/technicians/capabilities') CompanyController::updateTechnicianCapabilities();
    elseif ($method==='POST' && $path==='/company/technicians/status') CompanyController::changeTechnicianStatus();
    elseif ($method==='GET' && $path==='/company/revenue') CompanyController::revenue();

    elseif ($method==='GET' && $path==='/technician') TechnicianController::dashboard();
    elseif ($method==='POST' && $path==='/technician/status') TechnicianController::updateStatus();
    elseif ($method==='POST' && $path==='/technician/repair-report/submit') TechnicianController::submitRepairReport();
    elseif ($method==='POST' && $path==='/technician/repair-report/update') TechnicianController::updateRepairReport();

    elseif ($method==='GET' && $path==='/admin') AdminController::dashboard();
    elseif ($method==='GET' && $path==='/admin/accounts') AdminController::accounts();
    elseif ($method==='POST' && $path==='/admin/accounts/test-marker') AdminController::changeCustomerTestMarker();
    elseif ($method==='POST' && $path==='/admin/accounts/status') AdminController::changeCustomerStatus();
    elseif ($method==='POST' && $path==='/admin/accounts/purge') AdminController::purgeTestCustomer();
    elseif ($method==='GET' && $path==='/admin/partners') AdminController::partners();
    elseif ($method==='POST' && $path==='/admin/partners/review') AdminController::reviewPartner();
    elseif ($method==='GET' && $path==='/admin/orders') AdminController::orders();
    elseif ($method==='POST' && $path==='/admin/orders/assign') AdminController::assignOrder();
    elseif ($method==='GET' && $path==='/admin/companies') AdminController::companies();
    elseif ($method==='GET' && $path==='/admin/complaints') AdminController::complaints();
    elseif ($method==='POST' && $path==='/admin/complaints') AdminController::updateComplaint();
    else { http_response_code(404); render('404',[],'Không tìm thấy'); }
} catch (PDOException $e) {
    http_response_code(500);
    $message = config('app.debug', false) ? $e->getMessage() : 'Có lỗi cơ sở dữ liệu. Vui lòng thử lại sau.';
    render('error',['message'=>$message],'Lỗi hệ thống');
} catch (Throwable $e) {
    http_response_code(500);
    $message = config('app.debug', false) ? $e->getMessage() : 'Có lỗi hệ thống. Vui lòng thử lại sau.';
    render('error',['message'=>$message],'Lỗi hệ thống');
}
