<?php

namespace AmjadIqbal\FilamentUrlImageUploader\Tests\Fixtures;

use AmjadIqbal\FilamentUrlImageUploader\Components\UrlImageUploader;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class RepeaterTestFormComponent extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public ?TestModel $record = null;

    public ?array $data = [];

    public function mount(?int $recordId = null): void
    {
        $this->record = $recordId ? TestModel::findOrFail($recordId) : new TestModel;

        $this->form->fill($this->record->attributesToArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Repeater::make('gallery')
                    ->components([
                        UrlImageUploader::make('image')
                            ->directory('gallery'),
                    ])
                    ->reorderable()
                    ->addable()
                    ->deletable(),
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
        return view('filament-url-image-uploader-tests::repeater-test-form-component');
    }
}
