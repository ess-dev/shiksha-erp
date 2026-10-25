<?php

namespace App\Domain\Fees\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Fees\Contracts\FeeNotifier;
use App\Domain\Fees\Exceptions\FeeException;
use App\Domain\Fees\FeeVisibility;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Identity\Models\Identity;
use App\Domain\Shared\Action;
use App\Domain\Shared\CalendarDate;

class SendFeeReminders extends Action
{
    public function __construct(
        private readonly FeeVisibility $visibility,
        private readonly FeeNotifier $notifier,
    ) {}

    /**
     * Reminds guardians about unpaid installments (through the FeeNotifier;
     * the SMS and push providers are open decisions) and stamps today's date
     * on them, so the Dues screen shows "Reminded". Installments out of the
     * person's reach are treated as not found. Needs `fees.remind`.
     *
     * @param  list<string>  $invoiceUlids
     * @return array{invoices: int, students: int}
     */
    public function handle(Identity $actor, array $invoiceUlids): array
    {
        $this->visibility->assertHolds($actor, 'fees.remind');

        $invoiceUlids = array_values(array_unique($invoiceUlids));

        if ($invoiceUlids === []) {
            throw FeeException::nothingSelected();
        }

        $query = $this->visibility->invoices($actor, 'fees.remind') ?? throw FeeException::notAllowed();
        $invoices = $query->whereIn('fee_invoices.ulid', $invoiceUlids)->get();

        if ($invoices->count() !== count($invoiceUlids)) {
            throw FeeException::notFound();
        }

        $owing = $invoices->filter(fn (FeeInvoice $invoice) => $invoice->balancePaise() > 0)->values();

        if ($owing->isEmpty()) {
            throw FeeException::alreadyPaid();
        }

        $students = $owing->pluck('student_id')->unique()->count();

        $this->transaction(function () use ($owing, $students) {
            FeeInvoice::query()->whereIn('id', $owing->pluck('id')->all())->update(['last_reminded_on' => CalendarDate::today()]);

            $this->audit(new AuditEntry('fees.reminders_sent', 'fee_invoice', null, null, [
                'invoices' => $owing->count(),
                'students' => $students,
            ]));
        });

        $this->notifier->remind(array_values(array_map(strval(...), $owing->pluck('ulid')->all())));

        return ['invoices' => $owing->count(), 'students' => $students];
    }
}
