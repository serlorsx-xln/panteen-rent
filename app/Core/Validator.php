<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small rule-based validator with Thai error messages.
 *
 *   $v = Validator::make($_POST, ['email' => 'required|email|max:190']);
 *   if ($v->fails()) ...
 *
 * Rules: required, email, phone, min:n, max:n (string length), num, int, between:a,b (numeric),
 *        date, in:a,b,c, same:field, password, hex, slug, nullable, unique:table,column[,ignoreId]
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];
    /** @var array<string, mixed> */
    private array $clean = [];

    /**
     * @param array<int|string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     */
    private function __construct(private array $data, private array $rules, private array $labels)
    {
    }

    /**
     * @param array<int|string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data, $rules, $labels);
        $v->run();
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Validated, trimmed values for the fields that had rules.
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return $this->clean;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? $field;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);
            $raw = $this->data[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $nullable = in_array('nullable', $rules, true);
            $empty = $value === null || $value === '' || $value === [];

            if ($empty) {
                if (in_array('required', $rules, true)) {
                    $this->addError($field, 'กรุณากรอก' . $this->label($field));
                }
                $this->clean[$field] = $nullable ? null : ($value ?? '');
                continue;
            }

            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $msg = $this->check($name, $arg, $value, $field);
                if ($msg !== null) {
                    $this->addError($field, $msg);
                    break;
                }
            }
            $this->clean[$field] = $value;
        }
    }

    private function check(string $name, ?string $arg, mixed $value, string $field): ?string
    {
        $label = $this->label($field);
        switch ($name) {
            case 'required':
            case 'nullable':
                return null;
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'รูปแบบอีเมลไม่ถูกต้อง เช่น name@example.com';
            case 'phone':
                $digits = preg_replace('/\D/', '', (string) $value);
                return preg_match('/^0\d{8,9}$/', $digits) ? null : 'เบอร์โทรต้องเป็นตัวเลข 9–10 หลัก ขึ้นต้นด้วย 0';
            case 'min':
                return mb_strlen((string) $value) >= (int) $arg ? null : "$label ต้องมีอย่างน้อย $arg ตัวอักษร";
            case 'max':
                return mb_strlen((string) $value) <= (int) $arg ? null : "$label ยาวได้ไม่เกิน $arg ตัวอักษร";
            case 'num':
                return is_numeric($value) ? null : "$label ต้องเป็นตัวเลข";
            case 'int':
                return filter_var($value, FILTER_VALIDATE_INT) !== false ? null : "$label ต้องเป็นจำนวนเต็ม";
            case 'between':
                [$a, $b] = explode(',', (string) $arg);
                if (!is_numeric($value)) {
                    return "$label ต้องเป็นตัวเลข";
                }
                return ($value >= $a && $value <= $b) ? null : "$label ต้องอยู่ระหว่าง " . number_format((float) $a) . '–' . number_format((float) $b);
            case 'date':
                $d = \DateTime::createFromFormat('!Y-m-d', (string) $value);
                return ($d && $d->format('Y-m-d') === $value) ? null : "$label ไม่ใช่วันที่ที่ถูกต้อง";
            case 'in':
                return in_array((string) $value, explode(',', (string) $arg), true) ? null : "$label ไม่ถูกต้อง";
            case 'same':
                return $value === ($this->data[$arg] ?? null) ? null : 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
            case 'password':
                if (mb_strlen((string) $value) < 8) {
                    return 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
                }
                return (preg_match('/[A-Za-z]/', $value) && preg_match('/\d/', $value)) ? null : 'รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข';
            case 'hex':
                return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $value) ? null : "$label ต้องเป็นรหัสสี เช่น #7A1846";
            case 'slug':
                return preg_match('/^[a-z0-9-]+$/', (string) $value) ? null : "$label ใช้ได้เฉพาะ a-z, 0-9 และ -";
            case 'unique':
                [$table, $column, $ignore] = array_pad(explode(',', (string) $arg), 3, null);
                $sql = "SELECT COUNT(*) FROM `$table` WHERE `$column` = ?";
                $params = [$value];
                if ($ignore) {
                    $sql .= ' AND id <> ?';
                    $params[] = (int) $ignore;
                }
                return ((int) DB::value($sql, $params)) === 0 ? null : "$label นี้มีอยู่ในระบบแล้ว";
        }
        throw new \RuntimeException("Unknown rule $name");
    }
}
