<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class BrandingSettings
{
  private const DEFAULT_LOGO_PATH = 'branding/pusdata.png';
  private const DEFAULT_FAVICON_PATH = 'branding/gegana-fav.png';

  /**
   * Folder riwayat gambar (media library branding) per jenis.
   * Setiap upload disimpan sebagai berkas BARU, tidak menimpa berkas sebelumnya,
   * supaya gambar lama tetap bisa dipakai ulang dari UI (Settings -> Branding).
   */
  private const DIREKTORI_RIWAYAT = [
    'logo' => 'branding/library/logo',
    'favicon' => 'branding/library/favicon',
  ];

  /** Jumlah maksimum berkas riwayat yang disimpan per jenis (yang tertua dibuang). */
  private const MAX_RIWAYAT = 40;

  /**
   * Cari public directory yang benar.
   * Di shared hosting dengan struktur:
   *   <root>/
   *     laravel-app/   ← base_path()
   *     public_html/   ← doc root web server
   * public_path() ngarah ke laravel-app/public/, bukan public_html/.
   */
  private function publicDir(): string
  {
    $publicHtml = dirname(base_path()) . DIRECTORY_SEPARATOR . 'public_html';

    return is_dir($publicHtml) ? $publicHtml : public_path();
  }

  public function shared(): array
  {
    $settings = $this->read();

    return [
      'name' => $this->resolvedName($settings),
      'logo_url' => $this->resolvedUrl($this->aktifPath('logo')),
      'favicon_url' => $this->resolvedUrl($this->aktifPath('favicon')),
    ];
  }

  /** Path relatif gambar yang sedang aktif dipakai aplikasi. */
  public function aktifPath(string $jenis): string
  {
    $settings = $this->read();
    $path = trim((string) ($settings[$jenis] ?? ''));

    if ($path !== '' && $this->berkasAda($path)) {
      return $path;
    }

    return $jenis === 'favicon' ? self::DEFAULT_FAVICON_PATH : self::DEFAULT_LOGO_PATH;
  }

  public function aktif(): array
  {
    return [
      'logo' => $this->aktifPath('logo'),
      'favicon' => $this->aktifPath('favicon'),
    ];
  }

  /**
   * Daftar gambar yang bisa dipilih di UI: berkas lama di `branding/`
   * (mis. pusdata.png, geganafav, lgo.png) + riwayat upload di `branding/library/<jenis>/`.
   */
  public function library(): array
  {
    $hasil = [];

    foreach (array_keys(self::DIREKTORI_RIWAYAT) as $jenis) {
      $hasil[$jenis] = $this->daftarGambar($jenis);
    }

    return $hasil;
  }

  /** Daftar path relatif yang sah untuk satu jenis — dipakai untuk validasi input UI. */
  public function pathTersedia(string $jenis): array
  {
    return array_values(array_map(
      static fn (array $item): string => $item['path'],
      $this->daftarGambar($jenis),
    ));
  }

  /**
   * @param  string|null  $logoPath     Path relatif gambar lama yang dipilih dari riwayat
   * @param  string|null  $faviconPath  Path relatif gambar lama yang dipilih dari riwayat
   */
  public function update(
    ?string $name,
    ?UploadedFile $logo,
    ?UploadedFile $favicon,
    ?string $logoPath = null,
    ?string $faviconPath = null,
  ): void {
    $settings = $this->read();

    $trimmedName = trim((string) $name);
    $settings['name'] = $trimmedName !== '' ? $trimmedName : null;

    // Unggahan baru selalu menang atas pilihan riwayat.
    if ($logo instanceof UploadedFile) {
      $settings['logo'] = $this->simpanKeRiwayat('logo', $logo);
    } elseif ($logoPath !== null && $logoPath !== '') {
      $settings['logo'] = $this->pathRelatifAman($logoPath);
    }

    if ($favicon instanceof UploadedFile) {
      $settings['favicon'] = $this->simpanKeRiwayat('favicon', $favicon);
    } elseif ($faviconPath !== null && $faviconPath !== '') {
      $settings['favicon'] = $this->pathRelatifAman($faviconPath);
    }

    $this->write($settings);
    $this->pangkasRiwayat('logo');
    $this->pangkasRiwayat('favicon');
  }

  /** Hapus satu berkas riwayat. Berkas yang sedang aktif tidak boleh dihapus. */
  public function hapusMedia(string $path): bool
  {
    $path = $this->pathRelatifAman($path);

    if (! $this->bolehDihapus($path)) {
      return false;
    }

    $absolut = $this->absolut($path);

    return File::exists($absolut) && File::delete($absolut);
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
   * Riwayat gambar: berkas lama di folder `branding/` + berkas upload di `branding/library/<jenis>/`.
   * Urut dari yang paling baru.
   */
  private function daftarGambar(string $jenis): array
  {
    $direktori = self::DIREKTORI_RIWAYAT[$jenis] ?? null;
    if ($direktori === null) {
      return [];
    }

    $aktif = $this->aktifPath($jenis);
    $sumber = [
      $direktori => 'Riwayat upload',
      'branding' => 'Berkas lama',
    ];

    $items = [];

    foreach ($sumber as $relatif => $label) {
      $folder = $this->absolut($relatif);
      if (! is_dir($folder)) {
        continue;
      }

      foreach (File::files($folder) as $file) {
        if (strtolower($file->getExtension()) !== 'png') {
          continue;
        }

        $path = $relatif . '/' . $file->getFilename();
        $ukuran = $file->getSize();

        $items[] = [
          'path' => $path,
          'nama' => $file->getFilename(),
          'sumber' => $label,
          'url' => $this->resolvedUrl($path),
          'ukuran' => $ukuran,
          'ukuran_human' => $this->ukuranHuman($ukuran),
          'diubah' => date('d M Y H:i', $file->getMTime()),
          'diubah_ts' => $file->getMTime(),
          'aktif' => $path === $aktif,
          'bisa_dihapus' => $this->bolehDihapus($path),
        ];
      }
    }

    usort($items, static fn (array $a, array $b): int => $b['diubah_ts'] <=> $a['diubah_ts']);
    $items = array_map(static function (array $item): array {
      unset($item['diubah_ts']);

      return $item;
    }, $items);

    return $items;
  }

  /** Simpan upload sebagai berkas baru di folder riwayat, kembalikan path relatifnya. */
  private function simpanKeRiwayat(string $jenis, UploadedFile $file): string
  {
    $relatif = self::DIREKTORI_RIWAYAT[$jenis];
    $directory = $this->absolut($relatif);

    File::ensureDirectoryExists($directory, 0755, true);

    $nama = sprintf('%s-%s-%s.png', $jenis, date('Ymd-His'), bin2hex(random_bytes(3)));
    $target = $directory . DIRECTORY_SEPARATOR . $nama;

    if (file_put_contents($target, $file->get()) === false) {
      throw new \RuntimeException(sprintf(
        'Tidak dapat menulis ke %s. Periksa permission folder.',
        $target,
      ));
    }

    @chmod($target, 0644);

    return $relatif . '/' . $nama;
  }

  /** Jaga jumlah berkas riwayat tetap wajar — buang yang paling tua (bukan yang aktif). */
  private function pangkasRiwayat(string $jenis): void
  {
    $relatif = self::DIREKTORI_RIWAYAT[$jenis] ?? null;
    if ($relatif === null) {
      return;
    }

    $folder = $this->absolut($relatif);
    if (! is_dir($folder)) {
      return;
    }

    $aktif = $this->aktifPath($jenis);
    $berkas = collect(File::files($folder))
      ->filter(static fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'png')
      ->sortByDesc(static fn (\SplFileInfo $file): int => $file->getMTime())
      ->values();

    $berkas->slice(self::MAX_RIWAYAT)->each(function (\SplFileInfo $file) use ($relatif, $aktif): void {
      $path = $relatif . '/' . $file->getFilename();
      if ($path !== $aktif) {
        File::delete($file->getPathname());
      }
    });
  }

  private function bolehDihapus(string $path): bool
  {
    if (! preg_match('#^branding/library/(logo|favicon)/[A-Za-z0-9._-]+\.png$#', $path)) {
      return false;
    }

    foreach (['logo', 'favicon'] as $jenis) {
      if ($path === $this->aktifPath($jenis)) {
        return false;
      }
    }

    return true;
  }

  /** Pastikan path relatif tetap berada di dalam folder branding (anti path traversal). */
  private function pathRelatifAman(string $path): string
  {
    $bersih = str_replace('\\', '/', trim($path));
    $bersih = ltrim($bersih, '/');

    if (str_contains($bersih, '..') || ! preg_match('#^branding/[A-Za-z0-9._/-]+\.png$#', $bersih)) {
      throw new \RuntimeException('Path gambar tidak valid.');
    }

    return $bersih;
  }

  private function absolut(string $relatif): string
  {
    return $this->publicDir() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relatif);
  }

  private function berkasAda(string $relatif): bool
  {
    return File::exists($this->absolut($relatif));
  }

  private function ukuranHuman(int $bytes): string
  {
    if ($bytes >= 1048576) {
      return number_format($bytes / 1048576, 2) . ' MB';
    }

    if ($bytes >= 1024) {
      return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
  }

  private function resolvedUrl(string $relativePath): string
  {
    $absolutePath = $this->absolut($relativePath);

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
