<?php

namespace App\Orchid\Screens\ModelSettings;

use App\Models\LanguageModel;
use Illuminate\Http\Request;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Fields\Code;
use Orchid\Screen\Fields\TextArea;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;
use Illuminate\Support\Facades\Log;

class ModelInformationScreen extends Screen
{
    /**
     * @var LanguageModel
     */
    public $model;

    /**
     * Query data.
     *
     * @param LanguageModel $model
     *
     * @return array
     */
    public function query(LanguageModel $model): iterable
    {
        $this->model = $model;

        return [
            'model' => $model,
            'information' => json_encode($model->information, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Display header name.
     *
     * @return string|null
     */
    public function name(): ?string
    {
        return 'Model Information: ' . $this->model->label;
    }

    /**
     * Display header description.
     *
     * @return string|null
     */
    public function description(): ?string
    {
        return 'View model information details (read-only)';
    }

    /**
     * Button commands.
     *
     * @return \Orchid\Screen\Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Back to Models')
                ->icon('arrow-left')
                ->route('platform.modelsettings.modelslist'),
        ];
    }

    /**
     * Views.
     *
     * @return \Orchid\Screen\Layout[]|string[]
     */
    public function layout(): iterable
    {
        return [
            //Layout::block([
                Layout::rows([
                    TextArea::make('information')
                        ->title('Model ID: ' . $this->model->model_id . ' | Provider: ' . ($this->model->provider->provider_name ?? 'Unknown'))
                        ->help('This information describes model capabilities and details from the provider')
                        ->value(json_encode($this->model->information, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->rows(50)
                        ->style('min-width: 100%; font-family: monospace;')
                        ->readonly(true),
                ])->title(),            //])
                


        ];
    }
}
