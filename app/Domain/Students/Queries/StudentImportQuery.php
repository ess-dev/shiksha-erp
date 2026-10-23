<?php

namespace App\Domain\Students\Queries;

use App\Domain\Identity\Models\Identity;
use App\Domain\School\Data\SectionRefData;
use App\Domain\School\Queries\HousesQuery;
use App\Domain\School\Queries\SectionDirectoryQuery;
use App\Domain\Shared\CalendarDate;
use App\Domain\Shared\PhoneNumber;
use App\Domain\Students\Data\ImportAnalysis;
use App\Domain\Students\Data\ImportPreviewData;
use App\Domain\Students\Data\ImportRow;
use App\Domain\Students\Data\ImportRowData;
use App\Domain\Students\Data\NewGuardian;
use App\Domain\Students\Data\NewStudent;
use App\Domain\Students\Enums\BloodGroup;
use App\Domain\Students\Enums\Gender;
use App\Domain\Students\Enums\ImportRowStatus;
use App\Domain\Students\Enums\StudentStatus;
use App\Domain\Students\GuardianLinker;
use App\Domain\Students\Models\Guardian;
use App\Domain\Students\Models\Student;
use App\Domain\Students\StudentVisibility;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Checks a student CSV row by row without writing anything, so the screen can
 * show what would happen and the import can run only what is ready. The same
 * check runs again at import time: the server never trusts a preview.
 */
class StudentImportQuery
{
    public const MAX_ROWS = 1000;

    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Column headings, with the spellings a school might use. Value: [key, label, required].
     *
     * @var list<array{0: string, 1: string, 2: list<string>, 3: bool}>
     */
    private const COLUMNS = [
        ['first_name', 'First name', ['first name', 'firstname'], true],
        ['last_name', 'Last name', ['last name', 'lastname', 'surname'], true],
        ['gender', 'Gender', ['gender', 'sex'], true],
        ['born_on', 'Date of birth', ['date of birth', 'dob', 'birth date'], true],
        ['blood_group', 'Blood group', ['blood group', 'bloodgroup'], true],
        ['house', 'House', ['house'], true],
        ['address', 'Address', ['address', 'home address'], true],
        ['campus', 'Campus', ['campus'], false],
        ['class', 'Class', ['class', 'grade'], true],
        ['section', 'Section', ['section'], true],
        ['admission_number', 'Admission no', ['admission no', 'admission number', 'admission_no'], false],
        ['guardian', 'Guardian name', ['guardian name', 'guardian'], true],
        ['relation', 'Relation', ['relation', 'guardian relation'], false],
        ['mobile', 'Mobile', ['mobile', 'phone', 'guardian mobile', 'mobile number'], true],
        ['email', 'Email', ['email', 'guardian email'], false],
        ['guardian2', 'Second guardian name', ['second guardian name', 'guardian 2 name'], false],
        ['relation2', 'Second guardian relation', ['second guardian relation', 'guardian 2 relation'], false],
        ['mobile2', 'Second guardian mobile', ['second guardian mobile', 'guardian 2 mobile'], false],
    ];

    public function __construct(
        private readonly StudentVisibility $visibility,
        private readonly SectionDirectoryQuery $sections,
        private readonly HousesQuery $houses,
        private readonly GuardianLinker $linker,
    ) {}

    /**
     * The template's heading row and one example row, for the download.
     *
     * @return list<list<string>>
     */
    public function template(): array
    {
        return [
            array_map(fn (array $column): string => $column[1], self::COLUMNS),
            // fictional example row — illustrative
            ['Aarav', 'Sharma', 'Male', '2015-06-14', 'O+', 'Not assigned', 'Sector 70, Mohali', 'Main Campus', '6', 'A', '', 'Rohit Sharma', 'Father', '9876543210', 'rohit@mail.example', 'Neetu Sharma', 'Mother', '9876543211'],
        ];
    }

