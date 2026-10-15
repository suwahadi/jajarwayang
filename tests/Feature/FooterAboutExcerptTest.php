<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterAboutExcerptTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_shows_only_first_sentence_of_about_page(): void
    {
        Page::factory()->create([
            'slug' => 'tentang-kami',
            'content' => '<p><strong style="font-size: 0.95rem;">CV. Contoh Jaya</strong><span style="font-size: 0.95rem;"> adalah penyedia komponen CNC. Kami hadir untuk pelanggan.</span></p><p>Paragraf kedua.</p>',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<strong style="font-size: 0.95rem;">CV. Contoh Jaya</strong> adalah penyedia komponen CNC.', false)
            ->assertDontSee('Kami hadir untuk pelanggan.');
    }
}
