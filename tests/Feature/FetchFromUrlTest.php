<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Feature;

use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\TestFormComponent;
use AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures\TestModel;
use AmjadIqbal\FilamentUrlImageUploader\Tests\TestCase;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Drives the "URL Upload" tab's Fetch Image action end to end: a real HTTP
 * request (PHP's built-in server on 127.0.0.1, so no outside network), the
 * MIME check, storage on the configured disk and the saved model value. The
 * action is called the way the browser calls it (mountAction with the
 * field's schemaComponent key), so a wrong action wiring fails here too.
 */
class FetchFromUrlTest extends TestCase
{
    /** @var resource|null */
    protected static $server = null;

    protected static string $serverUrl = '';

    protected static string $docroot = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        static::$docroot = sys_get_temp_dir().'/url-image-uploader-http-'.getmypid();
        @mkdir(static::$docroot, 0777, true);

        $png = imagecreatetruecolor(4, 4);
        imagepng($png, static::$docroot.'/photo.png');
        file_put_contents(static::$docroot.'/notes.txt', 'definitely not an image');

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        static::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', static::$docroot],
            [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
            $pipes,
        );
        static::$serverUrl = "http://127.0.0.1:{$port}";

        for ($i = 0; $i < 50 && ! @fsockopen('127.0.0.1', $port); $i++) {
            usleep(100_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(static::$server)) {
            proc_terminate(static::$server);
            proc_close(static::$server);
        }

        array_map('unlink', glob(static::$docroot.'/*') ?: []);
        @rmdir(static::$docroot);

        parent::tearDownAfterClass();
    }

    protected function fetch(Testable $component, string $url): Testable
    {
        return $component
            ->set('data.image.url', $url)
            ->callAction(TestAction::make('fetch')->schemaComponent('image', schema: 'form'));
    }

    public function test_fetching_a_real_image_stores_it_and_saves_its_path(): void
    {
        $record = TestModel::create();

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);

        $this->fetch($component, static::$serverUrl.'/photo.png')
            ->assertNotified('Image fetched successfully');

        $livePath = Arr::first($component->get('data.image.path'));

        $this->assertMatchesRegularExpression('#^images/[A-Za-z0-9]{20}_photo\.png$#', $livePath);
        Storage::disk('public')->assertExists($livePath);
        $this->assertSame(file_get_contents(static::$docroot.'/photo.png'), Storage::disk('public')->get($livePath));

        // The preview renders before saving...
        $component->assertSee('/storage/'.$livePath, false);

        // ...and saving stores the plain path, which a fresh page load shows again.
        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame($livePath, $record->fresh()->image);

        Livewire::test(TestFormComponent::class, ['recordId' => $record->id])
            ->assertSee('/storage/'.$livePath, false);
    }

    public function test_a_url_that_is_not_an_image_is_rejected_and_nothing_is_stored(): void
    {
        $record = TestModel::create();

        $component = Livewire::test(TestFormComponent::class, ['recordId' => $record->id]);

        $this->fetch($component, static::$serverUrl.'/notes.txt')
            ->assertNotified('Failed to fetch image');

        $this->assertEmpty($component->get('data.image.path'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_missing_remote_file_shows_the_fetch_failed_notification(): void
    {
        $component = Livewire::test(TestFormComponent::class, ['recordId' => TestModel::create()->id]);

        $this->fetch($component, static::$serverUrl.'/does-not-exist.png')
            ->assertNotified('Failed to fetch image');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_invalid_url_shows_the_invalid_url_notification(): void
    {
        $component = Livewire::test(TestFormComponent::class, ['recordId' => TestModel::create()->id]);

        $this->fetch($component, 'not a url')
            ->assertNotified('Invalid URL');

        $this->assertEmpty($component->get('data.image.path'));
    }

    public function test_non_http_schemes_are_refused_before_any_read(): void
    {
        $component = Livewire::test(TestFormComponent::class, ['recordId' => TestModel::create()->id]);

        $this->fetch($component, 'file:///etc/passwd')
            ->assertNotified('Invalid URL');

        $this->assertEmpty($component->get('data.image.path'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_invalid_url_left_in_the_url_tab_blocks_save_with_a_field_error(): void
    {
        $record = TestModel::create();

        Livewire::test(TestFormComponent::class, ['recordId' => $record->id])
            ->set('data.image.url', 'not a url')
            ->call('save')
            ->assertHasFormErrors(['image.url']);

        $this->assertNull($record->fresh()->image);
    }
}
