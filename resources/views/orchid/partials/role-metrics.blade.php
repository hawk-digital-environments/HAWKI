@php
    $roleMetrics = $roleMetrics ?? [];
@endphp

<fieldset class="mb-3">
    <legend class="text-body-emphasis px-4 mb-0">Users per Role ({{ $monthName }})</legend>
    
    <div class="row mb-2 g-3 g-mb-4">
        <div class="col-12">
            <div class="bg-white rounded p-4 h-100">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <small class="text-muted d-block">Total users and new users per Orchid role with growth metrics.</small>
                    @if(count($roleMetrics) > 0)
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" id="roleFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
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
        <div class="alert alert-info mb-0 mt-2">
            No role data available for this period.
        </div>
    @endif
            </div>
        </div>
    </div>
</fieldset>

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
        chartContainers.forEach(container => {
            const chartParent = container.getAttribute('data-chart-parent');
            if (chartParent && (chartParent.includes('usersPerRole') || chartParent.includes('activeUsersByRole'))) {
                try {
                    const datasetsAttr = container.getAttribute('data-chart-datasets');
                    if (datasetsAttr) {
                        const datasets = JSON.parse(datasetsAttr);
                        const chartKey = chartParent.replace('#', '');
                        originalChartData[chartKey] = {
                            container: container,
                            datasets: JSON.parse(JSON.stringify(datasets))
                        };
                    }
                } catch (e) {
                    console.error('Error storing chart data:', e);
                }
            }
        });
    }
    
    function updateCharts(filterState) {
        Object.keys(originalChartData).forEach(chartKey => {
            const chartData = originalChartData[chartKey];
            const container = chartData.container;
            
            if (!container) return;
            
            // Filter datasets based on selected roles
            const filteredDatasets = chartData.datasets.filter(dataset => {
                return filterState[dataset.name] !== false;
            });
            
            // Get chart parent figure
            const figureId = container.getAttribute('data-chart-parent');
            const figure = document.querySelector(figureId);
            if (!figure) return;
            
            // Try to find Frappe Chart instance
            const chartInstance = figure.chart;
            
            if (chartInstance && typeof chartInstance.update === 'function') {
                // Update existing Frappe Chart
                const labelsAttr = container.getAttribute('data-chart-labels');
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
        
        bindEventListeners();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                storeOriginalChartData();
                initializeFilter();
            }, 1500);
        });
    } else {
        setTimeout(function() {
            storeOriginalChartData();
            initializeFilter();
        }, 1500);
    }
    
    // Also listen for turbo:load to re-initialize after page changes
    document.addEventListener('turbo:load', function() {
        setTimeout(function() {
            storeOriginalChartData();
            initializeFilter();
        }, 1500);
    });
})();
</script>
