<?php
declare(strict_types=1);

namespace App\Core;

final class Validator
{
    /**
     * Kural örneği: ['name' => 'required|string|max:80', 'adults' => 'required|int|min:1|max:10']
     * @param array<string,mixed> $input @param array<string,string> $rules
     * @return array<string,mixed> doğrulanmış ve tipi düzeltilmiş veri
     */
    public static function validate(array $input, array $rules): array
    {
        $errors = [];
        $clean = [];
        foreach ($rules as $field => $ruleString) {
            $rs = explode('|', $ruleString);
            $value = $input[$field] ?? null;
            $empty = $value === null || $value === '';
            if ($empty) {
                if (in_array('required', $rs, true)) {
                    $errors[$field] = 'Bu alan zorunludur.';
                }
                continue;
            }
            foreach ($rs as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $err = match ($name) {
                    'int' => filter_var($value, FILTER_VALIDATE_INT) === false ? 'Tam sayı olmalı.' : null,
                    'string' => !is_string($value) ? 'Metin olmalı.' : null,
                    'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'Geçerli bir e-posta girin.' : null,
                    'date' => !self::isDate($value) ? 'Tarih YYYY-AA-GG biçiminde olmalı.' : null,
                    'phone' => !is_string($value) || preg_match('/^\+?[0-9 ()-]{10,18}$/', $value) !== 1 ? 'Geçerli bir telefon girin.' : null,
                    'min' => in_array('int', $rs, true) && (int) $value < (int) $arg ? "En az {$arg} olmalı." : null,
                    'max' => self::tooBig($value, (int) $arg, in_array('int', $rs, true)) ? "En fazla {$arg} olabilir." : null,
                    default => null,
                };
                if ($err !== null) {
                    $errors[$field] = $err;
                    continue 2;
                }
            }
            $clean[$field] = in_array('int', $rs, true) ? (int) $value : $value;
        }
        if ($errors !== []) {
            throw new HttpException(422, 'Doğrulama hatası.', $errors);
        }
        return $clean;
    }

    private static function tooBig(mixed $v, int $max, bool $isInt): bool
    {
        return $isInt ? (int) $v > $max : (is_string($v) && mb_strlen($v) > $max);
    }

    private static function isDate(mixed $v): bool
    {
        if (!is_string($v)) {
            return false;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v;
    }
}
