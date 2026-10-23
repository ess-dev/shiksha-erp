<?php

namespace App\Domain\Students\Models;

use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A student of one school. Lifecycle is `status`; the record is never deleted.
 *
 * @property int $id
 * @property string $ulid
 * @property string $admission_number
 * @property string $first_name
 * @property string $last_name
 * @property Gender $gender
 * @property string $born_on
 * @property BloodGroup $blood_group
 * @property int|null $house_id
 * @property string $address
 * @property StudentStatus $status
 * @property string $admitted_on
 * @property string|null $left_on
 * @property string|null $leave_reason
 * @property string|null $tc_number
 */
class Student extends Model
{
    use UsesTenantConnection;

    protected $table = 'students';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'admission_number', 'first_name', 'last_name', 'gender', 'born_on', 'blood_group', 'house_id',
        'address', 'status', 'admitted_on', 'left_on', 'leave_reason', 'tc_number',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'blood_group' => BloodGroup::class,
            'status' => StudentStatus::class,
            'born_on' => 'string',
            'admitted_on' => 'string',
            'left_on' => 'string',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * @return BelongsToMany<Guardian, $this, GuardianStudent>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->using(GuardianStudent::class)
            ->withPivot(['relation', 'is_primary', 'has_portal_access'])
            ->orderByDesc('guardian_student.is_primary')
            ->orderBy('guardians.name');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasOne<Enrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->where('is_current', true);
    }

    /**
     * @return HasMany<StudentDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }
}
