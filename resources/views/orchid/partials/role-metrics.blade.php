@php
    $roleMetrics = $roleMetrics ?? [];
@endphp

<div class="mb-3">
    <div class="bg-white rounded shadow-sm p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h3 class="text-dark fw-light mb-1">
                    Users per Role | {{ $monthName }}
                </h3>
                <p class="text-muted small mb-0">
                    Total users and new users per Orchid role with growth metrics.
                </p>
            </div>
            @if(count($roleMetrics) > 0)
                <div class="dropdown">
                    <button class="btn btn-sm btn-link text-muted" type="button" id="roleFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <x-orchid-icon path="bs.funnel" class="me-1"/>
                        <span class="d-none d-md-inline">Filter Roles</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-3" aria-labelledby="roleFilterDropdown" style="min-width: 200px;">
                        <div class="mb-2">
                            <strong class="small">Show/Hide Roles</strong>
                        </div>
                        @foreach($roleMetrics as $roleName => $data)
                            <div class="form-check mb-1">
                                <input class="form-check-input role-filter-checkbox" type="checkbox" value="{{ $roleName }}" id="role-filter-{{ Str::slug($roleName) }}" {{ in_array(strtolower($roleName), ['no role', 'administrator']) ? '' : 'checked' }}>
                                <label class="form-check-label small" for="role-filter-{{ Str::slug($roleName) }}">
                                    {{ $roleName }}
                                </label>
                            </div>
                        @endforeach
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-sm btn-link text-muted p-0" id="selectAllRoles">Select All</button>
                            <button type="button" class="btn btn-sm btn-link text-muted p-0" id="deselectAllRoles">Deselect All</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    
    @if(count($roleMetrics) > 0)
        <div class="row" id="roleMetricsContainer">
            @foreach($roleMetrics as $roleName => $data)
                <div class="col-sm-6 col-md-4 col-lg-3 mb-3 role-metric-card" data-role="{{ $roleName }}">
                    <div class="card bg-white rounded shadow-sm h-100">
                        <div class="card-body p-3">
                            {{-- Titel: Rollenname --}}
                            <div class="mb-3">
                                <h5 class="text-dark fw-light mb-0">{{ $roleName }}</h5>
                            </div>
                            
                            {{-- Zeile 1: Anzahl gesamt | Prozentualer Anteil --}}
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                                <div>
                                    <h3 class="mb-0 fw-normal text-dark">{{ number_format($data['totalCount']) }}</h3>
                                    <small class="text-muted">Total Users</small>
                                </div>
                                <div class="text-end">
                                    <h5 class="mb-0 text-secondary">{{ $data['totalPercentage'] }}%</h5>
                                    <small class="text-muted">of all users</small>
                                </div>
                            </div>
                            
                            {{-- Zeile 2: Neue User | Wachstumsrate --}}
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h4 class="mb-0 fw-light text-dark">{{ number_format($data['newThisMonth']) }}</h4>
                                    <small class="text-muted">New</small>
                                </div>
                                <div class="text-end">
                                    @if($data['growthRate'] != 0)
                                        <span class="text-{{ $data['growthRate'] > 0 ? 'success' : 'danger' }} fw-bold">
                                            <x-orchid-icon path="bs.arrow-{{ $data['growthRate'] > 0 ? 'up' : 'down' }}"/>
                                            {{ abs($data['growthRate']) }}%
                                        </span>
                                    @else
                                        <span class="text-secondary">0%</span>
                                    @endif
                                    <div><small class="text-muted">Monthly Change</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white rounded shadow-sm p-4">
            <p class="text-muted text-center mb-0">No role data available for this period.</p>
        </div>
    @endif
</div>

