<?php

namespace App\Domain\School\Models;

use App\Domain\Access\Data\ScopeTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A class at one campus in one academic year ("Class 5 A, Main Campus, 2026-27").
 *
 * @property int $id
 * @property string $ulid
 * @property int $academic_year_id
 * @property int $campus_id
 * @property int $school_class_id
 * @property string $name
 * @property int|null $capacity
 * @property string $status
 */
class Section extends Model
{
    use UsesTenantConnection;

    protected $table = 'sections';

    /** @var list<string> */
    protected $fillable = ['ulid', 'academic_year_id', 'campus_id', 'school_class_id', 'name', 'capacity', 'status'];

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Campus, $this>
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Where this section sits, for scope checks (campus, class level, section).
     */
    public function scopeTarget(): ScopeTarget
    {
        return new ScopeTarget($this->campus->ulid, $this->schoolClass->level, $this->ulid);
    }
}