    /**
     * @param  list<list<string>>  $table  Heading row first
     */
    public function preview(Identity $identity, array $table, string $fileName): ImportPreviewData
    {
        $analysis = $this->analyse($identity, $table);

        $rows = array_map(fn (ImportRow $row) => new ImportRowData(
            line: $row->line,
            student_name: $row->studentName,
            details: $row->details,
            class_label: $row->classLabel,
            guardian: $row->guardianLabel,
            status: $row->errors !== [] ? ImportRowStatus::Fix : ($row->warnings !== [] ? ImportRowStatus::Warning : ImportRowStatus::Ready),
            errors: $row->errors,
            warnings: $row->warnings,
        ), $analysis->rows);

        return new ImportPreviewData(
            file_name: $fileName,
            missing_columns: $analysis->missingColumns,
            rows: $rows,
            ready: count(array_filter($analysis->rows, fn (ImportRow $row) => $row->isReady())),
            with_warnings: count(array_filter($analysis->rows, fn (ImportRow $row) => $row->isReady() && $row->warnings !== [])),
            need_fixing: count(array_filter($analysis->rows, fn (ImportRow $row) => $row->errors !== [])),
        );
    }

    /**
     * @param  list<list<string>>  $table  Heading row first
     */
    public function analyse(Identity $identity, array $table): ImportAnalysis
    {
        $heading = array_map(fn (string $cell): string => mb_strtolower(trim($cell)), $table[0] ?? []);
        $columns = [];

        foreach (self::COLUMNS as [$key, , $spellings]) {
            foreach ($heading as $index => $cell) {
                if (in_array($cell, [$key, ...$spellings, mb_strtolower(str_replace('_', ' ', $key))], true)) {
                    $columns[$key] = $index;

                    break;
                }
            }
        }

        $missing = [];

        foreach (self::COLUMNS as [$key, $label, , $required]) {
            if ($required && ! isset($columns[$key])) {
                $missing[] = $label;
            }
        }

        $importScope = $this->visibility->scopes($identity, 'students.import');
        $sections = $this->sections->currentYear();
        $enrolledAdded = [];
        $seenAdmission = [];
        $seenChild = [];
        $seenPhones = [];
        $rows = [];

        foreach (array_slice($table, 1) as $offset => $cells) {
            $line = $offset + 2;
            $get = fn (string $key): string => isset($columns[$key]) ? trim((string) ($cells[$columns[$key]] ?? '')) : '';
            $errors = [];
            $warnings = [];

            $first = $get('first_name');
            $last = $get('last_name');
            $gender = Gender::fromInput($get('gender'));
            $bloodGroup = BloodGroup::fromInput($get('blood_group'));
            $bornOn = $this->normaliseDate($get('born_on'));
            $address = $get('address');
            $guardianName = $get('guardian');

            if ($first === '') {
                $errors[] = 'First name is missing';
            }

            if ($last === '') {
                $errors[] = 'Last name is missing';
            }

            if ($gender === null) {
                $errors[] = 'Gender must be Male, Female or Other';
            }

            if ($bloodGroup === null) {
                $errors[] = 'Blood group must be like O+ or AB-, or Unknown';
            }

            if ($address === '') {
                $errors[] = 'Address is missing';
            }

            $section = $this->matchSection($sections, $get('campus'), $get('class'), $get('section'), $errors);

            if ($bornOn === null) {
                $errors[] = 'Date of birth must be YYYY-MM-DD or DD/MM/YYYY';
            } else {
                $ageMonths = $this->ageInMonths($bornOn);

                // illustrative — verify: plausible ages for a school roll and a class.
                if ($ageMonths < 30 || $ageMonths > 252) {
                    $errors[] = 'Date of birth gives an age of '.intdiv($ageMonths, 12);
                } elseif ($section !== null && ($ageMonths < ($section->classLevel + 3) * 12 + 6 || $ageMonths > ($section->classLevel + 8) * 12)) {
                    $warnings[] = "Unusual age for {$section->className}";
                }
            }

            $houseKey = null;
            $houseCell = $get('house');

            if ($houseCell === '') {
                $errors[] = 'House is missing (write Not assigned if the student has none yet)';
            } elseif (mb_strtolower($houseCell) !== 'not assigned') {
                $houseKey = $this->houses->activeKeyByName($houseCell);

                if ($houseKey === null) {
                    $errors[] = "House {$houseCell} does not exist";
                }
            }

            if ($section !== null) {
                if (! $importScope->allows($section->scopeTarget())) {
                    $errors[] = $section->label().' is outside your access';
                }

                $enrolledAdded[$section->ulid] = ($enrolledAdded[$section->ulid] ?? 0) + 1;

                if ($section->capacity !== null && $section->enrolledCount + $enrolledAdded[$section->ulid] > $section->capacity) {
                    $warnings[] = $section->label()." goes over its capacity of {$section->capacity}";
                }
            }

            $guardians = $this->guardians($get, $guardianName, $errors);
            $joinsExisting = false;

            foreach ($guardians as $guardian) {
                $phone = PhoneNumber::tryParse($guardian->phone)?->e164;

                if ($phone === null) {
                    continue;
                }

                $existing = Guardian::query()->where('phone', $phone)->first();
                $earlier = $seenPhones[$phone] ?? null;

                if ($existing !== null) {
                    if ($this->linker->normalise($existing->name) !== $this->linker->normalise($guardian->name)) {
                        $errors[] = "This mobile already belongs to {$existing->name}: use that name or change the mobile";
                    } else {
                        $joinsExisting = true;
                        $warnings[] = "Joins the existing family of {$existing->name}";
                    }
                } elseif ($earlier !== null) {
                    if ($earlier['name'] !== $this->linker->normalise($guardian->name)) {
                        $errors[] = "This mobile is used for a different guardian in row {$earlier['line']}: use the same name or change the mobile";
                    } else {
                        $joinsExisting = true;
                        $warnings[] = "Same family as row {$earlier['line']}";
                    }
                }

                $seenPhones[$phone] ??= ['line' => $line, 'name' => $this->linker->normalise($guardian->name)];
            }

            $admission = $get('admission_number');

            if ($admission !== '') {
                $key = mb_strtolower($admission);

                if (Student::query()->whereRaw('lower(admission_number) = ?', [$key])->exists()) {
                    $errors[] = "Admission no {$admission} is already used";
                } elseif (isset($seenAdmission[$key])) {
                    $errors[] = "Admission no repeats row {$seenAdmission[$key]}";
                }

                $seenAdmission[$key] ??= $line;
            }

            if ($first !== '' && $last !== '' && $bornOn !== null) {
                $childKey = mb_strtolower("{$first}|{$last}|{$bornOn}");

                if ($this->alreadyEnrolled($first, $last, $bornOn)) {
                    $errors[] = 'Already a student here (same name and date of birth)';
                } elseif (isset($seenChild[$childKey])) {
                    $errors[] = "Same student as row {$seenChild[$childKey]}";
                }

                $seenChild[$childKey] ??= $line;
            }

            $student = null;

            if ($errors === [] && $section !== null && $gender !== null && $bloodGroup !== null && $bornOn !== null) {
                $student = new NewStudent(
                    firstName: $first,
                    lastName: $last,
                    gender: $gender,
                    bornOn: $bornOn,
                    bloodGroup: $bloodGroup,
                    houseKey: $houseKey,
                    address: $address,
                    sectionUlid: $section->ulid,
                    guardians: $guardians,
                    admissionNumber: $admission !== '' ? $admission : null,
                );
            }

            $rows[] = new ImportRow(
                line: $line,
                studentName: trim("{$first} {$last}") !== '' ? trim("{$first} {$last}") : '—',
                details: trim(($gender?->label() ?? '?').' · '.($bornOn ?? 'no date of birth')),
                classLabel: $section?->label(),
                guardianLabel: $guardianName,
                student: $student,
                errors: array_values(array_unique($errors)),
                warnings: array_values(array_unique($warnings)),
                joinsExistingFamily: $joinsExisting,
            );
        }

        return new ImportAnalysis($missing, $rows);
    }

