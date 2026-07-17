<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Components;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

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

        // Push the value captured in hydrateDefaultState() into the child
        // upload/url fields once they've finished their own (destructive)
        // hydration, so the preview and inputs reflect the real stored image.
        $this->afterStateHydrated(function (self $component): void {
            $component->getChildComponentContainer()->fill([
                'path' => filled($component->initialPath) ? [$component->initialPath] : [],
                'url' => $component->initialUrl,
            ]);
        });

        // The field's own dehydrated value must stay a plain path string (or
        // null) regardless of whether it lives standalone or inside a
        // Repeater row, so consumers (Eloquent accessors/mutators) receive a
        // predictable scalar instead of the nested upload/url sub-state.
        $this->dehydrateStateUsing(function (self $component) {
            $childState = $component->getChildComponentContainer()->getRawState();

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

    public function getChildComponents(): array
    {
        return [
            Tabs::make('upload_methods')
                ->tabs([
                    Tabs\Tab::make('upload')
                        ->label('File Upload')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            FileUpload::make('path')
                                ->image()
                                ->preserveFilenames($this->shouldPreserveFilenames)
                                ->directory($this->directory)
                                ->disk($this->disk)
                                // Dehydrated by the parent field's own
                                // dehydrateStateUsing instead — otherwise this
                                // child's independent dehydration overwrites
                                // the parent's shared state path with its own
                                // nested {path, url} shape.
                                ->dehydrated(false),
                        ]),
                    Tabs\Tab::make('url')
                        ->label('URL Upload')
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
                                            $fail('The url field must be a valid URL.');
                                        }
                                    };
                                })
                                ->dehydrated(false)
                                ->helperText('Enter a valid image URL'),
                            Actions::make([
                                Actions\Action::make('fetch')
                                    ->label('Fetch Image')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->action(function (Set $set, $state) {
                                        $imageUrl = $state['url'] ?? null;

                                        if (! $imageUrl || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                                            Notification::make()
                                                ->danger()
                                                ->title('Invalid URL')
                                                ->body('Please enter a valid image URL.')
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
                                                ->title('Invalid URL')
                                                ->body('Only http:// and https:// URLs are supported.')
                                                ->send();

                                            return;
                                        }

                                        try {
                                            $rawFilename = basename(parse_url($imageUrl, PHP_URL_PATH) ?: 'image.jpg');
                                            $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawFilename) ?: 'image.jpg';

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
                                                ->title('Image fetched successfully')
                                                ->send();
                                        } catch (\Exception $e) {
                                            Notification::make()
                                                ->danger()
                                                ->title('Failed to fetch image')
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
}
