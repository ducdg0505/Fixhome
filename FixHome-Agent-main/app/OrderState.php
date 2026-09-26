<?php
declare(strict_types=1);

final class OrderState
{
    public const PENDING_DISTRIBUTION = 'pending_distribution';
    public const WAITING_QUOTE = 'waiting_quote';
    public const QUOTED = 'quoted';
    public const QUOTE_ACCEPTED = 'quote_accepted';
    public const TECH_ASSIGNED = 'tech_assigned';
    public const TECH_ACCEPTED = 'tech_accepted';
    public const ON_THE_WAY = 'on_the_way';
    public const INSPECTING = 'inspecting';
    public const REPAIRING = 'repairing';
    public const WORK_DONE = 'work_done';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    private const TRANSITIONS = [
        self::PENDING_DISTRIBUTION => [self::WAITING_QUOTE, self::CANCELLED],
        self::WAITING_QUOTE => [self::QUOTED, self::CANCELLED],
        self::QUOTED => [self::WAITING_QUOTE, self::QUOTE_ACCEPTED, self::CANCELLED],
        self::QUOTE_ACCEPTED => [self::TECH_ASSIGNED, self::CANCELLED],
        self::TECH_ASSIGNED => [self::TECH_ACCEPTED, self::ON_THE_WAY, self::INSPECTING, self::REPAIRING, self::CANCELLED],
        self::TECH_ACCEPTED => [self::ON_THE_WAY, self::INSPECTING, self::REPAIRING, self::CANCELLED],
        self::ON_THE_WAY => [self::INSPECTING, self::REPAIRING, self::CANCELLED],
        self::INSPECTING => [self::REPAIRING, self::CANCELLED],
        self::REPAIRING => [self::WORK_DONE, self::CANCELLED],
        self::WORK_DONE => [self::COMPLETED, self::CANCELLED],
        self::COMPLETED => [],
        self::CANCELLED => [],
    ];

    public static function isTerminal(string $status): bool
    {
        return $status === self::COMPLETED || $status === self::CANCELLED;
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new RuntimeException("Không thể chuyển trạng thái từ {$from} sang {$to}.");
        }
    }

    public static function technicianStatuses(): array
    {
        return [
            self::TECH_ASSIGNED,
            self::TECH_ACCEPTED,
            self::ON_THE_WAY,
            self::INSPECTING,
            self::REPAIRING,
            self::WORK_DONE,
        ];
    }

    public static function technicianProgressTransitionsFrom(string $status): array
    {
        $progressStatuses = [
            self::TECH_ACCEPTED,
            self::ON_THE_WAY,
            self::INSPECTING,
            self::REPAIRING,
        ];
        return array_values(array_filter(
            self::TRANSITIONS[$status] ?? [],
            static fn(string $next): bool => in_array($next, $progressStatuses, true)
        ));
    }

    public static function assertTechnicianTransition(string $from, string $to): void
    {
        $statuses = self::technicianStatuses();
        $fromIndex = array_search($from, $statuses, true);
        $toIndex = array_search($to, $statuses, true);
        if ($fromIndex === false || $toIndex === false || $toIndex <= $fromIndex) {
            throw new RuntimeException('Không thể chuyển lùi hoặc lặp lại trạng thái kỹ thuật.');
        }
        self::assertTransition($from, $to);
    }
}
