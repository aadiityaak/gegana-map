<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class AiSettings
{
    public function shared(): array
    {
        return $this->read();
    }

    public function update(string $endpoint, string $apiKey, string $model): void
    {
        $settings = $this->read();
        $settings['endpoint'] = $endpoint;
        $settings['api_key'] = $apiKey;
        $settings['model'] = $model;
        $this->write($settings);
    }

    private function read(): array
    {
        $path = $this->settingsPath();
        if (!File::exists($path)) {
            return [
                'endpoint' => '',
                'api_key' => '',
                'model' => '',
            ];
        }
        $decoded = json_decode((string) File::get($path), true);
        return is_array($decoded) ? $decoded : [
            'endpoint' => '',
            'api_key' => '',
            'model' => '',
        ];
    }

    private function write(array $settings): void
    {
        $path = $this->settingsPath();
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function settingsPath(): string
    {
        return storage_path('app/ai/settings.json');
    }
}
