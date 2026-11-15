<?php
/**
 * KCCA VRSP — Input validation.
 *
 * Every user-supplied value that reaches the database goes through here.
 * The rule the Authority needs is specific: reject the bad field, keep
 * everything else. A trader mistyping a phone number must not lose the
 * rest of the registration form.
 *
 * So validation returns a map of field => message, and the caller writes
 * back only the fields that passed.
 */

declare(strict_types=1);

final class Validator
{
    private array $errors = [];
    private array $clean  = [];
    private array $rules;

    public function __construct(array $input, array $rules)
    {
        $this->rules = $rules;
        foreach ($rules as $field => $spec) {
            $raw = $input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $result = $this->checkField($field, $value, $spec);
            if ($result['ok']) {
                $this->clean[$field] = $result['value'];
            } else {
                $this->errors[$field] = $result['message'];
            }
        }
    }

    /** @return array{0:bool,1:array<string,mixed>,2:array<string,string>} */
    private function checkField(string $field, mixed $value, array $spec): array
    {
        $type  = $spec['type']  ?? 'text';
        $label = $spec['label'] ?? ucwords(str_replace('_', ' ', $field));

        /* Required */
        if (!empty($spec['required'])) {
            $empty = $value === null || $value === ''
                || (is_array($value) && count($value) === 0);
            if ($empty) {
                return ['ok' => false, 'message' => $label . ' is required.'];
            }
        }

        /* An unchecked checkbox is absent from the request body. That is a
           definite "no", not an unknown, so it stores as 0 — the columns
           are NOT NULL, and a NULL would be refused by the database for
           a reason the user cannot act on. */
        if ($value === null || $value === '') {
            return ['ok' => true, 'value' => $type === 'checkbox' ? 0 : ($spec['default'] ?? null)];
        }

        switch ($type) {
            case 'int':
                if (!is_numeric($value) || (string) (int) $value !== (string) $value) {
                    return ['ok' => false, 'message' => $label . ' must be a whole number.'];
                }
                $n = (int) $value;
                return $this->range($n, $spec, $label);

            case 'decimal':
                if (!is_numeric($value)) {
                    return ['ok' => false, 'message' => $label . ' must be a number.'];
                }
                return $this->range((float) $value, $spec, $label);

            case 'money':
                $s = preg_replace('/[^0-9.\-]/', '', (string) $value);
                if ($s === '' || !is_numeric($s)) {
                    return ['ok' => false, 'message' => $label . ' must be an amount in shillings.'];
                }
                $n = (int) round((float) $s);
                if ($n < 0) {
                    return ['ok' => false, 'message' => $label . ' cannot be negative.'];
                }
                return $this->range($n, $spec, $label);

            case 'phone':
                $digits = preg_replace('/[^0-9+]/', '', (string) $value);
                if (!preg_match('/^\+?[0-9]{9,15}$/', $digits)) {
                    return ['ok' => false, 'message' => $label . ' must be a valid telephone number.'];
                }
                return ['ok' => true, 'value' => $digits];

            case 'national_id':
                /* A Ugandan national ID number is alphanumeric — NINs are
                   issued as CF12345678PQXA — and stripping the letters before
                   counting them left eight digits, which the previous rule
                   rejected as "must be 10 to 16 digits". A trader who typed
                   their own genuine NIN was told a detail was missing, for a
                   field that is optional.

                   So the number is taken as given, upper-cased with spaces
                   and punctuation removed, and judged on its length rather
                   than on how many digits happen to survive. Only a value
                   under six characters is refused: anything that short is a
                   mis-type, not an ID. */
                $id = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', (string) $value));
                if ($id === '') {
                    return ['ok' => true, 'value' => null];
                }
                if (strlen($id) < 6 || strlen($id) > 20) {
                    return ['ok' => false, 'message' => $label . ' does not look like a national ID number. Leave it blank if you would rather not give it.'];
                }
                return ['ok' => true, 'value' => $id];

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return ['ok' => false, 'message' => $label . ' must be a valid email address.'];
                }
                return ['ok' => true, 'value' => strtolower($value)];

            case 'date':
                $ts = strtotime((string) $value);
                if ($ts === false) {
                    return ['ok' => false, 'message' => $label . ' must be a real date.'];
                }
                if (!empty($spec['not_future']) && $ts > strtotime('+1 day')) {
                    return ['ok' => false, 'message' => $label . ' cannot be in the future.'];
                }
                if (!empty($spec['not_past']) && $ts < strtotime('-1 day')) {
                    return ['ok' => false, 'message' => $label . ' cannot be a past date.'];
                }
                return ['ok' => true, 'value' => date('Y-m-d', $ts)];

            case 'enum':
                $allowed = (array) ($spec['options'] ?? []);
                if ($allowed !== [] && !in_array((string) $value, array_map('strval', $allowed), true)) {
                    return ['ok' => false, 'message' => $label . ' is not a recognised option.'];
                }
                return ['ok' => true, 'value' => (string) $value];

            case 'lat':
            case 'lng':
                if (!is_numeric($value)) {
                    return ['ok' => false, 'message' => $label . ' must be a number.'];
                }
                $n = (float) $value;
                $inRange = $type === 'lat' ? ($n >= -90 && $n <= 90) : ($n >= -180 && $n <= 180);
                if (!$inRange) {
                    return ['ok' => false, 'message' => $label . ' is outside the valid range.'];
                }
                return ['ok' => true, 'value' => $n];

            case 'checkbox':
                return ['ok' => true, 'value' => $value ? 1 : 0];

            case 'textarea':
            case 'text':
            default:
                $v = (string) $value;
                if (!empty($spec['max']) && strlen($v) > (int) $spec['max']) {
                    return ['ok' => false, 'message' => $label . ' must be ' . $spec['max'] . ' characters or fewer.'];
                }
                if (!empty($spec['min']) && strlen($v) < (int) $spec['min']) {
                    return ['ok' => false, 'message' => $label . ' must be at least ' . $spec['min'] . ' characters.'];
                }
                if (!empty($spec['pattern']) && !preg_match($spec['pattern'], $v)) {
                    return ['ok' => false, 'message' => $spec['pattern_message'] ?? ($label . ' is not in the expected format.')];
                }
                return ['ok' => true, 'value' => $v];
        }
    }

    private function range(int|float $n, array $spec, string $label): array
    {
        if (isset($spec['min']) && $n < $spec['min']) {
            return ['ok' => false, 'message' => $label . ' cannot be less than ' . $spec['min'] . '.'];
        }
        if (isset($spec['max']) && $n > $spec['max']) {
            return ['ok' => false, 'message' => $label . ' cannot be more than ' . $spec['max'] . '.'];
        }
        return ['ok' => true, 'value' => $n];
    }

    /* ---------------- results ---------------- */

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * The field errors, by reference.
     *
     * Returned by reference so a caller can add a rule the field list cannot
     * express — a duplicate code, a date range that runs backwards, a total
     * larger than its parts. Written as `return $this->errors;` the array came
     * back by value, and every one of those rules was written to a temporary
     * and thrown away: the write looked correct and the rule never fired.
     *
     * @return array<string,string>
     */
    public function &errors(): array
    {
        return $this->errors;
    }

    /** Add a rule the field list cannot express. Preferred over writing errors()[]. */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    /** First error message, for a single-line summary. */
    public function firstError(): ?string
    {
        foreach ($this->errors as $msg) {
            return $msg;
        }
        return null;
    }

    /** Only the fields that passed — safe to write to the database. */
    public function validated(): array
    {
        return $this->clean;
    }

    public function value(string $field, mixed $default = null): mixed
    {
        return $this->clean[$field] ?? $default;
    }

    /**
     * Check a value against another table before insert.
     * Used for foreign keys the user picks from a list, so a tampered
     * id is rejected rather than trusted.
     */
    public function checkExists(string $field, string $table, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }
        $exists = Database::value(
            "SELECT 1 FROM `" . preg_replace('/[^a-z_]/', '', $table) . "` WHERE id = ? LIMIT 1",
            [$value]
        );
        if (!$exists) {
            $this->errors[$field] = 'That ' . str_replace('_', ' ', $field) . ' does not exist.';
            unset($this->clean[$field]);
            return false;
        }
        return true;
    }

    /**
     * Render the errors as a field-keyed structure for the form to use,
     * so each input can show its own message beside it.
     */
    public function toArray(): array
    {
        return ['errors' => $this->errors, 'values' => $this->clean];
    }
}