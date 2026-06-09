<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'sku',
        'original_price',
        'promo_price',
        'description',
        'weight',
        'stock',
        'is_active',
        'badge',
    ];

    protected function casts(): array
    {
        return [
            'original_price' => 'integer',
            'promo_price' => 'integer',
            'weight' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Variant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Gambar utama produk: yang ditandai is_main, jika tidak ada ambil yang pertama.
     */
    public function mainImage(): ?ProductImage
    {
        $images = $this->relationLoaded('images') ? $this->images : $this->images()->get();

        return $images->firstWhere('is_main', true) ?? $images->first();
    }

    /**
     * URL thumbnail utama produk, atau null bila belum ada gambar.
     */
    public function thumbnailUrl(): ?string
    {
        return $this->mainImage()?->url;
    }

    /**
     * Harga efektif yang dibayar pelanggan: promo bila ada, jika tidak harga asli.
     *
     * @return Attribute<int, never>
     */
    protected function effectivePrice(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->promo_price ?? $this->original_price,
        );
    }

    /**
     * Apakah produk sedang dalam masa promo (PRD §8.2 visualisasi harga coret).
     * Untuk produk bervarian: true bila ada varian yang sedang promo.
     */
    public function isOnPromo(): bool
    {
        if ($this->hasVariants()) {
            return $this->variants->contains(fn (Variant $v): bool => $v->isOnPromo());
        }

        return $this->promo_price !== null && $this->promo_price < $this->original_price;
    }

    /**
     * Apakah produk memiliki varian sebagai unit jual.
     */
    public function hasVariants(): bool
    {
        return $this->relationLoaded('variants')
            ? $this->variants->isNotEmpty()
            : $this->variants()->exists();
    }

    /**
     * Harga termurah untuk tampilan ("mulai dari"): min harga efektif varian
     * bila bervarian, jika tidak harga efektif produk.
     */
    public function fromPrice(): int
    {
        if ($this->hasVariants()) {
            return (int) $this->variants->min(fn (Variant $v): int => $v->effective_price);
        }

        return $this->effective_price;
    }

    /**
     * Ekspresi SQL "harga efektif terendah" — padanan fromPrice() di tingkat DB.
     * MIN(harga efektif varian) bila bervarian, jika tidak harga efektif produk.
     * Dipakai untuk sorting & filter rentang harga di katalog agar konsisten
     * dengan harga "mulai dari" yang ditampilkan kartu produk.
     */
    public static function effectivePriceSql(): string
    {
        return '(COALESCE('
            .'(SELECT MIN(COALESCE(variants.promo_price, variants.price)) '
            .'FROM variants WHERE variants.product_id = products.id), '
            .'COALESCE(products.promo_price, products.original_price)))';
    }

    /**
     * Total stok tersedia: jumlah stok varian bila bervarian, else stok produk.
     */
    public function totalStock(): int
    {
        if ($this->hasVariants()) {
            return (int) $this->variants->sum('stock');
        }

        return $this->stock;
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Metadata badge kuratif (kolom `badge`) untuk kartu produk.
     * Mengembalikan null bila tidak diberi label. Badge "Diskon" terpisah,
     * diturunkan dari isOnPromo() di layer tampilan.
     *
     * @return array{label: string, tone: string}|null
     */
    public function badgeMeta(): ?array
    {
        return match ($this->badge) {
            'new' => ['label' => 'Baru', 'tone' => 'new'],
            'hot' => ['label' => 'Terlaris', 'tone' => 'hot'],
            default => null,
        };
    }
}
