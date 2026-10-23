<?php

namespace App\Domain\Admissions;

use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Admissions\Models\AdmissionApplicationEvent;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\CalendarDate;

/**
 * Small helpers the admission Actions share: History lines and the count of
 * required documents still missing. Used inside Actions only.
 */
class ApplicationHistory
{
    public function __construct(private readonly AdmissionDocumentRules $rules) {}

    public function record(AdmissionApplication $application, AdmissionStage $stage, ?Identity $actor, ?string $note = null): void
    {
        AdmissionApplicationEvent::query()->create([
            'admission_application_id' => $application->id,
            'stage' => $stage,
            'note' => $note,
            'happened_on' => CalendarDate::today(),
            'by_identity_ulid' => $actor?->ulid,
        ]);
    }

    /**
     * How many of the documents this class requires are not ticked as received.
     */
    public function missingDocuments(AdmissionApplication $application): int
    {
        $classUlid = (string) AdmissionApplication::query()
            ->join('school_classes', 'school_classes.id', '=', 'admission_applications.school_class_id')
            ->where('admission_applications.id', $application->id)
            ->value('school_classes.ulid');

        $received = AdmissionDocument::query()
            ->where('admission_application_id', $application->id)
            ->where('is_received', true)
            ->get()
            ->map(fn (AdmissionDocument $document) => $document->kind->value)
            ->all();

        return count(array_filter(
            $this->rules->requiredFor($classUlid),
            fn ($kind) => ! in_array($kind->value, $received, true),
        ));
    }

    /**
     * The child's own details must be filled in before "Documents verified".
     */
    public function detailsComplete(AdmissionApplication $application): bool
    {
        return $application->born_on !== null
            && $application->blood_group !== null
            && $application->address !== null
            && trim($application->address) !== '';
    }
}
