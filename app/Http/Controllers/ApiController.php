<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCustomersRequest;
use App\Http\Requests\AssignTechnicianRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\CreatePurchaseRequest;
use App\Http\Requests\CreateServiceRequest;
use App\Http\Requests\CustomerApprovalRequest;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\PublicTrackingRequest;
use App\Http\Requests\SparepartRequest;
use App\Http\Requests\SparepartsIndexRequest;
use App\Http\Requests\StaffLoginRequest;
use App\Http\Requests\StaffStoreRequest;
use App\Http\Requests\StaffUpdateRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Requests\UpdateServiceStatusRequest;
use App\Http\Requests\UploadServicePhotoRequest;
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
use App\Models\ServiceItem;
use App\Models\ServiceLog;
use App\Models\ServicePhoto;
use App\Models\ServiceStatusLog;
use App\Models\Sparepart;
use App\Models\User;
use App\Notifications\ServicePanelNotification;
use App\Services\WhatsApp\PhoneNumber;
use App\Services\WhatsApp\WhatsAppNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        if ($includeCustomerChannelKey) {
            $unit['status_channel_key'] = $service->customerStatusChannelKey();
        }
        $unit['warranty_active'] = $service->warranty_expires_at?->isToday()
            || ($service->warranty_expires_at && $service->warranty_expires_at->isFuture());
        $photos = $service->photos->map(fn (ServicePhoto $photo) => [
            'id' => $photo->id,
            'kind' => $photo->kind,
            'url' => Storage::disk('public')->url($photo->path),
            'thumbnail_url' => Storage::disk('public')->url($photo->thumbnail_path),
            'created_at' => $photo->created_at,
        ])->all();
        $items = $service->items->map(fn (ServiceItem $item) => [
            'id' => $item->id,
            'sparepart_id' => $item->sparepart_id,
            'jumlah' => $item->jumlah,
            'harga_satuan' => $item->harga_satuan,
            'subtotal' => $item->subtotal,
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

    public function technicians(): JsonResponse
    {
        $query = User::where('role', 'teknisi')
            ->withCount(['assignedServices as beban_kerja' => fn ($services) => $services->whereNotIn('status', ['selesai', 'diambil', 'batal'])])
            ->orderBy('nama');
        if (request()->user()?->role === 'teknisi') {
            $query->whereKey(request()->user()->id);
        }

        return response()->json(['status' => 'success', 'data' => $query->get(['id', 'nama', 'username', 'role'])]);
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
                'estimated_completion_at' => $data['estimated_completion_at'] ?? null,
                'warranty_days' => $data['warranty_days'] ?? 0,
                'priority' => $data['priority'] ?? 'normal',
                'status_pembayaran' => 'belum_bayar',
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
                    "Penugasan teknisi diperbarui: ".($service->technician?->nama ?? 'Belum Ditugaskan')."; prioritas ".strtoupper($service->priority).'.',
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

            if (! empty($data['sparepart_id'])) {
                $sparepart = Sparepart::whereKey($data['sparepart_id'])->lockForUpdate()->firstOrFail();
                $quantity = (int) ($data['jumlah_sparepart'] ?? 0);
                if ($quantity < 1) {
                    abort(422, 'Jumlah sparepart harus lebih dari 0');
                }
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
                ]);
            }

            $service->biaya_jasa = $data['biaya_jasa'] ?? $service->biaya_jasa;
            $partsTotal = $service->items()->sum('subtotal');
            if (array_key_exists('total_biaya', $data)) {
                $service->total_biaya = $data['total_biaya'];
            } elseif (array_key_exists('biaya_jasa', $data) || ! empty($data['sparepart_id'])) {
                $service->total_biaya = (float) $service->biaya_jasa + (float) $partsTotal;
            }
            if (array_key_exists('estimated_completion_at', $data)) {
                $service->estimated_completion_at = $data['estimated_completion_at'];
            }
            if (array_key_exists('warranty_days', $data)) {
                $service->warranty_days = $data['warranty_days'];
            }
            $service->save();
            $service->changeStatus($data['status_baru'], $request->user(), $data['catatan_teknis'] ?? null);
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
        if ($statusChanged && $waitingForApproval) {
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
            'biaya_jasa' => $service->biaya_jasa,
            'total_biaya' => $service->total_biaya,
        ]);
    }

    public function activeServices(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['diperiksa', 'menunggu_persetujuan', 'dikerjakan', 'menunggu_sparepart', 'selesai', 'diambil', 'batal'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'teknisi_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);
        $query = Service::with(['customer', 'technician'])
            ->orderByRaw('CASE priority WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END', ['urgent', 'high', 'normal']);
        if ($request->user()->role === 'teknisi') {
            $query->where('teknisi_id', $request->user()->id);
        }
        if ($request->user()->role === 'admin') {
            $query->whereHas('customer', fn ($customers) => $customers->where('nama', '<>', 'Pelanggan dihapus')->where('no_wa', 'not like', 'DIHAPUS-%'));
        }
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(fn ($services) => $services->where('no_nota', 'like', "%{$search}%")
                ->orWhere('merek_tipe', 'like', "%{$search}%")
                ->orWhereHas('customer', fn ($customers) => $customers->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_wa', 'like', "%{$search}%")));
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
                'created_at', 'teknisi_id', 'customer_id', 'priority', 'estimated_completion_at', 'warranty_days',
                'warranty_expires_at', 'accessories', 'physical_condition',
            ]),
            'nama_teknisi' => $service->technician?->nama,
            'nama_pelanggan' => $service->customer->nama,
            'no_wa' => $service->customer->no_wa,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $this->paginationMeta($services),
        ]);
    }

    public function payment(PaymentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $isHandover = $request->is('api/admin/service/pelunasan');
        $result = DB::transaction(function () use ($data, $request, $isHandover): array {
            $service = Service::whereKey($data['service_id'])->lockForUpdate()->firstOrFail();
            $oldStatus = $service->status;
            $oldPaymentData = [
                'status_pembayaran' => $service->status_pembayaran,
                'metode_pembayaran' => $service->metode_pembayaran,
                'nominal_dibayar' => $service->nominal_dibayar,
                'dp_dibayar' => $service->dp_dibayar,
                'kembalian' => $service->kembalian,
                'status' => $service->status,
            ];
            $statusPayment = $data['status_pembayaran'] ?? ($isHandover ? 'lunas' : 'belum_bayar');
            $method = $data['metode_pembayaran'] ?? 'Cash';
            $amount = (float) ($data['nominal_dibayar'] ?? ($isHandover ? $service->total_biaya : 0));
            $dp = $statusPayment === 'dp' ? (float) ($data['dp_dibayar'] ?? $amount) : 0;
            $change = isset($data['kembalian'])
                ? (float) $data['kembalian']
                : ($statusPayment === 'lunas' ? max(0, $amount - (float) $service->total_biaya) : ($statusPayment === 'dp' ? max(0, $amount - $dp) : 0));
            $newStatus = (! empty($data['ambil_unit']) || ($isHandover || ($statusPayment === 'lunas' && ($data['ambil_unit'] ?? true)))) ? 'diambil' : $oldStatus;
            $warrantyExpiresAt = $newStatus === 'diambil' && $oldStatus !== 'diambil' && $service->warranty_days > 0
                ? now()->addDays($service->warranty_days)->toDateString()
                : $service->warranty_expires_at?->toDateString();

            $service->fill([
                'status_pembayaran' => $statusPayment,
                'metode_pembayaran' => $method,
                'nominal_dibayar' => $amount,
                'dp_dibayar' => $dp,
                'kembalian' => $change,
                'warranty_expires_at' => $warrantyExpiresAt,
            ])->save();
            $note = 'Pembayaran diperbarui: Status ['.strtoupper($statusPayment)."], Metode [{$method}]. Nominal Diterima: Rp ".number_format($amount, 0, ',', '.').', Kembalian: Rp '.number_format($change, 0, ',', '.').'.';
            if (! empty($data['catatan'])) {
                $note .= ' Catatan: '.$data['catatan'].'.';
            }
            if ($newStatus === 'diambil') {
                $note .= ' Unit telah diserahkan ke pelanggan.';
            }
            if ($newStatus !== $oldStatus) {
                $service->changeStatus($newStatus, $request->user(), $note);
            }
            $this->logActivity($request, 'pembayaran', 'service:'.$service->id, $oldPaymentData, [
                'status_pembayaran' => $service->status_pembayaran,
                'metode_pembayaran' => $service->metode_pembayaran,
                'nominal_dibayar' => $service->nominal_dibayar,
                'dp_dibayar' => $service->dp_dibayar,
                'kembalian' => $service->kembalian,
                'status' => $service->status,
            ]);

            return [$service, $statusPayment, $method, $amount, $change, $newStatus];
        });
        [$service, $statusPayment, $method, $amount, $change, $newStatus] = $result;

        return response()->json([
            'status' => 'success',
            'message' => 'Pembayaran berhasil disimpan ('.strtoupper($statusPayment).')',
            'data' => [
                'service_id' => $service->id,
                'status_pembayaran' => $statusPayment,
                'metode_pembayaran' => $method,
                'nominal_dibayar' => $amount,
                'kembalian' => $change,
                'status_unit' => $newStatus,
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
        $data['onderdil'] = $service->items->map(fn (ServiceItem $item) => [
            'id' => $item->id, 'jumlah' => $item->jumlah, 'harga_satuan' => $item->harga_satuan,
            'subtotal' => $item->subtotal, 'nama_barang' => $item->sparepart->nama_barang,
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
        $disk = Storage::disk('public');
        $savedPaths = [];

        try {
            $photos = DB::transaction(function () use ($data, $request, $service, $disk, &$savedPaths) {
                return collect($data['photos'])->map(function ($file) use ($data, $request, $service, $disk, &$savedPaths): ServicePhoto {
                    $image = imagecreatefromstring(file_get_contents($file->getRealPath()));
                    if ($image === false) {
                        throw new RuntimeException('File foto tidak dapat dibaca sebagai gambar yang valid.');
                    }

                    $width = imagesx($image);
                    $height = imagesy($image);
                    ob_start();
                    imagejpeg($image, null, 90);
                    $originalContents = ob_get_clean();
                    if (! is_string($originalContents) || $originalContents === '') {
                        imagedestroy($image);
                        throw new RuntimeException('Foto tidak dapat diproses dengan aman.');
                    }
                    $scale = min(1, 640 / max($width, $height));
                    $thumbnail = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
                    imagecopyresampled(
                        $thumbnail, $image, 0, 0, 0, 0,
                        imagesx($thumbnail), imagesy($thumbnail), $width, $height,
                    );
                    ob_start();
                    imagejpeg($thumbnail, null, 82);
                    $thumbnailContents = ob_get_clean();
                    imagedestroy($image);
                    imagedestroy($thumbnail);


                    if (! is_string($thumbnailContents) || $thumbnailContents === '') {
                        throw new RuntimeException('Thumbnail foto gagal dibuat.');
                    }

                    $directory = 'services/'.$service->id;
                    $name = Str::uuid()->toString();
                    $path = $directory.'/'.$name.'.jpg';
                    $thumbnailPath = $directory.'/thumbs/'.$name.'.jpg';
                    if (! $disk->put($path, $originalContents)) {
                        throw new RuntimeException('Foto gagal disimpan pada disk public.');
                    }
                    $savedPaths[] = $path;
                    if (! $disk->put($thumbnailPath, $thumbnailContents)) {
                        throw new RuntimeException('Thumbnail gagal disimpan pada disk public.');
                    }
                    $savedPaths[] = $thumbnailPath;

                    return $service->photos()->create([
                        'uploaded_by' => $request->user()->id,
                        'kind' => $data['kind'],
                        'path' => $path,
                        'thumbnail_path' => $thumbnailPath,
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
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
                'thumbnail_url' => $disk->url($photo->thumbnail_path),
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
        $customerSession = CustomerSession::with('accessToken')
            ->where('session_hash', $request->session()->get('customer_session_hash'))
            ->where('expires_at', '>', now())
            ->first();
        $sessionServiceId = $customerSession
            && ! $customerSession->accessToken->revoked_at
            && ! $customerSession->accessToken->expires_at->isPast()
                ? $request->session()->get('customer_service_id')
                : null;
        $user = $request->user();
        $isStaff = $user && in_array($user->role, ['admin', 'teknisi'], true);
        if ($user && ! $isStaff) {
            abort(403);
        }

        $serviceId = $isStaff ? ($data['service_id'] ?? null) : $sessionServiceId;
        if (! $serviceId) {
            return response()->json(['status' => 'error', 'message' => 'Masukkan kode akses pelanggan untuk melanjutkan'], 401);
        }
        if ($sessionServiceId && ! empty($data['service_id']) && (int) $data['service_id'] !== (int) $sessionServiceId) {
            return response()->json(['status' => 'error', 'message' => 'Kode akses tidak sesuai dengan servis'], 403);
        }
        if ($user?->role === 'teknisi' && ! Service::whereKey($serviceId)->where('teknisi_id', $user->id)->exists()) {
            abort(403, 'Teknisi hanya dapat mengubah persetujuan servis yang ditugaskan kepadanya.');
        }
        $serviceQuery = Service::query();
        if ($serviceId) {
            $serviceQuery->whereKey($serviceId);
        }
        $service = DB::transaction(function () use ($serviceQuery, $data, $request): Service {
            $service = (clone $serviceQuery)->lockForUpdate()->firstOrFail();
            if (! in_array($service->status, ['menunggu_persetujuan', 'menunggu persetujuan'], true)) {
                abort(409, 'Servis tidak sedang menunggu persetujuan');
            }
            $oldStatus = $service->status;
            $newStatus = in_array($data['status_baru'], ['batal', 'batalkan'], true) ? 'batal' : 'dikerjakan';
            $note = $newStatus === 'dikerjakan'
                ? 'Pelanggan menyetujui estimasi perbaikan sebesar Rp '.number_format((float) $service->total_biaya, 0, ',', '.').'.'
                : 'Pelanggan membatalkan servis melalui portal pelanggan.';
            $service->changeStatus($newStatus, $request->user(), $note);
            $this->logActivity($request, 'ubah_status_servis', 'service:'.$service->id,
                ['status' => $oldStatus], ['status' => $newStatus]);
            if (! $request->user() && in_array($newStatus, ['dikerjakan', 'batal'], true)) {
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
        $part = Sparepart::create([...$data, 'stok' => $data['stok'] ?? 0, 'harga_beli' => $data['harga_beli'] ?? 0, 'harga_jual' => $data['harga_jual'] ?? 0]);
        $this->logActivity($request, 'ubah_stok', 'sparepart:'.$part->id, null, [
            'stok' => $part->stok, 'kode_barang' => $part->kode_barang,
        ]);

        return response()->json(['status' => 'success', 'message' => 'Sparepart baru berhasil ditambahkan', 'data' => $part]);
    }

    public function updateSparepart(SparepartRequest $request, Sparepart $sparepart): JsonResponse
    {
        $data = $request->validated();
        $old = ['stok' => $sparepart->stok, 'kode_barang' => $sparepart->kode_barang];
        $sparepart->update([...$data, 'stok' => $data['stok'] ?? 0, 'harga_beli' => $data['harga_beli'] ?? 0, 'harga_jual' => $data['harga_jual'] ?? 0]);
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

    public function ownerDashboard(): JsonResponse
    {
        $services = Service::query();
        $billed = (float) (clone $services)->sum('total_biaya');
        $partCost = (float) DB::table('service_items')->sum(DB::raw('jumlah * harga_modal'));
        $summary = [
            'total_unit' => (clone $services)->count(),
            'unit_selesai' => (clone $services)->whereIn('status', ['selesai', 'diambil'])->count(),
            'total_pendapatan' => (clone $services)->where('status_pembayaran', 'lunas')->sum('total_biaya'),
            'total_dp' => (clone $services)->where('status_pembayaran', 'dp')->sum('dp_dibayar'),
            'laba_kotor' => $billed - $partCost,
            'total_piutang' => (float) (clone $services)->where('status_pembayaran', '<>', 'lunas')
                ->sum(DB::raw("total_biaya - CASE WHEN status_pembayaran = 'dp' THEN dp_dibayar ELSE 0 END")),
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
        $from = Carbon::parse($filters['date_from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();
        if ($from->diffInDays($to) > 366) {
            abort(422, 'Rentang laporan maksimal 366 hari.');
        }

        return [$from, $to, $filters['period'] ?? 'day'];
    }

    private function ownerReportMetrics(Carbon $from, Carbon $to, string $period): array
    {
        $services = Service::query()->whereBetween('created_at', [$from, $to]);
        $billed = (float) (clone $services)->sum('total_biaya');
        $partCost = (float) DB::table('service_items')
            ->join('services', 'services.id', '=', 'service_items.service_id')
            ->whereBetween('services.created_at', [$from, $to])
            ->sum(DB::raw('service_items.jumlah * service_items.harga_modal'));
        $daily = (clone $services)
            ->selectRaw('DATE(created_at) as period, SUM(total_biaya) as omzet, COUNT(*) as jumlah_servis')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('period')
            ->get()
            ->keyBy('period');
        $dailyCosts = DB::table('service_items')
            ->join('services', 'services.id', '=', 'service_items.service_id')
            ->whereBetween('services.created_at', [$from, $to])
            ->selectRaw('DATE(services.created_at) as period, SUM(service_items.jumlah * service_items.harga_modal) as modal')
            ->groupByRaw('DATE(services.created_at)')
            ->get()
            ->keyBy('period');
        $series = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $key = $period === 'month' ? $cursor->format('Y-m') : $cursor->toDateString();
            $record = $daily->get($cursor->toDateString());
            $omzet = (float) ($record->omzet ?? 0);
            $modal = (float) ($dailyCosts->get($cursor->toDateString())->modal ?? 0);
            if ($period === 'month') {
                $series[$key] ??= ['label' => $cursor->format('M Y'), 'omzet' => 0, 'modal' => 0, 'laba_kotor' => 0, 'jumlah_servis' => 0];
                $series[$key]['omzet'] += $omzet;
                $series[$key]['modal'] += $modal;
                $series[$key]['laba_kotor'] += $omzet - $modal;
                $series[$key]['jumlah_servis'] += (int) ($record->jumlah_servis ?? 0);
            } elseif ($period === 'day') {
                $omzet = (float) ($record->omzet ?? 0);
                $series[$key] = [
                    'label' => $cursor->format('d M'),
                    'omzet' => $omzet,
                    'modal' => $modal,
                    'laba_kotor' => $omzet - $modal,
                    'jumlah_servis' => (int) ($record->jumlah_servis ?? 0),
                ];
            }
            $cursor->addDay();
        }
        $statusCounts = (clone $services)->select('status', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status')->orderBy('status')->get();
        $technicianCounts = (clone $services)
            ->leftJoin('users as technicians', 'technicians.id', '=', 'services.teknisi_id')
            ->selectRaw("COALESCE(technicians.nama, 'Belum ditugaskan') as teknisi, COUNT(services.id) as jumlah_servis, SUM(services.total_biaya) as omzet")
            ->groupBy('technicians.nama')
            ->orderByDesc('jumlah_servis')
            ->get();
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
        $receivableTotal = (float) $this->receivablesQuery($from, $to)
            ->sum(DB::raw("total_biaya - CASE WHEN status_pembayaran = 'dp' THEN dp_dibayar ELSE 0 END"));

        return [
            'period' => $period,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'summary' => [
                'omzet' => $billed,
                'modal_sparepart' => $partCost,
                'laba_kotor' => $billed - $partCost,
                'jumlah_servis' => (clone $services)->count(),
                'piutang' => $receivableTotal,
            ],
            'series' => array_values($series),
            'status_counts' => $statusCounts,
            'technician_counts' => $technicianCounts,
            'top_parts' => $topParts,
        ];
    }

    private function receivablesQuery(Carbon $from, Carbon $to): Builder
    {
        return Service::with('customer:id,nama,no_wa')
            ->whereBetween('created_at', [$from, $to])
            ->where('status_pembayaran', '<>', 'lunas')
            ->whereRaw('(total_biaya - CASE WHEN status_pembayaran = ? THEN dp_dibayar ELSE 0 END) > 0', ['dp']);
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
            'terbayar' => $service->status_pembayaran === 'dp' ? (float) $service->dp_dibayar : 0,
            'piutang' => max(0, (float) $service->total_biaya - ($service->status_pembayaran === 'dp' ? (float) $service->dp_dibayar : 0)),
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
            fputcsv($output, ['Periode', 'Omzet tagihan servis', 'Modal sparepart', 'Laba kotor', 'Jumlah servis']);
            foreach ($report['series'] as $row) {
                fputcsv($output, [
                    $row['label'],
                    $row['omzet'],
                    $row['modal'],
                    $row['laba_kotor'],
                    $row['jumlah_servis'],
                ]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Status servis', 'Jumlah']);
            foreach ($report['status_counts'] as $row) {
                fputcsv($output, [$row->status, $row->jumlah]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Teknisi', 'Jumlah servis', 'Omzet']);
            foreach ($report['technician_counts'] as $row) {
                fputcsv($output, [$row->teknisi, $row->jumlah_servis, $row->omzet]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Sparepart terlaris', 'Jumlah terjual', 'Nilai jual', 'Modal']);
            foreach ($report['top_parts'] as $part) {
                fputcsv($output, [$part['nama_barang'], $part['jumlah_terjual'], $part['nilai_jual'], $part['modal']]);
            }
            fputcsv($output, []);
            fputcsv($output, ['Piutang / belum lunas']);
            fputcsv($output, ['Nota', 'Pelanggan', 'Tanggal servis', 'Status pembayaran', 'Tagihan', 'Piutang']);
            $this->receivablesQuery($from, $to)->with('customer:id,nama')->orderBy('created_at')
                ->chunk(500, function ($services) use ($output): void {
                    foreach ($services as $service) {
                        $paid = $service->status_pembayaran === 'dp' ? (float) $service->dp_dibayar : 0;
                        fputcsv($output, [
                            $service->no_nota,
                            $service->customer?->nama ?? '-',
                            $service->created_at?->toDateString(),
                            $service->status_pembayaran,
                            $service->total_biaya,
                            max(0, (float) $service->total_biaya - $paid),
                        ]);
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
                            'harga_jual' => $item['harga_jual'] ?? $item['harga_beli_satuan'] * 1.3,
                        ]
                    );
                } elseif (! empty($item['sparepart_id'])) {
                    $part = Sparepart::whereKey($item['sparepart_id'])->lockForUpdate()->firstOrFail();
                }
                if (! $part) {
                    abort(422, 'ID Sparepart tidak valid');
                }
                $quantity = (int) $item['jumlah'];
                $price = (float) $item['harga_beli_satuan'];
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
