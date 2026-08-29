<?php

namespace App\Modules\Meeting\Services;

use App\Modules\Meeting\Enums\MeetingSeatLayoutTypeEnum;
use App\Modules\Meeting\Models\Meeting;
use App\Modules\Meeting\Models\MeetingParticipant;
use App\Modules\Meeting\Models\MeetingSeat;
use App\Modules\Meeting\Models\MeetingSeatMap;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Sinh toạ độ ghế theo `layout_type`, tự động xếp đại biểu, gán/gỡ chỗ ngồi.
 *
 * Công thức sinh ghế (theater/presidium/curved/ushape) port 1:1 từ bản mô phỏng đã duyệt
 * `so-do-cho-ngoi-mockup.html` (PAD/TOP/METRICS, offset canvas, curve/rotation) để BE và
 * FE preview ra cùng toạ độ.
 */
class MeetingSeatMapService
{
    private const PAD = 22;

    private const TOP = 58;

    // Ghế đủ rộng cho tên tiếng Việt 2 dòng (vd "Nguyễn Thị Lan") + chừa dải
    // trên cho nhãn ghế và vương miện. ⚠️ Đổi ở đây phải đổi cả seatMapGenerators.js.
    private const METRICS_NORMAL = ['w' => 104, 'h' => 62, 'gx' => 10, 'gy' => 14, 'aisle' => 28];

    private const METRICS_DENSE = ['w' => 22, 'h' => 22, 'gx' => 6, 'gy' => 7, 'aisle' => 12];

    /** Rank chức vụ cho auto-arrange mode "rank" — port từ RANK_RULES trong mockup. */
    private const RANK_RULES = [
        ['/Bí thư Tỉnh ủy/ui', 1],
        ['/Chủ tịch UBND/ui', 2],
        ['/Chủ tịch HĐND/ui', 3],
        ['/Phó Bí thư/ui', 4],
        ['/Phó Chủ tịch UBND/ui', 5],
        ['/Phó Chủ tịch HĐND/ui', 6],
        ['/Chủ tịch Ủy ban MTTQ/ui', 7],
        ['/Giám đốc Công an|Chỉ huy trưởng/ui', 8],
        ['/Giám đốc Sở/ui', 10],
        ['/Cục trưởng|Chánh Thanh tra|Chánh Văn phòng/ui', 11],
        ['/Trưởng Ban|Bí thư Tỉnh đoàn/ui', 12],
        ['/Phó Giám đốc|Phó Chánh/ui', 14],
    ];

    public function showInMeeting(Meeting $meeting): ?MeetingSeatMap
    {
        return MeetingSeatMap::where('meeting_id', $meeting->id)
            ->with(['seats' => fn ($q) => $q->orderBy('sort_order'), 'seats.participant.attendance'])
            ->first();
    }

