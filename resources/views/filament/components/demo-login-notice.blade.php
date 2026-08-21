@if (\App\Support\DemoMode::enabled())
    <div
        role="note"
        class="mb-4 rounded-xl bg-warning-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-warning-600/20 dark:bg-gray-900 dark:text-white dark:ring-white/10"
    >
        <p class="font-medium">ポートフォリオ用の公開デモです</p>
        <p class="mt-1 text-gray-600 dark:text-gray-400">
            架空のデモユーザーでログインしてください。パスワードは「{{ config('app.demo_login_password', 'demo') }}」です。
        </p>
        <ul class="mt-2 space-y-1 pl-5 text-gray-600 dark:text-gray-400">
            <li>・ 本名などの個人情報は入力しないでください。</li>
            <li>・ 客室、プラン、料金、スタッフは削除できません。</li>
            <li>・ データは {{ \App\Support\DemoMode::refreshHours() }} 時間ごとに初期化されます。</li>
        </ul>
    </div>
@endif
