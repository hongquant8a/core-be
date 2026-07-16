<?php

namespace App\Modules\Beneficiary\Console\Commands;

use App\Modules\Beneficiary\Enums\BeneficiaryStatusEnum;
use App\Modules\Beneficiary\Enums\ScheduleStatusEnum;
use App\Modules\Beneficiary\Enums\VisitOccasionEnum;
use App\Modules\Beneficiary\Models\Beneficiary;
use App\Modules\Beneficiary\Models\VisitSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Sinh beneficiary_visit_schedules cho toàn bộ Beneficiary active theo dịp lễ.
 * KHÔNG tự gọi ReminderScheduler ở đây — VisitScheduleObserver::saved() tự làm việc đó
 * (đảm bảo mọi đường tạo VisitSchedule, kể cả tạo tay sau này, đều được lên lịch nhắc).
 */
class GenerateVisitSchedulesCommand extends Command
{
    protected $signature = 'beneficiary:generate-visit-schedules {occasion} {--date=}';

    protected $description = 'Sinh lịch viếng thăm/tặng quà cho toàn bộ người có công đang active theo dịp lễ';

    public function handle(): int
    {
        $occasion = $this->argument('occasion');

        if (! in_array($occasion, VisitOccasionEnum::values(), true)) {
            $this->error('occasion không hợp lệ. Giá trị hợp lệ: '.implode(', ', VisitOccasionEnum::values()));

            return self::FAILURE;
        }

        $scheduledDate = $this->option('date') ? Carbon::parse($this->option('date')) : now()->addDays(14);
        $created = 0;

        Beneficiary::withoutGlobalScope('organization')
            ->where('status', BeneficiaryStatusEnum::Active->value)
            ->whereDoesntHave('visitSchedules', fn ($q) => $q
                ->where('occasion', $occasion)
                ->whereYear('scheduled_date', $scheduledDate->year))
            ->chunkById(200, function ($beneficiaries) use ($occasion, $scheduledDate, &$created) {
                foreach ($beneficiaries as $beneficiary) {
                    // Người phụ trách mặc định = người đã tạo hồ sơ; nghiệp vụ có thể cần
                    // quy tắc phân công theo tổ dân phố sau này — chưa nằm trong scope bản đầu.
                    $assignedTo = $beneficiary->created_by;

                    if (! $assignedTo) {
                        continue;
                    }

                    VisitSchedule::create([
                        'organization_id' => $beneficiary->organization_id,
                        'subject_type' => Beneficiary::class,
                        'subject_id' => $beneficiary->id,
                        'occasion' => $occasion,
                        'scheduled_date' => $scheduledDate,
                        'status' => ScheduleStatusEnum::Pending->value,
                        'assigned_to' => $assignedTo,
                    ]);

                    $created++;
                }
            });

        $this->info("Đã sinh {$created} lịch viếng thăm dịp {$occasion}.");

        return self::SUCCESS;
    }
}
