<?php

namespace App\Domain\Admissions\Models;

use App\Domain\Admissions\Enums\AdmissionDecision;
use App\Domain\Admissions\Enums\AdmissionSource;
use App\Domain\Admissions\Enums\AdmissionStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One child's application, from enquiry to enrolment. Never deleted: a refused
 * application stays, closed.
 *
 * @property int $id
 * @property string $ulid
 * @property string $first_name
 * @property string $last_name
 * @property string $gender
 * @property string|null $born_on
 * @property string|null $blood_group
 * @property int|null $house_id
 * @property string|null $address
 * @property int $school_class_id
 * @property int $campus_id
 * @property int $academic_year_id
 * @property string $parent_name
 * @property string $parent_relation
 * @property string $phone
 * @property string|null $email
 * @property AdmissionSource $source
 * @property string|null $notes
 * @property AdmissionStage $stage
 * @property AdmissionDecision|null $decision
 * @property bool $is_closed
 * @property int|null $student_id
 * @property string $enquired_on
 * @property string|null $enrolled_on
 */
class AdmissionApplication extends Model
{
    use UsesTenantConnection;

    protected $table = 'admission_applications';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'first_name', 'last_name', 'gender', 'born_on', 'blood_group', 'house_id', 'address',
        'school_class_id', 'campus_id', 'academic_year_id', 'parent_name', 'parent_relation', 'phone', 'email',
        'source', 'notes', 'stage', 'decision', 'is_closed', 'student_id', 'enquired_on', 'enrolled_on',
        'created_by_identity_ulid',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'source' => AdmissionSource::class,
            'stage' => AdmissionStage::class,
            'decision' => AdmissionDecision::class,
            'is_closed' => 'boolean',
            'born_on' => 'string',
            'enquired_on' => 'string',
            'enrolled_on' => 'string',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return HasMany<AdmissionApplicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AdmissionApplicationEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<AdmissionDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(AdmissionDocument::class);
    }

    public function name(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function isLocked(): bool
    {
        return $this->is_closed || $this->stage === AdmissionStage::Enrolled;
    }
}
