<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceSchedulingService
{
    private const FALLBACK_DURATION_DAYS = 3;
    private const MINIMUM_SAMPLES = 3;

    public function durationEstimate(?string $unitType): array
    {
        $typeDurations = $this->completedDurations($unitType);
        if (count($typeDurations) >= self::MINIMUM_SAMPLES) {
            return ['days' => $this->median($typeDurations), 'source' => 'historis_jenis_unit'];
        }

        $allDurations = $this->completedDurations(null);
        if (count($allDurations) >= self::MINIMUM_SAMPLES) {
            return ['days' => $this->median($allDurations), 'source' => 'historis_umum'];
        }

        return ['days' => self::FALLBACK_DURATION_DAYS, 'source' => 'fallback'];
    }

    public function recommendationFor(User $technician, int $durationDays): array
    {
        $today = Carbon::today(config('app.timezone'));
        $activeServices = $technician->assignedServices()
            ->whereNotIn('status', ['selesai', 'diambil', 'batal'])
            ->get(['estimated_completion_at']);
        $deadlines = $activeServices
            ->pluck('estimated_completion_at')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());
        $latestDeadline = $deadlines->max();
        $availableAt = $latestDeadline && $latestDeadline->greaterThan($today)
            ? $latestDeadline->copy()->addDay()
            : $today->copy();
        $missingDeadlines = $activeServices->count() - $deadlines->count();
        if ($missingDeadlines > 0) {
            $availableAt->addDays($missingDeadlines * $durationDays);
        }

        return [
            'beban_kerja' => $activeServices->count(),
            'antrean_hari' => max(0, (int) $today->diffInDays($availableAt, false)),
            'tersedia_mulai' => $availableAt->toDateString(),
            'rekomendasi_selesai' => $availableAt->copy()->addDays($durationDays)->toDateString(),
        ];
    }

    public function bestRecommendation(string $unitType, ?int $technicianId = null): array
    {
        $duration = $this->durationEstimate($unitType);
        $technicians = User::query()->where('role', 'teknisi');
        if ($technicianId !== null) {
            $technicians->whereKey($technicianId);
        }

        $plans = $technicians->get()->map(fn (User $technician) => [
            'teknisi_id' => $technician->id,
            ...$this->recommendationFor($technician, $duration['days']),
        ])->sortBy('rekomendasi_selesai')->values();

        $plan = $plans->first() ?? [
            'teknisi_id' => null,
            'beban_kerja' => 0,
            'antrean_hari' => 0,
            'tersedia_mulai' => Carbon::today(config('app.timezone'))->toDateString(),
            'rekomendasi_selesai' => Carbon::today(config('app.timezone'))->addDays($duration['days'])->toDateString(),
        ];

        return [...$plan, 'durasi_estimasi_hari' => $duration['days'], 'sumber_durasi' => $duration['source']];
    }

    private function completedDurations(?string $unitType): array
    {
        $query = DB::table('services')
            ->where('status', 'selesai')
            ->whereNotNull('actual_completed_at')
            ->whereNotNull('created_at')
            ->orderByDesc('actual_completed_at')
            ->limit(500);
        if ($unitType !== null && $unitType !== '') {
            $query->where('jenis_unit', $unitType);
        }

        return $query->get(['created_at', 'actual_completed_at'])
            ->map(function (object $service): ?int {
                $started = Carbon::parse($service->created_at)->startOfDay();
                $completed = Carbon::parse($service->actual_completed_at)->startOfDay();
                if ($completed->lessThan($started)) {
                    return null;
                }

                return max(1, (int) $started->diffInDays($completed));
            })
            ->filter()
            ->values()
            ->all();
    }

    private function median(array $durations): int
    {
        sort($durations);
        $middle = intdiv(count($durations), 2);
        $median = count($durations) % 2 === 1
            ? $durations[$middle]
            : (int) ceil(($durations[$middle - 1] + $durations[$middle]) / 2);

        return max(1, $median);
    }
}
