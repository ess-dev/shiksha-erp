<?php

use App\Domain\Fees\Models\FeeInvoice;
use App\Domain\Shared\AmountInWords;
use App\Domain\Shared\Money;

it('adds, subtracts and compares whole paise', function () {
    $total = Money::paise(1050000)->plus(Money::paise(25));

    expect($total->paise)->toBe(1050025)
        ->and($total->minus(Money::paise(25))->equals(Money::paise(1050000)))->toBeTrue()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::paise(-1)->isNegative())->toBeTrue()
        ->and(Money::paise(5)->atMost(Money::paise(3))->paise)->toBe(3)
        ->and(Money::paise(5)->greaterThan(Money::paise(3)))->toBeTrue();
});

it('takes a percentage half-up to the paisa', function (int $paise, int $percent, int $expected) {
    expect(Money::paise($paise)->percent($percent)->paise)->toBe($expected);
})->with([
    'ten percent of 8,400 rupees' => [840000, 10, 84000],
    'half a paisa rounds up' => [5, 10, 1],
    'under half rounds down' => [4, 10, 0],
    'one third of a rupee' => [100, 33, 33],
    'zero percent' => [840000, 0, 0],
    'a hundred percent' => [840000, 100, 840000],
]);

it('reads whole rupees only', function () {
    expect(Money::fromRupees(8400)->paise)->toBe(840000)
        ->and(Money::fromRupees('15200')->paise)->toBe(1520000);

    expect(fn () => Money::fromRupees('15200.50'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::fromRupees('-5'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Money::fromRupees(''))->toThrow(InvalidArgumentException::class);
});

it('formats with Indian digit grouping', function (int $paise, string $expected) {
    expect(Money::paise($paise)->format())->toBe($expected);
})->with([
    'zero' => [0, '₹0'],
    'hundreds' => [99900, '₹999'],
    'thousands' => [1050000, '₹10,500'],
    'lakhs' => [12535000, '₹1,25,350'],
    'crores' => [1234567800, '₹1,23,45,678'],
    'with paise' => [1050050, '₹10,500.50'],
    'negative' => [-150000, '-₹1,500'],
]);

it('gives plain numbers for CSV files', function () {
    expect(Money::plain(1520000))->toBe('15200')
        ->and(Money::plain(1520050))->toBe('15200.50')
        ->and(Money::plain(5))->toBe('0.05');
});

it('writes amounts in words the Indian way', function (int $paise, string $words) {
    expect(AmountInWords::rupees($paise))->toBe($words);
})->with([
    [1050000, 'Rupees Ten Thousand Five Hundred only'],
    [975000, 'Rupees Nine Thousand Seven Hundred Fifty only'],
    [12535000, 'Rupees One Lakh Twenty Five Thousand Three Hundred Fifty only'],
    [100, 'Rupees One only'],
    [0, 'Rupees Zero only'],
    [1050050, 'Rupees Ten Thousand Five Hundred and Fifty Paise only'],
    [1000000000, 'Rupees One Crore only'],
]);

it('works out an installment status from the balance and the due date', function (int $net, int $paid, string $due, string $status) {
    expect(FeeInvoice::statusFor($net, $paid, $due, '2026-10-15')->value)->toBe($status);
})->with([
    'fully paid' => [1050000, 1050000, '2026-07-15', 'paid'],
    'late and nothing paid' => [1050000, 0, '2026-07-15', 'overdue'],
    'late and part paid' => [1050000, 500000, '2026-07-15', 'partial_overdue'],
    'due today' => [1050000, 0, '2026-10-15', 'due'],
    'due in five days' => [1050000, 0, '2026-10-20', 'due'],
    'due in exactly thirty days' => [1050000, 0, '2026-11-14', 'due'],
    'due in thirty one days' => [1050000, 0, '2026-11-15', 'upcoming'],
    'part paid and not late' => [1050000, 500000, '2026-10-20', 'partial'],
]);
