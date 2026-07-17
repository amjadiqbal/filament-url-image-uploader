<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures;

use AmjadIqbal\FilamentUrlImageUploader\Components\UrlImageUploader;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

class TestFormComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?TestModel $record = null;

    public ?array $data = [];

    public function mount(?int $recordId = null): void
    {
        $this->record = $recordId ? TestModel::findOrFail($recordId) : new TestModel;

        $this->form->fill($this->record->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                UrlImageUploader::make('image')
                    ->directory('images'),
            ])
            ->statePath('data')
            ->model($this->record);
    }

    public function save(): void
    {
        $this->record->fill($this->form->getState());
        $this->record->save();
    }

    public function render()
    {
        return view('filament-url-image-uploader-tests::test-form-component');
    }
}
