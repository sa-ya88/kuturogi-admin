<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STAY_STATUS_RESERVED = 'reserved';

    public const STAY_STATUS_PARTIALLY_IN_HOUSE = 'partially_in_house';

    public const STAY_STATUS_IN_HOUSE = 'in_house';

    public const STAY_STATUS_CHECKED_OUT = 'checked_out';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_AUTHORIZED = 'authorized';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUNDED = 'refunded';

    public const PAYMENT_FAILED = 'failed';

    protected $fillable = [
        'kuturogi_reservation_id',
        'customer_id',
        'user_id',
        'room_id',
        'plan_id',
        'checkin_date',
        'checkout_date',
        'guest_count',
        'room_count',
        'adult_count',
        'child_count',
        'total_price',
        'status',
        'stay_status',
        'payment_method',
        'payment_status',
        'stripe_payment_intent_id',
        'stripe_latest_charge_id',
        'authorized_at',
        'paid_at',
        'refunded_at',
        'cancel_fee_amount',
        'stripe_cancel_fee_payment_intent_id',
        'cancel_fee_uncollected',
        'guest_name',
        'guest_name_kana',
        'guest_tel',
        'guest_email',
        'guest_zip_code',
        'guest_address',
        'guest_building',
        'source',
        'synced_at',
        'selected_choices',
        'selected_option_fees',
    ];

    protected function casts(): array
    {
        return [
            'checkin_date' => 'date',
            'checkout_date' => 'date',
            'synced_at' => 'datetime',
            'selected_choices' => 'array',
            'selected_option_fees' => 'array',
            'authorized_at' => 'datetime',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancel_fee_uncollected' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function siteUser(): BelongsTo
    {
        return $this->belongsTo(SiteUser::class, 'user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function salesRecord(): HasOne
    {
        return $this->hasOne(SalesRecord::class);
    }

    public function numberForDisplay(): string
    {
        return '#'.$this->id;
    }

    public function stays(): HasMany
    {
        return $this->hasMany(ReservationStay::class)->orderBy('sort_order');
    }

    public function unitDateOccupancies(): HasMany
    {
        return $this->hasMany(RoomUnitDateOccupancy::class);
    }

    public static function stayStatusLabel(string $status): string
    {
        return match ($status) {
            self::STAY_STATUS_PARTIALLY_IN_HOUSE => '一部滞在中',
            self::STAY_STATUS_IN_HOUSE => '滞在中',
            self::STAY_STATUS_CHECKED_OUT => 'チェックアウト済',
            default => '予約',
        };
    }

    public static function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            self::PAYMENT_AUTHORIZED => '与信済',
            self::PAYMENT_PAID => '支払済',
            self::PAYMENT_REFUNDED => '返金済',
            self::PAYMENT_FAILED => '失敗',
            default => '未払',
        };
    }

    public static function paymentStatusColor(?string $status): string
    {
        return match ($status) {
            self::PAYMENT_AUTHORIZED => 'info',
            self::PAYMENT_PAID => 'success',
            self::PAYMENT_REFUNDED => 'gray',
            self::PAYMENT_FAILED => 'danger',
            default => 'warning',
        };
    }
}
