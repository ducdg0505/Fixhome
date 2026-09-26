<?php
declare(strict_types=1);

final class RoleWorkView
{
    public static function technicianViews(): array
    {
        return [
            'today' => 'Hôm nay',
            'upcoming' => 'Sắp tới',
            'active' => 'Đang thực hiện',
            'awaiting' => 'Chờ doanh nghiệp xác nhận',
            'history' => 'Lịch sử',
        ];
    }

    public static function normalizeTechnicianView(string $view): string
    {
        return array_key_exists($view, self::technicianViews()) ? $view : 'today';
    }

    public static function technicianActiveStatuses(): array
    {
        return [
            OrderState::TECH_ASSIGNED,
            OrderState::TECH_ACCEPTED,
            OrderState::ON_THE_WAY,
            OrderState::INSPECTING,
            OrderState::REPAIRING,
        ];
    }

    public static function companyGroups(): array
    {
        return [
            'opportunities' => 'Cơ hội mới',
            'waiting_customer' => 'Chờ khách quyết định',
            'active' => 'Đang thực hiện',
            'confirmation' => 'Chờ chốt kết quả',
            'closed' => 'Đã đóng',
        ];
    }

    public static function normalizeCompanyGroup(string $group): string
    {
        return $group === 'all' || array_key_exists($group, self::companyGroups()) ? $group : 'all';
    }

    public static function companyGroupFor(array $order, int $companyId): string
    {
        $status = (string)($order['status'] ?? '');
        $requestStatus = (string)($order['request_status'] ?? '');
        $selected = (int)($order['company_id'] ?? 0) === $companyId && $requestStatus === 'selected';

        if ($selected) {
            if ($status === OrderState::WORK_DONE) return 'confirmation';
            if (OrderState::isTerminal($status)) return 'closed';
            return 'active';
        }

        if (OrderState::isTerminal($status) || in_array($requestStatus, ['not_selected','declined','expired','cancelled_by_customer','cancelled_by_admin'], true)) {
            return 'closed';
        }

        $quoteStatus = (string)($order['latest_quote']['status'] ?? '');
        if (in_array($requestStatus, ['invited','viewed'], true) || $quoteStatus === 'changes_requested') {
            return 'opportunities';
        }
        if ($requestStatus === 'quote_submitted') return 'waiting_customer';

        return 'closed';
    }

    public static function adminGroups(): array
    {
        return [
            'distribution' => 'Chờ phân phối',
            'marketplace' => 'Marketplace',
            'selected' => 'Đã chọn doanh nghiệp',
            'repairing' => 'Đang sửa chữa',
            'confirmation' => 'Chờ xác nhận',
            'ended' => 'Kết thúc',
        ];
    }

    public static function normalizeAdminGroup(string $group): string
    {
        return $group === 'all' || array_key_exists($group, self::adminGroups()) ? $group : 'all';
    }

    public static function adminStatuses(string $group): array
    {
        return match ($group) {
            'distribution' => [OrderState::PENDING_DISTRIBUTION],
            'marketplace' => [OrderState::WAITING_QUOTE, OrderState::QUOTED],
            'selected' => [OrderState::QUOTE_ACCEPTED, OrderState::TECH_ASSIGNED, OrderState::TECH_ACCEPTED],
            'repairing' => [OrderState::ON_THE_WAY, OrderState::INSPECTING, OrderState::REPAIRING],
            'confirmation' => [OrderState::WORK_DONE],
            'ended' => [OrderState::COMPLETED, OrderState::CANCELLED],
            default => [],
        };
    }
}