<script>
(function() {
    'use strict';
    
    const STORAGE_KEY = 'hawki_role_metrics_filter';
    let originalChartData = {};
    
    function loadFilterState() {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            return saved ? JSON.parse(saved) : null;
        } catch (e) {
            return null;
        }
    }
    
    function saveFilterState(state) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (e) {
            console.error('Error saving filter state:', e);
        }
    }
    
    function storeOriginalChartData() {
        const chartContainers = document.querySelectorAll('[data-controller="chart"]');
        let foundCharts = 0;
        
        chartContainers.forEach(container => {
            const chartParent = container.getAttribute('data-chart-parent');
            if (chartParent && (chartParent.includes('usersPerRole') || chartParent.includes('activeUsersByRole'))) {
                try {
                    const datasetsAttr = container.getAttribute('data-chart-datasets');
                    if (datasetsAttr) {
                        const datasets = JSON.parse(datasetsAttr);
                        const chartKey = chartParent.replace('#', '');
                        
                        // Prüfe, ob das Chart-Element existiert und initialisiert ist
                        const figure = document.querySelector(chartParent);
                        if (figure && figure.chart) {
                            originalChartData[chartKey] = {
                                container: container,
                                datasets: JSON.parse(JSON.stringify(datasets)),
                                figure: figure
                            };
                            foundCharts++;
                        }
                    }
                } catch (e) {
                    console.error('Error storing chart data:', e);
                }
            }
        });
        
        return foundCharts;
    }
    
    function updateCharts(filterState) {
        Object.keys(originalChartData).forEach(chartKey => {
            const chartData = originalChartData[chartKey];
            
            if (!chartData || !chartData.figure) return;
            
            // Filter datasets based on selected roles
            const filteredDatasets = chartData.datasets.filter(dataset => {
                return filterState[dataset.name] !== false;
            });
            
            // Get Frappe Chart instance
            const chartInstance = chartData.figure.chart;
            
            if (chartInstance && typeof chartInstance.update === 'function') {
                const labelsAttr = chartData.container.getAttribute('data-chart-labels');
                const labels = labelsAttr ? JSON.parse(labelsAttr) : [];
                
                try {
                    chartInstance.update({
                        labels: labels,
                        datasets: filteredDatasets
                    });
                } catch (e) {
                    console.error('Error updating chart:', e);
                }
            }
        });
    }
    
    function applyFilter(shouldSave = true) {
        const checkboxes = document.querySelectorAll('.role-filter-checkbox');
        if (checkboxes.length === 0) return;
        
        const state = {};
        
        checkboxes.forEach(checkbox => {
            const roleName = checkbox.value;
            const isChecked = checkbox.checked;
            state[roleName] = isChecked;
            
            const cards = document.querySelectorAll(`.role-metric-card[data-role="${roleName}"]`);
            cards.forEach(card => {
                card.style.display = isChecked ? '' : 'none';
            });
        });
        
        if (shouldSave) {
            saveFilterState(state);
        }
        
        updateCharts(state);
        checkEmptyState();
    }
    
    function checkEmptyState() {
        const container = document.getElementById('roleMetricsContainer');
        if (!container) return;
        
        const visibleCards = Array.from(document.querySelectorAll('.role-metric-card'))
            .filter(card => card.style.display !== 'none');
        
        let emptyMessage = document.getElementById('roleMetricsEmptyMessage');
        
        if (visibleCards.length === 0) {
            if (!emptyMessage) {
                emptyMessage = document.createElement('div');
                emptyMessage.id = 'roleMetricsEmptyMessage';
                emptyMessage.className = 'col-12';
                emptyMessage.innerHTML = '<div class="bg-white rounded shadow-sm p-4"><p class="text-muted text-center mb-0">All roles are hidden. Use the filter to show roles.</p></div>';
                container.appendChild(emptyMessage);
            }
        } else {
            if (emptyMessage) {
                emptyMessage.remove();
            }
        }
    }
    
    function bindEventListeners() {
        document.querySelectorAll('.role-filter-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                applyFilter(true);
            });
        });
        
        const selectAllBtn = document.getElementById('selectAllRoles');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('.role-filter-checkbox').forEach(cb => cb.checked = true);
                applyFilter(true);
            });
        }
        
        const deselectAllBtn = document.getElementById('deselectAllRoles');
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('.role-filter-checkbox').forEach(cb => cb.checked = false);
                applyFilter(true);
            });
        }
    }
    
    function initializeFilter() {
        const savedState = loadFilterState();
        const checkboxes = document.querySelectorAll('.role-filter-checkbox');
        
        if (checkboxes.length === 0) return;
        
        if (savedState && Object.keys(savedState).length > 0) {
            checkboxes.forEach(checkbox => {
                const roleName = checkbox.value;
                if (savedState.hasOwnProperty(roleName)) {
                    checkbox.checked = savedState[roleName];
                }
            });
            applyFilter(false);
        } else {
            applyFilter(true);
        }
    // Warte auf Charts mit Retry-Logik
    function waitForChartsAndInitialize() {
        const maxRetries = 10;
        let retryCount = 0;
        
        function tryInitialize() {
            const foundCharts = storeOriginalChartData();
            
            if (foundCharts > 0 || retryCount >= maxRetries) {
                initializeFilter();
            } else {
                retryCount++;
                setTimeout(tryInitialize, 300);
            }
        }
        
        tryInitialize();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(waitForChartsAndInitialize, 500);
        });
    } else {
        setTimeout(waitForChartsAndInitialize, 500);
    }
    
    // Also listen for turbo:load to re-initialize after page changes
    document.addEventListener('turbo:load', function() {
        originalChartData = {};
        setTimeout(waitForChartsAndInitialize, .addEventListener('turbo:load', function() {
        setTimeout(function() {
            storeOriginalChartData();
            initializeFilter();
        }, 1500);
    });
})();
</script>
