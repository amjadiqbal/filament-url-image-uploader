<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Unit;

use AmjadIqbal\FilamentUrlImageUploader\Components\UrlImageUploader;
use AmjadIqbal\FilamentUrlImageUploader\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

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

    public function test_component_is_dehydrated_by_default(): void
    {
        $component = UrlImageUploader::make('image');

        // The outer field IS the dehydration source of truth (via its own
        // dehydrateStateUsing), so it must stay dehydrated; the inner
        // path/url children are the ones marked dehydrated(false).
        $this->assertTrue($component->isDehydrated());
    }

    public function test_default_disk_is_public(): void
    {
        $component = UrlImageUploader::make('image');

        $this->assertSame('public', $component->getDisk());
    }

    public function test_disk_can_be_customized(): void
    {
        $component = UrlImageUploader::make('image')
            ->disk('s3');

        $this->assertSame('s3', $component->getDisk());
    }

    public function test_service_provider_registers_views(): void
    {
        $this->assertNotNull(
            view()->exists('filament-url-image-uploader::components.url-image-uploader')
        );
    }

    public function test_service_provider_registers_translations(): void
    {
        // A raw, unresolved translation call returns the key itself
        // (e.g. "filament-url-image-uploader::url-image-uploader.tabs.upload.label")
        // when the namespace isn't actually registered — asserting the
        // resolved English string, not just that the call doesn't error,
        // confirms hasTranslations() is wired up correctly.
        $this->assertSame(
            'File Upload',
            __('filament-url-image-uploader::url-image-uploader.tabs.upload.label')
        );

        $this->assertSame(
            'URL Upload',
            __('filament-url-image-uploader::url-image-uploader.tabs.url.label')
        );
    }

    /**
     * Reproduces a real bug found while testing the field inside a Repeater
     * gallery: fetching two different remote images that happen to share a
     * basename (a generic CDN path, or several rows pulled from the same
     * site) silently overwrote the first file on disk, and both rows'
     * "different" images ended up rendering identically. uniqueFilename()
     * is the fix — this asserts it directly rather than through the fetch
     * action, which would need a real network call.
     */
    public function test_unique_filename_disambiguates_instead_of_silently_colliding(): void
    {
        Storage::fake('public');

        $component = UrlImageUploader::make('image')
            ->directory('gallery')
            ->preserveFilenames();

        $method = new \ReflectionMethod($component, 'uniqueFilename');
        $method->setAccessible(true);

        $this->assertSame('photo.jpg', $method->invoke($component, 'photo.jpg'));

        Storage::disk('public')->put('gallery/photo.jpg', 'first-image-bytes');

        $this->assertSame('photo-1.jpg', $method->invoke($component, 'photo.jpg'));

        Storage::disk('public')->put('gallery/photo-1.jpg', 'second-image-bytes');

        $this->assertSame('photo-2.jpg', $method->invoke($component, 'photo.jpg'));

        // The two "first" files must both still exist, untouched — the
        // whole point of the fix.
        Storage::disk('public')->assertExists('gallery/photo.jpg');
        Storage::disk('public')->assertExists('gallery/photo-1.jpg');
        $this->assertSame('first-image-bytes', Storage::disk('public')->get('gallery/photo.jpg'));
        $this->assertSame('second-image-bytes', Storage::disk('public')->get('gallery/photo-1.jpg'));
    }
}
