# S3-Compatible Storage

UniFileManager works with Laravel filesystem disks backed by Amazon S3,
Cloudflare R2, MinIO, or another S3-compatible service. The package still keeps
the same safety model: the browser selects only a named storage area, while your
application controls the disk, root prefix, visibility, credentials, endpoint,
and bucket.

## Install the Laravel S3 driver

Laravel's S3 disk uses Flysystem's AWS S3 adapter:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

## Private S3 area

Use private visibility for application documents, invoices, imports, and other
files that should always stream through authenticated File Manager routes:

```php
// config/filesystems.php
'disks' => [
    'unifilemanager_s3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        'throw' => true,
    ],
],
```

```php
// config/filament-file-manager.php
'storage_areas' => [
    'private' => [
        'enabled' => true,
        'disk' => 'unifilemanager_s3',
        'root' => 'file-manager/private',
        'visibility' => 'private',
    ],
],
```

Files are listed, uploaded, moved, renamed, deleted, previewed, and downloaded
below the configured root prefix. Stored picker values remain relative to that
root, for example `contracts/terms.pdf`.

## Public S3 or CDN media

Use a separate public area only for files that may be served directly by the
bucket or CDN:

```php
'storage_areas' => [
    'public' => [
        'enabled' => true,
        'disk' => 'unifilemanager_public_s3',
        'root' => 'file-manager/public',
        'visibility' => 'public',
    ],
],
```

Set the disk `url` to your public bucket URL or CDN origin so Laravel can build
asset URLs:

```env
AWS_URL=https://cdn.example.com
```

File Manager access still requires authorization. Public URLs are shown only for
public storage areas.

## Cloudflare R2 example

R2 uses S3-compatible credentials and a custom endpoint:

```env
AWS_ACCESS_KEY_ID=your-r2-access-key
AWS_SECRET_ACCESS_KEY=your-r2-secret-key
AWS_DEFAULT_REGION=auto
AWS_BUCKET=your-bucket
AWS_ENDPOINT=https://account-id.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_URL=https://files.example.com
```

Use `visibility => 'private'` unless the bucket or custom domain is intended for
public media.

## MinIO example

For local development or self-hosted MinIO, enable path-style endpoints:

```env
AWS_ACCESS_KEY_ID=minio
AWS_SECRET_ACCESS_KEY=password
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=unifilemanager
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
AWS_URL=http://127.0.0.1:9000/unifilemanager
```

In production, use HTTPS and credentials with the narrowest bucket/prefix
permissions your application needs.

## Laravel Vapor and Livewire temporary uploads

When Livewire temporary uploads use an S3 disk, Livewire only supports one
browser file per upload request. UniFileManager detects that configuration and
removes the `multiple` attribute from upload inputs so Vapor/S3 uploads can
complete normally.

Multiple File Manager or UniFilePicker selections still work. Users can upload
one file at a time and build up a multi-file selection, or choose multiple
existing files from the library.

## Multi-tenant S3 prefixes

Keep tenant isolation inside a trusted `StorageAreaResolver`:

```php
'private' => [
    'enabled' => true,
    'disk' => 'unifilemanager_s3',
    'root' => "tenants/{$tenantId}/file-manager",
    'visibility' => 'private',
],
```

The tenant id must come from server-side tenancy context. Do not include tenant
ids in browser-supplied paths, picker directories, or request parameters.

## Production checklist

- Use private visibility for sensitive files.
- Keep private buckets blocked from public access.
- Use a dedicated public bucket, prefix, or CDN for public media.
- Set `throw => true` on the Laravel disk so storage failures are visible.
- Restrict IAM or access keys to the required bucket and prefix.
- Add application-level malware scanning before exposing uploads to other users.
- Test uploads, previews, downloads, moves, renames, deletes, and public URLs
  against the same S3-compatible provider you use in production.

The core package includes S3-compatible integration tests using a custom Laravel
filesystem driver that exercises non-local disk configuration, root scoping,
upload visibility, and public URL generation without requiring cloud
credentials.
