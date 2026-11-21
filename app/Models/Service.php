<?php

namespace App\Models;

use App\Events\ServiceStatusChanged;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'no_nota', 'customer_id', 'teknisi_id', 'jenis_unit', 'merek_tipe', 'no_seri',
        'keluhan', 'kelengkapan', 'status', 'biaya_jasa', 'total_biaya',
        'metode_pembayaran', 'dp_dibayar', 'nominal_dibayar', 'kembalian',
        'estimated_completion_at', 'priority', 'accessories', 'physical_condition',
        'warranty_days', 'warranty_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'biaya_jasa' => 'decimal:2',
            'total_biaya' => 'decimal:2',
            'dp_dibayar' => 'decimal:2',
            'nominal_dibayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
            'estimated_completion_at' => 'date',
            'warranty_expires_at' => 'date',
            'accessories' => 'array',
            'warranty_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service): void {
            $service->tracking_key ??= Str::random(64);
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(ServiceStatusLog::class)->orderBy('created_at')->orderBy('id');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(ServiceAccessToken::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ServicePhoto::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidAmount(): int
    {
        return max(0, (int) $this->payments()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN -amount ELSE amount END), 0) as net_paid")
            ->value('net_paid'));
    }

    public function paymentStatus(?int $paidAmount = null): string
    {
        $paidAmount ??= $this->paidAmount();
        if ($paidAmount <= 0) {
            return 'belum';
        }

        return $paidAmount >= (int) round((float) $this->total_biaya) ? 'lunas' : 'sebagian';
    }

    public function syncPaymentSummary(): void
    {
        $paidAmount = $this->paidAmount();
        $status = $this->paymentStatus($paidAmount);
        $lastMethod = $this->payments()
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->value('method');
        $partialAmount = $status === 'sebagian' ? $paidAmount : 0;
        if (
            $this->status_pembayaran === $status
            && $this->metode_pembayaran === $lastMethod
            && (int) round((float) $this->dp_dibayar) === $partialAmount
            && (int) round((float) $this->nominal_dibayar) === $paidAmount
        ) {
            return;
        }

        $this->forceFill([
            'status_pembayaran' => $status,
            'metode_pembayaran' => $lastMethod,
            'dp_dibayar' => $partialAmount,
            'nominal_dibayar' => $paidAmount,
        ])->save();
    }

    public function balanceDue(): int
    {
        return max(0, (int) round((float) $this->total_biaya) - $this->paidAmount());
    }

    public function recordTimelineEvent(string $note, ?User $actor): ServiceStatusLog
    {
        $statusLog = $this->statusLogs()->create([
            'status_lama' => $this->status,
            'status_baru' => $this->status,
            'catatan' => $note,
            'user_id' => $actor?->id,
            'created_at' => now(),
        ]);
        event(new ServiceStatusChanged($statusLog));

        return $statusLog;
    }

    public function changeStatus(string $newStatus, ?User $actor, ?string $note = null): ?ServiceStatusLog
    {
        if (! $this->exists) {
            throw new LogicException('A service must be saved before changing its status.');
        }

        return DB::transaction(function () use ($newStatus, $actor, $note): ?ServiceStatusLog {
            $service = static::query()->lockForUpdate()->findOrFail($this->getKey());
            $oldStatus = $service->status;
            $hasHistory = $service->statusLogs()->exists();
            if ($oldStatus === $newStatus && $hasHistory) {
                $this->setRawAttributes($service->getAttributes(), true);

                return null;
            }

            if ($oldStatus !== $newStatus) {
                $service->status = $newStatus;
                $service->save();
            }

            $statusLog = $service->statusLogs()->create([
                'status_lama' => $oldStatus === $newStatus ? null : $oldStatus,
                'status_baru' => $newStatus,
                'catatan' => $note,
                'user_id' => $actor?->id,
                'created_at' => now(),
            ]);
            $this->setRawAttributes($service->getAttributes(), true);

            event(new ServiceStatusChanged($statusLog));

            return $statusLog;
        });
    }

    public function customerStatusChannelKey(): ?string
    {
        $tokenHash = $this->accessTokens()
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->value('token_hash');

        return $tokenHash
            ? hash_hmac('sha256', 'customer-status:'.$tokenHash, (string) config('app.key'))
            : null;
    }
}
