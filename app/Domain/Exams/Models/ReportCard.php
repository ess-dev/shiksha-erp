<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Enums\ReportCardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A class teacher's report card for one student and one exam.
 *
 * @property int $id
 * @property string $ulid
 * @property int $exam_id
 * @property int $student_id
 * @property int $section_id
 * @property ReportCardKind $kind
 * @property ReportCardStatus $status
 * @property string|null $remark
 * @property array<string, mixed>|null $snapshot ReportCardData as it was sent (generated cards)
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property int|null $prepared_by_staff_id
 * @property Carbon $prepared_at
 * @property int|null $decided_by_staff_id
 * @property Carbon|null $decided_at
 * @property string|null $return_note
 */
class ReportCard extends Model
{
    use UsesTenantConnection;

    protected $table = 'report_cards';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'exam_id', 'student_id', 'section_id', 'kind', 'status', 'remark', 'snapshot',
        'file_path', 'file_name', 'mime_type', 'size_bytes', 'prepared_by_staff_id', 'prepared_at',
        'decided_by_staff_id', 'decided_at', 'return_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReportCardKind::class,
            'status' => ReportCardStatus::class,
            'snapshot' => 'array',
            'prepared_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<Exam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
