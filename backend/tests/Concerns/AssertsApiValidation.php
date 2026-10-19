<?php

namespace Tests\Concerns;

use Illuminate\Testing\TestResponse;

trait AssertsApiValidation
{
    /**
     * Assert that the API returned the 422 envelope
     * `{ message, errors: [{ code, field, message }] }` for the given fields.
     */
    protected function assertApiValidationError(TestResponse $response, string ...$fields): void
    {
        $response->assertStatus(422);

        $payload = $response->json();

        $this->assertNotEmpty($payload['message'] ?? null, 'Validation envelope must carry a message.');
        $this->assertIsArray($payload['errors'] ?? null, 'Validation envelope must carry an errors array.');

        $actual = array_column($payload['errors'], 'field');

        foreach ($fields as $field) {
            $this->assertContains($field, $actual, "Expected a validation error for field [{$field}].");
        }
    }

    /**
     * Return the first message reported for a field, or null when absent.
     */
    protected function apiErrorMessage(TestResponse $response, string $field): ?string
    {
        foreach ($response->json('errors') ?? [] as $error) {
            if (($error['field'] ?? null) === $field) {
                return $error['message'] ?? null;
            }
        }

        return null;
    }
}
