<?php

namespace App\Domain\Admissions\Models;

use App\Domain\Admissions\Enums\AdmissionStage;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

/**
 * One line of an application's History list. Append-only.
 *
 * @property int $id
 * @property int $admission_application_id
 * @property AdmissionStage $stage
 * @property string|null $note
 * @property string $happened_on
 */
class AdmissionApplicationEvent extends Model
{
    use UsesTenantConnection;

    protected $table = 'admission_application_events';

    /** @var list<string> */
    protected $fillable = ['admission_application_id', 'stage', 'note', 'happened_on', 'by_identity_ulid'];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['stage' => AdmissionStage::class, 'happened_on' => 'string'];
    }
}
