<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUpdateSettingsRequest;
use App\Models\SiteSetting;
use App\Support\ConsultationModes;
use Illuminate\Http\JsonResponse;

/**
 * Admin-editable site settings. Only whitelisted keys are writable; values
 * are stored as JSON and validated per key.
 */
class AdminSettingController extends Controller
{
    private const WRITABLE_KEYS = ['supported_modes', 'contact_lines'];

    /**
     * @return array<string, mixed>
     */
    public function show(): array
    {
        return [
            'supported_modes' => ConsultationModes::all(),
            'contact_lines' => SiteSetting::get('contact_lines', []),
            'configured_keys' => SiteSetting::query()->pluck('key'),
        ];
    }

    public function update(AdminUpdateSettingsRequest $request): JsonResponse
    {
        if ($request->has('supported_modes')) {
            SiteSetting::put('supported_modes', array_values((array) $request->input('supported_modes')));
        }

        if ($request->has('contact_lines')) {
            SiteSetting::put('contact_lines', array_values((array) $request->input('contact_lines')));
        }

        return response()->json($this->show());
    }
}
