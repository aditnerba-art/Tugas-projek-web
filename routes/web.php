<?php

use App\Http\Controllers\ApiController;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'index');
Route::view('/index.html', 'index');
Route::view('/login.html', 'login');
Route::view('/admin-login.html', 'admin-login');
Route::view('/teknisi-login.html', 'teknisi-login');
Route::view('/owner-login.html', 'owner-login');

Route::view('/admin.html', 'admin')->middleware('role:admin');
Route::view('/teknisi.html', 'teknisi')->middleware('role:teknisi');
Route::view('/owner.html', 'owner')->middleware('role:owner');
Route::view('/spareparts.html', 'spareparts')->middleware('role:admin,owner');

Route::prefix('api')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/auth/logout', [ApiController::class, 'logout']);
    Route::middleware('role:admin,teknisi,owner')->group(function () {
        Route::get('/auth/me', [ApiController::class, 'me']);
    });
    Route::middleware('role:owner')->group(function () {
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
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/users', [ApiController::class, 'users']);
        Route::post('/admin/service', [ApiController::class, 'createService']);
        Route::put('/admin/service/assign-teknisi', [ApiController::class, 'assignTechnician']);
        Route::put('/admin/service/pembayaran', [ApiController::class, 'payment']);
        Route::put('/admin/service/pelunasan', function (Request $request, ApiController $controller) {
            $request->merge(['status_pembayaran' => 'lunas', 'ambil_unit' => true]);
            if (! $request->filled('nominal_dibayar') && $request->filled('service_id')) {
                $request->merge(['nominal_dibayar' => Service::find($request->input('service_id'))?->total_biaya ?? 0, 'kembalian' => 0]);
            }

            return $controller->payment($request);
        });
        Route::get('/admin/customers/access-codes', [ApiController::class, 'accessCodes']);
        Route::post('/admin/customers/access-codes/{service}', [ApiController::class, 'rotateAccessCode']);
        Route::get('/admin/customers', [ApiController::class, 'adminCustomers']);
        Route::put('/admin/customers/{customer}', [ApiController::class, 'updateCustomer']);
        Route::delete('/admin/customers/{customer}', [ApiController::class, 'deleteCustomer']);
        Route::delete('/admin/service/{service}', [ApiController::class, 'deleteService']);
    });
    Route::middleware('role:admin,teknisi')->group(function () {
        Route::get('/admin/teknisi', [ApiController::class, 'technicians']);
        Route::get('/admin/services-active', [ApiController::class, 'activeServices']);
        Route::get('/admin/nota/{noNota}', [ApiController::class, 'nota']);
        Route::put('/admin/service/status', [ApiController::class, 'updateServiceStatus']);
        Route::post('/admin/service/persetujuan', [ApiController::class, 'customerApproval']);
    });
    Route::middleware('role:admin,teknisi,owner')->group(function () {
        Route::get('/spareparts', [ApiController::class, 'spareparts']);
        Route::get('/spareparts/{sparepart}', [ApiController::class, 'sparepart']);
    });
    Route::middleware('role:admin,owner')->group(function () {
        Route::post('/spareparts', [ApiController::class, 'createSparepart']);
        Route::put('/spareparts/{sparepart}', [ApiController::class, 'updateSparepart']);
        Route::delete('/spareparts/{sparepart}', [ApiController::class, 'deleteSparepart']);
    });

    Route::post('/customer/login', [ApiController::class, 'customerLogin'])->middleware('throttle:10,1');
    Route::get('/customer/me', [ApiController::class, 'customerMe']);
    Route::post('/customer/logout', [ApiController::class, 'customerLogout']);
    Route::post('/customer/approval', [ApiController::class, 'customerApproval']);
    Route::post('/customer/persetujuan', [ApiController::class, 'customerApproval']);
    Route::get('/tracking', [ApiController::class, 'publicTracking'])->middleware('throttle:10,1');
    Route::get('/customer/{identifier}', [ApiController::class, 'publicTracking'])->middleware('throttle:10,1');
    Route::get('/tracking/{identifier}', [ApiController::class, 'publicTracking'])->middleware('throttle:10,1');
    Route::post('/tracking/persetujuan', [ApiController::class, 'customerApproval']);
});
