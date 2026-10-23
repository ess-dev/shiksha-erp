<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\Subject;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;

class CreateSubject extends Action
{
    public function __construct(private readonly StructureGuard $guard) {}

    public function handle(Identity $actor, string $name, string $code): Subject
    {
        $this->guard->assertMayManageSchool($actor);

        $name = trim($name);
        $code = strtoupper(trim($code));

        return $this->transaction(function () use ($name, $code) {
            if (Subject::query()->where('code', $code)->orWhere('name', $name)->exists()) {
                throw StructureException::duplicate("A subject named [{$name}] or with code [{$code}]");
            }

            $subject = Subject::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'name' => $name,
                'code' => $code,
                'status' => 'active',
                'type' => SubjectType::Core,
            ]);

            $this->audit(new AuditEntry('structure.subject_created', 'subject', $subject->ulid, null, [
                'name' => $name, 'code' => $code,
            ]));

            return $subject;
        });
    }
}
