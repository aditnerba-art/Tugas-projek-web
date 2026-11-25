<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\ServicePhotoController;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

Route::view('/', 'index');
Route::view('/lacak', 'index');
Route::get('/lacak/unit/{trackingKey}', fn (string $trackingKey) => view('index', ['trackingKey' => $trackingKey]))
    ->where('trackingKey', '[A-Za-z0-9]{64}')
    ->name('tracking.public.page');
Route::get('/service-photos/{photo}/{variant?}', ServicePhotoController::class)
    ->whereNumber('photo')
    ->where('variant', 'original|thumbnail')
    ->name('service-photos.show');
Route::view('/login', 'login')->name('staff.login');
Route::view('/admin/login', 'admin-login')->name('admin.login');
Route::view('/teknisi/login', 'teknisi-login')->name('teknisi.login');
Route::view('/owner/login', 'owner-login')->name('owner.login');
Route::view('/password/change', 'password-change')->middleware('auth');
Route::view('/offline-teknisi', 'offline-teknisi');

Route::view('/admin', 'admin')->middleware(['auth', 'password.changed', 'role:admin'])->name('admin.dashboard');
Route::view('/teknisi', 'teknisi')->middleware(['auth', 'password.changed', 'role:teknisi'])->name('teknisi.dashboard');
Route::view('/owner', 'owner')->middleware(['auth', 'password.changed', 'role:owner'])->name('owner.dashboard');
Route::view('/spareparts', 'spareparts')->middleware(['auth', 'password.changed', 'role:admin,owner']);
Route::get('/owner/activity-logs', function (Request $request) {
    $filters = $request->validate([
        'search' => ['nullable', 'string', 'max:100'],
        'action' => ['nullable', 'string', 'max:50'],
        'date_from' => ['nullable', 'date'],
        'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        'sort' => ['nullable', Rule::in(['created_at', 'aksi', 'subjek'])],
        'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        'per_page' => ['nullable', 'integer', 'between:10,100'],
    ]);
    $query = ActivityLog::with('user');
    if ($search = trim((string) ($filters['search'] ?? ''))) {
        $query->where(fn ($logs) => $logs->where('aksi', 'like', "%{$search}%")
            ->orWhere('subjek', 'like', "%{$search}%")
            ->orWhere('ip', 'like', "%{$search}%")
            ->orWhereHas('user', fn ($users) => $users->where('username', 'like', "%{$search}%")));
    }
    if (! empty($filters['action'])) {
        $query->where('aksi', $filters['action']);
    }
    if (! empty($filters['date_from'])) {
        $query->whereDate('created_at', '>=', $filters['date_from']);
    }
    if (! empty($filters['date_to'])) {
        $query->whereDate('created_at', '<=', $filters['date_to']);
    }

    return view('owner-activity-logs', [
        'logs' => $query->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString(),
        'filters' => $filters,
    ]);
})->middleware(['auth', 'password.changed', 'role:owner']);
Route::view('/owner/notifications', 'owner-notifications')->middleware(['auth', 'password.changed', 'role:owner']);

