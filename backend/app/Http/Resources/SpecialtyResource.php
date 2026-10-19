<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;

/**
 * @mixin Specialty
 */
class SpecialtyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // `name` is localized per the API locale: the English name when the
        // request is English and one is stored, otherwise the canonical
        // Persian name from the database. `name_fa` stays stable for clients
        // that need the original label regardless of locale.
        $localized = App::isLocale('en')
            && is_string($this->name_en)
            && $this->name_en !== '' ? $this->name_en : $this->name;

        return [
            'id' => $this->id,
            'name' => $localized,
            'name_fa' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
        ];
    }
}
