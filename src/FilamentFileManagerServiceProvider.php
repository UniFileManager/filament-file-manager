<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use UniFileManager\FilamentFileManager\Contracts\FileManagerAuthorizer;
use UniFileManager\FilamentFileManager\Livewire\FilePickerExplorer;
use UniFileManager\FilamentFileManager\Livewire\UniFilePickerUploader;
use UniFileManager\FilamentFileManager\Services\FileManager;

final class FilamentFileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/filament-file-manager.php', 'filament-file-manager');

        $this->app->bind(FileManagerAuthorizer::class, config('filament-file-manager.authorizer'));
        $this->app->singleton(FileManager::class);
    }

    public function boot(): void
    {
        RateLimiter::for('filament-file-manager-previews', static function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(max(1, (int) config('filament-file-manager.preview_rate_limit', 60)))
                ->by('filament-file-manager:preview:'.$key);
        });

        $this->publishes([
            __DIR__.'/../config/filament-file-manager.php' => config_path('filament-file-manager.php'),
        ], 'filament-file-manager-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-file-manager');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Livewire::component('unifile-manager.file-picker-explorer', FilePickerExplorer::class);
        Livewire::component('unifile-manager.uni-file-picker-uploader', UniFilePickerUploader::class);

        FilamentAsset::register([
            Css::make('file-manager', __DIR__.'/../resources/css/file-manager.css'),
        ], package: 'unifilemanager/filament-file-manager');
    }
}
