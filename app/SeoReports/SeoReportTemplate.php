<?php

namespace App\SeoReports;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SeoReportTemplate extends Model
{
    protected $table = 'seo_report_templates';

    protected $fillable = [
        'user_id',
        'title',
        'is_default',
        'agency_name',
        'agency_address',
        'agency_email',
        'agency_phone',
        'agency_logo_path',
        'brand_color',
        'manager_name',
        'manager_phone',
        'manager_email',
        'manager_avatar_path',
        'section_toggles',
        'settings_json',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'section_toggles' => 'array',
        'settings_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(SeoReportProject::class, 'template_id');
    }

    /**
     * @return array<string, bool>
     */
    public function resolvedSectionToggles(): array
    {
        $defaults = SeoReportSectionRegistry::defaultToggles();
        $stored = is_array($this->section_toggles) ? $this->section_toggles : [];
        foreach ($defaults as $key => $_enabled) {
            if (array_key_exists($key, $stored)) {
                $defaults[$key] = (bool) $stored[$key];
            }
        }

        return $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function reportSettings(): array
    {
        return is_array($this->settings_json) ? $this->settings_json : [];
    }

    public function agencyLogoUrl(): ?string
    {
        return $this->publicDiskUrl($this->agency_logo_path);
    }

    public function managerAvatarUrl(): ?string
    {
        return $this->publicDiskUrl($this->manager_avatar_path);
    }

    private function publicDiskUrl(?string $path): ?string
    {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return null;
        }

        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return asset('storage/' . ltrim($path, '/'));
    }
}