    /**
     * @param  list<SectionRefData>  $sections
     * @param  list<string>  $errors
     */
    private function matchSection(array $sections, string $campus, string $class, string $section, array &$errors): ?SectionRefData
    {
        if ($class === '' || $section === '') {
            $errors[] = 'Class and section are needed';

            return null;
        }

        $classKey = mb_strtolower(trim((string) preg_replace('/^class\s*/i', '', $class)));
        $sectionKey = mb_strtolower($section);

        $candidates = array_values(array_filter($sections, fn (SectionRefData $ref) => mb_strtolower($ref->name) === $sectionKey
            && ($classKey === (string) $ref->classLevel || $classKey === mb_strtolower($ref->className) || $classKey === mb_strtolower((string) preg_replace('/^class\s*/i', '', $ref->className)))));

        if ($campus !== '') {
            $campusKey = mb_strtolower($campus);
            $candidates = array_values(array_filter($candidates, fn (SectionRefData $ref) => str_starts_with(mb_strtolower($ref->campusName), $campusKey)));
        }

        if ($candidates === []) {
            $errors[] = "Class {$class}-{$section} does not exist".($campus !== '' ? " on {$campus}" : '');

            return null;
        }

        if (count($candidates) > 1) {
            $errors[] = "Class {$class}-{$section} is on several campuses: fill in Campus";

            return null;
        }

        return $candidates[0];
    }

