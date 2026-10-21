<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests;

use AmjadIqbal\FilamentUrlImageUploader\FilamentUrlImageUploaderServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            NotificationsServiceProvider::class,
            ActionsServiceProvider::class,
            SchemasServiceProvider::class,
            FormsServiceProvider::class,
            FilamentUrlImageUploaderServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ]);

        $app['view']->addNamespace(
            'filament-url-image-uploader-tests',
            __DIR__.'/Fixtures/views',
        );
    }

    /**
     * Filament 6 turns FileUpload::preventFilePathTampering() on by default
     * (it is opt-in on 4.11+/5.x and does not exist before that). This makes
     * Filament 4/5 behave the way 6 does, so the F6 default can be tested
     * without a 6.x install. Skips the test on a Filament without the method.
     */
    protected function simulateFilamentSixFilePathTampering(): void
    {
        if (! method_exists(FileUpload::class, 'preventFilePathTampering')) {
            $this->markTestSkipped('This Filament version has no preventFilePathTampering().');
        }

        FileUpload::configureUsing(fn (FileUpload $upload) => $upload->preventFilePathTampering());
    }
}
