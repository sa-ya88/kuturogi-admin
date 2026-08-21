<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\RoomUnit;
use App\Models\SalesRecord;
use App\Models\User;
use App\Services\ReservationStayService;
use App\Services\RoomInventoryService;
use App\Support\DemoMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    private const OCCUPANCY_RATE = 0.8;

    private const MEMBER_RATE = 0.3;

    private const REPEATER_RATE = 0.3;

    private const VIP_RATE = 0.1;

    private const VIP_SPEND_THRESHOLD = 100000;

    private const MIN_VIP_STAYS = 4;

    public function run(): void
    {
        $this->seedUsers();
        $this->seedSiteDemoGuest();
        $this->call(PropertyCatalogSeeder::class);

        $rooms = Room::query()->orderBy('sort_order')->with('plans')->get()->all();

        $this->clearDemoReservations();
        $this->seedInventories($rooms);

        $today = Carbon::today();
        $until = $today->copy()->addMonth();
        $slots = $this->occupancySlots($rooms, $today, $until);
        $counts = $this->segmentCounts(count($slots));
        $customers = $this->seedCustomers($counts);
        $this->seedPortfolioGuestCustomer();
        $this->seedReservations($rooms, $customers, $counts, $slots, $today, $until);
    }

    private function seedUsers(): void
    {
        $password = Hash::make((string) config('app.demo_login_password', 'demo'));

        User::query()->updateOrCreate(
            ['email' => User::DEMO_EMAIL],
            [
                'name' => 'デモ 管理者',
                'login_id' => User::DEMO_LOGIN_ID,
                'password' => $password,
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'staff@kuturogi.local'],
            [
                'name' => 'デモ スタッフ',
                'login_id' => 'k000002',
                'password' => $password,
                'role' => User::ROLE_STAFF,
            ]
        );
    }

    /**
     * @param  list<Room>  $rooms
     */
    private function seedInventories(array $rooms): void
    {
        $from = Carbon::today();
        $to = Carbon::today()->addDays(60);

        foreach ($rooms as $room) {
            $stock = $room->inServiceUnitsCount();
            $room->forceFill([
                'stock_count' => $stock,
                'available_from' => $from->toDateString(),
                'available_to' => $to->toDateString(),
            ])->saveQuietly();

            RoomInventory::query()
                ->where('room_id', $room->id)
                ->where(function ($query) use ($from, $to): void {
                    $query->whereDate('date', '<', $from->toDateString())
                        ->orWhereDate('date', '>', $to->toDateString());
                })
                ->delete();

            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                RoomInventory::upsertForRoomDate($room->id, $date->toDateString(), $stock);
            }
        }
    }

    /**
     * @return array{total: int, members: int, repeaters: int, vips: int}
     */
    private function segmentCounts(int $slotCount): array
    {
        $total = 10;

        for ($candidate = (int) (floor($slotCount / (1.1 + self::VIP_RATE * self::MIN_VIP_STAYS) / 10) * 10); $candidate >= 10; $candidate -= 10) {
            $total = $candidate;
            break;
        }

        return [
            'total' => $total,
            'members' => (int) round($total * self::MEMBER_RATE),
            'repeaters' => (int) round($total * self::REPEATER_RATE),
            'vips' => (int) round($total * self::VIP_RATE),
        ];
    }

    /**
     * @param  array{total: int, members: int, repeaters: int, vips: int}  $counts
     * @return list<Customer>
     */
    private function seedCustomers(array $counts): array
    {
        $customers = [];

        foreach ($this->customerDefinitions($counts) as $definition) {
            $customers[] = Customer::query()->updateOrCreate(
                ['email' => $definition['email']],
                $definition
            );
        }

        $keepEmails = collect($customers)->pluck('email')->all();
        $keepEmails[] = 'guest@example.com';

        Customer::query()
            ->whereNotIn('email', $keepEmails)
            ->whereDoesntHave('reservations')
            ->delete();

        return $customers;
    }

    private function seedSiteDemoGuest(): void
    {
        $now = now();

        DB::table('users')->updateOrInsert(
            ['email' => 'guest@example.com'],
            [
                'name' => 'ゲスト 太郎',
                'name_kana' => 'げすと たろう',
                'password' => Hash::make('password'),
                'email_verified_at' => $now,
                'birthday' => '1990-01-01',
                'gender' => 'other',
                'zip_code' => '1000001',
                'address' => '東京都千代田区千代田1-1',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function seedPortfolioGuestCustomer(): void
    {
        $siteUserId = DB::table('users')
            ->where('email', 'guest@example.com')
            ->value('id');

        Customer::query()->updateOrCreate(
            ['email' => 'guest@example.com'],
            [
                'kuturogi_user_id' => $siteUserId,
                'type' => Customer::TYPE_MEMBER,
                'name' => 'ゲスト 太郎',
                'name_kana' => 'げすと たろう',
                'tel' => '090-0000-0000',
                'zip_code' => '1000001',
                'address' => '東京都千代田区千代田1-1',
                'birthday' => '1990-01-01',
                'gender' => 'other',
                'notes' => 'ポートフォリオ用ゲスト会員です。',
            ]
        );
    }

    /**
     * @param  array{total: int, members: int, repeaters: int, vips: int}  $counts
     * @return list<array{
     *     type: string,
     *     name: string,
     *     name_kana: string,
     *     email: string,
     *     tel: string,
     *     zip_code: string,
     *     address: string,
     *     notes: string
     * }>
     */
    private function customerDefinitions(array $counts): array
    {
        $surnames = [
            ['佐藤', 'サトウ'], ['鈴木', 'スズキ'], ['高橋', 'タカハシ'], ['田中', 'タナカ'],
            ['伊藤', 'イトウ'], ['渡辺', 'ワタナベ'], ['小林', 'コバヤシ'], ['加藤', 'カトウ'],
            ['吉田', 'ヨシダ'], ['佐々木', 'ササキ'], ['松本', 'マツモト'], ['井上', 'イノウエ'],
            ['木村', 'キムラ'], ['林', 'ハヤシ'], ['斎藤', 'サイトウ'], ['清水', 'シミズ'],
            ['山本', 'ヤマモト'], ['中村', 'ナカムラ'], ['森田', 'モリタ'], ['池田', 'イケダ'],
        ];
        $givens = [
            ['美咲', 'ミサキ'], ['健太', 'ケンタ'], ['陽子', 'ヨウコ'], ['直樹', 'ナオキ'],
            ['彩', 'アヤ'], ['大輔', 'ダイスケ'], ['真由', 'マユ'], ['翔太', 'ショウタ'],
            ['恵', 'メグミ'], ['拓也', 'タクヤ'], ['里奈', 'リナ'], ['悠', 'ユウ'],
            ['香織', 'カオリ'], ['連', 'レン'], ['瞳', 'ヒトミ'], ['海斗', 'カイト'],
            ['千尋', 'チヒロ'], ['剛', 'ツヨシ'], ['咲', 'サキ'], ['亮', 'リョウ'],
        ];

        $named = [
            0 => [DemoMode::dummyName(), 'ヤマダ タロウ', DemoMode::dummyEmail()],
            1 => ['佐藤 花子', 'サトウ ハナコ', 'hanako@example.com'],
            $counts['repeaters'] => ['鈴木 一郎', 'スズキ イチロウ', 'ichiro@example.com'],
        ];

        $definitions = [];

        for ($index = 0; $index < $counts['total']; $index++) {
            $number = $index + 1;

            if (isset($named[$index])) {
                [$name, $kana, $email] = $named[$index];
            } else {
                [$surname, $surnameKana] = $surnames[$index % count($surnames)];
                [$given, $givenKana] = $givens[intdiv($index, count($surnames)) % count($givens)];
                $name = "{$surname} {$given}";
                $kana = "{$surnameKana} {$givenKana}";
                $email = sprintf('demo%03d@example.com', $number);
            }

            $definitions[] = [
                'type' => $index < $counts['members'] ? Customer::TYPE_MEMBER : Customer::TYPE_GUEST,
                'name' => $name,
                'name_kana' => $kana,
                'email' => $email,
                'tel' => sprintf('090-0000-%04d', $number),
                'zip_code' => sprintf('1%02d-0001', $number % 90),
                'address' => "東京都架空区デモ町{$number}-1-1",
                'notes' => 'ポートフォリオ用の架空顧客です。',
            ];
        }

        return $definitions;
    }

    private function clearDemoReservations(): void
    {
        Reservation::query()->where('source', 'demo')->delete();

        RoomUnit::query()
            ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
            ->update(['current_status' => RoomUnit::CURRENT_BOOKABLE]);
    }

    /**
     * @param  list<Room>  $rooms
     * @param  list<Customer>  $customers
     * @param  array{total: int, members: int, repeaters: int, vips: int}  $counts
     * @param  list<array{room: Room, unit: RoomUnit, plan: Plan, checkin: Carbon}>  $slots
     */
    private function seedReservations(
        array $rooms,
        array $customers,
        array $counts,
        array $slots,
        Carbon $today,
        Carbon $until,
    ): void {
        $assignments = $this->assignSlotsToCustomers($slots, $customers, $counts);
        $stayService = app(ReservationStayService::class);
        $created = [];

        foreach ($assignments as $assignment) {
            $created[] = $this->persistOccupancyReservation(
                $stayService,
                $assignment['plan'],
                $assignment['customer'],
                $assignment['room'],
                $assignment['unit'],
                $assignment['checkin'],
                $today,
            );
        }

        foreach ($this->seedTodayCheckouts($stayService, $customers, $created, $today) as $checkout) {
            $created[] = $checkout;
        }

        $this->recordSales($created);
        app(RoomInventoryService::class)->refreshRemainsFromOccupancy(
            $rooms,
            $today,
            Carbon::today()->addDays(60),
        );
        $this->refreshUnitStatuses($today);
        $this->refreshCustomerStats($customers);
    }

    /**
     * @param  list<Room>  $rooms
     * @return list<array{room: Room, unit: RoomUnit, plan: Plan, checkin: Carbon}>
     */
    private function occupancySlots(array $rooms, Carbon $from, Carbon $until): array
    {
        $roomsById = [];

        foreach ($rooms as $room) {
            $roomsById[$room->id] = $room;
        }

        $units = RoomUnit::query()
            ->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)
            ->whereIn('room_id', array_keys($roomsById))
            ->get();

        $targetOccupied = (int) round($units->count() * self::OCCUPANCY_RATE);
        $slots = [];

        for ($night = $from->copy(); $night->lt($until); $night->addDay()) {
            foreach ($units->shuffle()->take($targetOccupied) as $unit) {
                $room = $roomsById[$unit->room_id];

                $slots[] = [
                    'room' => $room,
                    'unit' => $unit,
                    'plan' => $this->pickPlanForRoom($room),
                    'checkin' => $night->copy(),
                ];
            }
        }

        return $slots;
    }

    private function pickPlanForRoom(Room $room): Plan
    {
        $plans = $room->plans->where('is_active', true)->values();

        if ($plans->isEmpty()) {
            throw new \RuntimeException("有効なプランがありません: {$room->name}");
        }

        return $plans->random();
    }

    /**
     * @param  list<array{room: Room, unit: RoomUnit, plan: Plan, checkin: Carbon}>  $slots
     * @param  list<Customer>  $customers
     * @param  array{total: int, members: int, repeaters: int, vips: int}  $counts
     * @return list<array{customer: Customer, room: Room, unit: RoomUnit, plan: Plan, checkin: Carbon}>
     */
    private function assignSlotsToCustomers(array $slots, array $customers, array $counts): array
    {
        $vips = array_slice($customers, 0, $counts['vips']);
        $nonVipRepeaters = array_slice($customers, $counts['vips'], $counts['repeaters'] - $counts['vips']);
        $oneTimers = array_slice($customers, $counts['repeaters']);

        $remaining = $slots;
        usort($remaining, function (array $left, array $right): int {
            $price = $this->stayPrice($left['room'], $left['plan'], 1)
                <=> $this->stayPrice($right['room'], $right['plan'], 1);

            return $price !== 0 ? $price : $left['checkin']->timestamp <=> $right['checkin']->timestamp;
        });

        $assignments = [];

        foreach ($nonVipRepeaters as $customer) {
            $spent = 0;

            for ($i = 0; $i < 2; $i++) {
                $index = $this->indexOfSlotWithinSpend($remaining, $spent);

                if ($index === null) {
                    break 2;
                }

                $slot = $remaining[$index];
                unset($remaining[$index]);
                $remaining = array_values($remaining);
                $spent += $this->stayPrice($slot['room'], $slot['plan'], 1);

                $assignments[] = [
                    'customer' => $customer,
                    'room' => $slot['room'],
                    'unit' => $slot['unit'],
                    'plan' => $slot['plan'],
                    'checkin' => $slot['checkin'],
                ];
            }
        }

        foreach ($oneTimers as $customer) {
            $slot = array_shift($remaining);

            if ($slot === null) {
                break;
            }

            $assignments[] = [
                'customer' => $customer,
                'room' => $slot['room'],
                'unit' => $slot['unit'],
                'plan' => $slot['plan'],
                'checkin' => $slot['checkin'],
            ];
        }

        $vipIndex = 0;

        foreach ($remaining as $slot) {
            if ($vips === []) {
                break;
            }

            $assignments[] = [
                'customer' => $vips[$vipIndex % count($vips)],
                'room' => $slot['room'],
                'unit' => $slot['unit'],
                'plan' => $slot['plan'],
                'checkin' => $slot['checkin'],
            ];
            $vipIndex++;
        }

        return $assignments;
    }

    /**
     * @param  list<array{room: Room, unit: RoomUnit, plan: Plan, checkin: Carbon}>  $slots
     */
    private function indexOfSlotWithinSpend(array $slots, int $spent): ?int
    {
        foreach ($slots as $index => $slot) {
            if ($spent + $this->stayPrice($slot['room'], $slot['plan'], 1) < self::VIP_SPEND_THRESHOLD) {
                return $index;
            }
        }

        return null;
    }

    private function persistOccupancyReservation(
        ReservationStayService $stayService,
        Plan $plan,
        Customer $customer,
        Room $room,
        RoomUnit $unit,
        Carbon $night,
        Carbon $today,
        bool $checkIn = false,
    ): Reservation {
        $reservation = $this->createReservation([
            'customer' => $customer,
            'room' => $room,
            'plan' => $plan,
            'checkin' => $night->copy(),
            'checkout' => $night->copy()->addDay(),
            'total' => $this->stayPrice($room, $plan, 1),
            'payment_status' => $night->lte($today)
                ? Reservation::PAYMENT_PAID
                : Reservation::PAYMENT_UNPAID,
            'paid_at' => $night->lte($today) ? $night->copy() : null,
        ]);

        $stayService->syncStaysForReservation($reservation);

        $stay = $reservation->fresh(['stays'])->stays->first();
        $stayService->assignUnit($stay, $unit->id);

        if ($night->isSameDay($today) || $checkIn) {
            foreach ($reservation->fresh(['stays'])->stays as $inHouseStay) {
                $stayService->checkIn($inHouseStay);
            }
        }

        return $reservation->fresh();
    }

    /**
     * @param  list<Customer>  $customers
     * @param  list<Reservation>  $created
     * @return list<Reservation>
     */
    private function seedTodayCheckouts(
        ReservationStayService $stayService,
        array $customers,
        array $created,
        Carbon $today,
    ): array {
        $yesterday = $today->copy()->subDay();
        $customerCount = count($customers);
        $departures = [];

        $arrivals = array_values(array_filter(
            $created,
            fn (Reservation $reservation) => $reservation->checkin_date?->isSameDay($today) ?? false
        ));

        foreach (array_slice($arrivals, 0, 3) as $index => $arrival) {
            $arrival->loadMissing(['room', 'plan', 'stays.roomUnit']);
            $unit = $arrival->stays->first()?->roomUnit;
            $room = $arrival->room;
            $plan = $arrival->plan;

            if (! $unit || ! $room || ! $plan || $customerCount === 0) {
                continue;
            }

            $departures[] = $this->persistOccupancyReservation(
                $stayService,
                $plan,
                $customers[($index + 1) % $customerCount],
                $room,
                $unit,
                $yesterday,
                $today,
                true,
            );
        }

        return $departures;
    }

    private function stayPrice(Room $room, Plan $plan, int $nights): int
    {
        return $nights * 2 * ((int) $room->price_per_person + (int) $plan->price_per_person);
    }

    /**
     * @param  list<Reservation>  $reservations
     */
    private function recordSales(array $reservations): void
    {
        foreach ($reservations as $reservation) {
            if ($reservation->status !== Reservation::STATUS_CONFIRMED) {
                continue;
            }

            if ($reservation->payment_status !== Reservation::PAYMENT_PAID) {
                continue;
            }

            SalesRecord::query()->updateOrCreate(
                ['reservation_id' => $reservation->id],
                [
                    'amount' => $reservation->total_price,
                    'recorded_at' => $reservation->checkin_date,
                    'status' => SalesRecord::STATUS_RECORDED,
                    'notes' => 'ポートフォリオ用の架空売上です。',
                ]
            );
        }
    }

    private function refreshUnitStatuses(Carbon $today): void
    {
        $todayString = $today->toDateString();

        foreach (RoomUnit::query()->where('operation_status', RoomUnit::OPERATION_IN_SERVICE)->get() as $unit) {
            $stay = $unit->stays()
                ->whereNull('checked_out_at')
                ->whereHas(
                    'reservation',
                    fn ($query) => $query
                        ->where('status', Reservation::STATUS_CONFIRMED)
                        ->whereDate('checkin_date', '<=', $todayString)
                        ->whereDate('checkout_date', '>', $todayString)
                )
                ->first();

            $status = match (true) {
                $stay?->checked_in_at !== null => RoomUnit::CURRENT_IN_HOUSE,
                $stay !== null => RoomUnit::CURRENT_AWAITING_ARRIVAL,
                default => RoomUnit::CURRENT_BOOKABLE,
            };

            $unit->update(['current_status' => $status]);
        }
    }

    /**
     * @param  list<Customer>  $customers
     */
    private function refreshCustomerStats(array $customers): void
    {
        foreach ($customers as $customer) {
            $customer->refreshStayStats();
        }
    }

    /**
     * @param  array{
     *     customer: Customer,
     *     room: Room,
     *     plan: Plan,
     *     checkin: Carbon,
     *     checkout: Carbon,
     *     total: int,
     *     status?: string,
     *     payment_status?: string,
     *     paid_at?: Carbon|null
     * }  $data
     */
    private function createReservation(array $data): Reservation
    {
        return Reservation::query()->create([
            'customer_id' => $data['customer']->id,
            'room_id' => $data['room']->id,
            'plan_id' => $data['plan']->id,
            'checkin_date' => $data['checkin']->toDateString(),
            'checkout_date' => $data['checkout']->toDateString(),
            'guest_count' => 2,
            'room_count' => 1,
            'adult_count' => 2,
            'child_count' => 0,
            'total_price' => $data['total'],
            'status' => $data['status'] ?? Reservation::STATUS_CONFIRMED,
            'stay_status' => Reservation::STAY_STATUS_RESERVED,
            'payment_method' => 'local',
            'payment_status' => $data['payment_status'] ?? Reservation::PAYMENT_UNPAID,
            'paid_at' => $data['paid_at'] ?? null,
            'guest_name' => $data['customer']->name,
            'guest_email' => $data['customer']->email,
            'guest_tel' => $data['customer']->tel,
            'source' => 'demo',
        ]);
    }
}
