@php
    // In Orchid Blade-Views sind query() Daten direkt als Variablen verfügbar
    $models = $system['top3Models'] ?? [];
@endphp

<div class="bg-white rounded p-4 mb-3 h-100 d-flex flex-column">
    <h4 class="mb-3">Top 3 Most Used Models</h4>

@if(is_array($models) && count($models) > 0)
    <div class="table-responsive flex-grow-1">
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Model</th>
                    <th class="text-end">Requests</th>
                </tr>
            </thead>
            <tbody>
                @foreach($models as $index => $model)
                <tr>
                    <td class="text-muted">{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $model['model'] }}</strong>
                    </td>
                    <td class="text-end">
                        <span class="badge bg-primary">{{ $model['requests'] }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="alert alert-info mb-0">
        No model usage data available yet.
    </div>
@endif
</div>
