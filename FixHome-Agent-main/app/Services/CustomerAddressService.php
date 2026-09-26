<?php
declare(strict_types=1);

final class CustomerAddressService
{
    public const LABEL_MAX_LENGTH = 60;
    public const ADDRESS_MAX_LENGTH = 255;

    public static function listForCustomer(int $customerId): array
    {
        self::assertCustomerExists(db(), $customerId, false);
        $stmt = db()->prepare(
            'SELECT id,user_id,label,address,is_default,created_at,updated_at
             FROM customer_addresses
             WHERE user_id=?
             ORDER BY is_default DESC,id ASC'
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function defaultForCustomer(int $customerId): ?array
    {
        self::assertCustomerExists(db(), $customerId, false);
        $stmt = db()->prepare(
            'SELECT id,user_id,label,address,is_default,created_at,updated_at
             FROM customer_addresses
             WHERE user_id=? AND is_default=1
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $address = $stmt->fetch();
        return $address ?: null;
    }

    public static function create(int $customerId, string $label, string $address, bool $makeDefault): int
    {
        [$label, $address] = self::validate($label, $address);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::assertCustomerExists($pdo, $customerId, true);
            $count = $pdo->prepare('SELECT COUNT(*) FROM customer_addresses WHERE user_id=?');
            $count->execute([$customerId]);
            $isDefault = $makeDefault || (int)$count->fetchColumn() === 0;
            if ($isDefault) {
                self::clearDefault($pdo, $customerId);
            }
            $stmt = $pdo->prepare(
                'INSERT INTO customer_addresses(user_id,label,address,is_default) VALUES(?,?,?,?)'
            );
            $stmt->execute([$customerId, $label, $address, $isDefault ? 1 : 0]);
            $id = (int)$pdo->lastInsertId();
            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function update(int $customerId, int $addressId, string $label, string $address): bool
    {
        [$label, $address] = self::validate($label, $address);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::assertCustomerExists($pdo, $customerId, true);
            $saved = self::ownedAddress($pdo, $customerId, $addressId);
            if ((string)$saved['label'] === $label && (string)$saved['address'] === $address) {
                $pdo->commit();
                return false;
            }
            $stmt = $pdo->prepare(
                'UPDATE customer_addresses SET label=?,address=?,updated_at=NOW() WHERE id=? AND user_id=?'
            );
            $stmt->execute([$label, $address, $addressId, $customerId]);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function setDefault(int $customerId, int $addressId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::assertCustomerExists($pdo, $customerId, true);
            self::ownedAddress($pdo, $customerId, $addressId);
            self::clearDefault($pdo, $customerId);
            $stmt = $pdo->prepare(
                'UPDATE customer_addresses SET is_default=1,updated_at=NOW() WHERE id=? AND user_id=?'
            );
            $stmt->execute([$addressId, $customerId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Không thể đặt địa chỉ mặc định.');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function delete(int $customerId, int $addressId): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            self::assertCustomerExists($pdo, $customerId, true);
            $saved = self::ownedAddress($pdo, $customerId, $addressId);
            $stmt = $pdo->prepare('DELETE FROM customer_addresses WHERE id=? AND user_id=?');
            $stmt->execute([$addressId, $customerId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Không thể xóa địa chỉ đã lưu.');
            }
            if ((int)$saved['is_default'] === 1) {
                $next = $pdo->prepare(
                    'SELECT id FROM customer_addresses WHERE user_id=? ORDER BY id ASC LIMIT 1 FOR UPDATE'
                );
                $next->execute([$customerId]);
                $nextId = $next->fetchColumn();
                if ($nextId !== false) {
                    $promote = $pdo->prepare(
                        'UPDATE customer_addresses SET is_default=1,updated_at=NOW() WHERE id=? AND user_id=?'
                    );
                    $promote->execute([(int)$nextId, $customerId]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private static function validate(string $label, string $address): array
    {
        $label = trim($label);
        $address = trim($address);
        if ($label === '') {
            throw new RuntimeException('Vui lòng nhập nhãn địa chỉ.');
        }
        if ($address === '') {
            throw new RuntimeException('Vui lòng nhập địa chỉ.');
        }
        if (self::length($label) > self::LABEL_MAX_LENGTH) {
            throw new RuntimeException('Nhãn địa chỉ không được vượt quá ' . self::LABEL_MAX_LENGTH . ' ký tự.');
        }
        if (self::length($address) > self::ADDRESS_MAX_LENGTH) {
            throw new RuntimeException('Địa chỉ không được vượt quá ' . self::ADDRESS_MAX_LENGTH . ' ký tự.');
        }
        return [$label, $address];
    }

    private static function assertCustomerExists(PDO $pdo, int $customerId, bool $lock): void
    {
        $sql = "SELECT id FROM users WHERE id=? AND role='customer' AND status='active' LIMIT 1";
        if ($lock) $sql .= ' FOR UPDATE';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$customerId]);
        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException('Tài khoản Khách hàng không hợp lệ.');
        }
    }

    private static function ownedAddress(PDO $pdo, int $customerId, int $addressId): array
    {
        $stmt = $pdo->prepare(
            'SELECT id,user_id,label,address,is_default
             FROM customer_addresses
             WHERE id=? AND user_id=?
             LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$addressId, $customerId]);
        $address = $stmt->fetch();
        if (!$address) {
            throw new RuntimeException('Không tìm thấy địa chỉ thuộc tài khoản của bạn.');
        }
        return $address;
    }

    private static function clearDefault(PDO $pdo, int $customerId): void
    {
        $stmt = $pdo->prepare(
            'UPDATE customer_addresses SET is_default=0,updated_at=NOW() WHERE user_id=? AND is_default=1'
        );
        $stmt->execute([$customerId]);
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }
}
