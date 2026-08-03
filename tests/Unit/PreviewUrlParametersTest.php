<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use UniFileManager\FilamentFileManager\Support\PreviewUrlParameters;

beforeEach(function (): void {
    Filament::setCurrentPanel(null);
    Filament::setTenant(null, true);
});

it('generates preview parameters without tenant context', function (): void {
    expect(PreviewUrlParameters::make('documents/report.pdf', 'private'))->toBe([
        'path' => 'documents/report.pdf',
        'area' => 'private',
    ]);
});

it('generates thumbnail parameters without tenant context', function (): void {
    expect(PreviewUrlParameters::make('images/avatar.jpg', 'private', true))->toBe([
        'path' => 'images/avatar.jpg',
        'area' => 'private',
        'thumbnail' => 1,
    ]);
});

it('includes Filament tenant context when generating preview parameters inside a tenant panel', function (): void {
    $panel = Panel::make()
        ->id('admin')
        ->tenant(PreviewUrlParametersTestTenant::class, slugAttribute: 'slug');

    $tenant = new PreviewUrlParametersTestTenant([
        'id' => 10,
        'slug' => 'acme-ltd',
    ]);

    Filament::setCurrentPanel($panel);
    Filament::setTenant($tenant, true);

    expect(PreviewUrlParameters::make('documents/report.pdf', 'private', true))->toBe([
        'path' => 'documents/report.pdf',
        'area' => 'private',
        'thumbnail' => 1,
        'panel' => 'admin',
        'tenant' => 'acme-ltd',
    ]);
});

final class PreviewUrlParametersTestTenant extends Model
{
    protected $guarded = [];
}
