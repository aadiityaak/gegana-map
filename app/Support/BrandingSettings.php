<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class BrandingSettings
{
  private const DEFAULT_LOGO_PATH = 'branding/lgo.png';
  private const DEFAULT_FAVICON_PATH = 'branding/gegana-fav.png';

  public function shared(): array
  {
    $settings = $this->read();

    return [
      'name' => $this->resolvedName($settings),
      'logo_url' => $this->versionedAssetUrl(self::DEFAULT_LOGO_PATH),
      'favicon_url' => $this->versionedAssetUrl(self::DEFAULT_FAVICON_PATH),
    ];
  }

  public function update(?string $name, ?UploadedFile $logo, ?UploadedFile $favicon): void
  {
    $settings = $this->read();

    $trimmedName = trim((string) $name);
    $settings['name'] = $trimmedName !== '' ? $trimmedName : null;

    if ($logo instanceof UploadedFile) {
      $this->replaceUploadedAsset($logo, self::DEFAULT_LOGO_PATH);
      $settings['logo_path'] = self::DEFAULT_LOGO_PATH;
    }

    if ($favicon instanceof UploadedFile) {
      $this->replaceUploadedAsset($favicon, self::DEFAULT_FAVICON_PATH);
      $settings['favicon_path'] = self::DEFAULT_FAVICON_PATH;
    }

    $this->write($settings);
  }

  public function faviconUrl(): string
  {
    return $this->shared()['favicon_url'];
  }

  private function resolvedName(array $settings): string
  {
    $name = trim((string) ($settings['name'] ?? ''));

    return $name !== '' ? $name : (string) config('app.name', 'Laravel');
  }

  private function replaceUploadedAsset(UploadedFile $file, string $targetRelativePath): void
  {
    $normalized = $this->normalizeRelativePath($targetRelativePath);
    if ($normalized === null) {
      return;
    }

    $directory = dirname(public_path($normalized));
    File::ensureDirectoryExists($directory);
    $file->move($directory, basename($normalized));
  }

  private function versionedAssetUrl(string $relativePath): string
  {
    $version = File::exists(public_path($relativePath))
      ? (string) File::lastModified(public_path($relativePath))
      : (string) time();

    return asset($relativePath) . '?v=' . $version;
  }

  private function read(): array
  {
    $path = $this->settingsPath();
    if (! File::exists($path)) {
      return [];
    }

    $decoded = json_decode((string) File::get($path), true);

    return is_array($decoded) ? $decoded : [];
  }

  private function write(array $settings): void
  {
    $path = $this->settingsPath();
    File::ensureDirectoryExists(dirname($path));
    File::put($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }

  private function settingsPath(): string
  {
    return storage_path('app/branding/settings.json');
  }

  private function normalizeRelativePath(?string $path): ?string
  {
    $trimmed = trim((string) $path);

    if ($trimmed === '') {
      return null;
    }

    return ltrim(str_replace('\\', '/', $trimmed), '/');
  }
}
