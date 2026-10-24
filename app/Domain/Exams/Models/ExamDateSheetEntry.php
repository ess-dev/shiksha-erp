<?php

namespace App\Domain\Exams\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One subject on an exam's date sheet.
 *
 * @property int $id
 * @property int $exam_id
 * @property int $subject_id
 * @property string $held_on
 * @property string|null $begins_at
 */
class ExamDateSheetEntry extends Model
{
    use UsesTenantConnection;

    public $timestamps = false;

    protected $table = 'exam_date_sheet_entries';

    /** @var list<string> */
    protected $fillable = ['exam_id', 'subject_id', 'held_on', 'begins_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['held_on' => 'string'];
    }
}
