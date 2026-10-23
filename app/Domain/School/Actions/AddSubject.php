<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Enums\SubjectType;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\Models\Subject;
use App\Domain\School\SubjectGuard;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;

class AddSubject extends Action
{
    public function __construct(private readonly SubjectGuard $guard) {}

    /**
     * Adds a subject to the school's list, optionally to some classes right
     * away (only classes the person may change).
     *
     * @param  list<SchoolClass>  $classes
     */
    public function handle(Identity $actor, string $name, string $code, SubjectType $type, array $classes = []): Subject
    {
        $this->guard->assertMayConfigure($actor);

        foreach ($classes as $class) {
            $this->guard->assertMayChangeClass($actor, $class);
        }

        $name = trim($name);
        $code = strtoupper(trim($code));

        return $this->transaction(function () use ($name, $code, $type, $classes) {
            if (Subject::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->orWhere('code', $code)->exists()) {
                throw StructureException::duplicate("A subject named [{$name}] or with code [{$code}]");
            }

            $subject = Subject::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'name' => $name,
                'code' => $code,
                'status' => 'active',
                'type' => $type,
            ]);

            foreach ($classes as $class) {
                $class->subjects()->syncWithoutDetaching([$subject->id]);
            }

            $this->audit(new AuditEntry('subjects.subject_added', 'subject', $subject->ulid, null, [
                'name' => $name,
                'code' => $code,
                'type' => $type->value,
                'classes' => array_map(fn (SchoolClass $class) => $class->name, $classes),
            ]));

            return $subject;
        });
    }
}
