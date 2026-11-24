<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\DB;

class LegacyPaymentImporter
{
    public function import(): int
    {
        $imported = 0;

        Service::query()->orderBy('id')->chunkById(100, function ($services) use (&$imported): void {
            foreach ($services as $service) {
                $imported += DB::transaction(function () use ($service): int {
                    $lockedService = Service::query()->lockForUpdate()->findOrFail($service->id);
                    if ($lockedService->payments()->exists()) {
                        $lockedService->syncPaymentSummary();

                        return 0;
                    }

                    $status = (string) $lockedService->getRawOriginal('status_pembayaran');
                    $legacyTotal = max(0, (int) round((float) $lockedService->total_biaya));
                    $paidAmount = match ($status) {
                        'lunas' => $legacyTotal > 0
                            ? $legacyTotal
                            : max(0, (int) round((float) $lockedService->nominal_dibayar)),
                        'dp', 'sebagian' => (int) round((float) (
                            $lockedService->dp_dibayar > 0
                                ? $lockedService->dp_dibayar
                                : $lockedService->nominal_dibayar
                        )),
                        default => max(0, (int) round((float) $lockedService->dp_dibayar)),
                    };
                    $invoiceAmount = $legacyTotal;
                    if ($invoiceAmount > 0) {
                        $paidAmount = min($paidAmount, $invoiceAmount);
                    }

                    if ($paidAmount > 0) {
                        $activity = DB::table('activity_logs')
                            ->where('aksi', 'pembayaran')
                            ->where('subjek', 'service:'.$lockedService->id)
                            ->orderByDesc('created_at')
                            ->first(['user_id', 'created_at']);
                        $handoverDate = $lockedService->status === 'diambil'
                            ? DB::table('service_status_logs')
                                ->where('service_id', $lockedService->id)
                                ->where('status_baru', 'diambil')
                                ->orderByDesc('created_at')
                                ->value('created_at')
                            : null;
                        $fallbackDate = $lockedService->status === 'diambil'
                            ? ($handoverDate ?? $lockedService->updated_at)
                            : ($lockedService->updated_at ?? $lockedService->created_at);

                        $lockedService->payments()->create([
                            'amount' => $paidAmount,
                            'method' => $this->normalizeMethod($lockedService->metode_pembayaran),
                            'paid_at' => $activity?->created_at ?? $fallbackDate ?? now(),
                            'received_by' => $activity?->user_id,
                            'note' => 'migrasi data lama',
                            'type' => 'payment',
                        ]);
                    }

                    $lockedService->syncPaymentSummary();

                    return $paidAmount > 0 ? 1 : 0;
                });
            }
        });

        return $imported;
    }

    private function normalizeMethod(?string $method): string
    {
        return match (strtolower(trim((string) $method))) {
            'cash', 'tunai' => 'tunai',
            'transfer', 'transfer bank' => 'transfer',
            'qris', 'qr' => 'qris',
            default => 'lainnya',
        };
    }
}
