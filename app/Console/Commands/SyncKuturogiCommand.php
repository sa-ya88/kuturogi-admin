<?php

namespace App\Console\Commands;

use App\Services\KuturogiSyncService;
use Illuminate\Console\Command;

class SyncKuturogiCommand extends Command
{
    protected $signature = 'kuturogi:sync
                            {--rooms : 客室のみ同期}
                            {--plans : プランのみ同期}
                            {--inventories : 在庫のみ同期}
                            {--reservations : 予約のみ同期}
                            {--customers : 顧客（会員・ゲスト）のみ同期}
                            {--from= : 在庫同期開始日 (Y-m-d)}
                            {--to= : 在庫同期終了日 (Y-m-d)}';

    protected $description = 'kuturogi 顧客サイトからマスタ・在庫・予約データを同期';

    public function handle(KuturogiSyncService $syncService): int
    {
        $onlyRooms = $this->option('rooms');
        $onlyPlans = $this->option('plans');
        $onlyInventories = $this->option('inventories');
        $onlyReservations = $this->option('reservations');
        $onlyCustomers = $this->option('customers');

        $specific = $onlyRooms || $onlyPlans || $onlyInventories || $onlyReservations || $onlyCustomers;

        try {
            if (! $specific || $onlyRooms) {
                $count = $syncService->syncRooms();
                $this->info("客室: {$count} 件同期");
            }

            if (! $specific || $onlyPlans) {
                $count = $syncService->syncPlans();
                $this->info("プラン: {$count} 件同期");
            }

            if (! $specific || $onlyInventories) {
                $count = $syncService->syncInventories($this->option('from'), $this->option('to'));
                $this->info("在庫: {$count} 件同期");
            }

            if (! $specific || $onlyReservations) {
                $count = $syncService->syncReservations();
                $this->info("予約: {$count} 件同期");
            }

            if (! $specific || $onlyCustomers) {
                $count = $syncService->syncCustomers();
                $this->info("顧客: {$count} 件同期");
            }

            $this->info('同期完了');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('同期失敗: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
