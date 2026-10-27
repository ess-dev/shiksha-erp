<?php

namespace App\Domain\Leave\Data;

use App\Domain\Access\Data\StaffOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The "Staff and leave" screen: leave requests of staff the person may decide
 * on (tab `leave`) and the staff directory (tab `directory`). Each tab is only
 * sent to someone allowed to see it.
 */
#[TypeScript]
class StaffLeaveRequestsScreenData extends Data
{
    /**
     * @param  list<StaffLeaveRequestData>  $requests
     * @param  list<StaffDirectoryRowData>  $directory
     * @param  list<StaffOptionData>  $cover_options
     */
    public function __construct(
        public string $tab,
        public bool $can_view_leave,
        public bool $can_view_directory,
        public int $pending_count,
        public array $requests,
        public array $directory,
        public array $cover_options,
    ) {}
}
