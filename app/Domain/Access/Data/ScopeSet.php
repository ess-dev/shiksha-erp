<?php

namespace App\Domain\Access\Data;

use App\Domain\Access\Enums\ScopeType;

/**
 * Every scope in which a person holds one permission (the union across their
 * roles). Queries use it to filter lists; policies use it for single records.
 */
final readonly class ScopeSet
{
    /**
     * @param  list<Scope>  $scopes
     */
    public function __construct(public array $scopes = []) {}

    public function isEmpty(): bool
    {
        return $this->scopes === [];
    }

    public function isSchoolWide(): bool
    {
        foreach ($this->scopes as $scope) {
            if ($scope->type === ScopeType::School) {
                return true;
            }
        }

        return false;
    }

    /**
     * With no target: whether the permission is held at all. With a target:
     * whether one of the scopes covers it.
     */
    public function allows(?ScopeTarget $target = null): bool
    {
        if ($target === null) {
            return ! $this->isEmpty();
        }

        foreach ($this->scopes as $scope) {
            if ($scope->covers($target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a scope the holder wants to hand on lies within their own.
     */
    public function containsScope(Scope $wanted): bool
    {
        foreach ($this->scopes as $scope) {
            if ($scope->contains($wanted)) {
                return true;
            }
        }

        return false;
    }
}
