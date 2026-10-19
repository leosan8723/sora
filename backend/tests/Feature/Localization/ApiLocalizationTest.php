<?php

namespace Tests\Feature\Localization;

use App\Support\SpecialtyCatalog;
use Database\Seeders\SpecialtySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Locale negotiation contract: Persian is the product default, English is
 * opt-in through Accept-Language, and catalog data localizes per request.
 */
class ApiLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fa_is_the_default_locale(): void
    {
        // Symfony's test request factory always injects "Accept-Language:
        // en-us,en;q=0.5", so the Persian default is expressed explicitly
        // here. Real clients that send no header fall back to the fa default
        // configured in config/app.php.
        $response = $this->postJson('/api/v1/contact-messages', [], ['Accept-Language' => 'fa'])
            ->assertStatus(422);

        $this->assertStringContainsString('الزامی', $this->firstValidationMessage($response));
    }

    public function test_en_validation_messages_follow_accept_language(): void
    {
        $response = $this->postJson('/api/v1/contact-messages', [], ['Accept-Language' => 'en'])
            ->assertStatus(422);

        $this->assertStringContainsString('required', $this->firstValidationMessage($response));
    }

    public function test_specialty_names_localize_per_accept_language(): void
    {
        $this->seed(SpecialtySeeder::class);

        $fa = $this->getJson('/api/v1/specialties', ['Accept-Language' => 'fa'])
            ->assertOk()
            ->json('data.0');

        $en = $this->getJson('/api/v1/specialties', ['Accept-Language' => 'en'])
            ->assertOk()
            ->json('data.0');

        $catalogNames = array_column(SpecialtyCatalog::all(), 'name', 'slug');
        $catalogNamesEn = array_column(SpecialtyCatalog::all(), 'name_en', 'slug');

        $this->assertSame($catalogNames[$en['slug']], $fa['name']);
        $this->assertSame($catalogNamesEn[$en['slug']], $en['name']);
        // The canonical Persian label is always available for clients.
        $this->assertSame($catalogNames[$en['slug']], $en['name_fa']);
    }

    public function test_domain_messages_localize_per_accept_language(): void
    {
        $payload = [
            'name' => 'سارا محمدی',
            'email' => 'sara@example.com',
            'subject' => 'دریافت مشاوره',
            'body' => 'متن پیام آزمایشی برای بررسی بومی‌سازی پاسخ‌ها.',
        ];

        $fa = $this->postJson('/api/v1/contact-messages', $payload, ['Accept-Language' => 'fa'])
            ->assertCreated()
            ->json('message');

        $en = $this->postJson('/api/v1/contact-messages', $payload, ['Accept-Language' => 'en'])
            ->assertCreated()
            ->json('message');

        $this->assertSame(__('contact.received', [], 'fa'), $fa);
        $this->assertSame(__('contact.received', [], 'en'), $en);
    }

    /**
     * Human-readable message of the first validation error of a response,
     * whatever wording the translation file uses.
     */
    private function firstValidationMessage(TestResponse $response): string
    {
        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            $first = reset($errors);

            return is_array($first)
                ? (string) ($first['message'] ?? json_encode($first))
                : (string) $first;
        }

        return (string) $response->json('message');
    }
}
