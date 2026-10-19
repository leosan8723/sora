<?php

namespace App\Support;

final class SpecialtyCatalog
{
    /**
     * Curated specialty catalogue. Slugs are ASCII so they are safe for URLs
     * and route-model binding. `name` is the Persian display name (also the
     * database value); `name_en` is the English display name localized in
     * SpecialtyResource when the API locale is English.
     *
     * @return list<array{name: string, name_en: string, slug: string}>
     */
    public static function all(): array
    {
        return [
            ['name' => 'روان‌شناسی بالینی', 'name_en' => 'Clinical Psychology', 'slug' => 'clinical-psychology'],
            ['name' => 'مشاوره خانواده', 'name_en' => 'Family Counseling', 'slug' => 'family-counseling'],
            ['name' => 'مشاوره شغلی', 'name_en' => 'Career Counseling', 'slug' => 'career-counseling'],
            ['name' => 'کودک و نوجوان', 'name_en' => 'Child & Adolescent', 'slug' => 'child-and-adolescent'],
            ['name' => 'اضطراب و استرس', 'name_en' => 'Anxiety & Stress', 'slug' => 'anxiety-and-stress'],
            ['name' => 'زوج‌درمانی', 'name_en' => 'Couples Therapy', 'slug' => 'couples-therapy'],
            ['name' => 'تربیت فرزند', 'name_en' => 'Parenting', 'slug' => 'parenting'],
            ['name' => 'مدیریت خشم', 'name_en' => 'Anger Management', 'slug' => 'anger-management'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function englishNames(): array
    {
        return array_column(self::all(), 'name_en', 'slug');
    }
}
