<?php

namespace Tests\Concerns;

use App\Domain\Fees\Actions\AssignClassToFeeGroup;
use App\Domain\Fees\Actions\CreateFeeGroup;
use App\Domain\Fees\Actions\SaveFeeGroupHeads;
use App\Domain\Fees\Actions\SetUpFeeSchedule;
use App\Domain\Fees\Enums\PaymentChannel;
use App\Domain\Fees\Enums\PaymentMode;
use App\Domain\Fees\Enums\PaymentStatus;
use App\Domain\Fees\InvoiceBiller;
use App\Domain\Fees\Models\FeeGroup;
use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Fees\Models\FeePayment;
use App\Domain\Fees\Models\FeePaymentAllocation;
use App\Domain\School\Models\AcademicYear;
use App\Domain\School\Models\SchoolClass;
use App\Domain\Students\Models\Student;
use Illuminate\Support\Str;

/**
 * Fee structure for the fictional school of BuildsStudents: a four-term
 * schedule and one fee group for Classes 1 and 2 (₹8,400 tuition, ₹1,200
 * development, ₹900 activity: ₹10,500 per term). Needs BuildsStudents and
 * the fees module in the plan.
 */
trait BuildsFees
{
    public FeeGroup $feeGroup;

    public function setUpFeeStructure(): void
    {
        app(SetUpFeeSchedule::class)->handle($this->owner);

        $this->feeGroup = app(CreateFeeGroup::class)->handle($this->owner, 'Classes 1–2', null, [
            SchoolClass::query()->where('level', 1)->value('ulid'),
            SchoolClass::query()->where('level', 2)->value('ulid'),
        ])['group'];

        app(SaveFeeGroupHeads::class)->handle($this->owner, $this->feeGroup, [
            ['name' => 'Tuition fee', 'amount' => 8400],
            ['name' => 'Development fee', 'amount' => 1200],
            ['name' => 'Activity fee', 'amount' => 900],
        ]);

        $this->feeGroup->refresh();
    }

    /**
     * Bills every term whatever the day the test runs on (a student admitted
     * today is normally billed only for terms that have not ended). Call it
     * after the students exist; set the fee structure up before creating them.
     */
    public function billAllTerms(): void
    {
        $year = AcademicYear::query()->where('is_current', true)->firstOrFail();

        app(InvoiceBiller::class)->bill($year->id, null, true);
    }

    /**
     * @return list<FeeInvoice> the student's invoices, first installment first
     */
    public function invoicesOf(Student $student): array
    {
        return FeeInvoice::query()->where('student_id', $student->id)->orderBy('due_on')->get()->all();
    }

    /**
     * Makes a student's installment fall due on a given day, so its status does not depend on today's date.
     */
    public function dueOn(FeeInvoice $invoice, string $date): FeeInvoice
    {
        $invoice->forceFill(['due_on' => $date])->save();

        return $invoice;
    }

    /**
     * An online payment that has not been applied to its installments:
     * pending (waiting for the gateway) or failed.
     *
     * @param  list<FeeInvoice>  $invoices
     */
    public function onlinePayment(Student $student, array $invoices, PaymentStatus $status = PaymentStatus::Pending, PaymentMode $mode = PaymentMode::NetBanking, string $reference = 'NB197164692'): FeePayment
    {
        $payment = FeePayment::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'student_id' => $student->id,
            'mode' => $mode,
            'status' => $status,
            'channel' => PaymentChannel::Online,
            'amount_paise' => array_sum(array_map(fn (FeeInvoice $invoice) => $invoice->balancePaise(), $invoices)),
            'paid_at' => now()->subHour(),
            'reference' => $reference,
            'gateway' => 'fake',
            'received_from' => 'Parent',
            'failure_reason' => $status === PaymentStatus::Failed ? 'Issuer declined the transaction' : null,
        ]);

        foreach ($invoices as $invoice) {
            FeePaymentAllocation::query()->create([
                'fee_payment_id' => $payment->id,
                'fee_invoice_id' => $invoice->id,
                'amount_paise' => $invoice->balancePaise(),
            ]);
        }

        return $payment;
    }

    /** Chooses the payment gateway driver for the current test school. */
    public function useGateway(?string $key = 'fake'): void
    {
        $this->school->forceFill(['payment_gateway' => $key])->save();
        $this->school->makeCurrent();
    }

    public function assignFeeGroupTo(string $classUlid): void
    {
        app(AssignClassToFeeGroup::class)->handle($this->owner, $classUlid, $this->feeGroup);
    }
}
