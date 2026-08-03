<?php

declare(strict_types=1);

namespace UniFileManager\FilamentFileManager\Http\Controllers;

use Filament\Facades\Filament;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UniFileManager\Core\Services\FileManager;

final class FilePreviewController
{
    public function __invoke(Request $request, FileManager $fileManager): StreamedResponse
    {
        $this->restoreFilamentTenantContext($request);

        $path = (string) $request->query('path', '');
        $fileManager = $fileManager->forArea((string) $request->query('area', 'private'));

        return $request->boolean('thumbnail')
            ? $fileManager->thumbnail($request->user(), $path)
            : $fileManager->preview($request->user(), $path);
    }

    private function restoreFilamentTenantContext(Request $request): void
    {
        $tenantKey = $request->query('tenant');

        if (! is_string($tenantKey) || $tenantKey === '') {
            return;
        }

        $panelId = $request->query('panel');
        $panel = is_string($panelId) && $panelId !== ''
            ? Filament::getPanel($panelId, false)
            : Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasTenancy()) {
            abort(404);
        }

        Filament::setCurrentPanel($panel);

        $tenant = $panel->getTenant($tenantKey);
        $user = $request->user();

        if (! $user instanceof HasTenants || ! $user->canAccessTenant($tenant)) {
            abort(404);
        }

        Filament::setTenant($tenant);
    }
}
