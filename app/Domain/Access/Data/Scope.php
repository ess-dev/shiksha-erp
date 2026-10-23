<?php

namespace App\Domain\Access\Data;

use App\Domain\Access\Enums\ScopeType;
use InvalidArgumentException;

/**
 * The part of a school a role assignment applies to: the whole school, some
 * campuses, a wing (class range at some campuses) or specific sections.
 * "Classes they teach" is stored as sections, written automatically from the
 * timetable.
 */
final readonly class Scope
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(public ScopeType $type, public array $payload = []) {}

    public static function school(): self
    {
        return new self(ScopeType::School);
    }

    /**
     * @param  list<string>  $campusUlids
     */
    public static function campuses(array $campusUlids): self
    {
        return new self(ScopeType::Campus, ['campus_ulids' => self::ulids($campusUlids)]);
    }

    /**
     * @param  list<string>  $campusUlids
     */
    public static function wing(array $campusUlids, int $fromLevel, int $toLevel): self
    {
        if ($fromLevel > $toLevel) {
            throw new InvalidArgumentException('A wing needs a class range from low to high.');
        }

        return new self(ScopeType::Wing, [
            'campus_ulids' => self::ulids($campusUlids),
            'from_level' => $fromLevel,
            'to_level' => $toLevel,
        ]);
    }

    /**
     * @param  list<string>  $sectionUlids
     */
    public static function sections(array $sectionUlids): self
    {
        return new self(ScopeType::Sections, ['section_ulids' => self::ulids($sectionUlids)]);
    }

    /**
     * @param  array<string, mixed>|null  $stored
     */
    public static function fromStored(ScopeType $type, ?array $stored): self
    {
        return match ($type) {
            ScopeType::School => self::school(),
            ScopeType::Campus => self::campuses($stored['campus_ulids'] ?? []),
            ScopeType::Wing => self::wing($stored['campus_ulids'] ?? [], (int) ($stored['from_level'] ?? 0), (int) ($stored['to_level'] ?? 0)),
            ScopeType::Sections => self::sections($stored['section_ulids'] ?? []),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function toStored(): ?array
    {
        return $this->type === ScopeType::School ? null : $this->payload;
    }

    public function covers(ScopeTarget $target): bool
    {
        return match ($this->type) {
            ScopeType::School => true,
            ScopeType::Campus => $target->campusUlid !== null
                && in_array($target->campusUlid, $this->list('campus_ulids'), true),
            ScopeType::Wing => $target->campusUlid !== null
                && $target->classLevel !== null
                && in_array($target->campusUlid, $this->list('campus_ulids'), true)
                && $target->classLevel >= $this->payload['from_level']
                && $target->classLevel <= $this->payload['to_level'],
            ScopeType::Sections => $target->sectionUlid !== null
                && in_array($target->sectionUlid, $this->list('section_ulids'), true),
        };
    }

    /**
     * Whether everything $other covers is also covered here. A campus scope
     * cannot be shown to contain specific sections (no section-to-campus map
     * exists yet), so only a school-wide scope contains those.
     */
    public function contains(self $other): bool
    {
        return match ($this->type) {
            ScopeType::School => true,
            ScopeType::Campus => match ($other->type) {
                ScopeType::Campus, ScopeType::Wing => $this->isSubset($other->list('campus_ulids'), $this->list('campus_ulids')),
                default => false,
            },
            ScopeType::Wing => $other->type === ScopeType::Wing
                && $this->isSubset($other->list('campus_ulids'), $this->list('campus_ulids'))
                && $other->payload['from_level'] >= $this->payload['from_level']
                && $other->payload['to_level'] <= $this->payload['to_level'],
            ScopeType::Sections => $other->type === ScopeType::Sections
                && $this->isSubset($other->list('section_ulids'), $this->list('section_ulids')),
        };
    }

    /**
     * @return list<string>
     */
    private function list(string $key): array
    {
        /** @var list<string> $values */
        $values = $this->payload[$key] ?? [];

        return $values;
    }

    /**
     * @param  list<string>  $inner
     * @param  list<string>  $outer
     */
    private function isSubset(array $inner, array $outer): bool
    {
        return array_diff($inner, $outer) === [];
    }

    /**
     * @param  list<string>  $ulids
     * @return list<string>
     */
    private static function ulids(array $ulids): array
    {
        $unique = array_values(array_unique($ulids));
        sort($unique);

        if ($unique === []) {
            throw new InvalidArgumentException('A scope needs at least one campus or section.');
        }

        return $unique;
    }
}
