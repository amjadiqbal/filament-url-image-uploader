@php
    use Illuminate\Support\Facades\Storage;
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="filament-url-image-uploader">
        {{ $getChildComponentContainer() }}

        @php
            $state = $field->getState();

            $uploadedImage = null;

            if (is_string($state) && filled($state)) {
                // Stored as a plain path or full URL
                if (filter_var($state, FILTER_VALIDATE_URL)) {
                    $uploadedImage = $state;
                } else {
                    $uploadedImage = Storage::disk('public')->url($state);
                }
            } elseif (is_array($state) && count($state) > 0) {
                // FileUpload stores state as [uuid => path] or [path]
                $path = array_values($state)[0] ?? null;

                if ($path && is_string($path)) {
                    if (filter_var($path, FILTER_VALIDATE_URL)) {
                        $uploadedImage = $path;
                    } else {
                        $uploadedImage = Storage::disk('public')->url($path);
                    }
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