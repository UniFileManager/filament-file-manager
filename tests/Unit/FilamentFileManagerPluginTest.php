<?php

declare(strict_types=1);

use Filament\Panel;
use UniFileManager\FilamentFileManager\Filament\Pages\FileManager;
use UniFileManager\FilamentFileManager\FilamentFileManagerPlugin;

beforeEach(function (): void {
    FileManager::configureNavigation();
    CustomFileManagerPage::configureNavigation();
});

it('registers the default File Manager page', function (): void {
    $panel = Panel::make()->id('file-manager-plugin-default');

    FilamentFileManagerPlugin::make()->register($panel);

    expect($panel->getPages())->toContain(FileManager::class);
});

it('registers a custom File Manager page', function (): void {
    $panel = Panel::make()->id('file-manager-plugin-custom');

    FilamentFileManagerPlugin::make()
        ->page(CustomFileManagerPage::class)
        ->register($panel);

    expect($panel->getPages())->toContain(CustomFileManagerPage::class)
        ->and($panel->getPages())->not->toContain(FileManager::class);
});

it('configures File Manager navigation options from the plugin', function (): void {
    $panel = Panel::make()->id('file-manager-plugin-navigation');

    FilamentFileManagerPlugin::make()
        ->navigationLabel('Assets')
        ->navigationGroup('Content')
        ->navigationSort(25)
        ->navigationIcon('heroicon-o-folder')
        ->shouldRegisterNavigation(false)
        ->register($panel);

    expect(FileManager::getNavigationLabel())->toBe('Assets')
        ->and(FileManager::getNavigationGroup())->toBe('Content')
        ->and(FileManager::getNavigationSort())->toBe(25)
        ->and(FileManager::getNavigationIcon())->toBe('heroicon-o-folder')
        ->and(FileManager::shouldRegisterNavigation())->toBeFalse();
});

it('rejects page classes that do not extend the File Manager page', function (): void {
    FilamentFileManagerPlugin::make()->page(NotAFileManagerPage::class);
})->throws(InvalidArgumentException::class);

final class CustomFileManagerPage extends FileManager
{
}

final class NotAFileManagerPage
{
}
