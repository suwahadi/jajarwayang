<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'idempotency_key',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_province_id',
        'shipping_city_id',
        'shipping_district_id',
        'shipping_destination_label',
        'shipping_address',
        'shipping_courier',
        'shipping_cost',
        'voucher_id',
        'discount_amount',
        'subtotal',
        'grand_total',
        'status',
        'active_payment_attempt_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_cost' => 'integer',
            'discount_amount' => 'integer',
            'subtotal' => 'integer',
            'grand_total' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Batasi query ke pesanan milik seorang user.
     *
     * Checkout bersifat guest sehingga tabel orders tidak menyimpan user_id;
     * satu-satunya tautan kepemilikan adalah kesamaan email pemesan dengan
     * email akun. Dipakai konsisten di seluruh dashboard user agar data tidak
     * pernah bocor antar akun.
     *
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('customer_email', $user->email);
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<PaymentAttempt, $this>
     */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    /**
     * Riwayat aktivitas pesanan (timeline), terurut kronologis.
     *
     * @return HasMany<OrderActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Attempt pembayaran yang sedang berlaku (acuan utama pelunasan).
     *
     * @return BelongsTo<PaymentAttempt, $this>
     */
    public function activePaymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'active_payment_attempt_id');
    }
}
