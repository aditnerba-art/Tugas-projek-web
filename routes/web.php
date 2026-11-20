<?php

use App\Http\Controllers\ApiController;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Route;

Route::view('/', 'index');
Route::view('/lacak', 'index');
Route::get('/index.html', fn () => redirect('/', 301));
Route::view('/login.html', 'login');
Route::view('/admin-login.html', 'admin-login');
Route::view('/teknisi-login.html', 'teknisi-login');
Route::view('/owner-login.html', 'owner-login');
Route::view('/password/change', 'password-change')->middleware('auth');

Route::view('/admin.html', 'admin')->middleware(['auth', 'password.changed', 'role:admin']);
Route::view('/teknisi.html', 'teknisi')->middleware(['auth', 'password.changed', 'role:teknisi']);
Route::view('/owner.html', 'owner')->middleware(['auth', 'password.changed', 'role:owner']);
Route::view('/spareparts.html', 'spareparts')->middleware(['auth', 'password.changed', 'role:admin,owner']);
Route::get('/owner/activity-logs', fn () => view('owner-activity-logs', [
    'logs' => ActivityLog::with('user')->latest('created_at')->limit(200)->get(),
]))->middleware(['auth', 'password.changed', 'role:owner']);

Route::prefix('api')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login'])->middleware('throttle:staff-login');
    Route::post('/auth/logout', [ApiController::class, 'logout']);
    Route::post('/auth/password', [ApiController::class, 'changePassword'])->middleware('auth');
    Route::middleware(['auth', 'password.changed', 'role:admin,teknisi,owner'])->group(function () {
        Route::get('/auth/me', [ApiController::class, 'me']);
    });
    Route::middleware(['auth', 'password.changed', 'role:owner'])->group(function () {
        Route::patch('/auth/account', [ApiController::class, 'updateAccount']);
        Route::get('/owner/staff', [ApiController::class, 'staffIndex']);
        Route::post('/owner/staff', [ApiController::class, 'staffStore']);
        Route::match(['put', 'patch'], '/owner/staff/{staff}', [ApiController::class, 'staffUpdate']);
        Route::patch('/owner/staff/{staff}/credentials', [ApiController::class, 'staffUpdate']);
        Route::delete('/owner/staff/{staff}', [ApiController::class, 'staffDestroy']);
        Route::get('/owner/dashboard', [ApiController::class, 'ownerDashboard']);
        Route::get('/owner/purchases', [ApiController::class, 'purchases']);
        Route::post('/owner/purchases', [ApiController::class, 'createPurchase']);
    });
    Route::middleware(['auth', 'password.changed', 'role:admin'])->group(function () {
        Route::get('/admin/users', [ApiController::class, 'users']);
        Route::post('/admin/service', [ApiController::class, 'createService']);
        Route::put('/admin/service/assign-teknisi', [ApiController::class, 'assignTechnician']);
        Route::put('/admin/service/pembayaran', [ApiController::class, 'payment']);
        Route::put('/admin/service/pelunasan', [ApiController::class, 'payment']);
        Route::get('/admin/customers/access-codes', [ApiController::class, 'accessCodes']);
        Route::post('/admin/customers/access-codes/{service}', [ApiController::class, 'rotateAccessCode']);
        Route::get('/admin/customers', [ApiController::class, 'adminCustomers']);
        Route::put('/admin/customers/{customer}', [ApiController::class, 'updateCustomer']);
        Route::delete('/admin/customers/{customer}', [ApiController::class, 'deleteCustomer']);
        Route::delete('/admin/service/{service}', [ApiController::class, 'deleteService']);
    });
    Route::middleware(['auth', 'password.changed', 'role:admin,teknisi'])->group(function () {
        Route::get('/admin/teknisi', [ApiController::class, 'technicians']);
        Route::get('/admin/services-active', [ApiController::class, 'activeServices']);
        Route::get('/admin/nota/{noNota}', [ApiController::class, 'nota'])->where('noNota', '[A-Za-z0-9-]{1,32}');
        Route::put('/admin/service/status', [ApiController::class, 'updateServiceStatus']);
        Route::post('/admin/service/persetujuan', [ApiController::class, 'customerApproval']);
    });
    Route::middleware(['auth', 'password.changed', 'role:admin,teknisi,owner'])->group(function () {
        Route::get('/spareparts', [ApiController::class, 'spareparts']);
        Route::get('/spareparts/{sparepart}', [ApiController::class, 'sparepart']);
    });
    Route::middleware(['auth', 'password.changed', 'role:admin,owner'])->group(function () {
        Route::post('/spareparts', [ApiController::class, 'createSparepart']);
        Route::put('/spareparts/{sparepart}', [ApiController::class, 'updateSparepart']);
        Route::delete('/spareparts/{sparepart}', [ApiController::class, 'deleteSparepart']);
    });

    Route::post('/customer/login', [ApiController::class, 'customerLogin'])->middleware('throttle:customer-code');
    Route::get('/customer/me', [ApiController::class, 'customerMe']);
    Route::post('/customer/logout', [ApiController::class, 'customerLogout']);
    Route::post('/customer/approval', [ApiController::class, 'customerApproval'])->middleware('throttle:10,1');
    Route::post('/customer/persetujuan', [ApiController::class, 'customerApproval'])->middleware('throttle:10,1');
    Route::get('/tracking', [ApiController::class, 'publicTracking'])->middleware('throttle:customer-code');
    Route::get('/customer/{identifier}', [ApiController::class, 'publicTracking'])->where('identifier', '[A-Za-z0-9-]{1,32}')->middleware('throttle:customer-code');
    Route::get('/tracking/{identifier}', [ApiController::class, 'publicTracking'])->where('identifier', '[A-Za-z0-9-]{1,32}')->middleware('throttle:customer-code');
    Route::post('/tracking/persetujuan', [ApiController::class, 'customerApproval'])->middleware('throttle:10,1');
});