    /**
     * Lưu cấu hình sơ đồ + sinh lại toàn bộ `meeting_seats`.
     *
     * @param  array{layout_type: string, config: array, canvas?: array|null, keep_assignments?: bool}  $data
     */
    public function saveInMeeting(Meeting $meeting, array $data): MeetingSeatMap
    {
        $layoutType = $data['layout_type'];
        $config = $this->mergeConfigDefaults($layoutType, $data['config']);
        $keepAssignments = $data['keep_assignments'] ?? true;
        $canvasOverride = $data['canvas'] ?? null;

        return DB::transaction(function () use ($meeting, $layoutType, $config, $keepAssignments, $canvasOverride) {
            $seatMap = MeetingSeatMap::firstOrNew(['meeting_id' => $meeting->id]);
            $seatMap->organization_id = $meeting->organization_id;
            $seatMap->meeting_id = $meeting->id;
            $seatMap->layout_type = $layoutType;

            $built = $this->buildLayout($layoutType, $config);
            $seatMap->config = $config;

            // MERGE chứ không thay thế: client chỉ được override width/height (vd
            // muốn canvas rộng hơn cho bản in). Các field hình học BE tính
            // (seat_w/seat_h/dense/table) luôn phải còn — thiếu chúng thì FE
            // không biết vẽ ghế to bao nhiêu và sơ đồ vỡ.
            $seatMap->canvas = array_merge($built['canvas'], $canvasOverride ?? []);
            $seatMap->save();

            $oldByLabel = $keepAssignments
                ? MeetingSeat::where('seat_map_id', $seatMap->id)->get(['label', 'meeting_participant_id', 'is_vip'])->keyBy('label')
                : collect();

            MeetingSeat::where('seat_map_id', $seatMap->id)->delete();

            $now = now();
            $userId = auth()->id();
            $rows = [];
            foreach ($built['seats'] as $index => $seat) {
                $old = $oldByLabel->get($seat['label']);
                $rows[] = [
                    'organization_id' => $meeting->organization_id,
                    'meeting_id' => $meeting->id,
                    'seat_map_id' => $seatMap->id,
                    'meeting_participant_id' => $old?->meeting_participant_id,
                    'zone' => $seat['zone'],
                    'is_vip' => $old?->is_vip ?? false,
                    'label' => $seat['label'],
                    'row_index' => $seat['row_index'],
                    'col_index' => $seat['col_index'],
                    'pos_x' => $seat['pos_x'],
                    'pos_y' => $seat['pos_y'],
                    'rotation' => $seat['rotation'],
                    'sort_order' => $index,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (! empty($rows)) {
                MeetingSeat::insert($rows);
            }

            return $seatMap->load(['seats' => fn ($q) => $q->orderBy('sort_order'), 'seats.participant.attendance']);
        });
    }

    /**
     * Gán/gỡ đại biểu vào ghế và/hoặc đổi cờ `is_vip`.
     *
     * @param  array<int, array{seat_id: int, meeting_participant_id?: int|null, is_vip?: bool}>  $assignments
     */
    public function assignInMeeting(Meeting $meeting, array $assignments): MeetingSeatMap
    {
        $seatMap = $this->requireSeatMap($meeting);

        return DB::transaction(function () use ($seatMap, $assignments) {
            $seatIds = array_column($assignments, 'seat_id');
            $seats = MeetingSeat::where('seat_map_id', $seatMap->id)->whereIn('id', $seatIds)->get()->keyBy('id');

            foreach ($assignments as $item) {
                $seat = $seats->get($item['seat_id']);
                if (! $seat) {
                    continue;
                }

                if (array_key_exists('meeting_participant_id', $item)) {
                    $participantId = $item['meeting_participant_id'];

                    // 1 đại biểu chỉ 1 ghế — gỡ gán cũ của đại biểu này ở ghế khác (nếu có) trước khi gán mới.
                    if ($participantId !== null) {
                        MeetingSeat::where('seat_map_id', $seatMap->id)
                            ->where('meeting_participant_id', $participantId)
                            ->where('id', '!=', $seat->id)
                            ->update(['meeting_participant_id' => null, 'updated_by' => auth()->id()]);
                    }

                    $seat->meeting_participant_id = $participantId;
                }

                if (array_key_exists('is_vip', $item)) {
                    $seat->is_vip = (bool) $item['is_vip'];
                }

                $seat->save();
            }

            return $seatMap->load(['seats' => fn ($q) => $q->orderBy('sort_order'), 'seats.participant.attendance']);
        });
    }

    /**
     * Tự động xếp đại biểu CHƯA xếp vào ghế còn TRỐNG — không đụng ghế đã có người.
     */
    public function autoArrangeInMeeting(Meeting $meeting, string $mode): MeetingSeatMap
    {
        $seatMap = $this->requireSeatMap($meeting);

        return DB::transaction(function () use ($meeting, $seatMap, $mode) {
            $assignedIds = MeetingSeat::where('seat_map_id', $seatMap->id)
                ->whereNotNull('meeting_participant_id')
                ->pluck('meeting_participant_id');

            $query = MeetingParticipant::where('meeting_id', $meeting->id)->whereNotIn('id', $assignedIds);
            // Sắp theo "tên gọi" (token cuối) trước — dùng làm tie-break cho mode rank, và thứ tự chính cho mode abc.
            $query->orderByRaw("SUBSTRING_INDEX(display_name, ' ', -1) COLLATE utf8mb4_vietnamese_ci asc");
            if ($mode === 'abc') {
                $query->orderByRaw('display_name COLLATE utf8mb4_vietnamese_ci asc');
            }
            $participants = $query->get();

            if ($mode === 'random') {
                $participants = $participants->shuffle()->values();
            } elseif ($mode !== 'abc') {
                // mode "rank" (mặc định) — stable sort theo rankOf(position_name), giữ nguyên tie-break tên gọi ở trên.
                $participants = $participants->sortBy(fn ($p) => $this->rankOf($p->position_name))->values();
            }

            $emptySeats = MeetingSeat::where('seat_map_id', $seatMap->id)->whereNull('meeting_participant_id')->get()->all();
            usort($emptySeats, function (MeetingSeat $a, MeetingSeat $b) {
                $zoneA = $a->zone === 'head' ? 0 : 1;
                $zoneB = $b->zone === 'head' ? 0 : 1;

                return [$zoneA, $a->pos_y, $a->pos_x] <=> [$zoneB, $b->pos_y, $b->pos_x];
            });

            $count = min($participants->count(), count($emptySeats));
            $userId = auth()->id();
            for ($i = 0; $i < $count; $i++) {
                $emptySeats[$i]->meeting_participant_id = $participants[$i]->id;
                $emptySeats[$i]->updated_by = $userId;
                $emptySeats[$i]->save();
            }

            return $seatMap->load(['seats' => fn ($q) => $q->orderBy('sort_order'), 'seats.participant.attendance']);
        });
    }

    private function requireSeatMap(Meeting $meeting): MeetingSeatMap
    {
        $seatMap = MeetingSeatMap::where('meeting_id', $meeting->id)->first();
        if (! $seatMap) {
            throw new ModelNotFoundException('Cuộc họp chưa có sơ đồ chỗ ngồi — vui lòng lưu cấu hình sơ đồ trước.');
        }

        return $seatMap;
    }

    private function rankOf(?string $position): int
    {
        if ($position === null) {
            return 20;
        }
        foreach (self::RANK_RULES as [$pattern, $rank]) {
            if (preg_match($pattern, $position)) {
                return $rank;
            }
        }

        return 20;
    }

    /**
     * v1: aisles/curve/stage không có UI chỉnh — set cứng giống mockup, không đọc từ FE.
     */
    private function mergeConfigDefaults(string $layoutType, array $config): array
    {
        return match ($layoutType) {
            MeetingSeatLayoutTypeEnum::Theater->value => array_merge($config, [
                'aisles' => [(int) ceil((($config['cols'] ?? 1)) / 2)],
                'stage' => 'top',
            ]),
            MeetingSeatLayoutTypeEnum::Curved->value => array_merge($config, [
                'curve' => 0.62,
                'stage' => 'top',
            ]),
            default => array_merge($config, ['stage' => 'top']),
        };
    }

    private function metricsFor(array $config): array
    {
        $perRow = $config['cols'] ?? $config['head'] ?? 0;

        return $perRow > 20 ? self::METRICS_DENSE : self::METRICS_NORMAL;
    }

    private function rowLetter(int $row): string
    {
        return chr(65 + $row);
    }

    /**
     * @return array{seats: array, canvas: array{width: int, height: int}}
     */
    private function buildLayout(string $layoutType, array $config): array
    {
        $metrics = $this->metricsFor($config);
        ['seats' => $raw, 'table' => $table] = match ($layoutType) {
            MeetingSeatLayoutTypeEnum::Theater->value => $this->genTheater($config, $metrics),
            MeetingSeatLayoutTypeEnum::Presidium->value => $this->genPresidium($config, $metrics),
            MeetingSeatLayoutTypeEnum::Curved->value => $this->genCurved($config, $metrics),
            MeetingSeatLayoutTypeEnum::Ushape->value => $this->genUshape($config, $metrics),
        };

        return $this->normalize($raw, $table, $metrics);
    }

    private function genTheater(array $c, array $m): array
    {
        $rows = (int) $c['rows'];
        $cols = (int) $c['cols'];
        $stepX = $m['w'] + $m['gx'];
        $stepY = $m['h'] + $m['gy'];
        $aisleAt = (int) ceil($cols / 2);

        $seats = [];
        for ($r = 0; $r < $rows; $r++) {
            for ($col = 0; $col < $cols; $col++) {
                $x = $col * $stepX + ($col >= $aisleAt ? $m['aisle'] : 0);
                $seats[] = $this->rawSeat($this->rowLetter($r).($col + 1), $x, $r * $stepY, 'audience', $r, $col);
            }
        }

        return ['seats' => $seats, 'table' => null];
    }

    private function genPresidium(array $c, array $m): array
    {
        $head = (int) $c['head'];
        $rows = (int) $c['rows'];
        $cols = (int) $c['cols'];
        $stepX = $m['w'] + $m['gx'];
        $stepY = $m['h'] + $m['gy'];
        $audW = $cols * $stepX - $m['gx'];
        $headW = $head * $stepX - $m['gx'];
        $base = max($audW, $headW);

        $seats = [];
        $hx0 = ($base - $headW) / 2;
        for ($i = 0; $i < $head; $i++) {
            $seats[] = $this->rawSeat('CT'.($i + 1), $hx0 + $i * $stepX, 0, 'head', 0, $i);
        }

        $ax0 = ($base - $audW) / 2;
        $ay0 = $stepY + 44;
        for ($r = 0; $r < $rows; $r++) {
            for ($col = 0; $col < $cols; $col++) {
                $seats[] = $this->rawSeat($this->rowLetter($r).($col + 1), $ax0 + $col * $stepX, $ay0 + $r * $stepY, 'audience', $r, $col);
            }
        }

        return ['seats' => $seats, 'table' => null];
    }

    private function genCurved(array $c, array $m): array
    {
        $rows = (int) $c['rows'];
        $cols = (int) $c['cols'];
        $curve = (float) ($c['curve'] ?? 0.62);
        $focalY = -240;
        $r0 = 300;
        $rowStep = $m['h'] + $m['gy'] + 8;

        $seats = [];
        for ($r = 0; $r < $rows; $r++) {
            $radius = $r0 + $r * $rowStep;
            for ($i = 0; $i < $cols; $i++) {
                $a = $cols === 1 ? 0.0 : (-$curve + (2 * $curve) * ($i / ($cols - 1)));
                $x = sin($a) * $radius;
                $y = $focalY + cos($a) * $radius;
                $seat = $this->rawSeat($this->rowLetter($r).($i + 1), $x, $y, 'audience', $r, $i);
                $seat['cx'] = true;
                $seat['rot'] = $a * 180 / M_PI;
                $seats[] = $seat;
            }
        }

        return ['seats' => $seats, 'table' => null];
    }

    private function genUshape(array $c, array $m): array
    {
        $head = (int) $c['head'];
        $side = (int) $c['side'];
        $stepX = $m['w'] + $m['gx'];
        $stepY = $m['h'] + $m['gy'];
        $tableW = $head * $stepX + 12;
        $tableX = $m['w'] + 26;
        $tableY = $m['h'] + 30;
        $tableH = $side * $stepY + 12;

        $seats = [];
        $hx0 = $tableX + ($tableW - ($head * $stepX - $m['gx'])) / 2;
        for ($i = 0; $i < $head; $i++) {
            $seats[] = $this->rawSeat('Đ'.($i + 1), $hx0 + $i * $stepX, $tableY - $m['h'] - 12, 'head', null, $i);
        }
        for ($i = 0; $i < $side; $i++) {
            $y = $tableY + 8 + $i * $stepY;
            $seats[] = $this->rawSeat('T'.($i + 1), $tableX - $m['w'] - 12, $y, 'audience', $i, null);
            $seats[] = $this->rawSeat('P'.($i + 1), $tableX + $tableW + 12, $y, 'audience', $i, null);
        }

        $table = ['x' => $tableX, 'y' => $tableY, 'w' => $tableW, 'h' => $tableH];

        return ['seats' => $seats, 'table' => $table];
    }

    private function rawSeat(string $label, float $x, float $y, string $zone, ?int $rowIndex, ?int $colIndex): array
    {
        return [
            'label' => $label,
            'x' => $x,
            'y' => $y,
            'zone' => $zone,
            'row_index' => $rowIndex,
            'col_index' => $colIndex,
            'cx' => false,
            'rot' => null,
        ];
    }

    /**
     * Chuẩn hoá toạ độ thô (relative, có thể center-based với `cx`) thành pos_x/pos_y cuối cùng
     * trên canvas — port từ hàm `render()` (tính bbox + offset PAD/TOP) trong mockup.
     *
     * @return array{seats: array, canvas: array{width: int, height: int}}
     */
    private function normalize(array $seats, ?array $table, array $metrics): array
    {
        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;

        foreach ($seats as &$seat) {
            $left = $seat['cx'] ? $seat['x'] - $metrics['w'] / 2 : $seat['x'];
            $top = $seat['cx'] ? $seat['y'] - $metrics['h'] / 2 : $seat['y'];
            $seat['_l'] = $left;
            $seat['_t'] = $top;
            $minX = min($minX, $left);
            $minY = min($minY, $top);
            $maxX = max($maxX, $left + $metrics['w']);
            $maxY = max($maxY, $top + $metrics['h']);
        }
        unset($seat);

        if ($table) {
            $minX = min($minX, $table['x']);
            $minY = min($minY, $table['y']);
            $maxX = max($maxX, $table['x'] + $table['w']);
            $maxY = max($maxY, $table['y'] + $table['h']);
        }

        $offX = self::PAD - $minX;
        $offY = self::TOP - $minY;
        $width = (int) round(($maxX - $minX) + self::PAD * 2);
        $height = (int) round(($maxY - $minY) + self::TOP + self::PAD);

        $result = [];
        foreach ($seats as $index => $seat) {
            $result[] = [
                'label' => $seat['label'],
                'zone' => $seat['zone'],
                'row_index' => $seat['row_index'],
                'col_index' => $seat['col_index'],
                'pos_x' => (int) round($seat['_l'] + $offX),
                'pos_y' => (int) round($seat['_t'] + $offY),
                'rotation' => $seat['rot'],
            ];
        }

        // seat_w/seat_h/dense/table đi kèm canvas để FE render đúng hình học mà
        // KHÔNG phải tự lặp lại ngưỡng ">20 ghế/hàng", hằng số METRICS_NORMAL/DENSE,
        // hay hình chữ nhật bàn họp (ushape) — BE là nguồn duy nhất quyết định hình
        // học, FE chỉ vẽ lại theo dữ liệu trả về.
        return [
            'seats' => $result,
            'canvas' => [
                'width' => $width,
                'height' => $height,
                'seat_w' => $metrics['w'],
                'seat_h' => $metrics['h'],
                'dense' => $metrics === self::METRICS_DENSE,
                'table' => $table ? [
                    'x' => (int) round($table['x'] + $offX),
                    'y' => (int) round($table['y'] + $offY),
                    'w' => (int) round($table['w']),
                    'h' => (int) round($table['h']),
                ] : null,
            ],
        ];
    }
}
