<?php

declare(strict_types=1);

use UniFileManager\Core\Support\DefaultFileManagerAuthorizer;
use UniFileManager\Core\Support\ConfigStorageAreaResolver;

return [
    /* @deprecated Use storage_areas.private.disk. */
    'disk' => env('FILAMENT_FILE_MANAGER_DISK', 'local'),

    /* @deprecated Use storage_areas.private.root. */
    'root' => env('FILAMENT_FILE_MANAGER_ROOT', 'file-manager'),

    /* @deprecated Use storage_areas.private.visibility. */
    'visibility' => 'private',

    /*
     * Storage areas are defined on the server. The browser can select an area
     * by key, but it can never provide a disk, root, or visibility value.
     * Keep public media disabled until you have configured a public disk or CDN.
     */
    'storage_areas' => [
        'private' => [
            'enabled' => true,
            'disk' => env('FILAMENT_FILE_MANAGER_DISK', 'local'),
            'root' => env('FILAMENT_FILE_MANAGER_ROOT', 'file-manager'),
            'visibility' => 'private',
        ],
        'public' => [
            'enabled' => false,
            'disk' => env('FILAMENT_FILE_MANAGER_PUBLIC_DISK', 'public'),
            'root' => env('FILAMENT_FILE_MANAGER_PUBLIC_ROOT', 'file-manager-public'),
            'visibility' => 'public',
        ],
    ],

    /*
     * Resolve storage areas from trusted, server-side request context. Leave the
     * default resolver in place for standard single-tenant applications.
     */
    'storage_area_resolver' => ConfigStorageAreaResolver::class,

    /* Used by UniFilePicker when more than one area is enabled. Private is the safer default. */
    'file_picker_default_area' => 'private',

    'max_upload_size' => 10 * 1024, // KiB

    'max_upload_files' => 10,

    /* Enable the embedded File Picker library. Fields can override this with ->library(false). */
    'file_picker_library' => true,

    /* Legacy File Picker manager URL metadata. Set this only for integrations that need it. */
    'file_picker_manager_url' => null,

    'folders_per_page' => 10,

    'files_per_page' => 10,

    'items_per_page' => 20,

    /* Maximum nested folder levels below the configured File Manager root. */
    'max_directory_depth' => 7,

    /* Show the optional "Folders first" layout choice. When disabled, all items stay together. */
    'show_item_layout' => false,

    'allowed_mimes' => [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
        'text/plain', 'text/csv', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ],

    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'],

    'preview_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf', 'text/plain'],

    /* Maximum authorised preview and thumbnail requests per minute, per user or guest IP address. */
    'preview_rate_limit' => 60,

    'thumbnails' => [
        'enabled' => true,
        'directory' => '.thumbnails',
        'max_dimension' => 360,
        /* Skip synchronous thumbnail generation for larger source images. */
        'max_source_pixels' => 8_000_000,
    ],

    /* Replace this with an application-specific authorizer before exposing the page. */
    'authorizer' => DefaultFileManagerAuthorizer::class,
];
