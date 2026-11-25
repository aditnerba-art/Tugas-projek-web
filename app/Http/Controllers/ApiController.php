<?php

namespace App\Http\Controllers;

use App\Events\CustomerServiceStatusChanged;
use App\Http\Requests\AdminCustomersRequest;
use App\Http\Requests\AssignTechnicianRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\CreatePurchaseRequest;
use App\Http\Requests\CreateEstimateChangeRequest;
use App\Http\Requests\CreateServiceRequest;
use App\Http\Requests\CustomerApprovalRequest;
use App\Http\Requests\CustomerEstimateApprovalRequest;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\PublicTrackingRequest;
use App\Http\Requests\SparepartRequest;
use App\Http\Requests\SparepartsIndexRequest;
use App\Http\Requests\ServiceRateRequest;
use App\Http\Requests\StaffLoginRequest;
use App\Http\Requests\StaffStoreRequest;
use App\Http\Requests\StaffUpdateRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Requests\UpdateServiceStatusRequest;
use App\Http\Requests\UploadServicePhotoRequest;
use App\Http\Requests\WarrantyPresetRequest;
use App\Jobs\SendWhatsAppMessage;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerSession;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Service;
use App\Models\ServiceAccessToken;
use App\Models\ServiceEstimateChange;
use App\Models\ServiceItem;
use App\Models\ServicePhoto;
use App\Models\ServiceRate;
use App\Models\ServiceStatusLog;
use App\Models\Sparepart;
use App\Models\User;
use App\Models\WarrantyPreset;
use App\Notifications\ServicePanelNotification;
use App\Services\ServicePhotoImageProcessor;
use App\Services\ServiceSchedulingService;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiController extends Controller
{
    private function paginateTable(Request $request, Builder $query, array $sortable, string $defaultSort, int $perPage = 25): LengthAwarePaginator
    {
        $filters = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in($sortable)],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ]);
        $sort = $filters['sort'] ?? $defaultSort;
        $direction = $filters['direction'] ?? 'desc';

        return $query->orderBy($sort, $direction)
            ->paginate((int) ($filters['per_page'] ?? $perPage))
            ->withQueryString();
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'nama' => $user->nama,
            'username' => $user->username,
            'role' => $user->role,
            'must_change_password' => $user->must_change_password,
        ];
    }

    private function logActivity(Request $request, string $action, string $subject, ?array $old = null, ?array $new = null): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->id,
            'aksi' => $action,
            'subjek' => $subject,
            'data_lama' => $old,
            'data_baru' => $new,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }

    private function accessHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function newAccessCode(): string
    {
        do {
            $code = (string) random_int(10000, 99999);
        } while (ServiceAccessToken::where('token_hash', $this->accessHash($code))->exists());

        return $code;
    }

    private function issueAccessCode(Service $service): string
    {
        $code = $this->newAccessCode();
        $service->accessTokens()->create([
            'token_hash' => $this->accessHash($code),
            'expires_at' => now()->addYear(),
        ]);

        return $code;
    }

    private function serviceDetails(Service $service, bool $includeCustomerChannelKey = true): array
    {
        $service->load(['customer', 'technician', 'items.sparepart', 'statusLogs.user', 'photos']);
        $unit = $service->toArray();
        unset($unit['photos']);
        $unit['nama_pelanggan'] = $service->customer->nama;
        $unit['nama_teknisi'] = $service->technician?->nama;
        $unit['pending_estimate_change'] = $service->estimateChanges()
            ->where('status', 'pending')
            ->latest('id')
            ->get()
            ->map(fn (ServiceEstimateChange $change) => $change->only([
                'id', 'previous_estimated_completion_at', 'proposed_estimated_completion_at',
                'additional_days', 'reason', 'status', 'created_at',
            ]))
            ->first();
        $unit['estimate_change_history'] = $service->estimateChanges()
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (ServiceEstimateChange $change) => $change->only([
                'id', 'previous_estimated_completion_at', 'proposed_estimated_completion_at',
                'additional_days', 'reason', 'status', 'responded_at', 'created_at',
            ]))
            ->all();
        if ($includeCustomerChannelKey) {
            $unit['status_channel_key'] = $service->customerStatusChannelKey();
        }
        $warrantyExpiresAt = $service->warranty_expires_at?->toDateString();
        $unit['warranty_active'] = $warrantyExpiresAt !== null
            && $warrantyExpiresAt >= now(config('app.timezone'))->toDateString();
        $photos = $service->photos->map(fn (ServicePhoto $photo) => [
            'id' => $photo->id,
            'kind' => $photo->kind,
            'url' => route('service-photos.show', [
                'photo' => $photo->id,
                'variant' => 'original',
                'tracking_key' => $service->tracking_key,
            ]),
            'thumbnail_url' => route('service-photos.show', [
                'photo' => $photo->id,
                'variant' => 'thumbnail',
                'tracking_key' => $service->tracking_key,
            ]),
            'created_at' => $photo->created_at,
        ])->all();
        $items = $service->items
            ->reject(fn (ServiceItem $item) => $item->approval_status === 'cancelled')
            ->map(fn (ServiceItem $item) => [
            'id' => $item->id,
            'sparepart_id' => $item->sparepart_id,
            'jumlah' => $item->jumlah,
            'harga_satuan' => $item->harga_satuan,
            'subtotal' => $item->subtotal,
            'approval_status' => $item->approval_status,
            'kode_barang' => $item->sparepart->kode_barang,
            'nama_barang' => $item->sparepart->nama_barang,
        ])->all();
        $timeline = $service->statusLogs->map(fn ($log) => [
            'status_lama' => $log->status_lama,
            'status_baru' => $log->status_baru,
            'catatan' => $log->catatan,
            'catatan_teknis' => $log->catatan,
            'user' => $log->user?->nama,
            'user_role' => $log->user?->role,
            'created_at' => $log->created_at,
        ])->all();

        return ['unit' => $unit, 'onderdil' => $items, 'timeline' => $timeline, 'photos' => $photos];
    }

    public function login(StaffLoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! Auth::attempt(['username' => trim($data['username']), 'password' => $data['password'], 'role' => $data['role']])) {
            return response()->json(['status' => 'error', 'message' => 'Username atau kata sandi salah'], 401);
        }

        $request->session()->regenerate();
        $this->logActivity($request, 'login', 'user:'.$request->user()->id, null, [
            'username' => $request->user()->username,
            'role' => $request->user()->role,
        ]);

        return response()->json(['status' => 'success', 'user' => $this->userData($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'success']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['status' => 'success', 'user' => $this->userData($request->user())]);
    }

    public function updateAccount(UpdateAccountRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        if (! Hash::check($data['currentPassword'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Kata sandi saat ini salah'], 401);
        }
        $username = trim($data['newUsername'] ?? '');
        $password = $data['newPassword'] ?? '';
        if ($username === '' && $password === '') {
            return response()->json(['status' => 'error', 'message' => 'Masukkan username atau kata sandi baru'], 400);
        }

        if ($username !== '') {
            $user->username = $username;
        }
        if ($password !== '') {
            $user->password = Hash::make($password);
            $user->must_change_password = false;
        }
        $user->save();
        DB::table('sessions')->where('user_id', $user->id)->where('id', '<>', $request->session()->getId())->delete();
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'user' => $this->userData($user)]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['status' => 'error', 'message' => 'Kata sandi saat ini salah'], 401);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ])->save();
        $this->logActivity($request, 'ubah_kata_sandi', 'user:'.$user->id);
        DB::table('sessions')->where('user_id', $user->id)->where('id', '<>', $request->session()->getId())->delete();
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'user' => $this->userData($user)]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ]);

        return response()->json([
            'status' => 'success',
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'data' => $notifications,
        ]);
    }

    public function markNotificationRead(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json([
            'status' => 'success',
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function markAllNotificationsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['status' => 'success', 'unread_count' => 0]);
    }

    public function staffIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(['admin', 'teknisi'])],
        ]);
        $query = User::whereIn('role', ['admin', 'teknisi']);
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($users) => $users->where('nama', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%"));
        }
        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        $users = $this->paginateTable($request, $query, ['nama', 'username', 'role', 'id'], 'nama', 20);

        return response()->json([
            'status' => 'success',
            'users' => $users->items(),
            'meta' => $this->paginationMeta($users),
        ]);
    }

    public function staffStore(StaffStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::create([...$data, 'nama' => trim($data['nama']), 'username' => trim($data['username'])]);

        return response()->json([
            'status' => 'success',
            'message' => 'Akun '.$user->role.' berhasil ditambahkan',
            'user' => $this->userData($user),
        ], 201);
    }

    public function staffUpdate(StaffUpdateRequest $request, User $staff): JsonResponse
    {
        abort_unless(in_array($staff->role, ['admin', 'teknisi'], true), 404);
        $data = $request->validated();
        $name = $data['nama'] ?? $data['name'] ?? null;
        $username = $data['username'] ?? $data['newUsername'] ?? null;
        $password = $data['password'] ?? $data['newPassword'] ?? null;
        if ($name !== null) {
            $staff->nama = trim($name);
        }
        if ($username !== null && trim($username) !== '') {
            $staff->username = trim($username);
        }
        if ($password) {
            $staff->password = Hash::make($password);
            $staff->must_change_password = true;
        }
        $staff->save();
        if ($password) {
            DB::table('sessions')->where('user_id', $staff->id)->delete();
        }

        return response()->json(['status' => 'success', 'message' => 'Data akun staf berhasil diperbarui', 'user' => $this->userData($staff)]);
    }

    public function staffDestroy(Request $request, User $staff): JsonResponse
    {
        abort_unless(in_array($staff->role, ['admin', 'teknisi'], true), 403);
        if ($staff->id === $request->user()->id) {
            return response()->json(['status' => 'error', 'message' => 'Anda tidak dapat menghapus akun Anda sendiri'], 400);
        }

        DB::transaction(function () use ($staff, $request): void {
            DB::table('sessions')->where('user_id', $staff->id)->delete();
            $staff->assignedServices()->update(['teknisi_id' => null]);
            Purchase::where('created_by', $staff->id)->update(['created_by' => null]);
            $this->logActivity($request, 'hapus_data', 'user:'.$staff->id, [
                'nama' => $staff->nama, 'username' => $staff->username, 'role' => $staff->role,
            ]);
            $staff->delete();
        });

        return response()->json(['status' => 'success', 'message' => 'Akun staf berhasil dihapus']);
    }

    public function technicians(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'jenis_unit' => ['nullable', 'string', 'max:100'],
        ]);
        $unitType = $filters['jenis_unit'] ?? 'Laptop';
        $scheduler = app(ServiceSchedulingService::class);
        $duration = $scheduler->durationEstimate($unitType);
        $query = User::where('role', 'teknisi')
            ->withCount(['assignedServices as beban_kerja' => fn ($services) => $services->whereNotIn('status', ['selesai', 'diambil', 'batal'])])
            ->orderBy('nama');
        if ($request->user()?->role === 'teknisi') {
            $query->whereKey($request->user()->id);
        }

        $technicians = $query->get(['id', 'nama', 'username', 'role'])
            ->map(fn (User $technician) => [
                ...$technician->only(['id', 'nama', 'username', 'role', 'beban_kerja']),
                ...$scheduler->recommendationFor($technician, $duration['days']),
                'durasi_estimasi_hari' => $duration['days'],
                'sumber_durasi' => $duration['source'],
            ])
            ->sortBy('rekomendasi_selesai')
            ->values();

        return response()->json(['status' => 'success', 'data' => $technicians]);
    }

    public function activeServiceRates(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => ServiceRate::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'amount']),
        ]);
    }

    public function serviceRates(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => ServiceRate::query()->orderBy('name')->get(['id', 'name', 'amount', 'active']),
        ]);
    }

    public function createServiceRate(ServiceRateRequest $request): JsonResponse
    {
        $rate = ServiceRate::create($request->validated());
        $this->logActivity($request, 'buat_tarif_jasa', 'service-rate:'.$rate->id, null, $rate->only(['name', 'amount', 'active']));

        return response()->json(['status' => 'success', 'data' => $rate], 201);
    }

    public function updateServiceRate(ServiceRateRequest $request, ServiceRate $serviceRate): JsonResponse
    {
        $old = $serviceRate->only(['name', 'amount', 'active']);
        $serviceRate->update($request->validated());
        $this->logActivity($request, 'ubah_tarif_jasa', 'service-rate:'.$serviceRate->id, $old, $serviceRate->only(['name', 'amount', 'active']));

        return response()->json(['status' => 'success', 'data' => $serviceRate]);
    }

    public function deleteServiceRate(Request $request, ServiceRate $serviceRate): JsonResponse
    {
        if (Service::where('service_rate_id', $serviceRate->id)->exists()) {
            $serviceRate->update(['active' => false]);

            return response()->json(['status' => 'success', 'message' => 'Tarif dinonaktifkan karena sudah dipakai servis.']);
        }

        $this->logActivity($request, 'hapus_data', 'service-rate:'.$serviceRate->id, $serviceRate->only(['name', 'amount', 'active']));
        $serviceRate->delete();

        return response()->json(['status' => 'success', 'message' => 'Tarif jasa berhasil dihapus.']);
    }

    public function activeWarrantyPresets(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => WarrantyPreset::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'days']),
        ]);
    }

    public function ownerWarrantyPresets(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => WarrantyPreset::query()->orderBy('name')->get(['id', 'name', 'days', 'active']),
        ]);
    }

    public function createWarrantyPreset(WarrantyPresetRequest $request): JsonResponse
    {
        $preset = WarrantyPreset::create($request->validated());
        $this->logActivity($request, 'buat_preset_garansi', 'warranty-preset:'.$preset->id, null, $preset->only(['name', 'days', 'active']));

        return response()->json(['status' => 'success', 'data' => $preset], 201);
    }

    public function updateWarrantyPreset(WarrantyPresetRequest $request, WarrantyPreset $warrantyPreset): JsonResponse
    {
        $old = $warrantyPreset->only(['name', 'days', 'active']);
        $warrantyPreset->update($request->validated());
        $this->logActivity($request, 'ubah_preset_garansi', 'warranty-preset:'.$warrantyPreset->id, $old, $warrantyPreset->only(['name', 'days', 'active']));

        return response()->json(['status' => 'success', 'data' => $warrantyPreset]);
    }

    public function deleteWarrantyPreset(Request $request, WarrantyPreset $warrantyPreset): JsonResponse
    {
        if (Service::where('warranty_preset_id', $warrantyPreset->id)->exists()) {
            $warrantyPreset->update(['active' => false]);

            return response()->json(['status' => 'success', 'message' => 'Preset dinonaktifkan karena sudah dipakai servis.']);
        }

        $this->logActivity($request, 'hapus_data', 'warranty-preset:'.$warrantyPreset->id, $warrantyPreset->only(['name', 'days', 'active']));
        $warrantyPreset->delete();

        return response()->json(['status' => 'success', 'message' => 'Preset garansi berhasil dihapus.']);
    }

    public function users(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => User::orderBy('role')->orderBy('nama')->get(['id', 'nama', 'username', 'role', 'created_at'])]);
    }

    public function createService(CreateServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = DB::transaction(function () use ($data, $request): array {
            $nama = preg_replace('/\s+/', ' ', trim($data['nama']));
            $noWa = PhoneNumber::normalize($data['no_wa']);
            $customer = null;

            if (! empty($data['customer_id'])) {
                $customer = Customer::whereKey($data['customer_id'])->first();
                if (! $customer) {
                    abort(422, 'Pelanggan lama tidak ditemukan atau sudah tidak aktif.');
                }
            } else {
                $customer = Customer::where('no_wa', $noWa)->first();
            }

            if (! $customer) {
                $customer = Customer::create([
                    'nama' => $nama,
                    'no_wa' => $noWa,
                    'alamat' => $data['alamat'] ?? null,
                ]);
            }
            do {
                $noNota = 'SRV-'.now()->format('ymd').'-'.strtoupper(Str::random(3));
            } while (Service::where('no_nota', $noNota)->exists());
            $warrantyPreset = null;
            if (! empty($data['warranty_preset_id'])) {
                $warrantyPreset = WarrantyPreset::query()
                    ->where('active', true)
                    ->whereKey($data['warranty_preset_id'])
                    ->first();
                if (! $warrantyPreset) {
                    abort(422, 'Preset garansi tidak aktif atau tidak ditemukan.');
                }
            }
            $schedule = app(ServiceSchedulingService::class)->bestRecommendation(
                (string) ($data['jenis_unit'] ?? 'Lainnya'),
                isset($data['teknisi_id']) ? (int) $data['teknisi_id'] : null,
            );
            $estimatedCompletionAt = $data['estimated_completion_at'] ?? $schedule['rekomendasi_selesai'];
            $service = Service::create([
                'no_nota' => $noNota,
                'customer_id' => $customer->id,
                'teknisi_id' => $data['teknisi_id'] ?? null,
                'jenis_unit' => $data['jenis_unit'] ?? null,
                'merek_tipe' => $data['merek_tipe'],
                'no_seri' => $data['no_seri'] ?? null,
                'keluhan' => $data['keluhan'],
                'kelengkapan' => $data['kelengkapan'] ?? null,
                'accessories' => $data['accessories'] ?? [],
                'physical_condition' => $data['physical_condition'] ?? null,
                'estimated_completion_at' => $estimatedCompletionAt,
                'initial_estimated_completion_at' => $estimatedCompletionAt,
                'warranty_preset_id' => $warrantyPreset?->id,
                'warranty_days' => $warrantyPreset?->days ?? 0,
                'priority' => $data['priority'] ?? 'normal',

            ]);
            $code = $this->issueAccessCode($service);
            $note = 'Unit berhasil didaftarkan di sistem';
            if ($service->teknisi_id) {
                $note .= ' dan ditugaskan kepada teknisi: '.User::find($service->teknisi_id)->nama;
            }
            $service->changeStatus('masuk', $request->user(), $note);

            if ($service->technician) {
                $service->technician->notify(new ServicePanelNotification(
                    'Servis baru ditugaskan',
                    "Servis {$service->no_nota} baru ditugaskan kepada Anda.",
                    'service_assigned',
                    $service->id,
                    $service->no_nota,
                ));
            }

            return [$service, $code, $customer];
        });
        [$service, $code, $customer] = $result;
        $customerLink = route('tracking.public.page', $service->tracking_key);
        $notification = app(WhatsAppNotificationService::class)->queue($service, 'service_registered', [
            'kode' => $code,
            'link' => $customerLink,
        ]);
        $notificationStatus = $notification->fresh()->status;

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil didaftarkan',
            'no_nota' => $service->no_nota,
            'service_id' => $service->id,
            'estimated_completion_at' => $service->estimated_completion_at?->toDateString(),
            'initial_estimated_completion_at' => $service->initial_estimated_completion_at?->toDateString(),
            'kode_akses' => $code,
            'customer_token' => $code,
            'customer_link' => $customerLink,
            'customer_message' => "Halo {$customer->nama}, perangkat Anda sudah diterima untuk servis. Pantau statusnya di {$customerLink}. Kode akses pelanggan Anda: {$code}",
            'whatsapp_sent' => $notificationStatus === 'sent',
            'whatsapp_status' => $notificationStatus,
            'tracking_url' => route('tracking.public.page', $service->tracking_key),
        ]);
    }

    public function assignTechnician(AssignTechnicianRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$service, $oldTechnicianId, $changed] = DB::transaction(function () use ($data, $request): array {
            $service = Service::whereKey($data['service_id'])->lockForUpdate()->firstOrFail();
            $oldTechnicianId = $service->teknisi_id;
            $oldPriority = $service->priority;
            $service->teknisi_id = $data['teknisi_id'] ?? null;
            $service->priority = $data['priority'] ?? $service->priority;
            $service->save();

            $changed = $oldTechnicianId !== $service->teknisi_id || $oldPriority !== $service->priority;
            if ($changed) {
                $this->logActivity($request, 'assign_teknisi', 'service:'.$service->id,
                    ['teknisi_id' => $oldTechnicianId, 'priority' => $oldPriority],
                    ['teknisi_id' => $service->teknisi_id, 'priority' => $service->priority]);
                $service->recordTimelineEvent(
                    'Penugasan teknisi diperbarui: '.($service->technician?->nama ?? 'Belum Ditugaskan').'; prioritas '.strtoupper($service->priority).'.',
                    $request->user(),
                );
            }

            return [$service, $oldTechnicianId, $changed];
        });
        $name = $service->technician?->nama ?? 'Belum Ditugaskan';
        if ($service->teknisi_id && $service->teknisi_id !== $oldTechnicianId) {
            $service->technician->notify(new ServicePanelNotification(
                'Servis baru ditugaskan',
                "Servis {$service->no_nota} baru ditugaskan kepada Anda.",
                'service_assigned',
                $service->id,
                $service->no_nota,
            ));
        }

        return response()->json(['status' => 'success', 'message' => "Teknisi penanggung jawab berhasil ditugaskan: {$name}"]);
    }

    public function updateServiceStatus(UpdateServiceStatusRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$service, $oldStatus, $estimateChanged] = DB::transaction(function () use ($request, $data): array {
            $query = Service::whereKey($data['service_id']);
            if ($request->user()->role === 'teknisi') {
                $query->where('teknisi_id', $request->user()->id);
            }

            $service = $query->lockForUpdate()->firstOrFail();
            $oldStatus = $service->status;
            $oldServiceData = [
                'status' => $oldStatus,
                'biaya_jasa' => $service->biaya_jasa,
                'total_biaya' => $service->total_biaya,
                'estimated_completion_at' => $service->estimated_completion_at?->toDateString(),
            ];
            $oldEstimate = [
                'total_biaya' => (float) $service->total_biaya,
                'estimated_completion_at' => $service->estimated_completion_at?->toDateString(),
            ];

            $rate = null;
            if (! empty($data['service_rate_id'])) {
                $rate = ServiceRate::query()
                    ->where('active', true)
                    ->whereKey($data['service_rate_id'])
                    ->first();
                if (! $rate) {
                    abort(422, 'Tarif jasa tidak aktif atau tidak ditemukan.');
                }
                $service->service_rate_id = $rate->id;
                $service->biaya_jasa = $rate->amount;
            }

            $requestedParts = $data['sparepart_items'] ?? [];
            if (! empty($data['sparepart_id'])) {
                $requestedParts[] = [
                    'sparepart_id' => $data['sparepart_id'],
                    'jumlah' => $data['jumlah_sparepart'],
                ];
            }
            usort($requestedParts, fn (array $first, array $second): int => (int) $first['sparepart_id'] <=> (int) $second['sparepart_id']);
            foreach ($requestedParts as $requestedPart) {
                $sparepart = Sparepart::whereKey($requestedPart['sparepart_id'])->lockForUpdate()->firstOrFail();
                $quantity = (int) $requestedPart['jumlah'];
                if ($sparepart->stok < $quantity) {
                    abort(422, "Stok sparepart \"{$sparepart->nama_barang}\" tidak mencukupi (sisa: {$sparepart->stok})");
                }
                $oldStock = $sparepart->stok;
                $sparepart->decrement('stok', $quantity);
                $this->logActivity($request, 'ubah_stok', 'sparepart:'.$sparepart->id,
                    ['stok' => $oldStock], ['stok' => $sparepart->fresh()->stok]);
                $service->items()->create([
                    'sparepart_id' => $sparepart->id,
                    'jumlah' => $quantity,
                    'harga_modal' => $sparepart->harga_beli,
                    'harga_satuan' => $sparepart->harga_jual,
                    'subtotal' => $sparepart->harga_jual * $quantity,
                    'approval_status' => 'pending_approval',
                ]);
            }

            if ($rate || $requestedParts) {
                $partsTotal = $service->items()->where('approval_status', '<>', 'cancelled')->sum('subtotal');
                $service->total_biaya = (int) $service->biaya_jasa + (int) $partsTotal;
            }
            if (array_key_exists('warranty_days', $data)) {
                abort(422, 'Durasi garansi hanya dapat dipilih dari preset Owner.');
            }
            if (! empty($data['warranty_preset_id'])) {
                $warrantyPreset = WarrantyPreset::query()
                    ->where('active', true)
                    ->whereKey($data['warranty_preset_id'])
                    ->first();
                if (! $warrantyPreset) {
                    abort(422, 'Preset garansi tidak aktif atau tidak ditemukan.');
                }
                $service->warranty_preset_id = $warrantyPreset->id;
                $service->warranty_days = $warrantyPreset->days;
            }
            $service->save();
            $service->syncPaymentSummary();
            $costIncrease = (int) $service->total_biaya > (int) $oldServiceData['total_biaya'];
            $requiresApproval = (bool) $requestedParts || $costIncrease;
            $requestedStatus = $data['status_baru'];
            if ($oldStatus === 'menunggu_persetujuan' && $requestedStatus !== 'menunggu_persetujuan') {
                abort(409, 'Keputusan persetujuan hanya dapat diberikan oleh pelanggan melalui portal.');
            }
            if ($requiresApproval) {
                $requestedStatus = 'menunggu_persetujuan';
            }
            $service->changeStatus($requestedStatus, $request->user(), $data['catatan_teknis'] ?? null);
            if ($service->status === 'selesai' && ! $service->actual_completed_at) {
                $service->forceFill(['actual_completed_at' => now()])->save();
            }
            $estimateChanged = $oldEstimate['total_biaya'] !== (float) $service->total_biaya
                || $oldEstimate['estimated_completion_at'] !== $service->estimated_completion_at?->toDateString();
            if ($estimateChanged) {
                $service->recordTimelineEvent(
                    'Estimasi diperbarui: biaya Rp '.number_format($oldEstimate['total_biaya'], 0, ',', '.')
                    .' menjadi Rp '.number_format((float) $service->total_biaya, 0, ',', '.')
                    .'; selesai '.($oldEstimate['estimated_completion_at']
                        ? Carbon::parse($oldEstimate['estimated_completion_at'])->format('d-m-Y')
                        : 'belum ditentukan')
                    .' menjadi '.($service->estimated_completion_at?->format('d-m-Y') ?? 'belum ditentukan').'.',
                    $request->user(),
                );
            }
            $this->logActivity($request, 'ubah_status_servis', 'service:'.$service->id, $oldServiceData, [
                'status' => $service->status,
                'biaya_jasa' => $service->biaya_jasa,
                'total_biaya' => $service->total_biaya,
            ]);

            return [$service, $oldStatus, $estimateChanged];
        });
        $notifications = app(WhatsAppNotificationService::class);
        $statusChanged = $service->status !== $oldStatus;
        $waitingForApproval = in_array($service->status, ['menunggu_persetujuan', 'menunggu persetujuan'], true);
        if ($waitingForApproval && ($statusChanged || $estimateChanged)) {
            $notifications->queue($service, 'cost_approval');
        } elseif ($estimateChanged && $service->status !== 'selesai') {
            $notifications->queue($service, 'estimate_changed');
        }
        if ($statusChanged && $service->status === 'selesai') {
            if ($estimateChanged) {
                $notifications->queue($service, 'estimate_changed');
            }
            $notifications->queue($service, 'service_ready');
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Status servis berhasil diperbarui',
            'status_baru' => $service->status,
            'biaya_jasa' => $service->biaya_jasa,
            'total_biaya' => $service->total_biaya,
        ]);
    }

    public function cancelPendingServiceItem(Request $request, ServiceItem $serviceItem): JsonResponse
    {
        $service = DB::transaction(function () use ($request, $serviceItem): Service {
            $query = Service::query()->whereKey($serviceItem->service_id);
            if ($request->user()->role === 'teknisi') {
                $query->where('teknisi_id', $request->user()->id);
            }
            $service = $query->lockForUpdate()->firstOrFail();
            if (! in_array($service->status, ['menunggu_persetujuan', 'menunggu persetujuan'], true)) {
                abort(409, 'Item hanya dapat dibatalkan saat servis menunggu persetujuan pelanggan.');
            }
            $item = $service->items()->whereKey($serviceItem->id)->lockForUpdate()->firstOrFail();
            if ($item->approval_status !== 'pending_approval') {
                abort(409, 'Item ini tidak lagi menunggu persetujuan.');
            }

            $sparepart = Sparepart::whereKey($item->sparepart_id)->lockForUpdate()->firstOrFail();
            $oldStock = $sparepart->stok;
            $sparepart->increment('stok', $item->jumlah);
            $item->update(['approval_status' => 'cancelled']);
            $service->total_biaya = (int) $service->biaya_jasa
                + (int) $service->items()->where('approval_status', '<>', 'cancelled')->sum('subtotal');
            $service->save();
            $note = "Sparepart {$sparepart->nama_barang} tidak jadi dipasang; {$item->jumlah} unit dikembalikan ke stok. Total biaya diperbarui menjadi Rp "
                .number_format((int) $service->total_biaya, 0, ',', '.').'.';
            $timeline = $service->recordTimelineEvent($note, $request->user());
            event(new CustomerServiceStatusChanged($timeline));
            $this->logActivity($request, 'pengembalian_stok', 'sparepart:'.$sparepart->id,
                ['stok' => $oldStock], [
                    'stok' => $sparepart->fresh()->stok,
                    'jumlah_dikembalikan' => $item->jumlah,
                    'service_item_id' => $item->id,
                ]);

            return $service;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Item dibatalkan dan stok dikembalikan.',
            'data' => ['service_id' => $service->id, 'total_biaya' => $service->total_biaya],
        ]);
    }

    public function proposeEstimateChange(CreateEstimateChangeRequest $request): JsonResponse
    {
        $data = $request->validated();
        [$service, $change] = DB::transaction(function () use ($data, $request): array {
            $query = Service::query()->whereKey($data['service_id']);
            if ($request->user()->role === 'teknisi') {
                $query->where('teknisi_id', $request->user()->id);
            }
            $service = $query->lockForUpdate()->firstOrFail();
            if (in_array($service->status, ['selesai', 'diambil', 'batal'], true)) {
                abort(409, 'Estimasi tidak dapat diubah setelah servis selesai atau dibatalkan.');
            }
            if ($service->estimateChanges()->where('status', 'pending')->exists()) {
                abort(409, 'Perubahan estimasi sebelumnya masih menunggu keputusan pelanggan.');
            }

            $today = Carbon::today(config('app.timezone'));
            $oldEstimate = $service->estimated_completion_at?->copy() ?? $today->copy();
            $baseDate = $oldEstimate->greaterThan($today) ? $oldEstimate : $today;
            $proposedDate = $baseDate->copy()->addDays((int) $data['additional_days']);
            $change = $service->estimateChanges()->create([
                'proposed_by' => $request->user()->id,
                'previous_estimated_completion_at' => $service->estimated_completion_at?->toDateString(),
                'proposed_estimated_completion_at' => $proposedDate->toDateString(),
                'additional_days' => $data['additional_days'],
                'reason' => trim($data['reason']),
                'status' => 'pending',
            ]);
            $oldLabel = $service->estimated_completion_at?->format('d-m-Y') ?? 'belum ditentukan';
            $note = "Usulan perubahan estimasi: {$oldLabel} menjadi ".$proposedDate->format('d-m-Y')
                ." (+{$data['additional_days']} hari). Alasan: {$change->reason}. Menunggu persetujuan pelanggan.";
            $timeline = $service->recordTimelineEvent($note, $request->user());
            event(new CustomerServiceStatusChanged($timeline));
            $this->logActivity($request, 'usulkan_perubahan_estimasi', 'service:'.$service->id, null, [
                'estimate_change_id' => $change->id,
                'estimated_completion_at' => $proposedDate->toDateString(),
                'additional_days' => $change->additional_days,
                'reason' => $change->reason,
            ]);

            return [$service->load('customer'), $change];
        });

        app(WhatsAppNotificationService::class)->queue($service, 'estimate_approval', [
            'estimasi_lama' => $change->previous_estimated_completion_at?->format('d-m-Y') ?? 'Belum ditentukan',
            'estimasi_baru' => $change->proposed_estimated_completion_at->format('d-m-Y'),
            'tambahan_hari' => (string) $change->additional_days,
            'alasan' => $change->reason,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Usulan perubahan estimasi dikirim untuk persetujuan pelanggan.',
            'data' => $change,
        ], 201);
    }

    public function activeServices(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'customer' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['masuk', 'diperiksa', 'menunggu_persetujuan', 'dikerjakan', 'menunggu_sparepart', 'selesai', 'diambil', 'batal'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'teknisi_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
        $query = Service::with(['customer', 'technician'])
            ->orderByRaw('CASE priority WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', ['urgent', 'high', 'normal']);
        if ($request->user()->role === 'teknisi') {
            $query->where('teknisi_id', $request->user()->id);
            if (! $request->filled('sort')) {
                $query->reorder()
                    ->orderByRaw("CASE WHEN status IN ('selesai', 'diambil', 'batal') THEN 1 ELSE 0 END")
                    ->orderByRaw('CASE WHEN estimated_completion_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('estimated_completion_at')
                    ->orderByRaw('CASE priority WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', ['urgent', 'high', 'normal'])
                    ->orderByDesc('created_at');
            }
        }
        if ($request->user()->role === 'admin') {
            $query->whereHas('customer', fn ($customers) => $customers->where('nama', '<>', 'Pelanggan dihapus')->where('no_wa', 'not like', 'DIHAPUS-%'));
        }
        $statusCounts = (clone $query)
            ->reorder()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $workCounts = [
            'all' => (int) $statusCounts->sum(),
            'not_started' => (int) $statusCounts->only(['masuk', 'diperiksa', 'menunggu_persetujuan'])->sum(),
            'in_progress' => (int) $statusCounts->only(['dikerjakan', 'menunggu_sparepart'])->sum(),
            'completed' => (int) $statusCounts->only(['selesai', 'diambil'])->sum(),
        ];
        $workFilter = $request->validate([
            'work_filter' => ['nullable', Rule::in(['all', 'not_started', 'in_progress', 'completed'])],
        ])['work_filter'] ?? 'all';
        if ($request->user()->role === 'teknisi') {
            match ($workFilter) {
                'not_started' => $query->whereIn('status', ['masuk', 'diperiksa', 'menunggu_persetujuan']),
                'in_progress' => $query->whereIn('status', ['dikerjakan', 'menunggu_sparepart']),
                'completed' => $query->whereIn('status', ['selesai', 'diambil']),
                default => null,
            };
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($services) => $services->where('no_nota', 'like', "%{$search}%")
                ->orWhere('merek_tipe', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customers) => $customers->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_wa', 'like', "%{$search}%")));
        }
        if (! empty($filters['customer'])) {
            $customer = trim($filters['customer']);
            $query->whereHas('customer', fn ($customers) => $customers->where('nama', 'like', "%{$customer}%"));
        }
        foreach (['status', 'teknisi_id', 'customer_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if ($request->filled('sort')) {
            $query->reorder();
        }
        $services = $this->paginateTable(
            $request,
            $query,
            ['created_at', 'status', 'total_biaya', 'priority', 'no_nota'],
            'created_at',
        );
        $data = collect($services->items())->map(fn (Service $service) => [
            ...$service->only([
                'id', 'no_nota', 'jenis_unit', 'merek_tipe', 'status', 'biaya_jasa', 'total_biaya',
                'status_pembayaran', 'metode_pembayaran', 'dp_dibayar', 'nominal_dibayar', 'kembalian',
                'created_at', 'teknisi_id', 'customer_id', 'priority', 'estimated_completion_at', 'warranty_days', 'warranty_preset_id',
                'initial_estimated_completion_at', 'actual_completed_at', 'service_rate_id', 'keluhan',
                'warranty_expires_at', 'accessories', 'physical_condition',
            ]),
            'nama_teknisi' => $service->technician?->nama,
            'nama_pelanggan' => $service->customer->nama,
            'no_wa' => $service->customer->no_wa,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => [...$this->paginationMeta($services), 'work_counts' => $workCounts],
        ]);
    }

    public function payment(PaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $isHandover = $request->is('api/admin/service/pelunasan');
        $type = $data['type'];
        if ($isHandover && $type === 'refund') {
            throw ValidationException::withMessages([
                'type' => 'Refund tidak dapat diproses bersamaan dengan serah terima unit.',
            ]);
        }

        $result = DB::transaction(function () use ($data, $request, $isHandover, $type): array {
            $service = Service::whereKey($data['service_id'])->lockForUpdate()->firstOrFail();
            $oldStatus = $service->status;
            $oldPaymentData = [
                'status_pembayaran' => $service->status_pembayaran,
                'metode_pembayaran' => $service->metode_pembayaran,
                'terbayar' => $service->paidAmount(),
                'piutang' => $service->balanceDue(),
                'status' => $service->status,
            ];
            $amount = (int) ($data['amount'] ?? $data['nominal_dibayar'] ?? 0);
            $method = $data['method'];
            $note = $data['note'] ?? null;
            $change = 0;
            $transactionRecorded = false;

            if ($type === 'refund') {
                if ($amount < 1) {
                    throw ValidationException::withMessages(['amount' => 'Nominal refund harus lebih dari nol.']);
                }
                if ($amount > $service->paidAmount()) {
                    throw ValidationException::withMessages(['amount' => 'Nominal refund melebihi total pembayaran bersih.']);
                }
                $service->payments()->create([
                    'amount' => $amount,
                    'method' => $method,
                    'paid_at' => now(config('app.timezone')),
                    'received_by' => $request->user()->id,
                    'note' => $note,
                    'type' => 'refund',
                ]);
                $transactionRecorded = true;
            } else {
                $balanceDue = $service->balanceDue();
                if (! $isHandover && ($amount < 1 || $balanceDue === 0)) {
                    throw ValidationException::withMessages([
                        'amount' => $balanceDue === 0
                            ? 'Tidak ada tagihan tersisa; gunakan refund jika perlu mengembalikan dana.'
                            : 'Nominal pembayaran harus lebih dari nol.',
                    ]);
                }
                $change = max(0, $amount - $balanceDue);
                $appliedAmount = min($amount, $balanceDue);
                if ($appliedAmount > 0) {
                    $service->payments()->create([
                        'amount' => $appliedAmount,
                        'method' => $method,
                        'paid_at' => now(config('app.timezone')),
                        'received_by' => $request->user()->id,
                        'note' => $note,
                        'type' => 'payment',
                    ]);
                    $transactionRecorded = true;
                }
            }

            $paidAmount = $service->paidAmount();
            $statusPayment = $service->paymentStatus($paidAmount);
            $methodToStore = $transactionRecorded
                ? $method
                : $service->payments()->orderByDesc('paid_at')->orderByDesc('id')->value('method');
            $isTakingUnit = $isHandover || (bool) ($data['ambil_unit'] ?? false);
            if ($isTakingUnit && $service->balanceDue() > 0 && $statusPayment !== 'lunas') {
                throw ValidationException::withMessages([
                    'amount' => 'Pelunasan penuh diperlukan sebelum unit diserahkan.',
                ]);
            }
            $newStatus = $isTakingUnit ? 'diambil' : $oldStatus;
            $warrantyExpiresAt = $newStatus === 'diambil' && $oldStatus !== 'diambil' && $service->warranty_days > 0
                ? now(config('app.timezone'))->addDays($service->warranty_days)->toDateString()
                : $service->warranty_expires_at?->toDateString();

            $service->forceFill([
                'status_pembayaran' => $statusPayment,
                'metode_pembayaran' => $methodToStore,
                'nominal_dibayar' => $paidAmount,
                'dp_dibayar' => $statusPayment === 'sebagian' ? $paidAmount : 0,
                'kembalian' => $change,
                'warranty_expires_at' => $warrantyExpiresAt,
            ])->save();
            $timelineNote = ($type === 'refund' ? 'Refund' : 'Pembayaran').' dicatat: Status ['.strtoupper($statusPayment)."], Metode [{$method}]. Nominal: Rp ".number_format($amount, 0, ',', '.').', Kembalian: Rp '.number_format($change, 0, ',', '.').'.';
            if ($note !== null && $note !== '') {
                $timelineNote .= ' Catatan: '.$note.'.';
            }
            if ($newStatus === 'diambil') {
                $timelineNote .= ' Unit telah diserahkan ke pelanggan.';
            }
            if ($newStatus !== $oldStatus) {
                $service->changeStatus($newStatus, $request->user(), $timelineNote);
            } elseif ($transactionRecorded) {
                $service->recordTimelineEvent($timelineNote, $request->user());
            }
            $this->logActivity($request, 'pembayaran', 'service:'.$service->id, $oldPaymentData, [
                'status_pembayaran' => $service->status_pembayaran,
                'metode_pembayaran' => $service->metode_pembayaran,
                'terbayar' => $paidAmount,
                'piutang' => max(0, (int) round((float) $service->total_biaya) - $paidAmount),
                'kembalian' => $service->kembalian,
                'status' => $service->status,
            ]);

            return [$service, $statusPayment, $methodToStore, $amount, $paidAmount, $change, $newStatus, $type];
        });
        [$service, $statusPayment, $method, $amount, $paidAmount, $change, $newStatus, $type] = $result;

        return response()->json([
            'status' => 'success',
            'message' => ($type === 'refund' ? 'Refund' : 'Pembayaran').' berhasil disimpan ('.strtoupper($statusPayment).')',
            'data' => [
                'service_id' => $service->id,
                'status_pembayaran' => $statusPayment,
                'metode_pembayaran' => $method,
                'nominal_transaksi' => $amount,
                'total_terbayar' => $paidAmount,
                'piutang' => max(0, (int) round((float) $service->total_biaya) - $paidAmount),
                'kembalian' => $change,
                'status_unit' => $newStatus,
                'type' => $type,
                'warranty_expires_at' => $service->warranty_expires_at?->toDateString(),
            ],
        ]);
    }

    public function nota(Request $request, string $noNota): JsonResponse
    {
        $query = Service::with(['customer', 'technician', 'items.sparepart'])->where('no_nota', $noNota);
        if ($request->user()->role === 'teknisi') {
            $query->where('teknisi_id', $request->user()->id);
        }
        $service = $query->firstOrFail();
        $data = $service->toArray();
        $data['nama_pelanggan'] = $service->customer->nama;
        $data['no_wa'] = $service->customer->no_wa;
        $data['alamat'] = $service->customer->alamat;
        $data['nama_teknisi'] = $service->technician?->nama;
        $data['status_timeline'] = $service->statusLogs()->with('user:id,nama,role')->get()->map(fn ($log) => [
            'status_lama' => $log->status_lama,
            'status_baru' => $log->status_baru,
            'catatan' => $log->catatan,
            'user' => $log->user?->nama,
            'user_role' => $log->user?->role,
            'created_at' => $log->created_at,
        ])->all();
        $data['onderdil'] = $service->items
            ->reject(fn (ServiceItem $item) => $item->approval_status === 'cancelled')
            ->map(fn (ServiceItem $item) => [
                'id' => $item->id,
                'jumlah' => $item->jumlah,
                'harga_satuan' => $item->harga_satuan,
                'subtotal' => $item->subtotal,
                'approval_status' => $item->approval_status,
                'nama_barang' => $item->sparepart->nama_barang,
                'kode_barang' => $item->sparepart->kode_barang,
            ]);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function publicTrackingLink(string $trackingKey): JsonResponse
    {
        $service = Service::where('tracking_key', $trackingKey)->firstOrFail();

        return response()->json(['status' => 'success', 'data' => $this->serviceDetails($service, false)]);
    }

    public function uploadServicePhotos(UploadServicePhotoRequest $request): JsonResponse
    {
        $data = $request->validated();
        $serviceQuery = Service::whereKey($data['service_id']);
        if ($request->user()->role === 'teknisi') {
            $serviceQuery->where('teknisi_id', $request->user()->id);
        }
        $service = $serviceQuery->firstOrFail();
        $disk = Storage::disk('service_photos');
        $imageProcessor = app(ServicePhotoImageProcessor::class);
        $savedPaths = [];

        try {
            $photos = DB::transaction(function () use ($data, $request, $service, $disk, $imageProcessor, &$savedPaths) {
                return collect($data['photos'])->map(function ($file) use ($data, $request, $service, $disk, $imageProcessor, &$savedPaths): ServicePhoto {
                    $uploadedContents = file_get_contents($file->getRealPath());
                    if (! is_string($uploadedContents)) {
                        throw new RuntimeException('File foto tidak dapat dibaca.');
                    }

                    $processed = $imageProcessor->process($uploadedContents);
                    $directory = 'service-photos/services/'.$service->id;
                    $name = Str::uuid()->toString();
                    $path = $directory.'/'.$name.'.jpg';
                    $thumbnailPath = $directory.'/thumbs/'.$name.'.jpg';
                    if (! $disk->put($path, $processed['original'])) {
                        throw new RuntimeException('Foto gagal disimpan pada disk privat.');
                    }
                    $savedPaths[] = $path;
                    if (! $disk->put($thumbnailPath, $processed['thumbnail'])) {
                        throw new RuntimeException('Thumbnail gagal disimpan pada disk privat.');
                    }
                    $savedPaths[] = $thumbnailPath;

                    return $service->photos()->create([
                        'uploaded_by' => $request->user()->id,
                        'kind' => $data['kind'],
                        'path' => $path,
                        'thumbnail_path' => $thumbnailPath,
                        'mime_type' => 'image/jpeg',
                        'size' => strlen($processed['original']),
                    ]);
                });
            });
        } catch (Throwable $exception) {
            $disk->delete($savedPaths);
            throw $exception;
        }

        $service->recordTimelineEvent(
            'Foto kondisi '.($data['kind'] === 'intake' ? 'saat penerimaan' : 'setelah selesai').' ditambahkan.',
            $request->user(),
        );

        return response()->json([
            'status' => 'success',
            'data' => $photos->map(fn (ServicePhoto $photo) => [
                'id' => $photo->id,
                'kind' => $photo->kind,
                'thumbnail_url' => route('service-photos.show', [
                    'photo' => $photo->id,
                    'variant' => 'thumbnail',
                    'tracking_key' => $service->tracking_key,
                ]),
            ]),
        ], 201);
    }

    public function receipt(Request $request, Service $service, string $type): View
    {
        abort_unless(in_array($type, ['intake', 'payment'], true), 404);
        $paper = $request->query('paper', '80');
        abort_unless(in_array($paper, ['58', '80'], true), 422);
        $service->load(['customer', 'technician', 'items.sparepart', 'photos']);

        return view('receipts.service', [
            'service' => $service,
            'type' => $type,
            'paper' => $paper,
            'trackingUrl' => route('tracking.public.page', $service->tracking_key),
        ]);
    }

    public function adminCustomers(AdminCustomersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $extraFilters = $request->validate([
            'status' => ['nullable', Rule::in(['diperiksa', 'menunggu_persetujuan', 'dikerjakan', 'menunggu_sparepart', 'selesai', 'diambil', 'batal'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'teknisi_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $queryText = trim((string) ($filters['q'] ?? ''));
        $customersQuery = Customer::with([
            'services' => fn ($services) => $services->with(['items.sparepart', 'accessTokens'])
                ->latest()->limit(100),
        ])->withCount('services')->orderBy('nama');

        if ($queryText !== '') {
            $matchingServiceIds = [];
            $accessToken = null;
            if (preg_match('/^\d{5}$/', $queryText)) {
                $accessToken = ServiceAccessToken::where('token_hash', $this->accessHash($queryText))
                    ->first();
            }
            if ($accessToken) {
                $matchingServiceIds[] = $accessToken->service_id;
            }

            $customersQuery->where(function ($query) use ($queryText, $matchingServiceIds) {
                $query->where('nama', 'like', '%'.$queryText.'%')
                    ->orWhere('no_wa', 'like', '%'.$queryText.'%')
                    ->orWhereHas('services', function ($services) use ($queryText, $matchingServiceIds) {
                        $services->where('no_nota', 'like', '%'.$queryText.'%');
                        if ($matchingServiceIds !== []) {
                            $services->orWhereIn('id', $matchingServiceIds);
                        }
                    });
            });
        }
        if (! empty($extraFilters['status']) || ! empty($extraFilters['teknisi_id']) || ! empty($extraFilters['date_from']) || ! empty($extraFilters['date_to'])) {
            $customersQuery->whereHas('services', function ($services) use ($extraFilters): void {
                foreach (['status', 'teknisi_id'] as $field) {
                    if (! empty($extraFilters[$field])) {
                        $services->where($field, $extraFilters[$field]);
                    }
                }
                if (! empty($extraFilters['date_from'])) {
                    $services->whereDate('created_at', '>=', $extraFilters['date_from']);
                }
                if (! empty($extraFilters['date_to'])) {
                    $services->whereDate('created_at', '<=', $extraFilters['date_to']);
                }
            });
        }
        $customers = $this->paginateTable($request, $customersQuery, ['nama', 'created_at', 'services_count', 'id'], 'nama', 20);
        $data = collect($customers->items())->map(fn (Customer $customer) => [
            'id' => $customer->id, 'nama' => $customer->nama, 'no_wa' => $customer->no_wa,
            'alamat' => $customer->alamat, 'created_at' => $customer->created_at,
            'jumlah_servis' => $customer->services_count,
            'riwayat_servis' => $customer->services->map(fn (Service $service) => [
                'id' => $service->id,
                'no_nota' => $service->no_nota,
                'jenis_unit' => $service->jenis_unit,
                'merek_tipe' => $service->merek_tipe,
                'no_seri' => $service->no_seri,
                'keluhan' => $service->keluhan,
                'kelengkapan' => $service->kelengkapan,
                'status' => $service->status,
                'biaya_jasa' => $service->biaya_jasa,
                'total_biaya' => $service->total_biaya,
                'status_pembayaran' => $service->status_pembayaran,
                'created_at' => $service->created_at,
            ]),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $this->paginationMeta($customers),
        ]);
    }

    public function updateCustomer(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        $customer->update(['nama' => preg_replace('/\s+/', ' ', trim($data['nama'])), 'no_wa' => trim($data['no_wa']), 'alamat' => $data['alamat'] ?? null]);

        return response()->json(['status' => 'success', 'message' => 'Data pelanggan berhasil diperbarui']);
    }

    public function deleteCustomer(Request $request, Customer $customer): JsonResponse
    {
        DB::transaction(function () use ($request, $customer): void {
            $this->logActivity($request, 'hapus_data', 'customer:'.$customer->id, [
                'nama' => $customer->nama, 'no_wa' => $customer->no_wa, 'alamat' => $customer->alamat,
            ]);
            $customer->delete();
        });

        return response()->json(['success' => true, 'message' => 'Pelanggan dipindahkan ke arsip. Riwayat servis tetap aman dan dapat dipulihkan.']);
    }

    public function deleteService(Request $request, Service $service): JsonResponse
    {
        DB::transaction(function () use ($request, $service): void {
            $this->logActivity($request, 'hapus_data', 'service:'.$service->id, [
                'no_nota' => $service->no_nota, 'status' => $service->status,
            ]);
            $service->delete();
        });

        return response()->json(['success' => true, 'message' => 'Data servis berhasil dihapus']);
    }

    public function customerLogin(CustomerLoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $token = ServiceAccessToken::with('service.customer')
            ->where('token_hash', $this->accessHash($data['token']))
            ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        $normalize = fn ($name) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)), 'UTF-8');
        if (! $token || $normalize($token->service->customer->nama) !== $normalize($data['customer_name'])) {
            return response()->json(['status' => 'error', 'message' => 'Nama pelanggan atau kode akses tidak cocok'], 401);
        }
        $session = Str::random(64);
        $request->session()->regenerate();
        $token->customerSessions()->create([
            'session_hash' => hash('sha256', $session),
            'expires_at' => now()->addDays(7),
        ]);
        $request->session()->put('customer_service_id', $token->service_id);
        $request->session()->put('customer_session_hash', hash('sha256', $session));

        return response()->json(['status' => 'success']);
    }

    public function customerMe(Request $request): JsonResponse
    {
        $serviceId = $request->session()->get('customer_service_id');
        $customerSession = CustomerSession::with('accessToken')
            ->where('session_hash', $request->session()->get('customer_session_hash'))
            ->where('expires_at', '>', now())
            ->first();
        if (! $serviceId || ! $customerSession || $customerSession->accessToken->revoked_at || $customerSession->accessToken->expires_at->isPast()) {
            return response()->json(['status' => 'error', 'message' => 'Masukkan kode akses pelanggan untuk melanjutkan'], 401);
        }
        $service = Service::find($serviceId);
        if (! $service) {
            $request->session()->forget('customer_service_id');

            return response()->json(['status' => 'error', 'message' => 'Data servis tidak ditemukan'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $this->serviceDetails($service)]);
    }

    public function customerStatusUpdates(Request $request): Response
    {
        $customerSession = CustomerSession::with('accessToken')
            ->where('session_hash', $request->session()->get('customer_session_hash'))
            ->where('expires_at', '>', now())
            ->first();

        if (! $customerSession || $customerSession->accessToken->revoked_at || $customerSession->accessToken->expires_at->isPast()) {
            return response()->json(['status' => 'error', 'message' => 'Sesi pelanggan tidak berlaku lagi'], 401);
        }

        $service = Service::find($customerSession->accessToken->service_id);
        if (! $service || (int) $request->session()->get('customer_service_id') !== $service->id) {
            return response()->json(['status' => 'error', 'message' => 'Data servis tidak ditemukan'], 404);
        }

        $latestId = $service->statusLogs()->max('id') ?? 0;
        $etag = '"'.hash('sha256', $service->id.'|'.$latestId).'"';
        if ($response = $this->notModifiedResponse($request, $etag)) {
            return $response;
        }

        return response()->json([
            'status' => 'success',
            'data' => $this->serviceDetails($service),
        ])->setEtag($etag)->header('Cache-Control', 'private, no-cache');
    }

    public function serviceStatusUpdates(Request $request): Response
    {
        $user = $request->user();
        $visibleServices = function ($query) use ($user): void {
            if ($user->role === 'teknisi') {
                $query->where('teknisi_id', $user->id);
            } elseif ($user->role === 'admin') {
                $query->whereHas('customer', fn ($customers) => $customers
                    ->where('nama', '<>', 'Pelanggan dihapus')
                    ->where('no_wa', 'not like', 'DIHAPUS-%'));
            }
        };
        $logsQuery = ServiceStatusLog::query()->whereHas('service', $visibleServices);
        $latestId = (clone $logsQuery)->max('id') ?? 0;
        $statusQuery = Service::query();
        $visibleServices($statusQuery);
        $latestServiceUpdate = (clone $statusQuery)->max('updated_at');
        $statusCounts = $statusQuery
            ->select('status', DB::raw('COUNT(id) AS jumlah'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $etag = '"'.hash('sha256', $latestId.'|'.$latestServiceUpdate.'|'.$statusCounts->toJson()).'"';

        if ($response = $this->notModifiedResponse($request, $etag)) {
            return $response;
        }

        $logs = (clone $logsQuery)
            ->with(['service:id,no_nota', 'user:id,nama,role'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (ServiceStatusLog $log) => [
                'service_id' => $log->service_id,
                'no_nota' => $log->service->no_nota,
                'status_lama' => $log->status_lama,
                'status_baru' => $log->status_baru,
                'catatan' => $log->catatan,
                'user' => $log->user?->nama,
                'user_role' => $log->user?->role,
                'created_at' => $log->created_at,
            ]);

        return response()->json([
            'status' => 'success',
            'status_counts' => $statusCounts,
            'logs' => $logs,
        ])->setEtag($etag)->header('Cache-Control', 'private, no-cache');
    }

    private function notModifiedResponse(Request $request, string $etag): ?Response
    {
        $validators = array_map('trim', explode(',', (string) $request->header('If-None-Match')));
        if (! in_array($etag, $validators, true) && ! in_array('*', $validators, true)) {
            return null;
        }

        return response()->noContent(304)
            ->setEtag(trim($etag, '"'))
            ->header('Cache-Control', 'private, no-cache');
    }

    private function customerPortalService(Request $request, ?int $requestedServiceId = null): Service
    {
        abort_if($request->user(), 403, 'Persetujuan harus diberikan melalui sesi portal pelanggan.');

        $customerSession = CustomerSession::with('accessToken')
            ->where('session_hash', $request->session()->get('customer_session_hash'))
            ->where('expires_at', '>', now())
            ->first();
        $accessToken = $customerSession?->accessToken;
        $sessionServiceId = (int) $request->session()->get('customer_service_id');
        if (
            ! $customerSession
            || ! $accessToken
            || $accessToken->revoked_at
            || $accessToken->expires_at->isPast()
            || $sessionServiceId !== (int) $accessToken->service_id
        ) {
            abort(401, 'Masukkan kode akses pelanggan untuk melanjutkan.');
        }
        if ($requestedServiceId !== null && $requestedServiceId !== $sessionServiceId) {
            abort(403, 'Kode akses tidak sesuai dengan servis.');
        }

        return Service::findOrFail($sessionServiceId);
    }

    public function customerLogout(Request $request): JsonResponse
    {
        CustomerSession::where('session_hash', $request->session()->get('customer_session_hash'))->delete();
        $request->session()->forget('customer_service_id');
        $request->session()->forget('customer_session_hash');

        return response()->json(['status' => 'success']);
    }

    public function customerApproval(CustomerApprovalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $portalService = $this->customerPortalService(
            $request,
            isset($data['service_id']) ? (int) $data['service_id'] : null,
        );
        $service = DB::transaction(function () use ($portalService, $data, $request): Service {
            $service = Service::whereKey($portalService->id)->lockForUpdate()->firstOrFail();
            if (! in_array($service->status, ['menunggu_persetujuan', 'menunggu persetujuan'], true)) {
                abort(409, 'Servis tidak sedang menunggu persetujuan');
            }
            $oldStatus = $service->status;
            $newStatus = in_array($data['status_baru'], ['batal', 'batalkan'], true) ? 'batal' : 'dikerjakan';
            $pendingItems = $service->items()
                ->where('approval_status', 'pending_approval')
                ->lockForUpdate()
                ->get();
            if ($newStatus === 'dikerjakan') {
                $service->items()
                    ->where('approval_status', 'pending_approval')
                    ->update(['approval_status' => 'approved']);
            } else {
                foreach ($pendingItems as $item) {
                    $sparepart = Sparepart::whereKey($item->sparepart_id)->lockForUpdate()->firstOrFail();
                    $oldStock = $sparepart->stok;
                    $sparepart->increment('stok', $item->jumlah);
                    $item->update(['approval_status' => 'cancelled']);
                    $this->logActivity($request, 'pengembalian_stok', 'sparepart:'.$sparepart->id,
                        ['stok' => $oldStock], ['stok' => $sparepart->fresh()->stok, 'jumlah_dikembalikan' => $item->jumlah]);
                }
                $service->total_biaya = (int) $service->biaya_jasa
                    + (int) $service->items()->where('approval_status', '<>', 'cancelled')->sum('subtotal');
                $service->save();
            }
            $note = $newStatus === 'dikerjakan'
                ? 'Pelanggan menyetujui estimasi perbaikan sebesar Rp '.number_format((float) $service->total_biaya, 0, ',', '.').'.'
                : 'Pelanggan membatalkan servis melalui portal pelanggan.';
            $service->changeStatus($newStatus, $request->user(), $note);
            $this->logActivity($request, 'ubah_status_servis', 'service:'.$service->id,
                ['status' => $oldStatus], ['status' => $newStatus]);
            if (in_array($newStatus, ['dikerjakan', 'batal'], true)) {
                $action = $newStatus === 'dikerjakan' ? 'menyetujui' : 'membatalkan';
                User::where('role', 'admin')->get()->each(fn (User $admin) => $admin->notify(new ServicePanelNotification(
                    $newStatus === 'dikerjakan' ? 'Pelanggan menyetujui servis' : 'Pelanggan membatalkan servis',
                    "Pelanggan {$service->customer->nama} {$action} servis {$service->no_nota}.",
                    'customer_approval',
                    $service->id,
                    $service->no_nota,
                )));
            }

            return $service;
        });

        return response()->json(['status' => 'success', 'message' => 'Persetujuan pelanggan tersimpan', 'data' => ['service_id' => $service->id, 'status_baru' => $service->status]]);
    }

    public function customerEstimateApproval(CustomerEstimateApprovalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $portalService = $this->customerPortalService($request);
        $result = DB::transaction(function () use ($data, $request, $portalService): array {
            $service = Service::whereKey($portalService->id)->lockForUpdate()->firstOrFail();
            $change = ServiceEstimateChange::query()
                ->whereKey($data['estimate_change_id'])
                ->where('service_id', $service->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($change->status !== 'pending') {
                abort(409, 'Usulan perubahan estimasi ini sudah diputuskan.');
            }

            $change->update([
                'status' => $data['decision'],
                'responded_at' => now(),
            ]);
            if ($data['decision'] === 'approved') {
                $service->estimated_completion_at = $change->proposed_estimated_completion_at;
                $service->save();
                $note = 'Pelanggan menyetujui perubahan estimasi menjadi '
                    .$change->proposed_estimated_completion_at->format('d-m-Y').'.';
            } else {
                $note = 'Pelanggan menolak usulan perubahan estimasi. Estimasi saat ini tetap '
                    .($service->estimated_completion_at?->format('d-m-Y') ?? 'belum ditentukan').'.';
            }
            $timeline = $service->recordTimelineEvent($note, null);
            event(new CustomerServiceStatusChanged($timeline));
            $this->logActivity($request, 'keputusan_perubahan_estimasi', 'service:'.$service->id, [
                'estimate_change_id' => $change->id,
                'status' => 'pending',
            ], [
                'estimate_change_id' => $change->id,
                'status' => $change->status,
            ]);

            return [$service, $change];
        });
        [$service, $change] = $result;

        return response()->json([
            'status' => 'success',
            'message' => $change->status === 'approved'
                ? 'Estimasi baru disetujui dan menjadi tanggal final.'
                : 'Usulan perubahan estimasi ditolak. Estimasi saat ini tetap berlaku.',
            'data' => [
                'service_id' => $service->id,
                'estimate_change_id' => $change->id,
                'status' => $change->status,
                'estimated_completion_at' => $service->estimated_completion_at?->toDateString(),
            ],
        ]);
    }

    public function publicTracking(PublicTrackingRequest $request, ?string $identifier = null): JsonResponse
    {
        $filters = $request->validated();
        $identifier ??= (string) ($filters['kode_akses'] ?? '');
        if ($identifier === '') {
            return response()->json(['status' => 'error', 'message' => 'Kode akses wajib diisi'], 400);
        }

        if (preg_match('/^\d{5}$/', $identifier)) {
            $token = ServiceAccessToken::with('service')
                ->where('token_hash', $this->accessHash($identifier))
                ->whereNull('revoked_at')->where('expires_at', '>', now())->first();
            $service = $token?->service;
        } else {
            $serviceId = $request->session()->get('customer_service_id');
            $service = $serviceId
                ? Service::whereKey($serviceId)->where('no_nota', $identifier)->first()
                : null;
        }
        if (! $service) {
            return response()->json(['status' => 'error', 'message' => 'Kode akses atau nomor nota tidak ditemukan'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $this->serviceDetails($service, false)]);
    }

    public function accessCodes(): JsonResponse
    {
        $services = Service::with('customer')->latest()->get()->map(fn (Service $service) => [
            'service_id' => $service->id,
            'no_nota' => $service->no_nota,
            'nama_pelanggan' => $service->customer->nama,
            'jenis_unit' => $service->jenis_unit,
            'merek_tipe' => $service->merek_tipe,
            'status' => $service->status,
            'has_active_code' => $service->accessTokens()->whereNull('revoked_at')->where('expires_at', '>', now())->exists(),
        ]);

        return response()->json(['status' => 'success', 'data' => $services]);
    }

    public function rotateAccessCode(Service $service): JsonResponse
    {
        $code = DB::transaction(function () use ($service): string {
            $service->accessTokens()->each(fn (ServiceAccessToken $token) => $token->customerSessions()->delete());
            $service->accessTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

            return $this->issueAccessCode($service);
        });

        return response()->json(['status' => 'success', 'message' => 'Kode baru berhasil dibuat. Kode sebelumnya sudah tidak berlaku.', 'data' => ['service_id' => $service->id, 'kode_akses' => $code, 'customer_token' => $code]]);
    }

    public function spareparts(SparepartsIndexRequest $request): JsonResponse
    {
        $extraFilters = $request->validate([
            'stock' => ['nullable', Rule::in(['low', 'available', 'out'])],
        ]);
        $query = Sparepart::query();
        if ($search = $request->validated('search')) {
            $query->where(fn ($parts) => $parts->where('kode_barang', 'like', "%{$search}%")->orWhere('nama_barang', 'like', "%{$search}%"));
        }
        if (($extraFilters['stock'] ?? null) === 'low') {
            $query->whereColumn('stok', '<=', 'stok_minimum');
        } elseif (($extraFilters['stock'] ?? null) === 'available') {
            $query->where('stok', '>', 0);
        } elseif (($extraFilters['stock'] ?? null) === 'out') {
            $query->where('stok', 0);
        }

        $parts = $this->paginateTable($request, $query, ['nama_barang', 'kode_barang', 'stok', 'harga_beli', 'harga_jual', 'id'], 'nama_barang');
        $data = collect($parts->items())->map(function (Sparepart $part): Sparepart {
            $part->setAttribute('stok_rendah', $part->stok <= $part->stok_minimum);

            return $part;
        });
        $summaryQuery = clone $query;
        $summary = [
            'total' => (clone $summaryQuery)->count(),
            'low_stock' => (clone $summaryQuery)->whereColumn('stok', '<=', 'stok_minimum')->count(),
            'available' => (clone $summaryQuery)->where('stok', '>', 0)->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $this->paginationMeta($parts),
            'summary' => $summary,
        ]);
    }

    public function sparepart(Sparepart $sparepart): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => $sparepart]);
    }

    public function createSparepart(SparepartRequest $request): JsonResponse
    {
        $data = $request->validated();
        $part = Sparepart::create([
            ...$data,
            'stok' => $data['stok'] ?? 0,
            'harga_beli' => $data['harga_beli'] ?? 0,
            'harga_jual' => $data['harga_jual'] ?? 0,
        ]);
        $this->logActivity($request, 'ubah_stok', 'sparepart:'.$part->id, null, [
            'stok' => $part->stok, 'kode_barang' => $part->kode_barang,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Sparepart baru berhasil ditambahkan', 'data' => $part]);
    }

    public function updateSparepart(SparepartRequest $request, Sparepart $sparepart): JsonResponse
    {
        $data = $request->validated();
        $old = ['stok' => $sparepart->stok, 'kode_barang' => $sparepart->kode_barang];
        $sparepart->update($data);
        if ((int) $old['stok'] !== (int) $sparepart->stok) {
            $this->logActivity($request, 'ubah_stok', 'sparepart:'.$sparepart->id, $old, [
                'stok' => $sparepart->stok, 'kode_barang' => $sparepart->kode_barang,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Data sparepart berhasil diperbarui']);
    }

    public function deleteSparepart(Request $request, Sparepart $sparepart): JsonResponse
    {
        if ($sparepart->serviceItems()->exists() || $sparepart->purchaseDetails()->exists()) {
            return response()->json(['status' => 'error', 'message' => "Sparepart \"{$sparepart->nama_barang}\" tidak dapat dihapus karena tercatat dalam riwayat servis atau pengadaan."], 400);
        }
        DB::transaction(function () use ($request, $sparepart): void {
            $this->logActivity($request, 'hapus_data', 'sparepart:'.$sparepart->id, [
                'kode_barang' => $sparepart->kode_barang,
                'nama_barang' => $sparepart->nama_barang,
                'stok' => $sparepart->stok,
            ]);
            $sparepart->delete();
        });

        return response()->json(['status' => 'success', 'message' => "Sparepart \"{$sparepart->nama_barang}\" berhasil dihapus"]);
    }

    private function paymentRecognitionBaseQuery(?Carbon $from = null, ?Carbon $to = null)
    {
        $serviceCosts = DB::table('service_items')
            ->select('service_id')
            ->selectRaw('SUM(jumlah * harga_modal) as modal')
            ->groupBy('service_id');
        $signedAmount = "CASE WHEN p.type = 'refund' THEN -p.amount ELSE p.amount END";
        $modalShare = "CASE WHEN s.total_biaya > 0 THEN ({$signedAmount}) * COALESCE(service_costs.modal, 0) / s.total_biaya ELSE 0 END";
        $query = DB::table('payments as p')
            ->join('services as s', 's.id', '=', 'p.service_id')
            ->leftJoinSub($serviceCosts, 'service_costs', 'service_costs.service_id', '=', 's.id')
            ->select([
                'p.service_id',
                'p.paid_at',
                'p.received_by',
                'p.method',
                's.teknisi_id as technician_id',
            ])
            ->selectRaw("{$signedAmount} as signed_amount")
            ->selectRaw("{$modalShare} as modal_share");
        if ($from !== null && $to !== null) {
            $query->whereBetween('p.paid_at', [$from, $to]);
        }

        return $query;
    }

    private function paymentTotalsSubquery()
    {
        return DB::table('payments')
            ->select('service_id')
            ->selectRaw("SUM(CASE WHEN type = 'refund' THEN -amount ELSE amount END) as net_paid")
            ->groupBy('service_id');
    }

    private function cashSummaryQuery(Carbon $from, Carbon $to)
    {
        return DB::table('payments as p')
            ->leftJoin('users as cashiers', 'cashiers.id', '=', 'p.received_by')
            ->whereBetween('p.paid_at', [$from, $to])
            ->selectRaw("DATE(p.paid_at) as tanggal, p.received_by, COALESCE(cashiers.nama, 'Kasir tidak diketahui') as kasir")
            ->selectRaw("SUM(CASE WHEN p.method = 'tunai' THEN CASE WHEN p.type = 'refund' THEN -p.amount ELSE p.amount END ELSE 0 END) as tunai")
            ->selectRaw("SUM(CASE WHEN p.method <> 'tunai' THEN CASE WHEN p.type = 'refund' THEN -p.amount ELSE p.amount END ELSE 0 END) as non_tunai")
            ->selectRaw("SUM(CASE WHEN p.type = 'refund' THEN -p.amount ELSE p.amount END) as total_bersih")
            ->selectRaw('COUNT(p.id) as jumlah_transaksi')
            ->groupByRaw('DATE(p.paid_at), p.received_by, cashiers.nama')
            ->orderBy('tanggal')
            ->orderBy('kasir')
            ->get()
            ->map(fn (object $row) => [
                'tanggal' => $row->tanggal,
                'kasir_id' => $row->received_by,
                'kasir' => $row->kasir,
                'tunai' => (int) $row->tunai,
                'non_tunai' => (int) $row->non_tunai,
                'total_bersih' => (int) $row->total_bersih,
                'jumlah_transaksi' => (int) $row->jumlah_transaksi,
            ]);
    }

    public function ownerDashboard(): JsonResponse
    {
        $services = Service::query();
        $recognizedPayments = DB::query()->fromSub($this->paymentRecognitionBaseQuery(), 'recognized_payments');
        $revenue = (float) $recognizedPayments->sum('signed_amount');
        $recognizedPartCost = (float) $recognizedPayments->sum('modal_share');
        $paymentTotals = $this->paymentTotalsSubquery();
        $serviceBalances = Service::query()
            ->leftJoinSub($paymentTotals, 'payment_totals', 'payment_totals.service_id', '=', 'services.id');
        $summary = [
            'total_unit' => (clone $services)->count(),
            'unit_selesai' => (clone $services)->whereIn('status', ['selesai', 'diambil'])->count(),
            'total_pendapatan' => $revenue,
            'total_dp' => (float) (clone $serviceBalances)
                ->whereRaw('COALESCE(payment_totals.net_paid, 0) > 0')
                ->whereRaw('COALESCE(payment_totals.net_paid, 0) < services.total_biaya')
                ->sum('payment_totals.net_paid'),
            'laba_kotor' => $revenue - $recognizedPartCost,
            'total_piutang' => (float) (clone $serviceBalances)
                ->whereRaw('services.total_biaya > COALESCE(payment_totals.net_paid, 0)')
                ->sum(DB::raw('services.total_biaya - COALESCE(payment_totals.net_paid, 0)')),
        ];
        $statusCounts = Service::select('status', DB::raw('COUNT(id) AS jumlah'))->groupBy('status')->get();

        return response()->json(['status' => 'success', 'data' => [
            'summary' => $summary,
            'statusCounts' => $statusCounts,
            'purchases' => ['total_transaksi_pengadaan' => Purchase::count(), 'total_pengeluaran_pengadaan' => Purchase::sum('total_biaya')],
            'spareparts' => [
                'total_jenis_barang' => Sparepart::count(),
                'total_stok_tersedia' => Sparepart::sum('stok'),
                'total_nilai_aset_barang' => DB::table('spareparts')->sum(DB::raw('stok * harga_beli')),
            ],
        ]]);
    }

    private function ownerReportParameters(Request $request): array
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'period' => ['nullable', Rule::in(['day', 'month'])],
        ]);
        $timezone = config('app.timezone');
        $from = Carbon::parse($filters['date_from'] ?? now($timezone)->startOfMonth()->toDateString(), $timezone)->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now($timezone)->toDateString(), $timezone)->endOfDay();
        if ($from->diffInDays($to) > 366) {
            abort(422, 'Rentang laporan maksimal 366 hari.');
        }

        return [$from, $to, $filters['period'] ?? 'day'];
    }

    private function ownerReportMetrics(Carbon $from, Carbon $to, string $period): array
    {
        $services = Service::query()->whereBetween('services.created_at', [$from, $to]);
        $paymentRows = DB::query()->fromSub($this->paymentRecognitionBaseQuery($from, $to), 'recognized_payments')
            ->selectRaw('DATE(paid_at) as paid_date, service_id')
            ->selectRaw('SUM(signed_amount) as omzet')
            ->selectRaw('SUM(signed_amount - modal_share) as laba_kotor')
            ->groupByRaw('DATE(paid_at), service_id')
            ->orderBy('paid_date')
            ->get();
        $periodStats = [];
        $billed = 0;
        $partCost = 0;
        $grossProfit = 0;
        foreach ($paymentRows as $row) {
            $date = Carbon::parse($row->paid_date, config('app.timezone'));
            $key = $period === 'month' ? $date->format('Y-m') : $date->toDateString();
            $periodStats[$key] ??= [
                'omzet' => 0,
                'modal' => 0,
                'laba_kotor' => 0,
            ];
            $revenue = (float) $row->omzet;
            $profit = (float) $row->laba_kotor;
            $modal = $revenue - $profit;
            $periodStats[$key]['omzet'] += $revenue;
            $periodStats[$key]['modal'] += $modal;
            $periodStats[$key]['laba_kotor'] += $profit;
            $billed += $revenue;
            $partCost += $modal;
            $grossProfit += $profit;
        }
        $serviceCounts = [];
        foreach ((clone $services)
            ->selectRaw('DATE(services.created_at) as service_date, COUNT(*) as jumlah_servis')
            ->groupByRaw('DATE(services.created_at)')
            ->get() as $row) {
            $date = Carbon::parse($row->service_date, config('app.timezone'));
            $key = $period === 'month' ? $date->format('Y-m') : $date->toDateString();
            $serviceCounts[$key] = ($serviceCounts[$key] ?? 0) + (int) $row->jumlah_servis;
        }
        $series = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $key = $period === 'month' ? $cursor->format('Y-m') : $cursor->toDateString();
            $record = $periodStats[$key] ?? [];
            $series[$key] = [
                'label' => $period === 'month' ? $cursor->copy()->startOfMonth()->format('M Y') : $cursor->format('d M'),
                'omzet' => (float) ($record['omzet'] ?? 0),
                'modal' => (float) ($record['modal'] ?? 0),
                'laba_kotor' => (float) ($record['laba_kotor'] ?? 0),
                'jumlah_servis' => $serviceCounts[$key] ?? 0,
            ];
            if ($period === 'month') {
                $cursor->endOfMonth();
            }
            $cursor->addDay();
        }
        $statusCounts = (clone $services)->select('status', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status')->orderBy('status')->get();
        $technicianCounts = (clone $services)
            ->leftJoin('users as technicians', 'technicians.id', '=', 'services.teknisi_id')
            ->selectRaw('technicians.id as teknisi_id')
            ->selectRaw("COALESCE(technicians.nama, 'Belum ditugaskan') as teknisi, COUNT(services.id) as jumlah_servis, 0 as omzet")
            ->groupBy('technicians.id', 'technicians.nama')
            ->orderByDesc('jumlah_servis')
            ->get()
            ->keyBy('teknisi_id');
        $technicianPayments = DB::query()->fromSub($this->paymentRecognitionBaseQuery($from, $to), 'recognized_payments')
            ->leftJoin('users as technicians', 'technicians.id', '=', 'recognized_payments.technician_id')
            ->selectRaw('recognized_payments.technician_id as teknisi_id')
            ->selectRaw("COALESCE(technicians.nama, 'Belum ditugaskan') as teknisi, SUM(recognized_payments.signed_amount) as omzet")
            ->groupBy('recognized_payments.technician_id', 'technicians.nama')
            ->get();
        foreach ($technicianPayments as $paymentSummary) {
            $technician = $technicianCounts->get($paymentSummary->teknisi_id);
            if ($technician === null) {
                $technician = (object) [
                    'teknisi_id' => $paymentSummary->teknisi_id,
                    'teknisi' => $paymentSummary->teknisi,
                    'jumlah_servis' => 0,
                    'omzet' => 0,
                ];
                $technicianCounts->put($paymentSummary->teknisi_id, $technician);
            }
            $technician->omzet = (float) $paymentSummary->omzet;
        }
        $technicianCounts = $technicianCounts->sortByDesc('jumlah_servis')->values();
        $topParts = DB::table('service_items')
            ->join('services', 'services.id', '=', 'service_items.service_id')
            ->leftJoin('spareparts', 'spareparts.id', '=', 'service_items.sparepart_id')
            ->whereBetween('services.created_at', [$from, $to])
            ->selectRaw("COALESCE(spareparts.nama_barang, 'Sparepart dihapus') as nama_barang, SUM(service_items.jumlah) as jumlah_terjual, SUM(service_items.subtotal) as nilai_jual, SUM(service_items.jumlah * service_items.harga_modal) as modal")
            ->groupBy('service_items.sparepart_id', 'spareparts.nama_barang')
            ->orderByDesc('jumlah_terjual')
            ->limit(10)
            ->get()
            ->map(fn (object $part) => [
                'nama_barang' => $part->nama_barang,
                'jumlah_terjual' => (int) $part->jumlah_terjual,
                'nilai_jual' => (float) $part->nilai_jual,
                'modal' => (float) $part->modal,
            ]);
        $receivableTotal = (float) DB::query()
            ->fromSub($this->receivablesQuery($from, $to), 'receivables')
            ->sum('receivables.piutang');
        $cashSummary = $this->cashSummaryQuery($from, $to);
        $estimateRows = Service::query()
            ->whereIn('status', ['selesai', 'diambil'])
            ->whereNotNull('initial_estimated_completion_at')
            ->whereNotNull('actual_completed_at')
            ->whereBetween('actual_completed_at', [$from, $to])
            ->get(['initial_estimated_completion_at', 'actual_completed_at']);
        $estimateSamples = $estimateRows->map(function (Service $service): array {
            $estimate = Carbon::parse($service->initial_estimated_completion_at)->startOfDay();
            $actual = Carbon::parse($service->actual_completed_at)->startOfDay();
            $deviation = (int) $estimate->diffInDays($actual, false);

            return ['on_time' => $actual->lte($estimate), 'absolute_deviation' => abs($deviation)];
        });
        $estimateSampleCount = $estimateSamples->count();

        return [
            'period' => $period,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'summary' => [
                'omzet' => $billed,
                'modal_sparepart' => $partCost,
                'laba_kotor' => $grossProfit,
                'jumlah_servis' => (clone $services)->count(),
                'piutang' => $receivableTotal,
                'akurasi_estimasi_awal' => [
                    'sampel' => $estimateSampleCount,
                    'tepat_waktu' => $estimateSamples->where('on_time', true)->count(),
                    'persentase_tepat_waktu' => $estimateSampleCount
                        ? round($estimateSamples->where('on_time', true)->count() / $estimateSampleCount * 100, 1)
                        : null,
                    'rata_rata_selisih_hari' => $estimateSampleCount
                        ? round($estimateSamples->sum('absolute_deviation') / $estimateSampleCount, 1)
                        : null,
                ],
            ],
            'series' => array_values($series),
            'status_counts' => $statusCounts,
            'technician_counts' => $technicianCounts,
            'top_parts' => $topParts,
            'cash_summary' => $cashSummary,
        ];
    }

    private function receivablesQuery(Carbon $from, Carbon $to): Builder
    {
        return Service::query()
            ->leftJoinSub($this->paymentTotalsSubquery(), 'payment_totals', 'payment_totals.service_id', '=', 'services.id')
            ->with('customer:id,nama,no_wa')
            ->select('services.*')
            ->selectRaw('COALESCE(payment_totals.net_paid, 0) as total_terbayar')
            ->selectRaw('CASE WHEN services.total_biaya > COALESCE(payment_totals.net_paid, 0) THEN services.total_biaya - COALESCE(payment_totals.net_paid, 0) ELSE 0 END as piutang')
            ->whereBetween('services.created_at', [$from, $to])
            ->whereRaw('services.total_biaya > COALESCE(payment_totals.net_paid, 0)');
    }

    public function ownerReport(Request $request): JsonResponse
    {
        [$from, $to, $period] = $this->ownerReportParameters($request);
        $report = $this->ownerReportMetrics($from, $to, $period);
        $receivables = $this->paginateTable(
            $request,
            $this->receivablesQuery($from, $to),
            ['created_at', 'total_biaya', 'no_nota', 'id'],
            'created_at',
            20,
        );
        $report['receivables'] = collect($receivables->items())->map(fn (Service $service) => [
            'no_nota' => $service->no_nota,
            'nama_pelanggan' => $service->customer?->nama ?? '-',
            'no_wa' => $service->customer?->no_wa,
            'status_pembayaran' => $service->status_pembayaran,
            'total_biaya' => (float) $service->total_biaya,
            'terbayar' => (int) $service->total_terbayar,
            'piutang' => (int) $service->piutang,
            'tanggal' => $service->created_at?->toDateString(),
        ]);
        $report['receivable_meta'] = $this->paginationMeta($receivables);

        return response()->json(['status' => 'success', 'data' => $report]);
    }

    public function ownerReportCsv(Request $request): Response
    {
        [$from, $to, $period] = $this->ownerReportParameters($request);
        $report = $this->ownerReportMetrics($from, $to, $period);
        $filename = 'laporan-owner-'.$from->toDateString().'-'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($report, $from, $to): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Periode pembayaran', 'Omzet pembayaran bersih', 'Modal sparepart proporsional', 'Laba kotor', 'Servis terdaftar'], ';');
            foreach ($report['series'] as $row) {
                fputcsv($output, [
                    $row['label'],
                    $row['omzet'],
                    $row['modal'],
                    $row['laba_kotor'],
                    $row['jumlah_servis'],
                ], ';');
            }
            fputcsv($output, [], ';');
            fputcsv($output, ['Akurasi estimasi awal servis selesai'], ';');
            fputcsv($output, ['Sampel', 'Tepat waktu', 'Persentase tepat waktu', 'Rata-rata selisih absolut (hari)'], ';');
            fputcsv($output, [
                $report['summary']['akurasi_estimasi_awal']['sampel'],
                $report['summary']['akurasi_estimasi_awal']['tepat_waktu'],
                $report['summary']['akurasi_estimasi_awal']['persentase_tepat_waktu'],
                $report['summary']['akurasi_estimasi_awal']['rata_rata_selisih_hari'],
            ], ';');
            fputcsv($output, [], ';');
            fputcsv($output, ['Status servis', 'Jumlah'], ';');
            foreach ($report['status_counts'] as $row) {
                fputcsv($output, [$row->status, $row->jumlah], ';');
            }
            fputcsv($output, [], ';');
            fputcsv($output, ['Teknisi', 'Jumlah servis', 'Omzet'], ';');
            foreach ($report['technician_counts'] as $row) {
                fputcsv($output, [$row->teknisi, $row->jumlah_servis, $row->omzet], ';');
            }
            fputcsv($output, [], ';');
            fputcsv($output, ['Sparepart terlaris', 'Jumlah terjual', 'Nilai jual', 'Modal'], ';');
            foreach ($report['top_parts'] as $part) {
                fputcsv($output, [$part['nama_barang'], $part['jumlah_terjual'], $part['nilai_jual'], $part['modal']], ';');
            }
            fputcsv($output, [], ';');
            fputcsv($output, ['Rekonsiliasi kas harian per kasir'], ';');
            fputcsv($output, ['Tanggal', 'Kasir', 'Tunai bersih', 'Non-tunai bersih', 'Total bersih', 'Jumlah transaksi'], ';');
            foreach ($report['cash_summary'] as $cash) {
                fputcsv($output, [
                    $cash['tanggal'],
                    $cash['kasir'],
                    $cash['tunai'],
                    $cash['non_tunai'],
                    $cash['total_bersih'],
                    $cash['jumlah_transaksi'],
                ], ';');
            }
            fputcsv($output, [], ';');
            fputcsv($output, ['Piutang / belum lunas'], ';');
            fputcsv($output, ['Nota', 'Pelanggan', 'Tanggal servis', 'Status pembayaran', 'Tagihan', 'Piutang'], ';');
            $this->receivablesQuery($from, $to)->with('customer:id,nama')->orderBy('created_at')
                ->chunk(500, function ($services) use ($output): void {
                    foreach ($services as $service) {
                        fputcsv($output, [
                            $service->no_nota,
                            $service->customer?->nama ?? '-',
                            $service->created_at?->toDateString(),
                            $service->status_pembayaran,
                            $service->total_biaya,
                            $service->piutang,
                        ], ';');
                    }
                });

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function ownerReportPrint(Request $request): View
    {
        [$from, $to, $period] = $this->ownerReportParameters($request);
        $report = $this->ownerReportMetrics($from, $to, $period);
        $receivables = $this->receivablesQuery($from, $to)->orderByDesc('created_at')->get();

        return view('owner-report-print', [
            'report' => $report,
            'receivables' => $receivables,
        ]);
    }

    public function purchases(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $query = Purchase::with('items.sparepart');
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->where(fn ($purchases) => $purchases->where('no_pembelian', 'like', "%{$search}%")
                ->orWhere('supplier', 'like', "%{$search}%"));
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('tanggal_pembelian', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('tanggal_pembelian', '<=', $filters['date_to']);
        }
        $purchases = $this->paginateTable($request, $query, ['tanggal_pembelian', 'total_biaya', 'supplier', 'id'], 'tanggal_pembelian');
        $data = collect($purchases->items())->map(fn (Purchase $purchase) => [
            ...$purchase->only(['id', 'no_pembelian', 'supplier', 'tanggal_pembelian', 'total_biaya', 'keterangan', 'created_at']),
            'items' => $purchase->items->map(fn (PurchaseDetail $detail) => [
                'id' => $detail->id, 'sparepart_id' => $detail->sparepart_id,
                'jumlah' => $detail->jumlah, 'harga_beli_satuan' => $detail->harga_beli_satuan,
                'subtotal' => $detail->subtotal, 'kode_barang' => $detail->sparepart->kode_barang,
                'nama_barang' => $detail->sparepart->nama_barang, 'stok_sekarang' => $detail->sparepart->stok,
            ]),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $this->paginationMeta($purchases),
        ]);
    }

    public function notificationTemplates(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => MessageTemplate::orderBy('id')->get(['id', 'key', 'name', 'body']),
        ]);
    }

    public function updateNotificationTemplate(Request $request, MessageTemplate $template): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $template->update($data);

        return response()->json(['status' => 'success', 'data' => $template->only(['id', 'key', 'name', 'body'])]);
    }

    public function notificationLogs(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['pending', 'sent', 'failed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $query = NotificationLog::with('service:id,no_nota');
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($logs) => $logs->where('recipient', 'like', "%{$search}%")
                ->orWhere('message', 'like', "%{$search}%")
                ->orWhereHas('service', fn ($services) => $services->where('no_nota', 'like', "%{$search}%")));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        $logs = $this->paginateTable($request, $query, ['created_at', 'status', 'recipient', 'id'], 'created_at');

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'meta' => $this->paginationMeta($logs),
        ]);
    }

    public function resendNotification(NotificationLog $notificationLog): JsonResponse
    {
        if ($notificationLog->status !== 'failed') {
            return response()->json(['status' => 'error', 'message' => 'Hanya pesan yang gagal dapat dikirim ulang.'], 422);
        }

        $notificationLog->update(['status' => 'pending', 'error' => null, 'sent_at' => null]);
        SendWhatsAppMessage::dispatch($notificationLog->id);

        return response()->json(['status' => 'success', 'message' => 'Pesan dimasukkan kembali ke antrean.']);
    }

    public function createPurchase(CreatePurchaseRequest $request): JsonResponse
    {
        $data = $request->validated();
        $purchase = DB::transaction(function () use ($data, $request): Purchase {
            $dateCode = str_replace('-', '', $data['tanggal_pembelian']);
            do {
                $number = 'PO-'.$dateCode.'-'.random_int(1000, 9999);
            } while (Purchase::where('no_pembelian', $number)->exists());
            $purchase = Purchase::create([
                'no_pembelian' => $number, 'supplier' => $data['supplier'],
                'tanggal_pembelian' => $data['tanggal_pembelian'], 'total_biaya' => 0,
                'keterangan' => $data['keterangan'] ?? null, 'created_by' => $request->user()->id,
            ]);
            $total = 0;
            foreach ($data['items'] as $item) {
                $part = null;
                if (! empty($item['is_new'])) {
                    $part = Sparepart::firstOrCreate(
                        ['kode_barang' => $item['kode_barang']],
                        [
                            'nama_barang' => $item['nama_barang'], 'stok' => 0,
                            'harga_beli' => $item['harga_beli_satuan'],
                            'harga_jual' => $item['harga_jual'] ?? (int) round($item['harga_beli_satuan'] * 1.3),
                        ]
                    );
                } elseif (! empty($item['sparepart_id'])) {
                    $part = Sparepart::whereKey($item['sparepart_id'])->lockForUpdate()->firstOrFail();
                }
                if (! $part) {
                    abort(422, 'ID Sparepart tidak valid');
                }
                $quantity = (int) $item['jumlah'];
                $price = (int) $item['harga_beli_satuan'];
                $oldStock = $part->stok;
                $subtotal = $quantity * $price;
                $purchase->items()->create([
                    'sparepart_id' => $part->id, 'jumlah' => $quantity,
                    'harga_beli_satuan' => $price, 'subtotal' => $subtotal,
                ]);
                $part->increment('stok', $quantity);
                $part->update(['harga_beli' => $price]);
                $this->logActivity($request, 'ubah_stok', 'sparepart:'.$part->id, [
                    'stok' => $oldStock,
                ], ['stok' => $part->fresh()->stok]);
                $total += $subtotal;
            }
            $purchase->update(['total_biaya' => $total]);
            $this->logActivity($request, 'pengadaan', 'purchase:'.$purchase->id, null, [
                'no_pembelian' => $purchase->no_pembelian,
                'total_biaya' => $purchase->total_biaya,
            ]);

            return $purchase;
        });

        return response()->json(['status' => 'success', 'message' => 'Pengadaan barang berhasil disimpan. Stok sparepart otomatis bertambah!', 'data' => [
            'purchase_id' => $purchase->id, 'no_pembelian' => $purchase->no_pembelian,
            'supplier' => $purchase->supplier, 'total_biaya' => $purchase->total_biaya,
            'total_items' => count($data['items']),
        ]]);
    }
}
