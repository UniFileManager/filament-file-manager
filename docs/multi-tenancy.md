# Multi-tenancy

UniFileManager deliberately uses one configured storage root. It does not infer
a tenant from the authenticated user. In a multi-tenant application, configure
the disk and root in your tenancy bootstrap and replace the default authorizer.

Do not rely on a generic `manageFileManager` ability alone: it grants access to the entire configured root.

## Recommended: one private disk root per tenant

Set a tenant-specific private disk root before Filament handles the request. The
package can then continue to use the same relative paths, such as
`avatars/jane.jpg`, without exposing another tenant's files.

```php
// Run from your tenancy middleware / tenancy bootstrapper.
config()->set('filesystems.disks.tenant-files', [
    'driver' => 'local',
    'root' => storage_path('app/tenants/'.tenant()->getKey().'/files'),
    'visibility' => 'private',
]);

config()->set('filament-file-manager.disk', 'tenant-files');
config()->set('filament-file-manager.root', 'file-manager');
```

Use your tenancy package's request-scoped configuration facility. With Laravel
Octane, do not mutate global configuration unless it is reset after every request.

## Shared disk alternative

If all tenants must share one private disk, set a tenant-specific package root in your tenancy bootstrapper:

```php
config()->set('filament-file-manager.disk', 'local');
config()->set('filament-file-manager.root', 'tenants/'.tenant()->getKey().'/file-manager');
```

The tenant identifier must come from trusted, server-side tenancy context—not a
browser-supplied path, query parameter, or form value.

## Authorizer

Replace the default authorizer with one that verifies tenant membership as well as the File Manager ability:

```php
namespace App\Support;

use UniFileManager\FilamentFileManager\Contracts\FileManagerAuthorizer;

final class TenantFileManagerAuthorizer implements FileManagerAuthorizer
{
    public function can(mixed $user, string $operation, string $path = ''): bool
    {
        return $user !== null
            && $user->tenant_id === tenant()->getKey()
            && $user->can('manageFileManager');
    }
}
```

Register it in the package configuration:

```php
'authorizer' => App\Support\TenantFileManagerAuthorizer::class,
```

The `$path` argument is relative to the configured File Manager root. Use it for
additional folder-level permissions when needed, but not as the only tenant boundary.

## File Picker directories

`UniFilePicker::directory()` is also relative to the active File Manager root. In a tenant-scoped setup, use a local path such as `avatars`:

```php
UniFilePicker::make('avatar')
    ->directory('avatars')
    ->allowedMimeTypes(['image/*']);
```

Do not include another tenant's identifier in a picker directory. Tenant isolation belongs in the trusted disk/root configuration and authorizer.

## Required tests in the consuming application

Test at least the following with two tenants and two users:

1. A user can list, upload, preview, download, rename, and delete files for their own tenant.
2. The same user cannot access a known path from a different tenant.
3. A user without tenant membership or `manageFileManager` receives `403`.
4. A File Picker restricted to `avatars` cannot navigate outside that directory.
