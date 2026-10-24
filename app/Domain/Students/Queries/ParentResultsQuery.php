<?php

namespace App\Domain\Students\Queries;

use App\Domain\Exams\Data\StudentResultsData;
use App\Domain\Exams\Queries\StudentResultsQuery;
use App\Domain\Students\Data\GuardianChildRef;
use App\Domain\Students\Data\ReportCardData;
use App\Domain\Students\Exceptions\StudentException;
use Illuminate\Support\Facades\DB;

/**
 * The child's results and report card in the parent portal. The child comes
 * from ParentPortalContextQuery, so it is one of the guardian's own and
 * `sectionKey` is the section it was enrolled in that session.
 */
class ParentResultsQuery
{
    public function __construct(
        private readonly StudentResultsQuery $results,
        private readonly ReportCardQuery $reportCards,
    ) {}

    /**
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     */
    public function get(GuardianChildRef $child, array $session): StudentResultsData
    {
        return $this->results->get($child->studentKey, $child->sectionKey, $session['key'], $session['is_current']);
    }

    public function admissionNumber(GuardianChildRef $child): string
    {
        return (string) DB::connection(config('multitenancy.tenant_database_connection_name'))
            ->table('students')->where('id', $child->studentKey)->value('admission_number');
    }

    /**
     * @param  array{key: int, ulid: string, name: string, is_current: bool, starts_on: string, ends_on: string}  $session
     *
     * @throws StudentException 404 when the exam is not among the child's results
     */
    public function reportCard(GuardianChildRef $child, array $session, string $examUlid): ReportCardData
    {
        return $this->reportCards->build($child->studentKey, $child->sectionKey, $session, $examUlid)
            ?? throw StudentException::notFound();
    }
}
