<?php

namespace Tests\Feature\Public;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class ContactMessageTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    public function test_a_guest_can_send_a_contact_message(): void
    {
        $response = $this->postJson('/api/v1/contact-messages', [
            'name' => 'سارا محمدی',
            'email' => 'sara@example.com',
            'subject' => 'سوال درباره نوبت‌دهی',
            'body' => 'سلام، می‌خواستم بدانم تغییر زمان جلسه تا چند ساعت قبل امکان‌پذیر است؟',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'sara@example.com',
            'status' => 'new',
            'user_id' => null,
        ]);
    }

    public function test_the_message_links_to_the_authenticated_user_when_present(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/contact-messages', [
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'پیشنهاد',
            'body' => 'یک پیشنهاد برای بهبود فرایند رزرو دارم.',
        ])->assertStatus(201);

        $this->assertDatabaseHas('contact_messages', [
            'user_id' => $user->id,
        ]);
    }

    public function test_the_message_is_validated(): void
    {
        $this->assertApiValidationError(
            $this->postJson('/api/v1/contact-messages', [
                'name' => '',
                'email' => 'not-an-email',
                'subject' => '',
                'body' => 'کوتاه',
            ]),
            'name', 'email', 'subject', 'body'
        );

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_sending_is_rate_limited(): void
    {
        foreach (range(1, 3) as $index) {
            $this->postJson('/api/v1/contact-messages', [
                'name' => 'کاربر',
                'email' => "user{$index}@example.com",
                'subject' => 'پیام شماره '.$index,
                'body' => 'متن پیام آزمایشی برای بررسی محدودیت ارسال.',
            ])->assertStatus(201);
        }

        $this->postJson('/api/v1/contact-messages', [
            'name' => 'کاربر',
            'email' => 'flood@example.com',
            'subject' => 'پیام اضافی',
            'body' => 'این پیام باید توسط محدودیت نرخ ارسال مسدود شود.',
        ])->assertStatus(429);
    }

    public function test_messages_are_not_exposed_publicly(): void
    {
        ContactMessage::create([
            'name' => 'کاربر',
            'email' => 'user@example.com',
            'subject' => 'موضوع',
            'body' => 'متن پیام که نباید عمومی باشد.',
        ]);

        $this->getJson('/api/v1/contact-messages')->assertStatus(405);
        $this->getJson('/api/v1/contact-messages/1')->assertStatus(404);
    }
}