    /**
     * @param  callable(string): string  $get
     * @param  list<string>  $errors
     * @return list<NewGuardian>
     */
    private function guardians(callable $get, string $name, array &$errors): array
    {
        $guardians = [];
        $mobile = $get('mobile');

        if (mb_strlen($name) < 2) {
            $errors[] = 'Guardian name is missing';
        } elseif (PhoneNumber::tryParse($mobile) === null) {
            $errors[] = 'Mobile must be a 10-digit Indian number';
        } else {
            $guardians[] = new NewGuardian(
                name: $name,
                relation: $get('relation') !== '' ? $get('relation') : 'Guardian',
                phone: $mobile,
                email: $get('email') !== '' ? $get('email') : null,
                isPrimary: true,
            );
        }

        if ($get('email') !== '' && filter_var($get('email'), FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email is not valid';
        }

        if ($get('guardian2') !== '' || $get('mobile2') !== '') {
            $secondPhone = PhoneNumber::tryParse($get('mobile2'));
            $firstPhone = PhoneNumber::tryParse($mobile);

            if ($get('guardian2') === '' || $secondPhone === null) {
                $errors[] = 'Second guardian needs a name and a 10-digit mobile';
            } elseif ($firstPhone !== null && $secondPhone->e164 === $firstPhone->e164) {
                $errors[] = 'The two guardians need different mobile numbers';
            } else {
                $guardians[] = new NewGuardian(
                    name: $get('guardian2'),
                    relation: $get('relation2') !== '' ? $get('relation2') : 'Guardian',
                    phone: $get('mobile2'),
                );
            }
        }

        return $guardians;
    }

    private function normaliseDate(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m) === 1) {
            $iso = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        } elseif (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4})$/', $value, $m) === 1) {
            $iso = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        } else {
            return null;
        }

        try {
            return CalendarDate::parse($iso);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private function ageInMonths(string $bornOn): int
    {
        $diff = (new DateTimeImmutable($bornOn))->diff(new DateTimeImmutable(CalendarDate::today()));

        return $diff->invert === 1 ? -1 : $diff->y * 12 + $diff->m;
    }

    private function alreadyEnrolled(string $first, string $last, string $bornOn): bool
    {
        return DB::connection(config('multitenancy.tenant_database_connection_name'))->table('students')
            ->whereRaw('lower(first_name) = ?', [mb_strtolower($first)])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower($last)])
            ->where('born_on', $bornOn)
            ->where('status', StudentStatus::Enrolled->value)
            ->exists();
    }
}
