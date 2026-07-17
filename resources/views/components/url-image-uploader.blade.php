@php
    use Illuminate\Support\Arr;
    use Illuminate\Support\Facades\Storage;
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="filament-url-image-uploader">
        {{ $getChildComponentContainer() }}

        @php
            $childState = $field->getChildComponentContainer()->getRawState();
            $path = $childState['path'] ?? null;

            // FileUpload's live state is a UUID-keyed array (e.g.
            // ['9f1c...' => 'images/photo.jpg']), not a plain [0 => ...]
            // indexed one — Arr::first() grabs the value regardless of key.
            $path = is_array($path) ? Arr::first($path) : $path;

            $record = $field->getRecord();

            $uploadedImage = null;

            if (filled($path)) {
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

        @if($uploadedImage)
            <div class="mt-2 flex items-center gap-2">
                <div class="relative group">
                    <img src="{{ $uploadedImage }}"
                         alt="Image Preview"
                         class="w-32 h-32 object-contain rounded-lg shadow-sm transition-all duration-300 group-hover:brightness-90" />
                    <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                        <span class="text-white text-sm">Preview</span>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
