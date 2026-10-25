<?php

namespace Database\Seeders;

use App\Domain\Fees\Actions\AssignClassToFeeGroup;
use App\Domain\Fees\Actions\CreateFeeGroup;
use App\Domain\Fees\Actions\RecordCounterPayment;
use App\Domain\Fees\Actions\SaveFeeGroupHeads;
use App\Domain\Fees\Actions\SetUpFeeSchedule;
use App\Domain\Fees\CounterPayment;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\InvoiceBiller;
use App\Domain\Fees\Models\FeeInstallment;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeePaymentAllocation;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\Students\Models\Student;
use App\Tenancy\Tenant;
use DateTimeInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fictional fee structure, invoices and payment history for the demo schools,
 * for local development only. Everything goes through the real Actions (as the
 * school's owner) so receipts, numbers and audit rows are the real thing; the
 * payment dates are then moved back to plausible days. Skipped for a school
 * that already has an installment schedule, so it is safe to re-run.
 * illustrative — verify: fee heads and amounts are placeholders from the prototype.
 */
class DemoFeesSeeder extends Seeder
{
    private const MODES = [PaymentMode::Upi, PaymentMode::Upi, PaymentMode::Cash, PaymentMode::Card, PaymentMode::BankTransfer];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo fee data must never be seeded in production.');
        }

        foreach (['greenfield', 'riverside'] as $subdomain) {
            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

            if ($tenant === null) {
                continue;
            }

            // Local only: the test gateway takes no real money (config limits it to local and testing).
            $tenant->forceFill(['payment_gateway' => 'fake'])->save();

            $owner = Identity::query()->where('email', "principal@{$subdomain}.test")->first();

            if ($owner !== null) {
                $tenant->execute(fn () => $this->seedSchool($owner));
            }
        }
    }

    private function seedSchool(Identity $owner): void
    {
        $year = AcademicYear::query()->where('is_current', true)->first();

        if ($year === null || FeeInstallment::query()->where('academic_year_id', $year->id)->exists()) {
            return;
        }

        $classes = SchoolClass::query()->orderBy('level')->get();

        if ($classes->isEmpty()) {
            return;
        }

        app(SetUpFeeSchedule::class)->handle($owner);

        $groups = [
            ['Classes 1–3', [1, 2, 3], [['Tuition fee', 8400], ['Development fee', 1200], ['Activity fee', 900]]],
            ['Classes 4–5', [4, 5], [['Tuition fee', 10200], ['Development fee', 1350], ['Computer lab fee', 1200]]],
        ];

        foreach ($groups as [$name, $levels, $heads]) {
            $ulids = $classes->whereIn('level', $levels)->pluck('ulid')->all();

            if ($ulids === []) {
                continue;
            }

            // Fees first, then the classes: a student is billed once their class has a priced group.
            $group = app(CreateFeeGroup::class)->handle($owner, $name, null, [])['group'];
            app(SaveFeeGroupHeads::class)->handle($owner, $group, array_map(fn (array $head) => ['name' => $head[0], 'amount' => $head[1]], $heads));

            foreach ($ulids as $ulid) {
                app(AssignClassToFeeGroup::class)->handle($owner, $ulid, $group);
            }
        }

        // The demo students joined this week, but a family's history reaches back: bill the whole year.
        app(InvoiceBiller::class)->bill($year->id, null, true);

        $this->payHistory($owner);
        $this->openProblems($owner);
    }

    private function payHistory(Identity $owner): void
    {
        $record = app(RecordCounterPayment::class);

        foreach (Student::query()->where('status', 'enrolled')->orderBy('id')->get() as $student) {
            $invoices = FeeInvoice::query()->where('student_id', $student->id)->orderBy('due_on')->get();

            // The demo parent's child gets a story of their own (see openProblems).
            if ($invoices->count() < 3 || $this->isDemoParentChild($student)) {
                continue;
            }

            $roll = abs(crc32($student->ulid)) % 100;
            $mode = self::MODES[$roll % count(self::MODES)];
            $plan = match (true) {
                $roll < 60 => ['paid' => $roll % 2 === 0 ? 3 : 2, 'partial' => 0],
                $roll < 78 => ['paid' => 2, 'partial' => 0],
                $roll < 90 => ['paid' => 1, 'partial' => 0],
                default => ['paid' => 2, 'partial' => 40],
            };

            foreach ($invoices->take($plan['paid']) as $index => $invoice) {
                $this->pay($record, $owner, $student, $invoice, $mode, null, $this->paidOn($index, $roll));
            }

            if ($plan['partial'] > 0) {
                $third = $invoices[2];
                // Whole rupees: 40% of the installment, rounded down to the rupee.
                $amount = intdiv(intdiv($third->net_paise * $plan['partial'], 100), 100) * 100;

                if ($amount > 0) {
                    $this->pay($record, $owner, $student, $third, PaymentMode::Upi, $amount, $this->paidOn(2, $roll));
                }
            }
        }
    }

    /**
     * A few stories to try the screens on: Priya Sharma's child (the demo parent) with two terms
     * paid and a part payment on the third, a pending net-banking payment, a failed card payment
     * and a cheque waiting to clear, each for a different student with the third term still open.
     */
    private function openProblems(Identity $owner): void
    {
        $record = app(RecordCounterPayment::class);
        $riya = Student::query()->where('first_name', 'Riya')->where('last_name', 'Sharma')->where('status', 'enrolled')->first();
        $used = [];

        if ($riya !== null) {
            $used[] = $riya->id;
            $invoices = FeeInvoice::query()->where('student_id', $riya->id)->orderBy('due_on')->get();

            foreach ($invoices->take(2) as $index => $invoice) {
                $this->pay($record, $owner, $riya, $invoice, PaymentMode::Upi, null, $this->paidOn($index, 4));
            }

            if (isset($invoices[2])) {
                $this->pay($record, $owner, $riya, $invoices[2], PaymentMode::Upi, 500000, now()->subDays(16));
            }
        }

        $openThirdTerm = function () use (&$used) {
            return FeeInvoice::query()
                ->where('paid_paise', 0)
                ->whereNotIn('student_id', $used)
                ->whereIn('installment_id', FeeInstallment::query()->where('position', 3)->select('id'))
                ->orderBy('id')
                ->first();
        };

        $pending = $openThirdTerm();

        if ($pending !== null) {
            $used[] = $pending->student_id;
            $this->online(Student::query()->findOrFail($pending->student_id), $pending, PaymentStatus::Pending, PaymentMode::NetBanking, 'NB'.random_int(100000000, 999999999));
        }

        $failed = $openThirdTerm();

        if ($failed !== null) {
            $used[] = $failed->student_id;
            $this->online(Student::query()->findOrFail($failed->student_id), $failed, PaymentStatus::Failed, PaymentMode::Card, 'CRD'.random_int(100000000, 999999999));
        }

        $cheque = $openThirdTerm();

        if ($cheque !== null) {
            $record->handle($owner, new CounterPayment(
                studentUlid: (string) Student::query()->whereKey($cheque->student_id)->value('ulid'),
                invoiceUlids: [$cheque->ulid],
                amountPaise: null,
                mode: PaymentMode::Cheque,
                reference: null,
                chequeNumber: (string) random_int(100000, 999999),
                bank: 'State Bank of India',
                chequeDate: now()->subDays(2)->format('Y-m-d'),
                note: null,
                receivedFrom: null,
                idempotencyKey: 'demo-'.Str::uuid(),
            ));
        }
    }

    private function pay(RecordCounterPayment $record, Identity $owner, Student $student, FeeInvoice $invoice, PaymentMode $mode, ?int $amountPaise, DateTimeInterface $at): void
    {
        $payment = $record->handle($owner, new CounterPayment(
            studentUlid: $student->ulid,
            invoiceUlids: [$invoice->ulid],
            amountPaise: $amountPaise,
            mode: $mode,
            reference: $mode === PaymentMode::Cash ? null : $mode->referencePrefix().random_int(100000000, 999999999),
            chequeNumber: null,
            bank: null,
            chequeDate: null,
            note: $amountPaise === null ? null : 'Part payment',
            receivedFrom: null,
            idempotencyKey: 'demo-'.Str::uuid(),
        ));

        $payment->forceFill(['paid_at' => $at])->save();
    }

    private function isDemoParentChild(Student $student): bool
    {
        return $student->last_name === 'Sharma' && $student->first_name === 'Riya';
    }

    private function paidOn(int $installmentIndex, int $roll): DateTimeInterface
    {
        $base = match ($installmentIndex) {
            0 => now()->setDate(2026, 4, 2),
            1 => now()->setDate(2026, 7, 3),
            default => now()->subDays(12),
        };

        return $base->addDays($roll % 9)->setTime(9 + $roll % 10, $roll % 60);
    }

    private function online(Student $student, FeeInvoice $invoice, PaymentStatus $status, PaymentMode $mode, string $reference): FeePayment
    {
        $payment = FeePayment::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'student_id' => $student->id,
            'mode' => $mode,
            'status' => $status,
            'channel' => 'online',
            'amount_paise' => $invoice->balancePaise(),
            'paid_at' => now()->subHours(20),
            'reference' => $reference,
            'gateway' => 'fake',
            'received_from' => 'Parent',
            'failure_reason' => $status === PaymentStatus::Failed ? 'Issuer declined the transaction' : null,
            'note' => $status === PaymentStatus::Failed ? 'Bank declined: insufficient funds (demo)' : 'Awaiting bank confirmation (demo)',
        ]);

        FeePaymentAllocation::query()->create([
            'fee_payment_id' => $payment->id,
            'fee_invoice_id' => $invoice->id,
            'amount_paise' => $invoice->balancePaise(),
        ]);

        return $payment;
    }
}
