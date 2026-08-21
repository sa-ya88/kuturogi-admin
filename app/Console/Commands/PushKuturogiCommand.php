<?php

namespace App\Console\Commands;

use App\Services\KuturogiSyncService;
use Illuminate\Console\Command;

class PushKuturogiCommand extends Command
{
    protected $signature = 'kuturogi:push';

    protected $description = '（非推奨）別 DB 時代の上書き。SHARED_DATABASE=true では何もしない';

    public function handle(KuturogiSyncService $syncService): int
    {
        if ($syncService->usesSharedDatabase()) {
            $this->info('SHARED_DATABASE=true のため kuturogi:push は不要です。');

            return self::SUCCESS;
        }

        $this->warn('kuturogi の予約・在庫を admin の内容で上書きします。');

        try {
            $result = $syncService->overwriteKuturogiFromAdmin();
        } catch (\Throwable $e) {
            $this->error('上書き失敗: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("客室: {$result['rooms']} 件");
        $this->info("プラン: {$result['plans']} 件");
        $this->info("予約: {$result['reservations']} 件");
        $this->info("在庫: {$result['inventories']} 件");
        $this->info('kuturogi を admin の内容で上書きしました。');

        return self::SUCCESS;
    }
}
