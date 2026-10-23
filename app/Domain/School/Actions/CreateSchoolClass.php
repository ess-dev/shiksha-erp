<?php

namespace App\Domain\School\Actions;

use App\Domain\Audit\Data\AuditEntry;
use App\Domain\Identity\Models\Identity;
use App\Domain\School\Exceptions\StructureException;
use App\Domain\School\Models\SchoolClass;
use App\Domain\School\StructureGuard;
use App\Domain\Shared\Action;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateSchoolClass extends Action
{
    public function __construct(private readonly StructureGuard $guard) {}

    /**
     * @param  int  $level  Orders classes and is what a wing's class range refers to
     */
    public function handle(Identity $actor, string $name, int $level): SchoolClass
    {
        $this->guard->assertMayManageSchool($actor);

        if ($level < -5 || $level > 20) {
            throw new InvalidArgumentException('Class level must be between -5 and 20.');
        }

        return $this->transaction(function () use ($name, $level) {
            $name = trim($name);

            if (SchoolClass::query()->where('name', $name)->orWhere('level', $level)->exists()) {
                throw StructureException::duplicate("A class named [{$name}] or at level {$level}");
            }

            $class = SchoolClass::query()->create([
                'ulid' => strtolower((string) Str::ulid()),
                'name' => $name,
                'level' => $level,
            ]);

            $this->audit(new AuditEntry('structure.class_created', 'school_class', $class->ulid, null, [
                'name' => $name, 'level' => $level,
            ]));

            return $class;
        });
    }
}
