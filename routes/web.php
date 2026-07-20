<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\Customer\BookingController as CustomerBooking;
use App\Http\Controllers\Customer\ProfileController as CustomerProfile;
use App\Http\Controllers\Customer\TicketController as CustomerTicket;
use App\Http\Controllers\Agent\DashboardController as AgentDashboard;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboard;
use App\Http\Controllers\Hq\DashboardController as HqDashboard;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Provider\DashboardController as ProviderDashboard;
use App\Http\Controllers\Manage\ProviderController as ManageProvider;
use App\Http\Controllers\Manage\PackageController as ManagePackage;
use App\Http\Controllers\Manage\CustomerController as ManageCustomer;
use App\Http\Controllers\Manage\CompanyController as ManageCompany;
use App\Http\Controllers\Manage\BookingController as ManageBooking;
use App\Http\Controllers\Agent\BookingController as AgentBooking;
use App\Http\Controllers\Provider\BookingController as ProviderBooking;
use App\Http\Controllers\BookingDocumentController;
use App\Http\Controllers\Manage\PaymentController as ManagePayment;
use App\Http\Controllers\Manage\FinanceController as ManageFinance;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\Manage\CommissionController as ManageCommission;
use App\Http\Controllers\Manage\WithdrawalController as ManageWithdrawal;
use App\Http\Controllers\Agent\WalletController as AgentWallet;
use App\Http\Controllers\Agent\GamificationController as AgentGame;
use App\Http\Controllers\Manage\RedemptionController as ManageRedemption;
use App\Http\Controllers\Manage\CouponController as ManageCoupon;
use App\Http\Controllers\Manage\BannerController as ManageBanner;
use App\Http\Controllers\Manage\MarketingMaterialController as ManageMaterial;
use App\Http\Controllers\Manage\TicketController as ManageTicket;
use App\Http\Controllers\Manage\BroadcastController as ManageBroadcast;
use App\Http\Controllers\Agent\MarketingController as AgentMarketing;
use App\Http\Controllers\Agent\TicketController as AgentTicket;
use App\Http\Controllers\Manage\ReportController as ManageReport;
use App\Http\Controllers\Manage\PaymentGatewayController as ManagePaymentGateway;
use App\Http\Controllers\NotificationController;

/*
|--------------------------------------------------------------------------
| Public landing
|--------------------------------------------------------------------------
*/
Route::get('/', [CatalogController::class, 'home'])->name('home');

