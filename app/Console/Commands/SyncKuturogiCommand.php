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
                            {--prune-rooms : admin に無い kuturogi 客室を削除（予約がある場合は非公開）}
                            {--prune-plans : admin に無い kuturogi プランを削除（予約がある場合は客室紐付け解除）}
                            {--from= : 在庫同期開始日 (Y-m-d)}
                            {--to= : 在庫同期終了日 (Y-m-d)}';

    protected $description = 'kuturogi 顧客サイトからマスタ・在庫・予約データを同期';

    public function handle(KuturogiSyncService $syncService): int
    {
        if ($syncService->usesSharedDatabase()) {
            $this->info('SHARED_DATABASE=true のため API 同期は不要です。客室・予約・在庫は同じ DB を参照します。');

            return self::SUCCESS;
        }
        $onlyRooms = $this->option('rooms');
        $onlyPlans = $this->option('plans');
        $onlyInventories = $this->option('inventories');
        $onlyReservations = $this->option('reservations');
        $onlyCustomers = $this->option('customers');
        $pruneRooms = $this->option('prune-rooms');
        $prunePlans = $this->option('prune-plans');

        $specific = $onlyRooms || $onlyPlans || $onlyInventories || $onlyReservations || $onlyCustomers || $pruneRooms || $prunePlans;

        try {
            if ($pruneRooms) {
                $pruned = $syncService->pruneUnlinkedKuturogiRooms();
                $this->info("余剰客室: 削除 {$pruned['deleted']} / 非公開 {$pruned['unpublished']} 件");
            }

            if ($prunePlans) {
                $pruned = $syncService->pruneUnlinkedKuturogiPlans();
                $this->info("余剰プラン: 削除 {$pruned['deleted']} / 紐付け解除 {$pruned['detached']} 件");
            }

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
