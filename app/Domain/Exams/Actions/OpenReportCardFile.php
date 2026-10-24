<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Enums\ReportCardKind;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Shared\Action;
use Illuminate\Support\Facades\Storage;

class OpenReportCardFile extends Action
{
    /**
     * Records that an uploaded report card was opened (downloads are audited)
     * and returns its path on the tenant disk. The caller has already checked
     * that the person may see this report card.
     */
    public function handle(ReportCard $card): string
    {
        if ($card->kind !== ReportCardKind::File || $card->file_path === null) {
            throw ReportCardException::notFound();
        }

        $path = $card->file_path;

        return $this->transaction(function () use ($card, $path) {
            if (! Storage::disk('tenant')->exists($path)) {
                throw ReportCardException::fileMissing();
            }

            $this->audit(new AuditEntry('report_cards.file_opened', 'report_card', $card->ulid));

            return $path;
        });
    }
}