/*
|--------------------------------------------------------------------------
| Customer portal  (public — /login)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->defaults('portal', 'customer')->name('login');
    Route::post('/login', [LoginController::class, 'login'])->defaults('portal', 'customer')->middleware('throttle:5,1');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:5,1');
});

// Public package catalog (browsable without an account)
Route::get('/packages', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/packages/{slug}', [CatalogController::class, 'show'])->name('catalog.show');

Route::middleware(['auth', 'role:customer'])->prefix('account')->name('customer.')->group(function () {
    Route::get('/', [CustomerDashboard::class, 'index'])->name('dashboard');

    Route::get('/bookings', [CustomerBooking::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [CustomerBooking::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [CustomerBooking::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [CustomerBooking::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/payment', [CustomerBooking::class, 'uploadPayment'])->name('bookings.payment');

    Route::get('/profile', [CustomerProfile::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [CustomerProfile::class, 'update'])->name('profile.update');

    Route::get('/tickets', [CustomerTicket::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [CustomerTicket::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [CustomerTicket::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [CustomerTicket::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [CustomerTicket::class, 'reply'])->name('tickets.reply');
});
Route::post('/logout', [LoginController::class, 'logout'])->defaults('portal', 'customer')->name('logout');

/*
|--------------------------------------------------------------------------
| Agent portal  (/agent/login) — uses blue mobile mockups
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->prefix('agent')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->defaults('portal', 'agent')->name('agent.login');
    Route::post('/login', [LoginController::class, 'login'])->defaults('portal', 'agent')->middleware('throttle:5,1');
});
Route::middleware(['auth', 'role:agent'])->prefix('agent')->name('agent.')->group(function () {
    Route::get('/dashboard', [AgentDashboard::class, 'index'])->name('dashboard');

    Route::get('/bookings', [AgentBooking::class, 'index'])->name('bookings.index');
    Route::get('/bookings/create', [AgentBooking::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [AgentBooking::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [AgentBooking::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/payment', [AgentBooking::class, 'uploadPayment'])->name('bookings.payment');

    // Wallet / commission / network
    Route::get('/wallet', [AgentWallet::class, 'index'])->name('wallet.index');
    Route::post('/wallet/withdraw', [AgentWallet::class, 'withdraw'])->name('wallet.withdraw');
    Route::get('/commissions', [AgentWallet::class, 'commissions'])->name('commissions');
    Route::get('/network', [AgentWallet::class, 'network'])->name('network');

    // Gamification
    Route::post('/checkin', [AgentGame::class, 'checkin'])->name('checkin');
    Route::post('/missions/{mission}/complete', [AgentGame::class, 'completeMission'])->name('missions.complete');
    Route::post('/redeem', [AgentGame::class, 'redeem'])->name('redeem');
    Route::get('/leaderboard', [AgentGame::class, 'leaderboard'])->name('leaderboard');
    Route::get('/achievements', [AgentGame::class, 'achievements'])->name('achievements');

    // Marketing + support + notifications
    Route::get('/marketing', [AgentMarketing::class, 'index'])->name('marketing.index');
    Route::get('/marketing/{material}/download', [AgentMarketing::class, 'download'])->name('marketing.download');
    Route::get('/tickets', [AgentTicket::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [AgentTicket::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [AgentTicket::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [AgentTicket::class, 'show'])->name('tickets.show');
    Route::post('/tickets/{ticket}/reply', [AgentTicket::class, 'reply'])->name('tickets.reply');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');

    Route::post('/logout', [LoginController::class, 'logout'])->defaults('portal', 'agent')->name('logout');
});

/*
|--------------------------------------------------------------------------
| Provider portal  (/provider/login)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->prefix('provider')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->defaults('portal', 'provider')->name('provider.login');
    Route::post('/login', [LoginController::class, 'login'])->defaults('portal', 'provider')->middleware('throttle:5,1');
});
Route::middleware(['auth', 'role:provider'])->prefix('provider')->name('provider.')->group(function () {
    Route::get('/dashboard', [ProviderDashboard::class, 'index'])->name('dashboard');

    Route::get('/bookings', [ProviderBooking::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [ProviderBooking::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/respond', [ProviderBooking::class, 'respond'])->name('bookings.respond');

    Route::post('/logout', [LoginController::class, 'logout'])->defaults('portal', 'provider')->name('logout');
});

/*
|--------------------------------------------------------------------------
| Staff back-office — HQ + Admin (/admin/login, /hq/login)
| Single staff guard, separated from Agent/Customer, gated by RBAC role
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'show'])->defaults('portal', 'staff')->name('admin.login');
    Route::post('/admin/login', [LoginController::class, 'login'])->defaults('portal', 'staff')->middleware('throttle:5,1');
    // /hq/login shares the same staff login screen
    Route::get('/hq/login', [LoginController::class, 'show'])->defaults('portal', 'staff')->name('hq.login');
});

Route::middleware(['auth', 'role:super_admin,hq'])->prefix('hq')->name('hq.')->group(function () {
    Route::get('/dashboard', [HqDashboard::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:admin,super_admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index'])->name('dashboard');
});

Route::middleware(['auth', 'role:super_admin,hq,admin'])->group(function () {
    Route::post('/staff/logout', [LoginController::class, 'logout'])->defaults('portal', 'staff')->name('staff.logout');

    // Core Catalog management (shared by HQ + Admin)
    Route::prefix('manage')->name('manage.')->group(function () {
        Route::get('company', [ManageCompany::class, 'edit'])->name('company.edit');
        Route::put('company', [ManageCompany::class, 'update'])->name('company.update');

        Route::resource('providers', ManageProvider::class)->except('show');
        Route::resource('customers', ManageCustomer::class)->except('show');
        Route::resource('packages', ManagePackage::class);

        // Booking engine
        Route::get('bookings', [ManageBooking::class, 'index'])->name('bookings.index');
        Route::get('bookings/create', [ManageBooking::class, 'create'])->name('bookings.create');
        Route::post('bookings', [ManageBooking::class, 'store'])->name('bookings.store');
        Route::get('bookings/{booking}', [ManageBooking::class, 'show'])->name('bookings.show');
        Route::post('bookings/{booking}/submit', [ManageBooking::class, 'submitToProvider'])->name('bookings.submit');
        Route::post('bookings/{booking}/confirm', [ManageBooking::class, 'confirm'])->name('bookings.confirm');
        Route::post('bookings/{booking}/reject', [ManageBooking::class, 'reject'])->name('bookings.reject');
        Route::post('bookings/{booking}/complete', [ManageBooking::class, 'complete'])->name('bookings.complete');
        Route::post('bookings/{booking}/cancel', [ManageBooking::class, 'cancel'])->name('bookings.cancel');
        Route::post('bookings/{booking}/note', [ManageBooking::class, 'addNote'])->name('bookings.note');
        Route::post('bookings/{booking}/payment', [ManageBooking::class, 'recordPayment'])->name('bookings.payment');

        // Payments
        Route::get('payments', [ManagePayment::class, 'index'])->name('payments.index');
        Route::post('payments/{payment}/verify', [ManagePayment::class, 'verify'])->name('payments.verify');
        Route::post('payments/{payment}/reject', [ManagePayment::class, 'reject'])->name('payments.reject');

        // Finance + refunds
        Route::get('finance', [ManageFinance::class, 'dashboard'])->name('finance.dashboard');
        Route::get('finance/refunds', [ManageFinance::class, 'refunds'])->name('finance.refunds');
        Route::post('bookings/{booking}/refund', [ManageFinance::class, 'requestRefund'])->name('bookings.refund');
        Route::post('finance/refunds/{refund}/approve', [ManageFinance::class, 'approveRefund'])->name('finance.refunds.approve');
        Route::post('finance/refunds/{refund}/reject', [ManageFinance::class, 'rejectRefund'])->name('finance.refunds.reject');
        Route::post('finance/refunds/{refund}/process', [ManageFinance::class, 'processRefund'])->name('finance.refunds.process');

        // Commission (dynamic-depth MLM)
        Route::get('commission', [ManageCommission::class, 'index'])->name('commission.index');
        Route::post('commission/{commission}/approve', [ManageCommission::class, 'approve'])->name('commission.approve');
        Route::post('commission/{commission}/reject', [ManageCommission::class, 'reject'])->name('commission.reject');
        Route::post('commission/approve-period', [ManageCommission::class, 'approvePeriod'])->name('commission.approve-period');
        Route::get('commission-levels', [ManageCommission::class, 'levels'])->name('commission.levels');
        Route::post('commission-levels', [ManageCommission::class, 'storeLevel'])->name('commission.levels.store');
        Route::put('commission-levels/{level}', [ManageCommission::class, 'updateLevel'])->name('commission.levels.update');
        Route::delete('commission-levels/{level}', [ManageCommission::class, 'destroyLevel'])->name('commission.levels.destroy');
        Route::post('commission-settings', [ManageCommission::class, 'saveSettings'])->name('commission.settings');

        // Withdrawals
        Route::get('withdrawals', [ManageWithdrawal::class, 'index'])->name('withdrawals.index');
        Route::post('withdrawals/{withdrawal}/approve', [ManageWithdrawal::class, 'approve'])->name('withdrawals.approve');
        Route::post('withdrawals/{withdrawal}/paid', [ManageWithdrawal::class, 'markPaid'])->name('withdrawals.paid');
        Route::post('withdrawals/{withdrawal}/reject', [ManageWithdrawal::class, 'reject'])->name('withdrawals.reject');

        // Reward redemptions
        Route::get('redemptions', [ManageRedemption::class, 'index'])->name('redemptions.index');
        Route::post('redemptions/{redemption}/approve', [ManageRedemption::class, 'approve'])->name('redemptions.approve');
        Route::post('redemptions/{redemption}/fulfill', [ManageRedemption::class, 'fulfill'])->name('redemptions.fulfill');
        Route::post('redemptions/{redemption}/reject', [ManageRedemption::class, 'reject'])->name('redemptions.reject');

        // Marketing
        Route::get('coupons', [ManageCoupon::class, 'index'])->name('coupons.index');
        Route::post('coupons', [ManageCoupon::class, 'store'])->name('coupons.store');
        Route::put('coupons/{coupon}', [ManageCoupon::class, 'update'])->name('coupons.update');
        Route::delete('coupons/{coupon}', [ManageCoupon::class, 'destroy'])->name('coupons.destroy');
        Route::get('banners', [ManageBanner::class, 'index'])->name('banners.index');
        Route::post('banners', [ManageBanner::class, 'store'])->name('banners.store');
        Route::put('banners/{banner}', [ManageBanner::class, 'update'])->name('banners.update');
        Route::delete('banners/{banner}', [ManageBanner::class, 'destroy'])->name('banners.destroy');
        Route::get('materials', [ManageMaterial::class, 'index'])->name('materials.index');
        Route::post('materials', [ManageMaterial::class, 'store'])->name('materials.store');
        Route::put('materials/{material}', [ManageMaterial::class, 'update'])->name('materials.update');
        Route::delete('materials/{material}', [ManageMaterial::class, 'destroy'])->name('materials.destroy');
        Route::get('broadcast', [ManageBroadcast::class, 'create'])->name('broadcast.create');
        Route::post('broadcast', [ManageBroadcast::class, 'send'])->name('broadcast.send');

        // Support tickets
        Route::get('tickets', [ManageTicket::class, 'index'])->name('tickets.index');
        Route::get('tickets/{ticket}', [ManageTicket::class, 'show'])->name('tickets.show');
        Route::post('tickets/{ticket}/reply', [ManageTicket::class, 'reply'])->name('tickets.reply');
        Route::post('tickets/{ticket}/status', [ManageTicket::class, 'status'])->name('tickets.status');

        // Payment gateway configuration — credentials are money-sensitive, so this is
        // HQ/super-admin only, unlike the rest of the /manage area.
        Route::middleware('role:super_admin,hq')->group(function () {
            Route::get('payment-gateway', [ManagePaymentGateway::class, 'edit'])->name('payment-gateway.edit');
            Route::put('payment-gateway', [ManagePaymentGateway::class, 'update'])->name('payment-gateway.update');
            Route::post('payment-gateway/test', [ManagePaymentGateway::class, 'test'])->name('payment-gateway.test');
        });

        // Reports & analytics
        Route::get('reports', [ManageReport::class, 'index'])->name('reports.index');
        Route::get('reports/{key}', [ManageReport::class, 'show'])->name('reports.show');
        Route::get('reports/{key}/export/{format}', [ManageReport::class, 'export'])->name('reports.export');
    });

    // Notifications (staff bell)
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Booking document download (staff)
    Route::get('documents/{document}/download', [BookingDocumentController::class, 'download'])->name('documents.download');
});

// Booking document download (agent / provider / customer share the same controller)
Route::middleware(['auth'])->get('my/documents/{document}/download', [BookingDocumentController::class, 'download'])->name('documents.download.portal');
Route::middleware(['auth'])->get('payments/{payment}/slip', [BookingDocumentController::class, 'slip'])->name('payments.slip');

// Notification actions (any authenticated user)
Route::middleware(['auth'])->group(function () {
    Route::get('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.readAll');
});

/*
|--------------------------------------------------------------------------
| FPX payment gateway (sandbox) — shared by staff / agent / customer
|--------------------------------------------------------------------------
*/
// Vendor webhook: server-to-server, no session, CSRF-exempt — the vendor SIGNATURE
// is the authentication (see bootstrap/app.php). Must sit outside the auth group.
Route::post('pay/webhook/{driver}', [PaymentGatewayController::class, 'webhook'])
    ->name('gateway.webhook')->middleware('throttle:60,1');
Route::get('pay/return/{driver}', [PaymentGatewayController::class, 'return'])
    ->name('gateway.return')->middleware('throttle:60,1');

Route::middleware(['auth'])->prefix('pay')->name('gateway.')->group(function () {
    Route::post('booking/{booking}/fpx', [PaymentGatewayController::class, 'initiate'])->name('initiate');
    Route::get('checkout/{ref}', [PaymentGatewayController::class, 'checkout'])->name('checkout');
    Route::post('callback/{ref}', [PaymentGatewayController::class, 'callback'])->name('callback')->middleware('throttle:30,1');
});
