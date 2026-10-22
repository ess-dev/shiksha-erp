<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\Campus;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;

class CreateCampus extends Action
{
    public function __construct(private readonly StructureGuard $guard) {}

    public function handle(Identity $actor, string $name, string $code): Campus
    {
        $this->guard->assertMayManageSchool($actor);

        $name = trim($name);
        $code = strtoupper(trim($code));

        return $this->transaction(function () use ($name, $code) {
            if (Campus::query()->where('code', $code)->exists()) {
                throw StructureException::duplicate("A campus with code [{$code}]");
            }

            $campus = Campus::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'name' => $name,
                'code' => $code,
                'status' => 'active',
            ]);

            $this->audit(new AuditEntry('structure.campus_created', 'campus', $campus->ulid, null, [
                'name' => $name, 'code' => $code,
            ]));

            return $campus;
        });
    }
}
