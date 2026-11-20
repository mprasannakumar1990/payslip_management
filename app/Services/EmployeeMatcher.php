<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Collection;

/** Finds the employee a name / ID in an imported sheet refers to (ID, exact name, saved alias, then close match). */
class EmployeeMatcher
{
    private Collection $employees;

    private array $index = [];

    public function __construct()
    {
        $this->employees = Employee::orderBy('emp_code')->get();
        foreach ($this->employees as $e) {
            $this->index[self::norm($e->name)][] = $e;
            foreach (array_filter(array_map('trim', explode(',', (string) $e->name_aliases))) as $alias) {
                $this->index[self::norm($alias)][] = $e;
            }
        }
    }

    public static function norm(?string $s): string
    {
        return preg_replace('/[^a-z]/', '', strtolower((string) $s));
    }

    public function employees(): Collection
    {
        return $this->employees;
    }

    /** @return array{0: ?Employee, 1: string} employee and how it was matched (id|name|alias|close|none) */
    public function match(?string $name, ?string $code = null): array
    {
        if ($code && ($e = $this->employees->first(fn ($x) => strcasecmp($x->emp_code, trim($code)) === 0))) {
            return [$e, 'id'];
        }
        $n = self::norm($name);
        if ($n === '') {
            return [null, 'none'];
        }
        if (isset($this->index[$n]) && count($this->index[$n]) === 1) {
            $e = $this->index[$n][0];

            return [$e, self::norm($e->name) === $n ? 'name' : 'alias'];
        }
        // close match: one name starts with the other ("Sathish Kumar .T" ~ "Sathish Kumar")
        $cands = collect($this->index)->filter(fn ($v, $k) => min(strlen($k), strlen($n)) >= 5 && (str_starts_with($k, $n) || str_starts_with($n, $k)))
            ->flatten()->unique('id');
        if ($cands->count() === 1) {
            return [$cands->first(), 'close'];
        }
        if ($cands->isEmpty() && strlen($n) >= 6) {
            // small spelling differences ("Chandrasekar" ~ "Chandra Sekhar.S"): best unique match within 2 edits
            $scored = collect($this->index)->map(fn ($v, $k) => levenshtein($n, $k))->filter(fn ($d) => $d <= 2)->sort();
            if ($scored->isNotEmpty() && ($scored->count() === 1 || $scored->values()[0] < $scored->values()[1])) {
                $hits = $this->index[$scored->keys()->first()];
                if (count($hits) === 1) {
                    return [$hits[0], 'close'];
                }
            }
        }

        return [null, 'none'];
    }

    /** Remember a manual mapping so next month's import matches automatically. */
    public static function remember(Employee $e, string $sheetName): void
    {
        $sheetName = trim($sheetName);
        if ($sheetName === '' || self::norm($sheetName) === self::norm($e->name)) {
            return;
        }
        $aliases = array_filter(array_map('trim', explode(',', (string) $e->name_aliases)));
        if (! in_array(self::norm($sheetName), array_map([self::class, 'norm'], $aliases), true)) {
            $aliases[] = str_replace(',', ' ', $sheetName);
            $e->update(['name_aliases' => mb_substr(implode(', ', $aliases), 0, 500)]);
        }
    }
}
