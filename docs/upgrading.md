# Upgrading

## Upgrade to the storage-area configuration

Older package configuration used one private `disk`, `root`, and `visibility`
setting. Those values continue to work as the legacy private area.

Publish the current configuration when you are ready to manage both private files and public website media:

```bash
php artisan vendor:publish --tag=filament-file-manager-config
```

Move any local configuration changes into `storage_areas.private` before replacing the old values.

Enable public media only when a dedicated public disk or CDN is configured:

```php
'storage_areas' => [
    'public' => [
        'enabled' => true,
        'disk' => 'public',
        'root' => 'file-manager/public',
        'visibility' => 'public',
    ],
],
```

Keep sensitive documents in `storage_areas.private`. Public media URLs are accessible without File Manager authorization.

When both areas are enabled, existing `UniFilePicker` fields default to `file_picker_default_area` (`private` by default). Add `->publicMedia()` to public course, blog, and marketing-image fields.

After upgrading package assets:

```bash
php artisan filament:assets
php artisan optimize:clear
```
