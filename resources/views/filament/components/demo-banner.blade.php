@if (\App\Support\DemoMode::enabled())
    <div
        role="note"
        class="my-4 rounded-xl bg-warning-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-warning-600/20 dark:bg-gray-900 dark:text-white dark:ring-white/10"
    >
        <p class="font-medium">ポートフォリオ用の公開デモです</p>
        <ul class="mt-2 space-y-1 pl-5 text-gray-600 dark:text-gray-400">
            <li>・ 実在の名前、電話番号、メール、住所、クレジットカード等は入力しないでください。</li>
            <li>・ 客室、プラン、料金、スタッフなどのマスタは削除できません。予約の作成、キャンセルと画面の閲覧は操作できます。</li>
            <li>・ デモデータは {{ \App\Support\DemoMode::refreshHours() }} 時間ごとに初期化されます。</li>
        </ul>
        <p class="mt-2 text-gray-600 dark:text-gray-400">
            ダミー例: {{ \App\Support\DemoMode::dummyName() }} /
            {{ \App\Support\DemoMode::dummyTel() }} /
            {{ \App\Support\DemoMode::dummyEmail() }}
        </p>
    </div>
@endif
