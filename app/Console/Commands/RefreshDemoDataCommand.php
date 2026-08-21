<?php

namespace App\Console\Commands;

use App\Support\DemoMode;
use Illuminate\Console\Command;

class RefreshDemoDataCommand extends Command
{
    protected $signature = 'demo:refresh {--force : DEMO_MODE がオフでも実行する}';

    protected $description = 'ポートフォリオデモ用データを初期化する';

    public function handle(): int
    {
        if (! DemoMode::enabled() && ! $this->option('force')) {
            $this->warn('DEMO_MODE=false のためスキップしました。');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--force' => true]);
        cache()->put('demo_last_refreshed_at', now()->toIso8601String());
        $this->info('デモデータを初期化しました。');

        return self::SUCCESS;
    }
}
