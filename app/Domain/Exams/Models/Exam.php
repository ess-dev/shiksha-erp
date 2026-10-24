<?php

namespace App\Domain\Exams\Models;

use App\Domain\Exams\Enums\ExamStatus;
use App\Domain\Exams\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A unit test or exam for some classes at some campuses.
 *
 * @property int $id
 * @property string $ulid
 * @property int $academic_year_id
 * @property string $name
 * @property ExamType $type
 * @property int $max_marks
 * @property string $starts_on
 * @property string $ends_on
 * @property ExamStatus $status
 * @property string|null $instructions
 * @property bool $applies_to_all_classes
 * @property bool $applies_to_all_campuses
 * @property bool $show_on_calendar
 * @property int|null $created_by_staff_id
 * @property Carbon|null $published_at
 */
class Exam extends Model
{
    use UsesTenantConnection;

    protected $table = 'exams';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'academic_year_id', 'name', 'type', 'max_marks', 'starts_on', 'ends_on', 'status', 'instructions',
        'applies_to_all_classes', 'applies_to_all_campuses', 'show_on_calendar', 'created_by_staff_id', 'published_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'status' => ExamStatus::class,
            'starts_on' => 'string',
            'ends_on' => 'string',
            'applies_to_all_classes' => 'boolean',
            'applies_to_all_campuses' => 'boolean',
            'show_on_calendar' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<ExamDateSheetEntry, $this>
     */
    public function dateSheet(): HasMany
    {
        return $this->hasMany(ExamDateSheetEntry::class);
    }

    /**
     * Whether any marks were entered for this exam. Marks entry is not built
     * yet, so this is always false; it is the one place to change when it is
     * (it locks maximum marks and blocks deleting the exam).
     */
    public function hasMarks(): bool
    {
        return false;
    }
}
