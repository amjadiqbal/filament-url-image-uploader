<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Components;

use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class UrlImageUploader extends Field
{
    protected string $view = 'filament-url-image-uploader::components.url-image-uploader';

    protected string $directory = 'images';

    protected bool $shouldPreserveFilenames = false;

    protected function setUp(): void
    {
        parent::setUp();

        // The outer field is a UI container only; the inner FileUpload handles
        // its own dehydration directly to the same state-path key.
        $this->dehydrated(false);
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function preserveFilenames(bool $condition = true): static
    {
        $this->shouldPreserveFilenames = $condition;

        return $this;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Override the child container so that its state-path is rooted at the
     * form level rather than nested under this field's own state-path.
     * This allows inner components (FileUpload, TextInput) to be registered
     * at their declared names (e.g. "image", "image_url") without the
     * double-nesting that would otherwise occur ("image.image", "image.image_url").
     */
    public function getChildComponentContainer($key = null): ComponentContainer
    {
        if (filled($key) && array_key_exists($key, $containers = $this->getChildComponentContainers())) {
            return $containers[$key];
        }

        return ComponentContainer::make($this->getLivewire())
            ->components($this->getChildComponents());
    }

    public function getChildComponents(): array
    {
        $fieldName = $this->getName();
        $directory = $this->directory;
        $shouldPreserveFilenames = $this->shouldPreserveFilenames;

        return [
            Tabs::make('upload_methods')
                ->tabs([
                    Tabs\Tab::make('upload')
                        ->label('File Upload')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->schema([
                            FileUpload::make($fieldName)
                                ->image()
                                ->preserveFilenames($shouldPreserveFilenames)
                                ->directory($directory)
                                ->disk('public')
                                ->live(),
                        ]),
                    Tabs\Tab::make('url')
                        ->label('URL Upload')
                        ->icon('heroicon-o-globe-alt')
                        ->schema([
                            TextInput::make("{$fieldName}_url")
                                ->url()
                                ->dehydrated(false)
                                ->helperText('Enter a valid image URL'),
                            Actions::make([
                                Actions\Action::make('fetch')
                                    ->label('Fetch Image')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->action(function (Set $set, $state) use ($fieldName, $directory) {
                                        $imageUrl = $state["{$fieldName}_url"] ?? null;

                                        if (! $imageUrl || ! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                                            Notification::make()
                                                ->danger()
                                                ->title('Invalid URL')
                                                ->body('Please enter a valid image URL.')
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
                                                ],
                                                'ssl' => [
                                                    'verify_peer' => true,
                                                ],
                                            ]);

                                            $imageData = @file_get_contents($imageUrl, false, $context);

                                            if ($imageData === false || $imageData === '') {
                                                throw new \Exception('Could not fetch image from the provided URL.');
                                            }

                                            $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                            $mimeType = $finfo->buffer($imageData);

                                            if (! str_starts_with((string) $mimeType, 'image/')) {
                                                throw new \Exception('The URL does not point to a valid image (detected: '.$mimeType.').');
                                            }

                                            $storagePath = "{$directory}/{$filename}";

                                            Storage::disk('public')->put($storagePath, $imageData);

                                            $set($fieldName, [$storagePath]);

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
