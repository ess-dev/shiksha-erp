<?php

namespace App\Domain\Students;

use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Exceptions\StudentException;
use App\Domain\Students\Models\Guardian;
use Illuminate\Support\Str;

/**
 * Applies the one-phone-one-guardian rule (locked 2026-10-23, replacing the
 * earlier "warn, don't block"): a phone that already belongs to a guardian
 * links the child to that guardian when the names match, and is refused when
 * they do not, so the phone has to change. Used inside Actions only.
 */
class GuardianLinker
{
    /**
     * @return array{guardian: Guardian, existing: bool, phone: string}
     */
    public function resolve(NewGuardian $input): array
    {
        $phone = PhoneNumber::tryParse($input->phone)->e164
            ?? throw StudentException::invalidPhone($input->name);

        $existing = Guardian::query()->where('phone', $phone)->first();

        if ($existing !== null) {
            if ($this->normalise($existing->name) !== $this->normalise($input->name)) {
                throw StudentException::guardianPhoneConflict($existing->name);
            }

            $existing->fill(array_filter([
                'email' => $existing->email === null || $existing->email === '' ? $this->blankToNull($input->email) : null,
                'occupation' => $existing->occupation === null || $existing->occupation === '' ? $this->blankToNull($input->occupation) : null,
            ], fn (?string $value) => $value !== null));

            if ($existing->isDirty()) {
                $existing->save();
            }

            return ['guardian' => $existing, 'existing' => true, 'phone' => $phone];
        }

        $guardian = Guardian::query()->create([
            'ulid' => strtolower((string) Str::ulid()),
            'name' => trim($input->name),
            'phone' => $phone,
            'email' => $this->blankToNull($input->email),
            'occupation' => $this->blankToNull($input->occupation),
        ]);

        return ['guardian' => $guardian, 'existing' => false, 'phone' => $phone];
    }

    /**
     * Two guardians of one child cannot share a phone.
     *
     * @param  list<NewGuardian>  $guardians
     */
    public function assertDistinctPhones(array $guardians): void
    {
        $seen = [];

        foreach ($guardians as $guardian) {
            $phone = PhoneNumber::tryParse($guardian->phone)->e164 ?? throw StudentException::invalidPhone($guardian->name);

            if (isset($seen[$phone])) {
                throw StudentException::guardiansShareAPhone();
            }

            $seen[$phone] = true;
        }
    }

    public function normalise(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? '');
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
