<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Feature;

use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\RepeaterTestFormComponent;
use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\TestFormComponent;
use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\TestModel;
use AmjadIqbal\FilamentUrlImageUploader\Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Filament 6 validates every submitted FileUpload path against the record's
 * original value for the attribute named like the field. The inner upload is
 * named "path", which is not a model attribute, so with that check on every
 * stored image (existing or just fetched) would fail validation with "tampered
 * file path". The inner upload therefore opts out; these tests turn the F6
 * default on for every FileUpload and assert the field still saves.
 */
class FilePathTamperingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->simulateFilamentSixFilePathTampering();
    }

    public function test_an_existing_image_survives_a_noop_save(): void
    {
        Storage::disk('public')->put('images/existing.jpg', 'fake-image-bytes');

        $record = TestModel::create(['image' => 'images/existing.jpg']);

        Livewire::test(TestFormComponent::class, ['recordId' => $record->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('images/existing.jpg', $record->fresh()->image);
    }

    public function test_an_existing_gallery_survives_a_noop_save(): void
    {
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');
        Storage::disk('public')->put('gallery/b.jpg', 'fake-b');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
                ['image' => 'gallery/b.jpg'],
            ],
        ]);

        Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['gallery/a.jpg', 'gallery/b.jpg'],
            array_column($record->fresh()->gallery, 'image')
        );
    }
}
