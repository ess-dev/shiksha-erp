<?php

namespace App\Http\Controllers\Guardian;

use App\Domain\Fees\Data\ParentFeesData;
use App\Domain\Fees\Queries\ParentFeesQuery;
use App\Domain\Identity\Authentication\SchoolContext;
use App\Domain\Identity\Models\Identity;
use App\Domain\Students\Data\ParentPortalContext;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Queries\ParentPortalContextQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeesController extends Controller
{
    public function __construct(
        private readonly ParentPortalContextQuery $portal,
        private readonly SchoolContext $contexts,
        private readonly ParentFeesQuery $fees,
    ) {}

    /**
     * The selected child's installments, payments and receipts in the viewed
     * session (`?child=<ulid>`). Another family's child is a 404 (see
     * ParentPortalContextQuery).
     */
    public function index(Request $request): Response
    {
        $context = $this->context($request);

        return Inertia::render('parent/fees', [
            'shell' => $context->shell,
            'fees' => $this->feesOf($context),
            'statement_url' => $context->child === null ? null : route('parent.fees.statement', ['child' => $context->child->ulid]),
        ]);
    }

    /**
     * A printable statement of the child's fees for the viewed session.
     */
    public function statement(Request $request): Response
    {
        $context = $this->context($request);

        if ($context->child === null) {
            throw StudentException::notFound();
        }

        return Inertia::render('parent/fee-statement', [
            'fees' => $this->feesOf($context),
            'school_name' => $context->shell->school_name,
            'timezone' => $context->shell->timezone,
            'back_url' => route('parent.fees', ['child' => $context->child->ulid]),
        ]);
    }

    /**
     * A confirmed payment's printable receipt.
     */
    public function receipt(Request $request, string $payment): Response
    {
        $context = $this->context($request);
        $child = $context->child ?? throw StudentException::notFound();
        $receipt = $this->fees->receipt($child->studentKey, $payment) ?? throw StudentException::notFound();

        return Inertia::render('parent/fee-receipt', [
            'receipt' => $receipt,
            'timezone' => $context->shell->timezone,
            'back_url' => route('parent.fees', ['child' => $child->ulid]),
        ]);
    }

    private function feesOf(ParentPortalContext $context): ?ParentFeesData
    {
        $child = $context->child;

        return $child === null ? null : $this->fees->get(
            $child->studentKey,
            $context->session['key'],
            $context->session['name'],
            $context->session['is_current'],
            $child->fullName,
            $child->firstName,
            $child->sectionLabel,
        );
    }

    private function context(Request $request): ParentPortalContext
    {
        $identity = $request->user();
        abort_unless($identity instanceof Identity, 403);

        return $this->portal->resolve(
            $identity,
            $request->query('child') === null ? null : $request->string('child')->toString(),
            $request->session()->get('parent.session'),
            $this->contexts->canActAsStaff($identity),
        );
    }
}
