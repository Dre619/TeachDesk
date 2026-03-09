<?php

namespace App\Imports;

use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithSkipDuplicates;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithChunkReading;

/**
 * Expected spreadsheet columns (heading row):
 *   first_name | last_name | gender | date_of_birth
 *
 * date_of_birth accepts: YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY
 * gender accepts: male | female | other (case-insensitive)
 */
class StudentImport implements
    ToCollection,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure,
    WithChunkReading
{
    use SkipsFailures;

    public int $importedCount = 0;
    public int $skippedCount  = 0;

    public function __construct(
        private readonly int $classId,
        private readonly int $userId,
    ) {}

    // ──────────────────────────────────────────
    // Process rows
    // ──────────────────────────────────────────

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $dob = $this->parseDate($row['date_of_birth'] ?? null);

            $exists = Student::withTrashed()
                ->where('class_id', $this->classId)
                ->where('user_id', $this->userId)
                ->where('first_name', trim($row['first_name']))
                ->where('last_name', trim($row['last_name']))
                ->exists();

            if ($exists) {
                $this->skippedCount++;
                continue;
            }

            Student::create([
                'class_id'      => $this->classId,
                'user_id'       => $this->userId,
                'first_name'    => trim($row['first_name']),
                'last_name'     => trim($row['last_name']),
                'gender'        => strtolower(trim($row['gender'])),
                'date_of_birth' => $dob,
            ]);

            $this->importedCount++;
        }
    }

    // ──────────────────────────────────────────
    // Validation rules (applied before collection)
    // ──────────────────────────────────────────

    public function rules(): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'gender'        => ['required', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'string'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'gender.in' => 'Gender must be male, female, or other.',
        ];
    }

    // ──────────────────────────────────────────
    // Chunk size for large files
    // ──────────────────────────────────────────

    public function chunkSize(): int
    {
        return 500;
    }

    // ──────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────

    private function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Excel numeric date serial
        if (is_numeric($value)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)
                ->format('Y-m-d');
        }

        $value = (string) $value;

        // Try common formats
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'] as $format) {
            $date = \DateTime::createFromFormat($format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
