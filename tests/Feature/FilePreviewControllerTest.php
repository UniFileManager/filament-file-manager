<?php

declare(strict_types=1);

use Filament\Models\Contracts\HasTenants;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use UniFileManager\Core\Contracts\StorageAreaResolver;
use UniFileManager\Core\Services\FileManager;

beforeEach(function (): void {
    Storage::fake('testing');
    Filament::setCurrentPanel(null);
    Filament::setTenant(null, true);
});

it('restores Filament tenant context before streaming a preview', function (): void {
    Storage::disk('testing')->put('tenant-a/report.txt', 'tenant a file');
    Storage::disk('testing')->put('tenant-b/report.txt', 'tenant b file');

    registerPreviewTenantPanel();
    bindPreviewTenantStorageResolver();

    $this->actingAs(new FilePreviewControllerTestUser('b'));

    $response = $this->get(route('filament-file-manager.preview', [
        'path' => 'report.txt',
        'area' => 'private',
        'panel' => 'tenant-preview',
        'tenant' => 'b',
    ]));

    $response->assertOk();

    expect($response->streamedContent())->toBe('tenant b file');
});

it('does not stream a tenant preview when the authenticated user cannot access the tenant', function (): void {
    Storage::disk('testing')->put('tenant-b/report.txt', 'tenant b file');

    registerPreviewTenantPanel();
    bindPreviewTenantStorageResolver();

    $this->actingAs(new FilePreviewControllerTestUser('a'));

    $this->get(route('filament-file-manager.preview', [
        'path' => 'report.txt',
        'area' => 'private',
        'panel' => 'tenant-preview',
        'tenant' => 'b',
    ]))->assertNotFound();
});

function registerPreviewTenantPanel(): void
{
    app(PanelRegistry::class)->register(
        Panel::make()
            ->id('tenant-preview')
            ->tenant(FilePreviewControllerTestTenant::class)
            ->resolveTenantUsing(
                static fn (string $key): FilePreviewControllerTestTenant => new FilePreviewControllerTestTenant([
                    'id' => $key,
                ]),
            ),
    );
}

function bindPreviewTenantStorageResolver(): void
{
    app()->forgetInstance(FileManager::class);
    app()->bind(StorageAreaResolver::class, static fn (): StorageAreaResolver => new class () implements StorageAreaResolver {
        public function areas(): array
        {
            $tenantId = Filament::getTenant()?->getKey();

            if ($tenantId === null) {
                return [];
            }

            return [
                'private' => [
                    'enabled' => true,
                    'disk' => 'testing',
                    'root' => "tenant-{$tenantId}",
                    'visibility' => 'private',
                ],
            ];
        }

        public function resolve(string $area): ?array
        {
            $configuration = $this->areas()[$area] ?? null;

            return is_array($configuration) ? $configuration : null;
        }
    });
}

final class FilePreviewControllerTestTenant extends Model
{
    protected $guarded = [];

    protected $keyType = 'string';

    public $incrementing = false;
}

final class FilePreviewControllerTestUser extends GenericUser implements HasTenants
{
    public function __construct(private readonly string $tenantId)
    {
        parent::__construct([
            'id' => 1,
        ]);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant->getKey() === $this->tenantId;
    }

    public function getTenants(Panel $panel): array | Collection
    {
        return [
            new FilePreviewControllerTestTenant([
                'id' => $this->tenantId,
            ]),
        ];
    }
}
