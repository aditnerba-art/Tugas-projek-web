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
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerSession;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Service;
use App\Models\ServiceAccessToken;
use App\Models\ServiceItem;
use App\Models\ServiceLog;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiController extends Controller
{
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

    private function serviceDetails(Service $service): array
    {
        $service->load(['customer', 'technician', 'items.sparepart', 'logs']);
        $unit = $service->toArray();
        $unit['nama_pelanggan'] = $service->customer->nama;
        $unit['nama_teknisi'] = $service->technician?->nama;
        $items = $service->items->map(fn (ServiceItem $item) => [
            'id' => $item->id,
            'sparepart_id' => $item->sparepart_id,
            'jumlah' => $item->jumlah,
            'harga_satuan' => $item->harga_satuan,
            'subtotal' => $item->subtotal,
            'kode_barang' => $item->sparepart->kode_barang,
            'nama_barang' => $item->sparepart->nama_barang,
        ])->all();
        $timeline = $service->logs->map(fn (ServiceLog $log) => [
            'status_lama' => $log->status_lama,
            'status_baru' => $log->status_baru,
            'catatan_teknis' => $log->catatan_teknis,
            'created_at' => $log->created_at,
        ])->all();

        return ['unit' => $unit, 'onderdil' => $items, 'timeline' => $timeline];
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
        DB::table('sessions')->where('user_id', $user->id)->where('id', '<>', $request->session()->getId())->delete();
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'user' => $this->userData($user)]);
    }

    public function staffIndex(): JsonResponse
    {
        $users = User::whereIn('role', ['admin', 'teknisi'])->orderBy('role')->orderBy('nama')->get(['id', 'nama', 'username', 'role']);

        return response()->json(['status' => 'success', 'users' => $users]);
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

        DB::transaction(function () use ($staff): void {
            DB::table('sessions')->where('user_id', $staff->id)->delete();
            $staff->assignedServices()->update(['teknisi_id' => null]);
            Purchase::where('created_by', $staff->id)->update(['created_by' => null]);
            $staff->delete();
        });

        return response()->json(['status' => 'success', 'message' => 'Akun staf berhasil dihapus']);
    }

    public function technicians(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => User::where('role', 'teknisi')->orderBy('nama')->get(['id', 'nama', 'username', 'role'])]);
    }

    public function users(): JsonResponse
    {
        return response()->json(['status' => 'success', 'data' => User::orderBy('role')->orderBy('nama')->get(['id', 'nama', 'username', 'role', 'created_at'])]);
    }

    public function createService(CreateServiceRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = DB::transaction(function () use ($data): array {
            $nama = preg_replace('/\s+/', ' ', trim($data['nama']));
            $noWa = trim($data['no_wa']);
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
                'status' => 'masuk',
                'status_pembayaran' => 'belum_bayar',
            ]);
            $code = $this->issueAccessCode($service);
            $note = 'Unit berhasil didaftarkan di sistem';
            if ($service->teknisi_id) {
                $note .= ' dan ditugaskan kepada teknisi: '.User::find($service->teknisi_id)->nama;
            }
            $service->logs()->create(['status_baru' => 'masuk', 'catatan_teknis' => $note]);

            return [$service, $code, $customer];
        });
        [$service, $code, $customer] = $result;
        $customerLink = url('/lacak');

        return response()->json([
            'status' => 'success',
            'message' => 'Unit berhasil didaftarkan',
            'no_nota' => $service->no_nota,
            'service_id' => $service->id,
            'kode_akses' => $code,
            'customer_token' => $code,
            'customer_link' => $customerLink,
            'customer_message' => "Halo {$customer->nama}, perangkat Anda sudah diterima untuk servis. Pantau statusnya di {$customerLink}. Kode akses pelanggan Anda: {$code}",
            'whatsapp_sent' => false,
            'whatsapp_status' => 'provider_not_configured',
        ]);
    }

    public function assignTechnician(AssignTechnicianRequest $request): JsonResponse
    {
        $data = $request->validated();
        $service = Service::findOrFail($data['service_id']);
        $service->teknisi_id = $data['teknisi_id'] ?? null;
        $service->save();
        $name = $service->technician?->nama ?? 'Belum Ditugaskan';
        $service->logs()->create(['status_lama' => $service->status, 'status_baru' => $service->status, 'catatan_teknis' => "Penugasan Teknisi PIC diperbarui: {$name}"]);

        return response()->json(['status' => 'success', 'message' => "Teknisi penanggung jawab berhasil ditugaskan: {$name}"]);
    }

    public function updateServiceStatus(UpdateServiceStatusRequest $request): JsonResponse
    {
        $data = $request->validated();
        $service = DB::transaction(function () use ($request, $data): Service {
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
                    'harga_satuan' => $sparepart->harga_jual,
                    'subtotal' => $sparepart->harga_jual * $quantity,
                ]);
            }

            $service->biaya_jasa = $data['biaya_jasa'] ?? $service->biaya_jasa;
            $partsTotal = $service->items()->sum('subtotal');
            $service->total_biaya = ! empty($data['total_biaya'])
                ? $data['total_biaya']
                : (float) $service->biaya_jasa + (float) $partsTotal;
            $service->status = $data['status_baru'];
            $service->save();
            $service->logs()->create([
                'status_lama' => $oldStatus,
                'status_baru' => $service->status,
                'catatan_teknis' => $data['catatan_teknis'] ?? null,
            ]);
            $this->logActivity($request, 'ubah_status_servis', 'service:'.$service->id, $oldServiceData, [
                'status' => $service->status,
                'biaya_jasa' => $service->biaya_jasa,
                'total_biaya' => $service->total_biaya,
            ]);

            return $service;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Status servis berhasil diperbarui',
            'biaya_jasa' => $service->biaya_jasa,
            'total_biaya' => $service->total_biaya,
        ]);
    }

    public function activeServices(Request $request): JsonResponse
    {
        $query = Service::with(['customer', 'technician'])->latest();
        if ($request->user()->role === 'teknisi') {
            $query->where('teknisi_id', $request->user()->id);
        }
        if ($request->user()->role === 'admin') {
            $query->whereHas('customer', fn ($customers) => $customers->where('nama', '<>', 'Pelanggan dihapus')->where('no_wa', 'not like', 'DIHAPUS-%'));
        }
        $services = $query->get()->map(fn (Service $service) => [
            ...$service->only([
                'id', 'no_nota', 'jenis_unit', 'merek_tipe', 'status', 'biaya_jasa', 'total_biaya',
                'status_pembayaran', 'metode_pembayaran', 'dp_dibayar', 'nominal_dibayar', 'kembalian',
                'created_at', 'teknisi_id',
            ]),
            'nama_teknisi' => $service->technician?->nama,
            'nama_pelanggan' => $service->customer->nama,
            'no_wa' => $service->customer->no_wa,
        ]);

        return response()->json(['status' => 'success', 'data' => $services]);
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

            $service->fill([
                'status' => $newStatus,
                'status_pembayaran' => $statusPayment,
                'metode_pembayaran' => $method,
                'nominal_dibayar' => $amount,
                'dp_dibayar' => $dp,
                'kembalian' => $change,
            ])->save();
            $note = 'Pembayaran diperbarui: Status ['.strtoupper($statusPayment)."], Metode [{$method}]. Nominal Diterima: Rp ".number_format($amount, 0, ',', '.').', Kembalian: Rp '.number_format($change, 0, ',', '.').'.';
            if (! empty($data['catatan'])) {
                $note .= ' Catatan: '.$data['catatan'].'.';
            }
            if ($newStatus === 'diambil') {
                $note .= ' Unit telah diserahkan ke pelanggan.';
            }
            $service->logs()->create(['status_lama' => $oldStatus, 'status_baru' => $newStatus, 'catatan_teknis' => $note]);
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
        $data['onderdil'] = $service->items->map(fn (ServiceItem $item) => [
            'id' => $item->id, 'jumlah' => $item->jumlah, 'harga_satuan' => $item->harga_satuan,
            'subtotal' => $item->subtotal, 'nama_barang' => $item->sparepart->nama_barang,
            'kode_barang' => $item->sparepart->kode_barang,
        ]);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function adminCustomers(AdminCustomersRequest $request): JsonResponse
    {
        $filters = $request->validated();
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

        $limit = (int) ($filters['limit'] ?? 100);
        $customers = $customersQuery->limit($limit)->get();

        return response()->json(['status' => 'success', 'data' => $customers->map(fn (Customer $customer) => [
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
        ])]);
    }

    public function updateCustomer(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        $customer->update(['nama' => preg_replace('/\s+/', ' ', trim($data['nama'])), 'no_wa' => trim($data['no_wa']), 'alamat' => $data['alamat'] ?? null]);

        return response()->json(['status' => 'success', 'message' => 'Data pelanggan berhasil diperbarui']);
    }

    public function deleteCustomer(Request $request, Customer $customer): JsonResponse
    {
        $this->logActivity($request, 'hapus_data', 'customer:'.$customer->id, [
            'nama' => $customer->nama, 'no_wa' => $customer->no_wa, 'alamat' => $customer->alamat,
        ]);
        $customer->delete();

        return response()->json(['success' => true, 'message' => 'Pelanggan dipindahkan ke arsip. Riwayat servis tetap aman dan dapat dipulihkan.']);
    }

    public function deleteService(Request $request, Service $service): JsonResponse
    {
        $this->logActivity($request, 'hapus_data', 'service:'.$service->id, [
            'no_nota' => $service->no_nota, 'status' => $service->status,
        ]);
        $service->delete();

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
        $service = DB::transaction(function () use ($serviceQuery, $data): Service {
            $service = (clone $serviceQuery)->lockForUpdate()->firstOrFail();
            if (! in_array($service->status, ['menunggu_persetujuan', 'menunggu persetujuan'], true)) {
                abort(409, 'Servis tidak sedang menunggu persetujuan');
            }
            $oldStatus = $service->status;
            $newStatus = in_array($data['status_baru'], ['batal', 'batalkan'], true) ? 'batal' : 'dikerjakan';
            $service->update(['status' => $newStatus]);
            $note = $newStatus === 'dikerjakan'
                ? 'Pelanggan menyetujui estimasi perbaikan sebesar Rp '.number_format((float) $service->total_biaya, 0, ',', '.').'.'
                : 'Pelanggan membatalkan servis melalui portal pelanggan.';
            $service->logs()->create(['status_lama' => $oldStatus, 'status_baru' => $newStatus, 'catatan_teknis' => $note]);

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

        return response()->json(['status' => 'success', 'data' => $this->serviceDetails($service)]);
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
        $query = Sparepart::query();
        if ($search = $request->validated('search')) {
            $query->where(fn ($parts) => $parts->where('kode_barang', 'like', "%{$search}%")->orWhere('nama_barang', 'like', "%{$search}%"));
        }

        return response()->json(['status' => 'success', 'data' => $query->orderBy('nama_barang')->get()]);
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

    public function deleteSparepart(Sparepart $sparepart): JsonResponse
    {
        if ($sparepart->serviceItems()->exists() || $sparepart->purchaseDetails()->exists()) {
            return response()->json(['status' => 'error', 'message' => "Sparepart \"{$sparepart->nama_barang}\" tidak dapat dihapus karena tercatat dalam riwayat servis atau pengadaan."], 400);
        }
        $sparepart->delete();

        return response()->json(['status' => 'success', 'message' => "Sparepart \"{$sparepart->nama_barang}\" berhasil dihapus"]);
    }

    public function ownerDashboard(): JsonResponse
    {
        $services = Service::query();
        $summary = [
            'total_unit' => (clone $services)->count(),
            'unit_selesai' => (clone $services)->whereIn('status', ['selesai', 'diambil'])->count(),
            'total_pendapatan' => (clone $services)->where('status_pembayaran', 'lunas')->sum('total_biaya'),
            'total_dp' => (clone $services)->where('status_pembayaran', 'dp')->sum('dp_dibayar'),
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

    public function purchases(): JsonResponse
    {
        $purchases = Purchase::with('items.sparepart')->orderByDesc('tanggal_pembelian')->orderByDesc('id')->get()->map(fn (Purchase $purchase) => [
            ...$purchase->only(['id', 'no_pembelian', 'supplier', 'tanggal_pembelian', 'total_biaya', 'keterangan', 'created_at']),
            'items' => $purchase->items->map(fn (PurchaseDetail $detail) => [
                'id' => $detail->id, 'sparepart_id' => $detail->sparepart_id,
                'jumlah' => $detail->jumlah, 'harga_beli_satuan' => $detail->harga_beli_satuan,
                'subtotal' => $detail->subtotal, 'kode_barang' => $detail->sparepart->kode_barang,
                'nama_barang' => $detail->sparepart->nama_barang, 'stok_sekarang' => $detail->sparepart->stok,
            ]),
        ]);

        return response()->json(['status' => 'success', 'data' => $purchases]);
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
