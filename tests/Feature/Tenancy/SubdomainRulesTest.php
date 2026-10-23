<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\SubdomainRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubdomainRulesTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function subdomains(): array
    {
        return [
            'plain' => ['greenfield', true],
            'digits and hyphen' => ['dps-2', true],
            'too short' => ['ab', false],
            'uppercase' => ['Greenfield', false],
            'underscore' => ['green_field', false],
            'leading hyphen' => ['-green', false],
            'trailing hyphen' => ['green-', false],
            'dot' => ['a.green', false],
            'reserved' => ['admin', false],
            'too long' => [str_repeat('a', 64), false],
        ];
    }

    #[DataProvider('subdomains')]
    public function test_subdomain_validation(string $subdomain, bool $valid): void
    {
        $this->assertSame($valid, SubdomainRules::isValid($subdomain));
    }
}
