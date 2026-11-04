<?php

namespace Javaabu\Imports\Columns;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use Throwable;
use TypeError;

/**
 * Reads a cell as a column's type
 *
 * Values that can't be converted are returned unchanged, so the column's validation rules
 * (boolean, integer, numeric, date, enum) report them with a proper message.
 */
class ValueConverter
{
    /** @var array<string, bool>|null */
    protected ?array $booleans = null;

    protected ?string $booleans_locale = null;

    /** @var array<class-string, array<string, BackedEnum>> */
    protected array $enum_labels = [];

    /**
     * Trim text and turn blank cells into null
     */
    public function normalize(mixed $value, bool $trim = true): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return $trim ? $trimmed : $value;
    }

    public function convert(Column $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($enum = $column->getEnum()) {
            return $this->toEnum($enum, $value);
        }

        return match ($column->getType()) {
            Column::BOOLEAN => $this->toBoolean($value),
            Column::INTEGER => $this->toInteger($value),
            Column::NUMBER => $this->toNumber($value),
            Column::DATE => $this->toDate($value, false),
            Column::DATETIME => $this->toDate($value, true),
            Column::STRING => $this->toText($value),
            default => $value,
        };
    }

    /**
     * Whether a converted value has the column's type, so the type's validation rule would pass
     */
    public function hasType(Column $column, mixed $value): bool
    {
        if ($value === null || $column->getLookup()) {
            return true;
        }

        if ($enum = $column->getEnum()) {
            return (is_int($value) || is_string($value)) && $this->enumCase($enum, $value) !== null;
        }

        return match ($column->getType()) {
            Column::BOOLEAN => is_bool($value),
            Column::INTEGER => is_int($value),
            Column::NUMBER => is_int($value) || is_float($value),
            Column::DATE => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            Column::DATETIME => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1,
            Column::STRING => is_string($value),
            default => true,
        };
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     */
    protected function enumCase(string $enum, int|string $value): ?BackedEnum
    {
        try {
            return $enum::tryFrom($value);
        } catch (TypeError) {
            return null;
        }
    }

    public function toBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return match ((float) $value) {
                1.0 => true,
                0.0 => false,
                default => $value,
            };
        }

        if (! is_string($value)) {
            return $value;
        }

        return $this->booleans()[Str::lower(trim($value))] ?? $value;
    }

    public function toInteger(mixed $value): mixed
    {
        $number = $this->toNumber($value);

        if (is_float($number) && floor($number) === $number && abs($number) < PHP_INT_MAX) {
            return (int) $number;
        }

        return $number;
    }

    public function toNumber(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $clean = str_replace([',', ' ', "\u{00A0}"], '', trim($value));

        if (! is_numeric($clean)) {
            return $value;
        }

        return str_contains($clean, '.') || stripos($clean, 'e') !== false ? (float) $clean : (int) $clean;
    }

    /**
     * Date cells, Excel serial numbers (days since 1899-12-30) and text Carbon understands.
     * Dates are returned as `Y-m-d`, date-times as `Y-m-d H:i:s`, in the app timezone.
     */
    public function toDate(mixed $value, bool $with_time): mixed
    {
        $format = $with_time ? 'Y-m-d H:i:s' : 'Y-m-d';

        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        if ((is_int($value) || is_float($value)) && $value > 0 && $value < 2958466) {
            $days = (int) floor($value);
            $seconds = (int) round(($value - $days) * 86400);

            return CarbonImmutable::create(1899, 12, 30, 0, 0, 0, 'UTC')
                ->addDays($days)
                ->addSeconds($seconds)
                ->format($format);
        }

        if (! is_string($value)) {
            return $value;
        }

        try {
            return CarbonImmutable::parse($value, config('app.timezone'))->format($format);
        } catch (Throwable) {
            return $value;
        }
    }

    public function toText(mixed $value): mixed
    {
        return match (true) {
            is_int($value), is_float($value) => (string) $value,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };
    }

    /**
     * An enum case by value, or by label (exports write labels), ignoring case
     *
     * @param  class-string<BackedEnum>  $enum
     */
    public function toEnum(string $enum, mixed $value): mixed
    {
        if ($value instanceof $enum) {
            return $value->value;
        }

        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }

        $int_backed = is_int($enum::cases()[0]->value ?? null);

        if (! $int_backed) {
            $case = $enum::tryFrom((string) $value);
        } elseif (is_numeric($value)) {
            $case = $enum::tryFrom((int) $value);
        }

        if ($case ?? null) {
            return $case->value;
        }

        $normalized = Str::lower(trim((string) $value));

        foreach ($enum::cases() as $case) {
            if (Str::lower((string) $case->value) === $normalized || Str::lower($case->name) === $normalized) {
                return $case->value;
            }
        }

        return ($this->enumLabels($enum)[$normalized] ?? null)?->value ?? $value;
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, BackedEnum>
     */
    protected function enumLabels(string $enum): array
    {
        $key = $enum . '|' . app()->getLocale();

        if (isset($this->enum_labels[$key])) {
            return $this->enum_labels[$key];
        }

        $labels = [];

        foreach ($enum::cases() as $case) {
            if (! method_exists($case, 'label')) {
                continue;
            }

            $labels[Str::lower(trim((string) $case->label()))] = $case;
        }

        return $this->enum_labels[$key] = $labels;
    }

    /**
     * @return array<string, bool>
     */
    protected function booleans(): array
    {
        $locale = app()->getLocale();

        if ($this->booleans !== null && $this->booleans_locale === $locale) {
            return $this->booleans;
        }

        $booleans = [
            'true' => true, 'yes' => true, 'y' => true, '1' => true, 'on' => true,
            'false' => false, 'no' => false, 'n' => false, '0' => false, 'off' => false,
        ];

        $booleans[Str::lower(__('Yes'))] = true;
        $booleans[Str::lower(__('No'))] = false;

        $this->booleans_locale = $locale;

        return $this->booleans = $booleans;
    }
}
