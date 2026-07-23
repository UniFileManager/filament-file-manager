# Multi-tenancy

UniFileManager does not infer a tenant from the authenticated user. In a
multi-tenant application, use a request-aware storage-area resolver and replace
the default authorizer.

Do not rely on a generic `manageFileManager` ability alone: it grants access to the entire configured root.

## Recommended: a tenant-aware storage-area resolver

The resolver receives no browser input. It obtains the tenant from your trusted
server-side tenancy context and returns only that tenant's storage areas. The
package can then continue to use relative paths, such as `avatars/jane.jpg`,
without exposing another tenant's files.

```php
namespace App\Support;

use UniFileManager\FilamentFileManager\Contracts\StorageAreaResolver;

final class TenantStorageAreaResolver implements StorageAreaResolver
{
    public function areas(): array
    {
        $tenantId = tenant()->getKey();

        return [
            'private' => [
                'enabled' => true,
                'disk' => 'local',
                'root' => "tenants/{$tenantId}/file-manager",
                'visibility' => 'private',
            ],
        ];
    }

    public function resolve(string $area): ?array
    {
        $configuration = $this->areas()[$area] ?? null;

        return is_array($configuration) ? $configuration : null;
    }
}
```

Register it in `config/filament-file-manager.php`:

```php
'storage_area_resolver' => App\Support\TenantStorageAreaResolver::class,
```

This is safe for Laravel Octane as long as your tenancy context is correctly
reset between requests. Do not cache a tenant identifier inside the resolver.

## One disk per tenant

The example above uses a shared private disk with a tenant-specific root. You
may instead return a tenant-specific disk name when your tenancy system creates
one disk per tenant.

```php
'private' => [
    'enabled' => true,
    'disk' => 'tenant-'.tenant()->getKey(),
    'root' => 'file-manager',
    'visibility' => 'private',
],
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