Route::prefix('api')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login'])->middleware('throttle:staff-login');
    Route::post('/auth/logout', [ApiController::class, 'logout']);
    Route::post('/auth/password', [ApiController::class, 'changePassword'])->middleware('auth');
    Route::middleware(['auth', 'password.changed', 'role:admin,teknisi,owner'])->group(function () {
        Route::get('/auth/me', [ApiController::class, 'me']);
        Route::get('/services/status-updates', [ApiController::class, 'serviceStatusUpdates']);
        Route::get('/notifications', [ApiController::class, 'notifications']);
        Route::post('/notifications/read-all', [ApiController::class, 'markAllNotificationsRead']);
        Route::post('/notifications/{notification}/read', [ApiController::class, 'markNotificationRead']);
    });
    Route::middleware(['auth', 'password.changed', 'role:owner'])->group(function () {
        Route::patch('/auth/account', [ApiController::class, 'updateAccount']);
        Route::get('/owner/staff', [ApiController::class, 'staffIndex']);
        Route::post('/owner/staff', [ApiController::class, 'staffStore']);
        Route::match(['put', 'patch'], '/owner/staff/{staff}', [ApiController::class, 'staffUpdate']);
        Route::patch('/owner/staff/{staff}/credentials', [ApiController::class, 'staffUpdate']);
        Route::delete('/owner/staff/{staff}', [ApiController::class, 'staffDestroy']);
        Route::get('/owner/dashboard', [ApiController::class, 'ownerDashboard']);
        Route::get('/owner/reports', [ApiController::class, 'ownerReport']);
        Route::get('/owner/reports.csv', [ApiController::class, 'ownerReportCsv']);
        Route::get('/owner/reports/print', [ApiController::class, 'ownerReportPrint']);
        Route::get('/owner/purchases', [ApiController::class, 'purchases']);
        Route::post('/owner/purchases', [ApiController::class, 'createPurchase']);
        Route::get('/owner/notification-templates', [ApiController::class, 'notificationTemplates']);
        Route::put('/owner/notification-templates/{template}', [ApiController::class, 'updateNotificationTemplate']);
        Route::get('/owner/notification-logs', [ApiController::class, 'notificationLogs']);
        Route::post('/owner/notification-logs/{notificationLog}/resend', [ApiController::class, 'resendNotification']);
        Route::get('/owner/service-rates', [ApiController::class, 'serviceRates']);
        Route::post('/owner/service-rates', [ApiController::class, 'createServiceRate']);
        Route::put('/owner/service-rates/{serviceRate}', [ApiController::class, 'updateServiceRate']);
        Route::delete('/owner/service-rates/{serviceRate}', [ApiController::class, 'deleteServiceRate']);
    });
    Route::middleware(['auth', 'password.changed', 'role:admin'])->group(function () {
        Route::get('/admin/users', [ApiController::class, 'users']);
        Route::post('/admin/service', [ApiController::class, 'createService']);
        Route::put('/admin/service/assign-teknisi', [ApiController::class, 'assignTechnician']);
        Route::post('/admin/service/payments', [ApiController::class, 'payment']);
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
        Route::get('/admin/receipts/{service}/{type}', [ApiController::class, 'receipt'])->where('type', 'intake|payment');
        Route::put('/admin/service/status', [ApiController::class, 'updateServiceStatus']);
        Route::post('/admin/service/estimate-changes', [ApiController::class, 'proposeEstimateChange']);
        Route::post('/admin/service/photos', [ApiController::class, 'uploadServicePhotos']);
        Route::post('/admin/service/persetujuan', [ApiController::class, 'customerApproval']);
    });
    Route::middleware(['auth', 'password.changed', 'role:admin,teknisi,owner'])->group(function () {
        Route::get('/spareparts', [ApiController::class, 'spareparts']);
        Route::get('/spareparts/{sparepart}', [ApiController::class, 'sparepart']);
        Route::get('/service-rates', [ApiController::class, 'activeServiceRates']);
    });
    Route::middleware(['auth', 'password.changed', 'role:admin,owner'])->group(function () {
        Route::post('/spareparts', [ApiController::class, 'createSparepart']);
        Route::put('/spareparts/{sparepart}', [ApiController::class, 'updateSparepart']);
        Route::delete('/spareparts/{sparepart}', [ApiController::class, 'deleteSparepart']);
    });

    Route::post('/customer/login', [ApiController::class, 'customerLogin'])->middleware('throttle:customer-code');
    Route::get('/customer/me', [ApiController::class, 'customerMe']);
    Route::get('/customer/status-updates', [ApiController::class, 'customerStatusUpdates']);
    Route::post('/customer/logout', [ApiController::class, 'customerLogout']);
    Route::post('/customer/approval', [ApiController::class, 'customerApproval'])->middleware('throttle:10,1');
    Route::post('/customer/persetujuan', [ApiController::class, 'customerApproval'])->middleware('throttle:10,1');
    Route::post('/customer/estimate-approval', [ApiController::class, 'customerEstimateApproval'])->middleware('throttle:10,1');
    Route::get('/tracking', [ApiController::class, 'publicTracking'])->middleware('throttle:customer-code');
    Route::get('/tracking-link/{trackingKey}', [ApiController::class, 'publicTrackingLink'])
        ->where('trackingKey', '[A-Za-z0-9]{64}')
        ->middleware('throttle:customer-code');
    Route::get('/customer/{identifier}', [ApiController::class, 'publicTracking'])->where('identifier', '[A-Za-z0-9-]{1,32}')->middleware('throttle:customer-code');
    Route::get('/tracking/{identifier}', [ApiController::class, 'publicTracking'])->where('identifier', '[A-Za-z0-9-]{1,32}')->middleware('throttle:customer-code');
    Route::post('/tracking/persetujuan', [ApiController::class, 'customerApproval'])->middleware('throttle:10,1');
});
