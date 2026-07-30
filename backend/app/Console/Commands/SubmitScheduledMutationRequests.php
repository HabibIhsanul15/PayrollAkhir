<?php

namespace App\Console\Commands;

use App\Models\MutationRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SubmitScheduledMutationRequests extends Command
{
    protected $signature = 'mutation-requests:submit-scheduled {--date=}';

    protected $description = 'Kirim otomatis pengajuan promosi/demosi yang jadwal pengajuannya telah tiba.';

    public function handle(): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $submitted = 0;

        MutationRequest::query()
            ->where('status', 'scheduled')
            ->whereDate('scheduled_submission_date', '<=', $date)
            ->orderBy('id')
            ->chunkById(100, function ($requests) use (&$submitted) {
                foreach ($requests as $request) {
                    $didSubmit = DB::transaction(function () use ($request) {
                        $locked = MutationRequest::query()->whereKey($request->id)->lockForUpdate()->first();
                        if (! $locked || $locked->status !== 'scheduled') {
                            return false;
                        }

                        $locked->update([
                            'status' => 'pending',
                            'requested_date' => now()->toDateString(),
                            'submitted_at' => now(),
                        ]);

                        return true;
                    });

                    if ($didSubmit) {
                        $submitted++;
                    }
                }
            });

        if ($submitted > 0) {
            $this->info("{$submitted} pengajuan terjadwal dikirim untuk persetujuan Direktur.");
        }

        return self::SUCCESS;
    }
}
