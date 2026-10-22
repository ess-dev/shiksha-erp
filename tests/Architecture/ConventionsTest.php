<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\Finder;

/*
 * Architecture rules from .ai/guidelines/40-security-quality-testing.md.
 * Rules about app/Domain pass vacuously until that folder exists; they then
 * guard every module as it is added. Add to these, never weaken them.
 */

/**
 * @return array<string, string> path relative to $directory => contents
 */
function sourceFiles(string $directory): array
{
    $root = dirname(__DIR__, 2).'/'.$directory;

    if (! is_dir($root)) {
        return [];
    }

    $files = [];

    foreach ((new Finder)->files()->in($root)->name('*.php') as $file) {
        $files[str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname())] = $file->getContents();
    }

    return $files;
}

/**
 * @return array<string, string>
 */
function controllerFiles(): array
{
    $files = [];

    foreach (['Controllers', 'Web', 'Api'] as $folder) {
        foreach (sourceFiles("app/Http/{$folder}") as $path => $contents) {
            $files["Http/{$folder}/{$path}"] = $contents;
        }
    }

    return $files;
}

arch('no debugging helpers are left in the code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('env() is only read in config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('controllers do not use the DB facade')
    ->expect(DB::class)
    ->not->toBeUsedIn('App\Http');

arch('models are Eloquent models')
    ->expect('App\Models')
    ->toExtend(Model::class);

it('keeps Eloquent writes out of controllers', function () {
    $writes = '/->(save|saveOrFail|update|updateOrCreate|delete|forceDelete|restore|insert|increment|decrement|fill|forceFill)\(|::(create|forceCreate|updateOrCreate|firstOrCreate|insert|upsert|destroy)\(/';

    $offenders = [];

    foreach (controllerFiles() as $path => $contents) {
        if (preg_match($writes, $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('requires every Domain Action to extend the audited base Action', function () {
    $offenders = [];

    foreach (sourceFiles('app/Domain') as $path => $contents) {
        if (preg_match('#^[^/]+/Actions/#', $path) !== 1) {
            continue;
        }

        if (preg_match('/class\s+\w+\s+extends\s+(\\\\?App\\\\Domain\\\\Shared\\\\)?Action\b/', $contents) !== 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('never exposes the internal id from a Data object', function () {
    $offenders = [];

    foreach (sourceFiles('app/Domain') as $path => $contents) {
        if (preg_match('#^[^/]+/Data/#', $path) !== 1) {
            continue;
        }

        if (preg_match('/public\s+(readonly\s+)?\??(int|string)\s+\$id\b/', $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps Domain modules out of each other\'s internals', function () {
    $offenders = [];

    foreach (sourceFiles('app/Domain') as $path => $contents) {
        $module = explode('/', $path)[0];

        preg_match_all('/^use\s+App\\\\Domain\\\\(\w+)\\\\(\w+)(?:\\\\(\w+))?/m', $contents, $uses, PREG_SET_ORDER);

        foreach ($uses as $use) {
            [, $otherModule, $layer] = $use;
            $class = $use[3] ?? '';
            $allowed = in_array($layer, ['Actions', 'Queries', 'Data'], true);

            // Shared is the foundation every module builds on; it alone may use Audit.
            $sharedUsesAudit = $module === 'Shared' && $otherModule === 'Audit';
            // Audit's and Access's enums (action scope, permission scope types) are their public vocabulary.
            $auditVocabulary = $layer === 'Enums' && in_array($otherModule, ['Audit', 'Access'], true);

            // The signed-in Identity is the principal every module's checks are about.
            $principal = $otherModule === 'Identity' && $layer === 'Models' && $class === 'Identity';

            if ($otherModule !== $module && $otherModule !== 'Shared' && ! $allowed && ! $sharedUsesAudit && ! $auditVocabulary && ! $principal) {
                $offenders[] = "{$path} uses {$otherModule}\\{$layer}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('uses no floats in Domain code', function () {
    $offenders = [];

    foreach (sourceFiles('app/Domain') as $path => $contents) {
        if (preg_match('/(\bfloat\s+\$|:\s*\??float\b|\(float\)|\bfloatval\()/', $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

it('never puts a tenant id into an Inertia page', function () {
    $offenders = [];

    foreach (sourceFiles('resources/js/pages') as $path => $contents) {
        if (preg_match('/\b(tenant_id|tenantId)\b/', $contents) === 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
