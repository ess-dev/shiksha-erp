<?php

it('keeps the generated TypeScript types up to date', function () {
    $path = resource_path('js/types/generated.d.ts');
    $before = file_get_contents($path);

    $this->artisan('typescript:transform')->assertSuccessful();

    expect(file_get_contents($path))->toBe($before);
});
