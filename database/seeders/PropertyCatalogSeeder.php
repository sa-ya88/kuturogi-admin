<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Room;
use App\Models\RoomDetailOption;
use App\Models\RoomFeatureOption;
use App\Models\RoomUnit;
use Illuminate\Database\Seeder;

class PropertyCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedFeatures();
        $this->seedDetailOptions();
        $rooms = $this->seedRooms();
        $this->seedPlans($rooms);
    }

    private function seedFeatures(): void
    {
        $names = [
            '和室',
            '和洋室',
            'Wi-Fi',
            'バストイレ別',
            '禁煙',
            '客室露天風呂',
            '露天風呂付',
            '定員1〜4名',
            '定員2〜4名',
            '定員2〜3名',
        ];

        foreach ($names as $index => $name) {
            RoomFeatureOption::query()->updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }

    private function seedDetailOptions(): void
    {
        $facilities = [
            'バス',
            'シャワー',
            'トイレ',
            '洗浄機能付きトイレ',
            '冷暖房',
            'テレビ',
            '冷蔵庫',
            '金庫',
            '加湿空気清浄機',
        ];

        $amenities = [
            'タオル',
            '歯ブラシ',
            '浴衣',
            'ドライヤー',
            '石鹸類',
            'シャンプー・リンス',
            'ボディソープ',
            'カミソリ',
            'コットン・綿棒',
        ];

        foreach ($facilities as $index => $name) {
            RoomDetailOption::query()->updateOrCreate(
                ['category' => RoomDetailOption::CATEGORY_FACILITY, 'name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }

        foreach ($amenities as $index => $name) {
            RoomDetailOption::query()->updateOrCreate(
                ['category' => RoomDetailOption::CATEGORY_AMENITY, 'name' => $name],
                ['sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }

    private function seedRooms(): array
    {
        $standardFacilities = ['トイレ', '冷暖房', 'テレビ', '冷蔵庫', '金庫', '加湿空気清浄機'];
        $ensuiteFacilities = ['バス', 'シャワー', 'トイレ', '洗浄機能付きトイレ', '冷暖房', 'テレビ', '冷蔵庫', '金庫', '加湿空気清浄機'];
        $newWingFacilities = ['シャワー', '冷暖房', 'テレビ', '冷蔵庫', 'バス', '洗浄機能付きトイレ', '金庫', '加湿空気清浄機'];
        $basicAmenities = ['タオル', '歯ブラシ', '浴衣', 'ドライヤー', '石鹸類'];
        $fullAmenities = ['タオル', '歯ブラシ', '浴衣', 'ドライヤー', '石鹸類', 'シャンプー・リンス', 'ボディソープ', 'カミソリ', 'コットン・綿棒'];

        $definitions = [
            [
                'name' => 'スタンダード和室',
                'price_per_person' => 6000,
                'description' => '伝統的な日本の情緒を大切にした、落ち着きのあるスタンダードなお部屋です。',
                'features' => ['和室', '禁煙', '定員1〜4名'],
                'details' => [
                    'facilities' => $standardFacilities,
                    'internet' => '全室Wi-Fi無料',
                    'smoking' => '全室禁煙',
                    'amenities' => $basicAmenities,
                ],
                'sort_order' => 1,
                'units' => [
                    ['code' => '101'],
                    ['code' => '102'],
                    ['code' => '103', 'operation_status' => RoomUnit::OPERATION_OUT_OF_SERVICE, 'notes' => '水漏れ修理中（月末まで）'],
                ],
            ],
            [
                'name' => 'スタンダード和室（新館）',
                'price_per_person' => 8500,
                'description' => '伝統的な日本の情緒を大切にした、落ち着きのあるスタンダードなお部屋です。 こちらは今年の1月にリニューアルした新館のお部屋となっております。',
                'features' => ['和室', '禁煙', '定員1〜4名'],
                'details' => [
                    'facilities' => $newWingFacilities,
                    'internet' => '全室Wi-Fi無料',
                    'smoking' => '全室禁煙（喫煙スペースあり）',
                    'amenities' => $fullAmenities,
                ],
                'sort_order' => 2,
                'units' => [
                    ['code' => '201'],
                    ['code' => '202'],
                    ['code' => '203', 'operation_status' => RoomUnit::OPERATION_OUT_OF_SERVICE, 'notes' => '煙草消臭処理中（月曜稼働予定）'],
                ],
            ],
            [
                'name' => '和モダン客室「凛」- RIN -',
                'price_per_person' => 12000,
                'description' => '研ぎ澄まされた静寂と川のせせらぎが聴こえる、美しい滝の見えるお部屋です。',
                'features' => ['和室', '禁煙', '定員1〜4名'],
                'details' => [
                    'facilities' => $ensuiteFacilities,
                    'internet' => '全室Wi-Fi無料',
                    'smoking' => '全室禁煙',
                    'amenities' => $fullAmenities,
                ],
                'sort_order' => 3,
                'units' => [
                    ['code' => '301'],
                    ['code' => '302'],
                ],
            ],
            [
                'name' => '特別室「雅」- MIYABI -',
                'price_per_person' => 18000,
                'description' => '琉球畳の香りに包まれながら、洗練された現代的な空間でお寛ぎください。',
                'features' => ['和室', '禁煙', '定員2〜4名'],
                'details' => [
                    'facilities' => $ensuiteFacilities,
                    'internet' => '全室Wi-Fi無料',
                    'smoking' => '全室禁煙',
                    'amenities' => $fullAmenities,
                ],
                'sort_order' => 4,
                'units' => [
                    ['code' => '401'],
                    ['code' => '402'],
                ],
            ],
            [
                'name' => '離れ「茜」- AKANE -',
                'price_per_person' => 25000,
                'description' => '当館最高級の広さを誇る、源泉掛け流し露天風呂付きの客室です。',
                'features' => ['和室', '禁煙', '露天風呂付', '定員2〜4名'],
                'details' => [
                    'facilities' => $ensuiteFacilities,
                    'internet' => '全室Wi-Fi無料',
                    'smoking' => '全室禁煙',
                    'amenities' => $fullAmenities,
                ],
                'sort_order' => 5,
                'units' => [
                    ['code' => '501', 'notes' => '１室のみ'],
                ],
            ],
        ];

        $rooms = [];

        foreach ($definitions as $definition) {
            $units = $definition['units'];
            unset($definition['units']);

            $room = Room::query()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    ...$definition,
                    'is_active' => true,
                    'available_from' => $definition['available_from'] ?? now()->toDateString(),
                    'available_to' => null,
                ]
            );

            foreach ($units as $index => $unit) {
                $record = RoomUnit::query()->firstOrNew([
                    'room_id' => $room->id,
                    'code' => $unit['code'],
                ]);

                $record->fill([
                    'operation_status' => $unit['operation_status'] ?? RoomUnit::OPERATION_IN_SERVICE,
                    'notes' => $unit['notes'] ?? null,
                    'sort_order' => $index + 1,
                ]);

                if (! $record->exists) {
                    $record->current_status = RoomUnit::CURRENT_BOOKABLE;
                }

                $record->save();
            }

            $rooms[$room->name] = $room->fresh();
        }

        return $rooms;
    }

    private function seedPlans(array $rooms): void
    {
        $all = [
            'スタンダード和室',
            'スタンダード和室（新館）',
            '和モダン客室「凛」- RIN -',
            '特別室「雅」- MIYABI -',
            '離れ「茜」- AKANE -',
        ];
        $standard = ['スタンダード和室', 'スタンダード和室（新館）'];
        $withoutDetached = [
            'スタンダード和室',
            'スタンダード和室（新館）',
            '和モダン客室「凛」- RIN -',
            '特別室「雅」- MIYABI -',
        ];

        $definitions = [
            [
                'name' => '【基本】創作会席・1泊2食付プラン',
                'price_per_person' => 8500,
                'has_breakfast' => true,
                'has_dinner' => true,
                'rooms' => $all,
                'description' => <<<'TEXT'
迷ったらこれ！料理長自慢の四季折々の創作会席を堪能するスタンダードステイ

当館の魅力を最もシンプルに味わっていただける、1泊2食付きの基本プランです。
ご夕食は、地元の新鮮な山海の幸をふんだんに使用し、伝統の技に現代の感性を織り交ぜた「特製 創作会席」を。
目でも舌でも楽しめるお料理の数々を、五感でご堪能ください。
日常の喧騒から離れ、上質な空間と温泉、そして自慢の美食に癒される至福のひとときをお過ごしいただけます。

■プラン内容
チェックイン：15:00〜18:00 ／ チェックアウト：〜10:00
お食事：夕食（個室和食処にて創作会席） ／ 朝食（和洋食ビュッフェ）
TEXT,
            ],
            [
                'name' => '【贅沢】特選ブランド牛堪能プラン',
                'price_per_person' => 13500,
                'has_breakfast' => true,
                'has_dinner' => true,
                'rooms' => $all,
                'description' => <<<'TEXT'
お肉好きのための贅沢特選ブランド牛をメインに迎える最上級会席プランです。

せっかくの旅行だから、食事にはとことんこだわりたい。そんなグルメなあなたへ贈る、ワンランク上のアップグレードプランです。
メインディッシュには、見事な霜降りととろけるような食感が特徴の「特選ブランド牛」をご用意。
素材本来の旨味をダイレクトに味わえる絶妙な焼き加減でご提供いたします。
記念日やご褒美旅行など、特別な日を彩る華やかなディナータイムをお約束いたします。

■プラン内容
チェックイン：15:00〜18:00 ／ チェックアウト：〜10:00
お食事：夕食（特選ブランド牛メインの特別会席） ／ 朝食（和洋食ビュッフェ）
TEXT,
            ],
            [
                'name' => '【早割30】30日前までの予約でお得なプラン',
                'price_per_person' => 8500,
                'has_breakfast' => true,
                'has_dinner' => true,
                'has_early_bird' => true,
                'early_bird_discount_type' => Plan::DISCOUNT_TYPE_FIXED,
                'early_bird_discount_value' => 1000,
                'early_bird_days_before' => 30,
                'rooms' => $standard,
                'description' => <<<'TEXT'
予定が決まれば先取りがお得！1泊2食付スタンダードプラン。

ご宿泊日の30日前までに予約を完了することで、当館の【基本プラン】と全く同じ内容（客室・お食事）を、特別割引価格でご利用いただける大変お得な先取りプランです。
「旅行の計画は早めに立てる派」の方や、人気の客室を確実に押さえたい方にぴったり。
室数限定のプランとなりますので、週末やハイシーズンのご旅行をご検討中の方は、ぜひお早めにご予約ください。

■プラン内容
適用条件：ご宿泊日の30日前までの予約限定
お食事：夕食（個室和食処にて創作会席） ／ 朝食（和洋食ビュッフェ）
TEXT,
            ],
            [
                'name' => '【朝食付き】40種類の和洋食ビュッフェプラン',
                'price_per_person' => 2500,
                'has_breakfast' => true,
                'has_dinner' => false,
                'rooms' => $withoutDetached,
                'description' => <<<'TEXT'
夜は自由に、朝は贅沢に。40種類の和洋食ビュッフェでエネルギーチャージ！

「夕食は周辺の地元の名店で自由に食べたい」「到着が遅くなりそう」という方に最適な、朝食のみが付いたB&B（ベッド＆ブレックファスト）プランです。
ご朝食は、地元の厳選食材や炊き立てのご飯、焼き立てパンなど、バラエティ豊かな40種類のメニューが並ぶ和洋食ビュッフェ。
朝の光が差し込むレストランで、お好きなものをお好きなだけ、お腹いっぱいお召し上がりください。

■プラン内容
チェックイン：15:00〜21:00（夕食なしのため遅めの到着もOK）
お食事：夕食（なし） ／ 朝食（和洋食ビュッフェ）
TEXT,
            ],
            [
                'name' => '【食事なし】素泊まりプラン',
                'price_per_person' => 0,
                'has_breakfast' => false,
                'has_dinner' => false,
                'rooms' => $standard,
                'description' => <<<'TEXT'
気ままに過ごす、自由でシンプルな大人の贅沢温泉ステイ。

お食事の時間を気にせず、温泉と客室をシンプルに楽しみたい方のための素泊まりプランです。
誰にも邪魔されない、自分だけの「おひとりさま時間」をのんびりとお過ごしいただけます。
ビジネスでのご利用や、周辺観光をアクティブに楽しみたい方にもおすすめです。
時間を忘れて温泉に浸かり、ふかふかのお布団で眠る。そんな自由気ままなご滞在をお楽しみください。

■プラン内容
チェックイン：15:00〜22:00（最終受付）
お食事：なし（素泊まり）
TEXT,
            ],
        ];

        foreach ($definitions as $definition) {
            $roomNames = $definition['rooms'];
            unset($definition['rooms']);

            $plan = Plan::query()->updateOrCreate(
                ['name' => $definition['name']],
                [
                    ...$definition,
                    'is_active' => true,
                    'has_early_bird' => $definition['has_early_bird'] ?? false,
                ]
            );

            $plan->rooms()->sync(
                collect($roomNames)
                    ->map(fn (string $name): int => $rooms[$name]->id)
                    ->all()
            );
        }
    }
}
