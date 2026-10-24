<?php

namespace App\Domain\Exams\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Exams\Enums\ReportCardStatus;
use App\Domain\Exams\Exceptions\ReportCardException;
use App\Domain\Exams\Models\ReportCard;
use App\Domain\Exams\ReportCardAccess;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\Action;

class WithdrawReportCard extends Action
{
    public const WITHDRAWN_NOTE = 'Withdrawn by the class teacher';

    public function __construct(
        private readonly ReportCardAccess $access,
        private readonly SectionDirectoryQuery $sections,
    ) {}

    /**
     * The class teacher takes back a report card that is waiting for
     * approval, to change it and send it again. Published ones are final.
     */
    public function handle(Identity $actor, ReportCard $card): void
    {
        $section = $this->sections->byKeys([$card->section_id])[$card->section_id] ?? throw ReportCardException::notFound();
        $this->access->preparerSectionOrFail($actor, $section->ulid);

        $this->transaction(function () use ($card) {
            $card = ReportCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();

            if ($card->status !== ReportCardStatus::Submitted) {
                throw ReportCardException::nothingToDo();
            }

            $card->update(['status' => ReportCardStatus::Returned, 'return_note' => self::WITHDRAWN_NOTE]);

            $this->audit(new AuditEntry('report_cards.withdrawn', 'report_card', $card->ulid, ['status' => 'submitted'], ['status' => 'returned']));
        });
    }
}
