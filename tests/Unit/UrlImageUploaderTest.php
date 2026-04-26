<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Unit;

use AmjadIqbal\FilamentUrlImageUploader\Components\UrlImageUploader;
use AmjadIqbal\FilamentUrlImageUploader\Tests\TestCase;

class UrlImageUploaderTest extends TestCase
{
    public function test_component_can_be_instantiated(): void
    {
        $component = UrlImageUploader::make('image');

        $this->assertInstanceOf(UrlImageUploader::class, $component);
    }

    public function test_default_directory_is_images(): void
    {
        $component = UrlImageUploader::make('image');

        $this->assertSame('images', $component->getDirectory());
    }

    public function test_directory_can_be_customized(): void
    {
        $component = UrlImageUploader::make('photo')
            ->directory('avatars');

        $this->assertSame('avatars', $component->getDirectory());
    }

    public function test_component_is_not_dehydrated_by_default(): void
    {
        $component = UrlImageUploader::make('image');

        // The outer field must not dehydrate – the inner FileUpload handles it.
        $this->assertFalse($component->isDehydrated());
    }

    public function test_service_provider_registers_views(): void
    {
        $this->assertNotNull(
            view()->exists('filament-url-image-uploader::components.url-image-uploader')
        );
    }
}
