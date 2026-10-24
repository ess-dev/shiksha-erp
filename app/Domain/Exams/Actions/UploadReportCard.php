<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Access\Queries\StaffRefQuery;
use App\Domain\Attendance\Queries\RosterQuery;
use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Contracts\ReportCardNotifier;
use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\Exam;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\ReportCardAccess;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SchoolSettingsQuery;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;
use App\Tenancy\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class UploadReportCard extends Action
{
    /** Types are read from the file's content, never its extension. */
    public const ALLOWED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** illustrative — verify: 5 MB, as for student documents. */
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly ReportCardAccess $access,
        private readonly RosterQuery $roster,
        private readonly SchoolSettingsQuery $settings,
        private readonly StaffRefQuery $staff,
        private readonly ReportCardNotifier $notifier,
    ) {}

    /**
     * The class teacher uploads a scan of a report card they made outside the
     * system (PDF, JPEG or PNG up to 5 MB) for one student of their section.
     * Stored on the tenant disk under the school's prefix; never public. It
     * replaces nothing published: the student must have no report card for
     * this exam yet, or one that was sent back or withdrawn. The marks need
     * not be ready, since the school made the card itself.
     */
    public function handle(Identity $actor, Exam $exam, string $sectionUlid, string $studentUlid, UploadedFile $file): ReportCard
    {
        $section = $this->access->preparerSectionOrFail($actor, $sectionUlid);
        $exam = $this->access->examOrFail($section, $exam);

        $student = null;

        foreach ($this->roster->for($section->sectionKey, CalendarDate::today()) as $row) {
            if ($row->ulid === $studentUlid) {
                $student = $row;
            }
        }

        if ($student === null) {
            throw ReportCardException::notFound();
        }

        $existing = ReportCard::query()->where('exam_id', $exam->id)->where('student_id', $student->studentKey)->first();

        if ($existing !== null && $existing->status !== ReportCardStatus::Returned) {
            throw ReportCardException::alreadyPrepared($student->name);
        }

        $mime = (string) $file->getMimeType();
        $size = $file->getSize();

        if (! isset(self::ALLOWED_TYPES[$mime]) || $size === false || $size > self::MAX_BYTES) {
            throw ReportCardException::badFile();
        }

        $tenant = Tenant::current() ?? throw ReportCardException::notAllowed();
        $ulid = strtolower((string) Str::ulid());
        $path = "tenants/{$tenant->ulid}/report-cards/{$exam->ulid}/{$ulid}.".self::ALLOWED_TYPES[$mime];

        Storage::disk('tenant')->putFileAs(dirname($path), $file, basename($path));

        $status = $this->settings->reportCardApproval() ? ReportCardStatus::Submitted : ReportCardStatus::Published;
        $staffKey = $this->staff->forIdentity($actor)?->staffKey;

        try {
            $card = $this->transaction(function () use ($exam, $section, $student, $status, $staffKey, $ulid, $path, $file, $mime, $size) {
                $card = ReportCard::query()->updateOrCreate(['exam_id' => $exam->id, 'student_id' => $student->studentKey], [
                    'ulid' => $ulid,
                    'section_id' => $section->sectionKey,
                    'kind' => ReportCardKind::File,
                    'status' => $status,
                    'remark' => null,
                    'snapshot' => null,
                    'file_path' => $path,
                    'file_name' => mb_substr($file->getClientOriginalName(), 0, 200),
                    'mime_type' => $mime,
                    'size_bytes' => (int) $size,
                    'prepared_by_staff_id' => $staffKey,
                    'prepared_at' => now(),
                    'decided_by_staff_id' => $status === ReportCardStatus::Published ? $staffKey : null,
                    'decided_at' => $status === ReportCardStatus::Published ? now() : null,
                    'return_note' => null,
                ]);

                $this->audit(new AuditEntry('report_cards.uploaded', 'exam', $exam->ulid, null, [
                    'section' => $section->ulid,
                    'student' => $student->ulid,
                    'report_card' => $ulid,
                    'status' => $status->value,
                ]));

                return $card;
            });
        } catch (Throwable $e) {
            Storage::disk('tenant')->delete($path);

            throw $e;
        }

        $this->notifier->notify($status === ReportCardStatus::Published ? 'published' : 'submitted', $exam->ulid, $section->ulid, [$student->ulid]);

        return $card;
    }
}
