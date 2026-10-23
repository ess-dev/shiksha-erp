<?php

namespace App\Domain\Admissions\Models;

use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * A document ticked as received and/or uploaded for an application. The file,
 * when there is one, lives on the tenant disk and is copied to the student on
 * enrolment.
 *
 * @property int $id
 * @property int $admission_application_id
 * @property AdmissionDocumentKind $kind
 * @property bool $is_received
 * @property string|null $original_name
 * @property string|null $path
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $uploaded_on
 * @property string|null $uploaded_by_identity_ulid
 */
class AdmissionDocument extends Model
{
    use UsesTenantConnection;

    protected $table = 'admission_documents';

    /** @var list<string> */
    protected $fillable = [
        'admission_application_id', 'kind', 'is_received', 'original_name', 'path', 'mime_type', 'size_bytes',
        'uploaded_on', 'uploaded_by_identity_ulid',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['kind' => AdmissionDocumentKind::class, 'is_received' => 'boolean', 'uploaded_on' => 'string'];
    }

    /**
     * @return BelongsTo<AdmissionApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }
}
