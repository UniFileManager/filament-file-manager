<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Support;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

final class PreviewUrlParameters
{
    /** @return array<string, mixed> */
    public static function make(string $path, string $area, bool $thumbnail = false): array
    {
        $parameters = [
            'path' => $path,
            'area' => $area,
        ];

        if ($thumbnail) {
            $parameters['thumbnail'] = 1;
        }

        $panel = Filament::getCurrentPanel();
        $tenant = Filament::getTenant();

        if ($panel === null || ! $panel->hasTenancy() || ! $tenant instanceof Model) {
            return $parameters;
        }

        $parameters['panel'] = $panel->getId();
        $parameters['tenant'] = self::tenantRouteKey($tenant, $panel->getTenantSlugAttribute());

        return $parameters;
    }

    private static function tenantRouteKey(Model $tenant, ?string $slugAttribute): string
    {
        if ($slugAttribute !== null && $slugAttribute !== '') {
            return (string) $tenant->getAttributeValue($slugAttribute);
        }

        return (string) $tenant->getRouteKey();
    }
}
