<?php
declare(strict_types=1);

final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 72;
    public const MAX_BYTES = 72;
    public const HELP_TEXT = 'Mật khẩu tối thiểu 12 ký tự, gồm ít nhất 1 chữ cái và 1 chữ số.';

    public static function validationError(string $password): ?string
    {
        if (!self::isSafeBcryptInput($password)) {
            if (str_contains($password, "\0")) {
                return 'Mật khẩu chứa ký tự không hợp lệ.';
            }
            return 'Mật khẩu quá dài. Vui lòng sử dụng mật khẩu ngắn hơn.';
        }

        $length = function_exists('mb_strlen')
            ? mb_strlen($password, 'UTF-8')
            : (preg_match_all('/./us', $password, $matches) !== false ? count($matches[0]) : strlen($password));

        if ($length < self::MIN_LENGTH) {
            return 'Mật khẩu phải có ít nhất 12 ký tự.';
        }
        if ($length > self::MAX_LENGTH) {
            return 'Mật khẩu quá dài. Vui lòng sử dụng mật khẩu ngắn hơn.';
        }
        if (preg_match('/\p{L}/u', $password) !== 1 || preg_match('/[0-9]/', $password) !== 1) {
            return 'Mật khẩu cần có ít nhất 1 chữ cái và 1 chữ số.';
        }
        return null;
    }

    public static function isSafeBcryptInput(string $password): bool
    {
        if (str_contains($password, "\0")) {
            return false;
        }
        return strlen($password) <= self::MAX_BYTES;
    }

    public static function isValid(string $password): bool
    {
        return self::validationError($password) === null;
    }
}
