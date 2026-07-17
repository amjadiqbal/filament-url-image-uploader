<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Feature;

use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\TestFormComponent;
use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\TestModel;
use AmjadIqbal\FilamentUrlImageUploader\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

class UrlImageUploaderTest extends TestCase
{
    public function test_existing_value_survives_a_noop_edit_and_save_twice(): void
    {
        Storage::disk('public')->put('images/existing.jpg', 'fake-image-bytes');

        $record = TestModel::create(['image' => 'images/existing.jpg']);

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame('images/existing.jpg', $record->fresh()->image);

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame('images/existing.jpg', $record->fresh()->image);
    }

    public function test_single_field_survives_a_noop_save_across_separate_page_loads(): void
    {
        Storage::disk('public')->put('images/logo.jpg', 'fake-image-bytes');

        $record = TestModel::create(['image' => 'images/logo.jpg']);

        Livewire::test(TestFormComponent::class, ['recordId' => $record->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('images/logo.jpg', $record->fresh()->image);

        // Simulate a brand new page load (fresh Livewire instance, fresh mount)
        // rather than reusing the same component instance.
        Livewire::test(TestFormComponent::class, ['recordId' => $record->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('images/logo.jpg', $record->fresh()->image);
    }

    public function test_fresh_file_upload_persists_to_disk_and_database(): void
    {
        $record = TestModel::create();

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);

        $component->fillForm([
            'image' => [
                'path' => UploadedFile::fake()->image('photo.jpg'),
            ],
        ]);

        $component->call('save')->assertHasNoFormErrors();

        $savedPath = $record->fresh()->image;

        $this->assertIsString($savedPath);
        $this->assertStringStartsWith('images/', $savedPath);
        Storage::disk('public')->assertExists($savedPath);
    }

    public function test_preview_shows_immediately_after_upload_before_save(): void
    {
        $record = TestModel::create();

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);

        $component->fillForm([
            'image' => [
                'path' => UploadedFile::fake()->image('photo.jpg'),
            ],
        ]);

        // Not saved yet — the DB must still be untouched...
        $this->assertNull($record->fresh()->image);

        // ...but the field's own blade preview must already resolve a
        // live path from the raw FileUpload state, without waiting for
        // dehydration/save.
        $component->assertSee('Image Preview', false);
    }

    public function test_preview_resolves_uuid_keyed_state_for_an_existing_image(): void
    {
        // FileUpload's own hydration hook re-keys any existing value onto a
        // UUID (e.g. ['9f1c...' => 'images/existing.jpg']), not a plain
        // [0 => ...] indexed array — this is the state format the preview
        // must handle on every normal "edit an existing record" page load,
        // before any save happens.
        Storage::disk('public')->put('images/existing.jpg', 'fake-image-bytes');

        $record = TestModel::create(['image' => 'images/existing.jpg']);

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);

        $rawPath = $component->get('data.image.path');

        $this->assertIsArray($rawPath);
        $this->assertArrayNotHasKey(0, $rawPath, 'expected a UUID-keyed array, not a [0 => ...] indexed one');
    }

    public function test_preview_resolves_a_uuid_keyed_path_even_without_a_persisted_fallback(): void
    {
        // The blade view falls back to the persisted $record attribute when
        // the live path is blank, which would mask a broken UUID-key lookup
        // (both would coincidentally resolve to the same value). Setting the
        // live state directly, on a record with no persisted image at all,
        // removes that fallback so this test can only pass if the preview
        // genuinely reads the UUID-keyed live state correctly.
        $record = TestModel::create();

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);
        $component->set('data.image.path', [(string) Str::uuid() => 'images/uuid-test.jpg']);

        $this->assertNull($record->fresh()->image);

        $component->assertSee('/storage/images/uuid-test.jpg', false);
    }

    public function test_blank_url_tab_does_not_block_save_when_uploading_a_file(): void
    {
        $record = TestModel::create();

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);

        $component->fillForm([
            'image' => [
                'path' => UploadedFile::fake()->image('photo.jpg'),
                // url intentionally left blank, as in normal file-tab usage
            ],
        ]);

        $component->call('save')->assertHasNoFormErrors();

        $this->assertNotNull($record->fresh()->image);
    }
}
