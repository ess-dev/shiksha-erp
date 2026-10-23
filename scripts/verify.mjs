// Runs the project's quality gates in order and prints one line per step.
//
//   composer verify          full run: nothing is changed except by --fix
//   composer verify:fix      same, but Pint and the formatter fix files
//   composer verify:quick    only what the changed files need (see below)
//   composer verify -- --build      also runs `npm run build` at the end
//   composer verify -- --no-tests   everything except Pest
//
// Steps stop at the first failure. Quick mode looks at files changed against
// HEAD (including untracked): it checks only those PHP/TypeScript files and
// runs only the test files that changed. It cannot know which tests cover
// changed app code, so run the full verify before opening a pull request.
// Pest runs once at a time on purpose: tests share the test databases.

import { spawnSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const args = new Set(process.argv.slice(2));
const quick = args.has('--quick');
const fix = args.has('--fix');
const build = args.has('--build');
const isWindows = process.platform === 'win32';

const quote = (value) => (/[^\w./\\:@=-]/.test(value) ? `"${value}"` : value);

function run(command, commandArgs) {
    return (
        spawnSync(command, commandArgs.map(quote), {
            stdio: 'inherit',
            shell: isWindows,
        }).status === 0
    );
}

function output(command, commandArgs) {
    const result = spawnSync(command, commandArgs, {
        encoding: 'utf8',
        shell: isWindows,
    });

    return result.status === 0 ? result.stdout : '';
}

function changedFiles() {
    const tracked = output('git', [
        'diff',
        '--name-only',
        '--diff-filter=ACMR',
        'HEAD',
    ]);
    const untracked = output('git', [
        'ls-files',
        '--others',
        '--exclude-standard',
    ]);

    return [
        ...new Set(`${tracked}\n${untracked}`.split(/\r?\n/).filter(Boolean)),
    ];
}

const changed = quick ? changedFiles() : [];
const phpFiles = changed.filter((file) => file.endsWith('.php'));
const phpSources = phpFiles.filter((file) =>
    /^(app|bootstrap|config|database|routes)\//.test(file),
);
const frontendFiles = changed.filter(
    (file) =>
        /^resources\/.+\.(ts|tsx|js|jsx|css)$/.test(file) &&
        !/^resources\/js\/(actions|routes|wayfinder)\//.test(file) &&
        file !== 'resources/js/types/generated.d.ts',
);
const testFiles = phpFiles.filter((file) => /^tests\/.+Test\.php$/.test(file));
const touchesFrontend = changed.some((file) => file.startsWith('resources/'));

const generatedTypes = 'resources/js/types/generated.d.ts';
let typesBefore = '';

const steps = [];

function step(name, enabled, action) {
    if (enabled) {
        steps.push({ name, action });
    }
}

step('Wayfinder routes', touchesFrontend || !quick, () =>
    run('php', ['artisan', 'wayfinder:generate', '--with-form']),
);

step(
    'TypeScript types from Data objects',
    !quick || phpSources.some((file) => file.includes('/Data/')),
    () => {
        typesBefore = readFileSync(generatedTypes, 'utf8');

        if (!run('php', ['artisan', 'typescript:transform'])) {
            return false;
        }

        if (readFileSync(generatedTypes, 'utf8') === typesBefore) {
            return true;
        }

        console.log(
            `${generatedTypes} was out of date and has been regenerated: commit it.`,
        );

        return fix || !quick;
    },
);

step('Pint', !quick || phpFiles.length > 0, () => {
    const targets = quick ? phpFiles : [];

    return run('php', [
        'vendor/bin/pint',
        ...(fix || quick ? [] : ['--test']),
        ...targets,
    ]);
});

step('Larastan', !quick || phpSources.length > 0, () =>
    run('php', [
        'vendor/bin/phpstan',
        'analyse',
        '--memory-limit=1G',
        '--no-progress',
        ...(quick ? phpSources : []),
    ]),
);

step('Format and lint (vp check)', !quick || frontendFiles.length > 0, () =>
    run('npx', [
        'vp',
        'check',
        ...(fix || quick ? ['--fix'] : []),
        ...(quick ? frontendFiles : []),
    ]),
);

step('TypeScript (tsc)', !quick || touchesFrontend, () =>
    run('npm', ['run', 'types:check']),
);

step('Pest', (!quick || testFiles.length > 0) && !args.has('--no-tests'), () =>
    run('php', ['artisan', 'test', '--compact', ...(quick ? testFiles : [])]),
);

step('Build', build, () => run('npm', ['run', 'build']));

if (quick && changed.length === 0) {
    console.log('Nothing changed against HEAD: nothing to check.');
    process.exit(0);
}

const results = [];
let failed = false;

for (const { name, action } of steps) {
    console.log(`\n=== ${name} ===`);
    const started = Date.now();
    const ok = action();
    results.push({
        name,
        ok,
        seconds: ((Date.now() - started) / 1000).toFixed(1),
    });

    if (!ok) {
        failed = true;
        break;
    }
}

console.log('\n--- verify summary ---');

for (const { name, ok, seconds } of results) {
    console.log(`${ok ? 'PASS' : 'FAIL'}  ${name} (${seconds}s)`);
}

if (quick) {
    console.log(
        `\nQuick mode: checked ${changed.length} changed file(s), ran ${testFiles.length} test file(s). Run the full verify before a pull request.`,
    );
}

process.exit(failed ? 1 : 0);
