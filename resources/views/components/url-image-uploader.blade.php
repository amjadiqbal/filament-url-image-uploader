@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Facades\Storage;
    use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="filament-url-image-uploader">
        {{ $getChildSchema() }}

        @php
            $childState = $field->getChildSchema()->getRawState();
            $path = $childState['path'] ?? null;

            // FileUpload's live state is a UUID-keyed array (e.g.
            // ['9f1c...' => 'images/photo.jpg']), not a plain [0 => ...]
            // indexed one — Arr::first() grabs the value regardless of key.
            $path = is_array($path) ? Arr::first($path) : $path;

            $record = $field->getRecord();

            $uploadedImage = null;

            if ($path instanceof TemporaryUploadedFile) {
                // A file picked in the upload tab but not saved yet lives in
                // Livewire's temporary storage, not on the field's disk.
                try {
                    $uploadedImage = $path->isPreviewable() ? $path->temporaryUrl() : null;
                } catch (\Throwable) {
                    $uploadedImage = null;
                }
            } elseif (filled($path)) {
                $uploadedImage = filter_var($path, FILTER_VALIDATE_URL)
                    ? $path
                    : Storage::disk($field->getDisk())->url($path);
            } elseif ($record && $field->getName() && $record->{$field->getName()}) {
                $existingImage = $record->{$field->getName()};

                if (filter_var($existingImage, FILTER_VALIDATE_URL)) {
                    $uploadedImage = $existingImage;
                } else {
                    $uploadedImage = Storage::disk($field->getDisk())->url($existingImage);
                }
            }
        @endphp

        {{--
            Filament 4+ panels ship a prebuilt stylesheet that only contains
            the Tailwind classes Filament itself uses, so utility classes in a
            plugin view (w-32, group-hover:..., bg-black/50) are silently
            missing. The preview is styled here instead, with no build step.
        --}}
        @once
            <style>
                .fi-url-image-uploader-preview { position: relative; display: inline-block; margin-top: 0.5rem; }
                .fi-url-image-uploader-preview img { display: block; width: 8rem; height: 8rem; object-fit: contain; border-radius: 0.5rem; box-shadow: 0 1px 2px rgb(0 0 0 / 0.05); transition: filter 0.3s; }
                .fi-url-image-uploader-preview span { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; border-radius: 0.5rem; background: rgb(0 0 0 / 0.5); color: #fff; font-size: 0.875rem; opacity: 0; transition: opacity 0.15s; }
                .fi-url-image-uploader-preview:hover img { filter: brightness(0.9); }
                .fi-url-image-uploader-preview:hover span { opacity: 1; }
            </style>
        @endonce

        @if($uploadedImage)
            <div class="fi-url-image-uploader-preview">
                <img src="{{ $uploadedImage }}"
                     alt="{{ __('filament-url-image-uploader::url-image-uploader.preview.alt') }}" />
                <span>{{ __('filament-url-image-uploader::url-image-uploader.preview.hover') }}</span>
            </div>
        @endif
    </div>
</x-dynamic-component>
