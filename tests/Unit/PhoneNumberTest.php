<?php

use App\Domain\Shared\PhoneNumber;

it('normalises phone numbers to E.164', function (string $input, ?string $expected) {
    expect(PhoneNumber::tryParse($input)?->e164)->toBe($expected);
})->with([
    'ten digits' => ['9876543210', '+919876543210'],
    'with spaces' => ['98765 43210', '+919876543210'],
    'with zero' => ['09876543210', '+919876543210'],
    'with 91' => ['919876543210', '+919876543210'],
    'with plus 91' => ['+91 98765-43210', '+919876543210'],
    'other country' => ['+14155552671', '+14155552671'],
    'starts with 5' => ['5876543210', null],
    'too short' => ['98765', null],
    'letters' => ['98765abcde', null],
    'plus without digits' => ['+', null],
    'empty' => ['', null],
]);

it('masks all but the last four digits', function () {
    expect(PhoneNumber::tryParse('9876543210')?->masked())->toBe('+91 ******3210');
});
