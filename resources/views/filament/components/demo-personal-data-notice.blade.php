<div class="rounded-lg bg-warning-50 px-3 py-2 text-sm text-gray-950 ring-1 ring-warning-600/20 dark:bg-gray-900 dark:text-white dark:ring-white/10">
    <p class="font-medium">ダミー情報を入力してください</p>
    <p class="mt-1 text-gray-600 dark:text-gray-400">
        実在の個人情報は使わないでください。例:
        {{ \App\Support\DemoMode::dummyName() }} /
        {{ \App\Support\DemoMode::dummyTel() }} /
        {{ \App\Support\DemoMode::dummyEmail() }}
    </p>
</div>
