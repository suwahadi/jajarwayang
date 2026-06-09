<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'tentang-kami' => [
                'title' => 'Tentang Kami',
                'content' => '<p>Lorem ipsum dolor sit amet, omnis nusquam sit ea. Oratio putant cetero te his, te sed augue inimicus consequuntur. Eu mei dicta neglegentur, duo cu blandit deseruisse efficiantur, eam ne liber deserunt consectetuer. Pro ipsum prompta temporibus in, te nihil omittam vis, sed ei zril noluisse consequat. Eum essent vidisse equidem ei.</p>',
            ],
            'kebijakan-pengiriman' => [
                'title' => 'Kebijakan Pengiriman',
                'content' => '<p>Lorem ipsum dolor sit amet, omnis nusquam sit ea. Oratio putant cetero te his, te sed augue inimicus consequuntur. Eu mei dicta neglegentur, duo cu blandit deseruisse efficiantur, eam ne liber deserunt consectetuer. Pro ipsum prompta temporibus in, te nihil omittam vis, sed ei zril noluisse consequat. Eum essent vidisse equidem ei.</p>',
            ],
            'faq' => [
                'title' => 'Pertanyaan Umum (FAQ)',
                'content' => '<p>Lorem ipsum dolor sit amet, omnis nusquam sit ea. Oratio putant cetero te his, te sed augue inimicus consequuntur. Eu mei dicta neglegentur, duo cu blandit deseruisse efficiantur, eam ne liber deserunt consectetuer. Pro ipsum prompta temporibus in, te nihil omittam vis, sed ei zril noluisse consequat. Eum essent vidisse equidem ei.</p>',
            ],
        ];

        foreach ($pages as $slug => $data) {
            Page::query()->updateOrCreate(
                ['slug' => $slug],
                [...$data, 'is_active' => true],
            );
        }
    }
}
