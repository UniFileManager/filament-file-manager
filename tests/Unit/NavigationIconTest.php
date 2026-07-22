<?php

declare(strict_types=1);

use BladeUI\Icons\Factory as IconFactory;

it('registers the File Manager navigation icon', function (): void {
    $icon = app(IconFactory::class)->svg('ufm-file-manager')->toHtml();

    expect($icon)
        ->toContain('<svg')
        ->toContain('viewBox="0 0 24 24"');
});
