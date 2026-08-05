<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use UnitEnum;
use UniFileManager\FilamentFileManager\Filament\Pages\FileManager;

class FilamentFileManagerPlugin implements Plugin
{
    /** @var class-string<FileManager> */
    protected string $page = FileManager::class;

    protected string | Closure | null $navigationLabel = null;

    protected string | UnitEnum | Closure | null $navigationGroup = null;

    protected string | BackedEnum | Closure | null $navigationIcon = null;

    protected int | Closure | null $navigationSort = null;

    protected bool | Closure | null $shouldRegisterNavigation = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'filament-file-manager';
    }

    public function register(Panel $panel): void
    {
        $this->page::configureNavigation(
            label: $this->navigationLabel,
            group: $this->navigationGroup,
            icon: $this->navigationIcon,
            sort: $this->navigationSort,
            shouldRegister: $this->shouldRegisterNavigation,
        );

        $panel->pages([$this->page]);
    }

    public function boot(Panel $panel): void
    {
    }

    /** @param class-string<FileManager> $page */
    public function page(string $page): static
    {
        if (! is_a($page, FileManager::class, true)) {
            throw new \InvalidArgumentException(sprintf(
                'File Manager page [%s] must extend [%s].',
                $page,
                FileManager::class,
            ));
        }

        $this->page = $page;

        return $this;
    }

    public function navigationLabel(string | Closure | null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function navigationGroup(string | UnitEnum | Closure | null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function navigationIcon(string | BackedEnum | Closure | null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function navigationSort(int | Closure | null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function shouldRegisterNavigation(bool | Closure $condition = true): static
    {
        $this->shouldRegisterNavigation = $condition;

        return $this;
    }
}
