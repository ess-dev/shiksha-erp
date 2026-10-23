<?php

namespace App\Domain\Students\Models;

use App\Domain\Students\Enums\DocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A scanned document of a student, stored on the tenant disk (never public).
 *
 * @property int $id
 * @property string $ulid
 * @property int $student_id
 * @property DocumentKind $kind
 * @property string $original_name
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $source
 * @property string|null $uploaded_by_identity_ulid
 * @property Carbon|null $verified_at
 * @property string|null $verified_by_identity_ulid
 * @property Carbon|null $superseded_at
 * @property Carbon $created_at
 */
class StudentDocument extends Model
{
    use UsesTenantConnection;

    protected $table = 'student_documents';

    /** @var list<string> */
    protected $fillable = [
        'ulid', 'student_id', 'kind', 'original_name', 'path', 'mime_type', 'size_bytes', 'source',
        'uploaded_by_identity_ulid', 'verified_at', 'verified_by_identity_ulid', 'superseded_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['kind' => DocumentKind::class, 'verified_at' => 'datetime', 'superseded_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
