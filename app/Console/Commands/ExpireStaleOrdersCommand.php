<?php

namespace App\Console\Commands;

use App\Services\Orders\OrderService;
use Illuminate\Console\Command;

final class ExpireStaleOrdersCommand extends Command
{
    protected $signature = 'orders:expire-stale
                            {--minutes=30 : Cancel pending orders older than this many minutes}
                            {--limit=100 : Maximum number of orders to process in one run}';

    protected $description = 'Expire stale pending orders and release their reserved ticket inventory';

    public function handle(OrderService $orderService): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $limit = max(1, (int) $this->option('limit'));

        $expired = $orderService->expireStalePendingOrders($minutes, $limit);
        $this->reportResult($expired, $minutes, $limit);

        return self::SUCCESS;
    }

    private function reportResult(int $expired, int $minutes, int $limit): void
    {
        $this->line(sprintf(
            'Stale orders: expired=%d minutes=%d limit=%d',
            $expired,
            $minutes,
            $limit,
        ));
    }
}
