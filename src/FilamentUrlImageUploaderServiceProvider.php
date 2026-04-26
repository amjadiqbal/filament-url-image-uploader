<?php

namespace AmjadIqbal\FilamentUrlImageUploader;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentUrlImageUploaderServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-url-image-uploader')
            ->hasViews();
    }
}
