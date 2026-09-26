<?php
declare(strict_types=1);

final class OrderMediaService
{
    public static function accessibleImage(PDO $pdo, array $user, int $orderId): ?array
    {
        if ($orderId < 1) return null;

        $stmt = $pdo->prepare('SELECT id,customer_id,company_id,status,image_name FROM orders WHERE id=? LIMIT 1');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order || empty($order['image_name'])) return null;

        $role = (string)($user['role'] ?? '');
        $authorized = false;
        if ($role === 'customer') {
            $authorized = (int)$order['customer_id'] === (int)($user['id'] ?? 0);
        } elseif ($role === 'admin') {
            // Current admin order supervision is global across the marketplace.
            $authorized = true;
        } elseif ($role === 'company' && !empty($user['company_id'])) {
            $companyId = (int)$user['company_id'];
            $company = $pdo->prepare(
                "SELECT r.status request_status,c.legal_status,c.account_status
                 FROM order_company_requests r
                 JOIN companies c ON c.id=r.company_id
                 WHERE r.order_id=? AND r.company_id=? LIMIT 1"
            );
            $company->execute([$orderId, $companyId]);
            $request = $company->fetch();
            if ($request && $request['legal_status'] === 'verified' && $request['account_status'] === 'active') {
                $isWinner = (int)($order['company_id'] ?? 0) === $companyId;
                $isActiveOpportunity = $order['company_id'] === null
                    && !OrderState::isTerminal((string)$order['status'])
                    && in_array((string)$request['request_status'], ['invited','viewed','quote_submitted'], true);
                $authorized = $isWinner || $isActiveOpportunity;
            }
        }

        if (!$authorized) return null;
        return ['order_id'=>(int)$order['id'], 'image_name'=>(string)$order['image_name']];
    }
}
