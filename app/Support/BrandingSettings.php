<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class BrandingSettings
{
  private const DEFAULT_LOGO_PATH = 'branding/pusdata.png';
  private const DEFAULT_FAVICON_PATH = 'branding/gegana-fav.png';

  public function shared(): array
  {
    $settings = $this->read();

    return [
      'name' => $this->resolvedName($settings),
      'logo_url' => $this->resolvedUrl(self::DEFAULT_LOGO_PATH),
      'favicon_url' => $this->resolvedUrl(self::DEFAULT_FAVICON_PATH),
    ];
  }

  public function update(?string $name, ?UploadedFile $logo, ?UploadedFile $favicon): void
  {
    $settings = $this->read();

    $trimmedName = trim((string) $name);
    $settings['name'] = $trimmedName !== '' ? $trimmedName : null;

    if ($logo instanceof UploadedFile) {
      $this->replaceUploadedAsset($logo, self::DEFAULT_LOGO_PATH);
    }

    if ($favicon instanceof UploadedFile) {
      $this->replaceUploadedAsset($favicon, self::DEFAULT_FAVICON_PATH);
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

  /**
   * Simpan file upload ke public/branding/.
   * file_put_contents lebih kompatibel di shared hosting daripada $file->move().
   */
  private function replaceUploadedAsset(UploadedFile $file, string $filename): void
  {
    $targetPath = public_path($filename);
    $directory = dirname($targetPath);

    File::ensureDirectoryExists($directory, 0755, true);

    $contents = $file->get();

    if (file_put_contents($targetPath, $contents) === false) {
      throw new \RuntimeException(sprintf(
        'Tidak dapat menulis ke %s. Periksa permission folder public/branding/.',
        $targetPath,
      ));
    }
  }

  private function resolvedUrl(string $relativePath): string
  {
    $absolutePath = public_path($relativePath);

    $version = File::exists($absolutePath)
      ? (string) File::lastModified($absolutePath)
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
}
