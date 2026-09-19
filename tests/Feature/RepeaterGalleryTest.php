<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Feature;

use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\RepeaterTestFormComponent;
use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\TestModel;
use AmjadIqbal\FilamentUrlImageUploader\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Covers UrlImageUploader used as a Repeater item — a gallery of several
 * images per record. This is a materially different code path from a single
 * standalone field: every row gets its own cloned component instance sharing
 * the same class-level {@see UrlImageUploader} implementation, and Filament
 * keys each row by its own UUID, not by array index. Every row mutation here
 * goes through Repeater's real "add"/"delete"/"moveUp"/"moveDown" actions —
 * the same code path the admin UI buttons call — rather than driving the
 * array state directly, so these tests fail the same way real usage would.
 */
class RepeaterGalleryTest extends TestCase
{
    public function test_multiple_fresh_uploads_persist_in_order(): void
    {
        $record = TestModel::create();

        $component = Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id]);

        foreach (['one.jpg', 'two.jpg', 'three.jpg'] as $filename) {
            $component->callFormComponentAction('gallery', 'add');

            $newKey = array_key_last($component->get('data.gallery'));

            $component->set("data.gallery.{$newKey}.image.path", [UploadedFile::fake()->image($filename)]);
        }

        $component->call('save')->assertHasNoFormErrors();

        $gallery = $record->fresh()->gallery;

        $this->assertCount(3, $gallery);

        foreach ($gallery as $row) {
            $this->assertIsString($row['image']);
            $this->assertStringStartsWith('gallery/', $row['image']);
            Storage::disk('public')->assertExists($row['image']);
        }

        // Each upload must be its own file — not the same one duplicated
        // across rows because of state-path collisions between siblings.
        $this->assertCount(3, array_unique(array_column($gallery, 'image')));
    }

    public function test_existing_gallery_survives_a_noop_save_across_separate_page_loads(): void
    {
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');
        Storage::disk('public')->put('gallery/b.jpg', 'fake-b');
        Storage::disk('public')->put('gallery/c.jpg', 'fake-c');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
                ['image' => 'gallery/b.jpg'],
                ['image' => 'gallery/c.jpg'],
            ],
        ]);

        Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['gallery/a.jpg', 'gallery/b.jpg', 'gallery/c.jpg'],
            array_column($record->fresh()->gallery, 'image')
        );

        // A second, independent page load/component instance — the scenario
        // most likely to expose stale state bleeding between component
        // clones, since hydrateDefaultState()/initialPath are per-instance.
        Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            ['gallery/a.jpg', 'gallery/b.jpg', 'gallery/c.jpg'],
            array_column($record->fresh()->gallery, 'image')
        );
    }

    public function test_deleting_a_middle_row_keeps_the_remaining_rows_correct(): void
    {
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');
        Storage::disk('public')->put('gallery/b.jpg', 'fake-b');
        Storage::disk('public')->put('gallery/c.jpg', 'fake-c');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
                ['image' => 'gallery/b.jpg'],
                ['image' => 'gallery/c.jpg'],
            ],
        ]);

        $component = Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id]);

        $keys = array_keys($component->get('data.gallery'));
        $this->assertCount(3, $keys);

        // Delete the middle row (b), the same action Filament's own trash
        // icon triggers.
        $component->callFormComponentAction('gallery', 'delete', arguments: ['item' => $keys[1]]);

        $component->call('save')->assertHasNoFormErrors();

        $remaining = array_column($record->fresh()->gallery, 'image');

        $this->assertSame(['gallery/a.jpg', 'gallery/c.jpg'], $remaining);
    }

    public function test_reordering_rows_keeps_each_image_with_its_own_row(): void
    {
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');
        Storage::disk('public')->put('gallery/b.jpg', 'fake-b');
        Storage::disk('public')->put('gallery/c.jpg', 'fake-c');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
                ['image' => 'gallery/b.jpg'],
                ['image' => 'gallery/c.jpg'],
            ],
        ]);

        $component = Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id]);

        $keys = array_keys($component->get('data.gallery'));

        // Move row a (index 0) down twice: a,b,c -> b,a,c -> b,c,a.
        $component->callFormComponentAction('gallery', 'moveDown', arguments: ['item' => $keys[0]]);
        $component->callFormComponentAction('gallery', 'moveDown', arguments: ['item' => $keys[0]]);

        $component->call('save')->assertHasNoFormErrors();

        $this->assertSame(
            ['gallery/b.jpg', 'gallery/c.jpg', 'gallery/a.jpg'],
            array_column($record->fresh()->gallery, 'image')
        );
    }

    public function test_adding_a_new_row_to_an_existing_gallery_keeps_the_originals_intact(): void
    {
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');
        Storage::disk('public')->put('gallery/b.jpg', 'fake-b');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
                ['image' => 'gallery/b.jpg'],
            ],
        ]);

        $component = Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id]);

        $component->callFormComponentAction('gallery', 'add');

        $newKey = array_key_last($component->get('data.gallery'));

        $component->set("data.gallery.{$newKey}.image.path", [UploadedFile::fake()->image('new.jpg')]);

        $component->call('save')->assertHasNoFormErrors();

        $gallery = array_column($record->fresh()->gallery, 'image');

        $this->assertCount(3, $gallery);
        $this->assertSame('gallery/a.jpg', $gallery[0]);
        $this->assertSame('gallery/b.jpg', $gallery[1]);
        $this->assertStringStartsWith('gallery/', $gallery[2]);
        $this->assertNotSame('gallery/a.jpg', $gallery[2]);
        $this->assertNotSame('gallery/b.jpg', $gallery[2]);
    }

    public function test_each_row_previews_its_own_uuid_keyed_state_independently(): void
    {
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');
        Storage::disk('public')->put('gallery/b.jpg', 'fake-b');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
                ['image' => 'gallery/b.jpg'],
            ],
        ]);

        $component = Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id]);

        $rawGallery = $component->get('data.gallery');
        $keys = array_keys($rawGallery);

        $pathA = $rawGallery[$keys[0]]['image']['path'];
        $pathB = $rawGallery[$keys[1]]['image']['path'];

        $this->assertIsArray($pathA);
        $this->assertIsArray($pathB);

        // Each row's inner FileUpload state must resolve to that row's own
        // image, not the other row's (or the first row's) — the exact
        // failure mode a shared/static state path would produce.
        $this->assertSame('gallery/a.jpg', array_values($pathA)[0]);
        $this->assertSame('gallery/b.jpg', array_values($pathB)[0]);

        $component->assertSee('/storage/gallery/a.jpg', false);
        $component->assertSee('/storage/gallery/b.jpg', false);
    }

    public function test_deleting_then_adding_does_not_resurrect_the_deleted_image(): void
    {
        // The failure mode a real user would hit: delete row b, add a new
        // row, save — b must stay gone, not reappear because a stale
        // component instance or leftover UUID key resurrected it.
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');
        Storage::disk('public')->put('gallery/b.jpg', 'fake-b');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
                ['image' => 'gallery/b.jpg'],
            ],
        ]);

        $component = Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id]);

        $keys = array_keys($component->get('data.gallery'));
        $component->callFormComponentAction('gallery', 'delete', arguments: ['item' => $keys[1]]);

        $component->callFormComponentAction('gallery', 'add');
        $newKey = array_key_last($component->get('data.gallery'));
        $component->set("data.gallery.{$newKey}.image.path", [UploadedFile::fake()->image('new.jpg')]);

        $component->call('save')->assertHasNoFormErrors();

        $gallery = array_column($record->fresh()->gallery, 'image');

        $this->assertCount(2, $gallery);
        $this->assertSame('gallery/a.jpg', $gallery[0]);
        $this->assertNotContains('gallery/b.jpg', $gallery);
    }

    public function test_an_added_row_left_empty_saves_as_a_null_image_not_a_validation_crash(): void
    {
        // A real user adds a row (e.g. by mistake, or to fill in later) and
        // saves without uploading anything into it. The field must degrade
        // to null for that row rather than throwing, since the field itself
        // has no required() by default.
        Storage::disk('public')->put('gallery/a.jpg', 'fake-a');

        $record = TestModel::create([
            'gallery' => [
                ['image' => 'gallery/a.jpg'],
            ],
        ]);

        $component = Livewire::test(RepeaterTestFormComponent::class, ['recordId' => $record->id]);

        $component->callFormComponentAction('gallery', 'add');

        $component->call('save')->assertHasNoFormErrors();

        $gallery = $record->fresh()->gallery;

        $this->assertCount(2, $gallery);
        $this->assertSame('gallery/a.jpg', $gallery[0]['image']);
        $this->assertNull($gallery[1]['image']);
    }
}
