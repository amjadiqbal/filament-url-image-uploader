<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UrlImageUploader extends Field
{
    protected string $view = 'filament-url-image-uploader::components.url-image-uploader';

    protected string $directory = 'images';

    protected string $disk = 'public';

    protected bool $shouldPreserveFilenames = false;

    protected int $maxUrlFetchBytes = 10 * 1024 * 1024;

    protected ?string $initialPath = null;

    protected ?string $initialUrl = null;

    /**
     * Filament hydrates a component's own bound value first, then recurses
     * into its child components. The child FileUpload's built-in hydration
     * hook overwrites this field's shared state path with `[]` the moment it
     * finds nothing at its own (deeper, nested) path — which is always, since
     * the real value lives one level up. That happens before this field's own
     * afterStateHydrated hook runs, so the real value must be captured here,
     * as early as possible, before any child gets a chance to destroy it.
     */
    public function hydrateDefaultState(?array &$hydratedDefaultState): void
    {
        $rawState = $this->getState();

        $path = null;
        $url = null;

        if (is_string($rawState) && $rawState !== '') {
            $path = $rawState;
        } elseif (is_array($rawState)) {
            $path = $rawState['path'] ?? $rawState['image'] ?? $rawState[0] ?? null;
            $url = $rawState['url'] ?? $rawState['image_url'] ?? null;

            if (is_array($path)) {
                $path = Arr::first($path);
            }
        }

        $this->initialPath = $path;
        $this->initialUrl = $url;

        parent::hydrateDefaultState($hydratedDefaultState);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Filament 4+ only walks child schemas registered through
        // schema()/childComponents() (overriding getChildComponents(), as
        // the Filament 3 version did, is no longer enough: the children are
        // then rendered but never hydrated, validated or saved).
        $this->schema(fn (): array => $this->getUploaderComponents());

        // Push the value captured in hydrateDefaultState() into the child
        // upload/url fields once they've finished their own (destructive)
        // hydration, so the preview and inputs reflect the real stored image.
        $this->afterStateHydrated(function (self $component): void {
            $component->getChildSchema()->fill([
                'path' => filled($component->initialPath) ? [$component->initialPath] : [],
                'url' => $component->initialUrl,
            ]);
        });

        // The field's own dehydrated value must stay a plain path string (or
        // null) regardless of whether it lives standalone or inside a
        // Repeater row, so consumers (Eloquent accessors/mutators) receive a
        // predictable scalar instead of the nested upload/url sub-state.
        $this->dehydrateStateUsing(function (self $component) {
            $childState = $component->getChildSchema()->getRawState();

            $path = $childState['path'] ?? null;

            // FileUpload keeps its own state as a UUID-keyed array
            // (e.g. ['9f1c...' => 'cars/main/test.jpg']), not a plain
            // numerically-indexed one — grab the first value regardless
            // of its key.
            if (is_array($path)) {
                $path = Arr::first($path);
            }

            return filled($path) ? $path : null;
        });
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function preserveFilenames(bool $condition = true): static
    {
        $this->shouldPreserveFilenames = $condition;

        return $this;
    }

    public function maxUrlFetchSize(int $bytes): static
    {
        $this->maxUrlFetchBytes = $bytes;

        return $this;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * The upload/URL tabs. Registered as this field's child schema in
     * setUp() through a closure, so it is built lazily and
     * directory()/disk()/preserveFilenames() set after make() still apply.
     *
     * @return array<Component>
     */
    protected function getUploaderComponents(): array
    {
        $upload = FileUpload::make('path')
            ->image()
            ->preserveFilenames($this->shouldPreserveFilenames)
            ->directory($this->directory)
            ->disk($this->disk)
            // Dehydrated by the parent field's own
            // dehydrateStateUsing instead — otherwise this
            // child's independent dehydration overwrites
            // the parent's shared state path with its own
            // nested {path, url} shape.
            ->dehydrated(false);

        // Filament 6 checks every submitted upload path against the record's
        // original value for the attribute named like the field ($record->
        // getOriginal('path')). This inner upload is named "path", which is
        // not a model attribute, so with the check on every stored image (one
        // just fetched from a URL, or one loaded from the record, inside a
        // Repeater row too) fails validation as a "tampered file path".
        // preventFilePathTampering() exists from Filament 4.11 / 5.6 and is
        // off by default until 6; older versions have neither the method nor
        // the check, so call it only where it exists. (Reflection rather than
        // method_exists(): static analysis only sees the installed Filament,
        // where the method exists, and would call a plain guard redundant.)
        if ((new \ReflectionClass($upload))->hasMethod('preventFilePathTampering')) {
            $upload->preventFilePathTampering(false);
        }

        return [
            Tabs::make('upload_methods')
                ->tabs([
                    Tab::make('upload')
                        ->label(__('filament-url-image-uploader::url-image-uploader.tabs.upload.label'))
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([$upload]),
                    Tab::make('url')
                        ->label(__('filament-url-image-uploader::url-image-uploader.tabs.url.label'))
                        ->icon('heroicon-o-globe-alt')
                        ->schema([
                            TextInput::make('url')
                                ->rule(function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        if (blank($value)) {
                                            return;
                                        }

                                        // Storage::url() can legitimately return a
                                        // root-relative path (e.g. "/storage/...")
                                        // depending on disk config, so accept that
                                        // alongside fully-qualified URLs.
                                        if (str_starts_with($value, '/')) {
                                            return;
                                        }

                                        if (! filter_var($value, FILTER_VALIDATE_URL)) {
                                            $fail(__('filament-url-image-uploader::url-image-uploader.url.invalid'));
                                        }
                                    };
                                })
                                ->dehydrated(false)
                                ->helperText(__('filament-url-image-uploader::url-image-uploader.url.helper_text')),
                            Actions::make([
                                Action::make('fetch')
                                    ->label(__('filament-url-image-uploader::url-image-uploader.actions.fetch.label'))
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->action(function (Get $get, Set $set) {
                                        $imageUrl = $get('url');

                                        if (! $imageUrl || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                                            Notification::make()
                                                ->danger()
                                                ->title(__('filament-url-image-uploader::url-image-uploader.notifications.invalid_url.title'))
                                                ->body(__('filament-url-image-uploader::url-image-uploader.notifications.invalid_url.body'))
                                                ->send();

                                            return;
                                        }

                                        // Only allow fetching over plain HTTP(S) — reject
                                        // file://, ftp://, php://, data: and similar schemes
                                        // that filter_var(..., FILTER_VALIDATE_URL) alone
                                        // would still accept.
                                        $scheme = strtolower((string) parse_url($imageUrl, PHP_URL_SCHEME));

                                        if (! in_array($scheme, ['http', 'https'], true)) {
                                            Notification::make()
                                                ->danger()
                                                ->title(__('filament-url-image-uploader::url-image-uploader.notifications.invalid_scheme.title'))
                                                ->body(__('filament-url-image-uploader::url-image-uploader.notifications.invalid_scheme.body'))
                                                ->send();

                                            return;
                                        }

                                        try {
                                            $rawFilename = basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'image.jpg');
                                            $sanitizedFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawFilename) ?: 'image.jpg';

                                            // Different source URLs frequently share the same
                                            // basename (generic CDN paths, a query string that
                                            // got stripped, several rows in a Repeater gallery
                                            // fetched from the same site) — without a unique
                                            // filename, the second fetch silently overwrites the
                                            // first file on disk, and every row that pointed at
                                            // that name ends up showing the same, wrong image.
                                            // Default to a random prefix (matching FileUpload's
                                            // own default of not preserving the original name);
                                            // preserveFilenames() keeps the readable name but
                                            // still avoids a silent overwrite by disambiguating
                                            // only when the exact path is already taken.
                                            $filename = $this->shouldPreserveFilenames
                                                ? $this->uniqueFilename($sanitizedFilename)
                                                : Str::random(20).'_'.$sanitizedFilename;

                                            $context = stream_context_create([
                                                'http' => [
                                                    'timeout' => 10,
                                                    'method' => 'GET',
                                                    'header' => "User-Agent: PHP\r\n",
                                                    'follow_location' => 1,
                                                    'max_redirects' => 3,
                                                ],
                                                'ssl' => [
                                                    'verify_peer' => true,
                                                ],
                                            ]);

                                            // Read one byte past the limit so we can tell a
                                            // legitimately max-sized file apart from a
                                            // truncated, oversized one.
                                            $imageData = @file_get_contents(
                                                $imageUrl,
                                                false,
                                                $context,
                                                0,
                                                $this->maxUrlFetchBytes + 1
                                            );

                                            if ($imageData === false || $imageData === '') {
                                                throw new \Exception('Could not fetch image from the provided URL.');
                                            }

                                            if (strlen($imageData) > $this->maxUrlFetchBytes) {
                                                throw new \Exception('The image exceeds the maximum allowed size of '.number_format($this->maxUrlFetchBytes / 1024 / 1024, 1).' MB.');
                                            }

                                            $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                            $mimeType = $finfo->buffer($imageData);

                                            if (! str_starts_with((string) $mimeType, 'image/')) {
                                                throw new \Exception('The URL does not point to a valid image (detected: '.$mimeType.').');
                                            }

                                            $storagePath = "{$this->directory}/{$filename}";

                                            Storage::disk($this->disk)->put($storagePath, $imageData);

                                            $set('path', [$storagePath]);
                                            $set('url', Storage::disk($this->disk)->url($storagePath));

                                            Notification::make()
                                                ->success()
                                                ->title(__('filament-url-image-uploader::url-image-uploader.notifications.fetch_success.title'))
                                                ->send();
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->danger()
                                                ->title(__('filament-url-image-uploader::url-image-uploader.notifications.fetch_failed.title'))
                                                ->body($e->getMessage())
                                                ->send();
                                        }
                                    }),
                            ]),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    /**
     * Keeps $sanitizedFilename as-is if that path is free; otherwise appends
     * an incrementing "-1", "-2", ... suffix before the extension until it
     * finds one that is. Used by preserveFilenames() so the human-readable
     * name is kept whenever possible without silently overwriting an
     * existing file.
     */
    protected function uniqueFilename(string $sanitizedFilename): string
    {
        $disk = Storage::disk($this->disk);

        if (! $disk->exists("{$this->directory}/{$sanitizedFilename}")) {
            return $sanitizedFilename;
        }

        $extension = pathinfo($sanitizedFilename, PATHINFO_EXTENSION);
        $basename = pathinfo($sanitizedFilename, PATHINFO_FILENAME);

        $suffix = 1;

        do {
            $candidate = $extension !== ''
                ? "{$basename}-{$suffix}.{$extension}"
                : "{$basename}-{$suffix}";

            $suffix++;
        } while ($disk->exists("{$this->directory}/{$candidate}"));

        return $candidate;
    }
}
