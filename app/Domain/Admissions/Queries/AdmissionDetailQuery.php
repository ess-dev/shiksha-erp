<?php

namespace App\Domain\Admissions\Queries;

use App\Domain\Admissions\AdmissionDocumentRules;
use App\Domain\Admissions\AdmissionVisibility;
use App\Domain\Admissions\ApplicationHistory;
use App\Domain\Admissions\Data\ApplicationDetailData;
use App\Domain\Admissions\Data\DocumentRowData;
use App\Domain\Admissions\Data\HistoryRowData;
use App\Domain\Admissions\Enums\AdmissionDocumentKind;
use App\Domain\Admissions\Enums\AdmissionStage;
use App\Domain\Admissions\Exceptions\AdmissionException;
use App\Domain\Admissions\Models\AdmissionApplication;
use App\Domain\Admissions\Models\AdmissionApplicationEvent;
use App\Domain\Admissions\Models\AdmissionDocument;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AdmissionDetailQuery
{
    public function __construct(
        private readonly AdmissionVisibility $visibility,
        private readonly AdmissionDocumentRules $rules,
        private readonly ApplicationHistory $history,
    ) {}

    /**
     * The same, looked up by ulid; an unknown ulid is a 404 too.
     */
    public function find(Identity $identity, string $ulid): ApplicationDetailData
    {
        $application = AdmissionApplication::query()->where('ulid', $ulid)->first() ?? throw AdmissionException::notFound();

        return $this->get($identity, $application);
    }

    /**
     * One application for the details dialog. An application outside the
     * person's reach is a 404 (not a refusal), so its existence is not
     * confirmed.
     */
    public function get(Identity $identity, AdmissionApplication $application): ApplicationDetailData
    {
        if (! $this->visibility->allows($identity, $application, 'admissions.view')) {
            throw AdmissionException::notFound();
        }

        $connection = DB::connection(config('multitenancy.tenant_database_connection_name'));
        $campus = (string) $connection->table('campuses')->where('id', $application->campus_id)->value('name');
        $class = $connection->table('school_classes')->where('id', $application->school_class_id)->first(['ulid', 'name']);
        $houseUlid = $application->house_id === null ? null : $connection->table('houses')->where('id', $application->house_id)->value('ulid');
        $studentUlid = $application->student_id === null ? null : $connection->table('students')->where('id', $application->student_id)->value('ulid');

        $mayWork = ! $application->isLocked();
        $next = $mayWork && $application->stage->position() < AdmissionStage::Decision->position()
            ? AdmissionStage::cases()[$application->stage->position() + 1]
            : null;

        $canOpen = $this->visibility->scopes($identity, 'students.view_sensitive')->allows($this->visibility->target($application));

        $phone = $canOpen
            ? $application->phone
            : PhoneNumber::mask($application->phone);

        return new ApplicationDetailData(
            ulid: $application->ulid,
            name: $application->name(),
            stage: $application->stage->value,
            stage_label: $application->stage->label(),
            next_stage: $next?->value,
            next_stage_label: $next?->label(),
            decision: $application->decision?->value,
            decision_label: $application->decision?->label(),
            is_closed: $application->is_closed,
            is_locked: $application->isLocked(),
            class_name: (string) ($class->name ?? ''),
            campus_name: $campus,
            gender: $application->gender,
            gender_label: ucfirst($application->gender),
            born_on: $application->born_on,
            blood_group: $application->blood_group,
            address: $application->address,
            house_ulid: $houseUlid === null ? null : (string) $houseUlid,
            parent_name: $application->parent_name,
            parent_relation: $application->parent_relation,
            phone: $phone,
            email: $application->email,
            source_label: $application->source->label(),
            enquired_on: $application->enquired_on,
            notes: $application->notes,
            details_complete: $this->history->detailsComplete($application),
            missing_documents: $this->history->missingDocuments($application),
            student_ulid: $studentUlid === null ? null : (string) $studentUlid,
            documents: $this->documents($application, (string) ($class->ulid ?? ''), $canOpen),
            history: array_values($application->events->map(fn (AdmissionApplicationEvent $event) => new HistoryRowData(
                label: $event->stage->label(),
                happened_on: $event->happened_on,
                note: $event->note,
            ))->all()),
        );
    }

    /**
     * Required documents for the class, plus any other kind already ticked or uploaded.
     *
     * @return list<DocumentRowData>
     */
    private function documents(AdmissionApplication $application, string $classUlid, bool $canOpen): array
    {
        $required = array_map(fn (AdmissionDocumentKind $kind) => $kind->value, $this->rules->requiredFor($classUlid));
        $rows = $application->documents()->get()->keyBy(fn (AdmissionDocument $document) => $document->kind->value);

        $out = [];

        foreach (AdmissionDocumentKind::cases() as $kind) {
            $document = $rows->get($kind->value);
            $isRequired = in_array($kind->value, $required, true);

            if (! $isRequired && ($document === null || (! $document->is_received && $document->path === null))) {
                continue;
            }

            $out[] = new DocumentRowData(
                kind: $kind->value,
                label: $kind->label(),
                is_required: $isRequired,
                is_received: (bool) $document?->is_received,
                file_name: $document?->original_name,
                uploaded_on: $document?->uploaded_on,
                view_url: $canOpen && $document?->path !== null
                    ? URL::temporarySignedRoute('admissions.documents.show', now()->addMinutes(5), ['application' => $application->ulid, 'kind' => $kind->value])
                    : null,
            );
        }

        return $out;
    }
}
