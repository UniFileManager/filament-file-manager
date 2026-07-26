<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager;

use BladeUI\Icons\Factory as IconFactory;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use UniFileManager\Core\Contracts\FileManagerAuthorizer;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\Core\Services\FileManager;
use UniFileManager\Core\Services\ImageThumbnailer;
use UniFileManager\Core\Support\ConfigStorageAreaResolver;
use UniFileManager\Core\Support\DefaultFileManagerAuthorizer;
use UniFileManager\FilamentFileManager\Livewire\FilePickerExplorer;
use UniFileManager\FilamentFileManager\Livewire\UniFilePickerUploader;

final class FilamentFileManagerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->ensureCorePackageIsAvailable();
        $this->registerCompatibilityAliases();

        $this->mergeConfigFrom(__DIR__.'/../config/filament-file-manager.php', 'filament-file-manager');
        $this->syncCoreConfig();

        $this->app->bind(
            FileManagerAuthorizer::class,
            function (): FileManagerAuthorizer {
                $authorizer = $this->resolveConfigClass(
                    config('filament-file-manager.authorizer'),
                    DefaultFileManagerAuthorizer::class,
                );

                return $authorizer === DefaultFileManagerAuthorizer::class
                    ? new DefaultFileManagerAuthorizer()
                    : $this->app->make($authorizer);
            },
        );

        $this->app->bind(
            StorageAreaResolver::class,
            function (): StorageAreaResolver {
                $this->syncCoreConfig();

                $resolver = $this->resolveConfigClass(
                    config('filament-file-manager.storage_area_resolver'),
                    ConfigStorageAreaResolver::class,
                );

                return $resolver === ConfigStorageAreaResolver::class
                    ? new ConfigStorageAreaResolver()
                    : $this->app->make($resolver);
            },
        );

        $this->app->singleton(FileManager::class, function (): FileManager {
            $this->syncCoreConfig();

            return new FileManager(
                $this->app->make(FileManagerAuthorizer::class),
                $this->app->make(ImageThumbnailer::class),
                $this->app->make(StorageAreaResolver::class),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->resolved(IconFactory::class)) {
            $this->registerIcons($this->app->make(IconFactory::class));
        } else {
            $this->callAfterResolving(IconFactory::class, function (IconFactory $icons): void {
                $this->registerIcons($icons);
            });
        }

        RateLimiter::for('filament-file-manager-previews', static function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(max(1, (int) config('filament-file-manager.preview_rate_limit', 60)))
                ->by('filament-file-manager:preview:'.$key);
        });

        $this->publishes([
            __DIR__.'/../config/filament-file-manager.php' => config_path('filament-file-manager.php'),
        ], 'filament-file-manager-config');

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'filament-file-manager');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'filament-file-manager');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/filament-file-manager'),
        ], 'filament-file-manager-translations');

        Livewire::component('unifile-manager.file-picker-explorer', FilePickerExplorer::class);
        Livewire::component('unifile-manager.uni-file-picker-uploader', UniFilePickerUploader::class);

        FilamentAsset::register([
            Css::make('file-manager', __DIR__.'/../resources/css/file-manager.css'),
        ], package: 'unifilemanager/filament-file-manager');
    }

    private function registerIcons(IconFactory $icons): void
    {
        $icons->add('unifile-manager', [
            'path' => __DIR__.'/../resources/svg',
            'prefix' => 'ufm',
        ]);
    }

    private function ensureCorePackageIsAvailable(): void
    {
        if (class_exists(FileManager::class)
            && class_exists(ConfigStorageAreaResolver::class)
            && class_exists(DefaultFileManagerAuthorizer::class)) {
            return;
        }

        throw new \LogicException(
            'UniFileManager for Filament requires the unifilemanager/core package. Run "composer require unifilemanager/core" or update this package with Composer.',
        );
    }

    private function syncCoreConfig(): void
    {
        config([
            'unifilemanager' => array_replace_recursive(
                config('unifilemanager', []),
                config('filament-file-manager', []),
                [
                    'authorizer' => $this->resolveConfigClass(
                        config('filament-file-manager.authorizer'),
                        DefaultFileManagerAuthorizer::class,
                    ),
                    'storage_area_resolver' => $this->resolveConfigClass(
                        config('filament-file-manager.storage_area_resolver'),
                        ConfigStorageAreaResolver::class,
                    ),
                ],
            ),
        ]);
    }

    private function resolveConfigClass(mixed $configuredClass, string $defaultClass): string
    {
        if (! is_string($configuredClass) || $configuredClass === '') {
            return $defaultClass;
        }

        return match ($configuredClass) {
            'UniFileManager\\FilamentFileManager\\Support\\DefaultFileManagerAuthorizer' => DefaultFileManagerAuthorizer::class,
            'UniFileManager\\FilamentFileManager\\Support\\ConfigStorageAreaResolver' => ConfigStorageAreaResolver::class,
            default => $configuredClass,
        };
    }

    private function registerCompatibilityAliases(): void
    {
        foreach ($this->compatibilityAliases() as $legacyClass => $coreClass) {
            if (! class_exists($legacyClass, false) && class_exists($coreClass)) {
                class_alias($coreClass, $legacyClass);
            }
        }
    }

    /** @return array<class-string, class-string> */
    private function compatibilityAliases(): array
    {
        return [
            'UniFileManager\\FilamentFileManager\\Exceptions\\FolderNotEmpty' => 'UniFileManager\\Core\\Exceptions\\FolderNotEmpty',
            'UniFileManager\\FilamentFileManager\\Exceptions\\InvalidFilePath' => 'UniFileManager\\Core\\Exceptions\\InvalidFilePath',
            'UniFileManager\\FilamentFileManager\\Exceptions\\UnsafeDiskConfiguration' => 'UniFileManager\\Core\\Exceptions\\UnsafeDiskConfiguration',
            'UniFileManager\\FilamentFileManager\\Services\\FileManager' => 'UniFileManager\\Core\\Services\\FileManager',
            'UniFileManager\\FilamentFileManager\\Services\\ImageThumbnailer' => 'UniFileManager\\Core\\Services\\ImageThumbnailer',
            'UniFileManager\\FilamentFileManager\\Support\\ConfigStorageAreaResolver' => 'UniFileManager\\Core\\Support\\ConfigStorageAreaResolver',
            'UniFileManager\\FilamentFileManager\\Support\\DefaultFileManagerAuthorizer' => 'UniFileManager\\Core\\Support\\DefaultFileManagerAuthorizer',
            'UniFileManager\\FilamentFileManager\\Support\\DirectoryScope' => 'UniFileManager\\Core\\Support\\DirectoryScope',
            'UniFileManager\\FilamentFileManager\\Support\\MimeTypeMatcher' => 'UniFileManager\\Core\\Support\\MimeTypeMatcher',
        ];
    }
}
