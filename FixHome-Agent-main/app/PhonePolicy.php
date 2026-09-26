<?php
declare(strict_types=1);

final class PhonePolicy
{
    public const ERROR_MESSAGE = 'Số điện thoại không hợp lệ. Vui lòng nhập số Việt Nam, ví dụ 0901234567 hoặc +84901234567.';

    public static function normalize(string $input): ?string
    {
        $value = trim($input);
        if ($value === '' || preg_match('/^\+?[0-9 .()\-]+$/D', $value) !== 1) {
            return null;
        }
        if (preg_match('/(^[.\-]|[.\-]$|[.\-]\s*[.\-])/', $value) === 1) {
            return null;
        }

        $withoutGroups = preg_replace('/\([0-9]+\)/', '', $value);
        if ($withoutGroups === null || str_contains($withoutGroups, '(') || str_contains($withoutGroups, ')')) {
            return null;
        }

        $compact = str_replace([' ', '.', '-', '(', ')'], '', $value);
        if (str_starts_with($compact, '+84')) {
            $compact = '0' . substr($compact, 3);
        } elseif (!str_starts_with($compact, '0')) {
            return null;
        }

        if (
            preg_match('/^0(?:3|5|7|8|9)[0-9]{8}$/D', $compact) !== 1
            && preg_match('/^02[0-9]{9}$/D', $compact) !== 1
        ) {
            return null;
        }

        return $compact;
    }
}
