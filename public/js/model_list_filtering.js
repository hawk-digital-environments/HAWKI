// === FILTER RULES ===
const FILTER_RULES = {
    vision: {
        implies: ['file_upload'],
        onlyIf: [],
        prohibits: [],
    },
    // Add more rules as needed
};

// === Global Filter State ===
let inputFilters = new Map();                // Per fieldId => [user filters]


function initModelFilter(){
    inputFilters = new Map();
}


// === Utility: Expand Filters (with implication logic) ===
function expandFilters(filters, rules = FILTER_RULES) {
    const result = new Set(filters);
    let size;
    do {
        size = result.size;
        for (const filter of Array.from(result)) {
            if (rules[filter]?.implies) {
                for (const implied of rules[filter].implies) {
                    result.add(implied);
                }
            }
        }
    } while (result.size !== size);
    return Array.from(result);
}

// === Model Eligibility Function ===
function isModelEligible(model, filters) {
    const expandedFilters = expandFilters(filters);

    // Apply onlyIf and prohibits logic
    for (const filter of expandedFilters) {
        const rule = FILTER_RULES[filter];
        if (rule) {
            if (rule.onlyIf?.length) {
                if (!rule.onlyIf.some(f => expandedFilters.includes(f))) {
                    return false;
                }
            }
            if (rule.prohibits?.length) {
                if (rule.prohibits.some(f => expandedFilters.includes(f))) {
                    return false;
                }
            }
        }
    }

    // All required filters must be true in model.tools
    return expandedFilters.every(f => !!model.tools[f]);
}


// === Filter Models for a Field ===
function filterModels(fieldId) {
    const filters = inputFilters.get(fieldId) || [];
    return modelsList.filter(model => isModelEligible(model, filters));
}

// === Refresh UI: Enable/Disable model selectors ===
function refreshModelList(fieldId, forceSelection = false) {
    const filteredModels = filterModels(fieldId);
    const allowedIds = new Set(filteredModels.map(m => m.id));
    const inputCont = document.querySelector(`.input[id="${fieldId}"]`).closest('.input-container');
    inputCont.querySelectorAll('.model-selector').forEach(button => {
        if(button.dataset.status ==='offline'){
            return;
        }
        // Don't disable buttons - just add/remove 'filtered-out' class for visual feedback
        // Buttons remain clickable and will auto-disable conflicting filters
        if (!allowedIds.has(button.dataset.modelId)) {
            button.classList.add('filtered-out');
        } else {
            button.classList.remove('filtered-out');
        }
    });

    return selectFallbackModel(fieldId, forceSelection);

}

// === Add Input Filter ===
function addInputFilter(fieldId, filterName) {
    const filters = new Set(inputFilters.get(fieldId) || []);
    const wasAdded = !filters.has(filterName);
    filters.add(filterName);
    inputFilters.set(fieldId, Array.from(filters));
    // Force model selection when adding reasoning filter
    return refreshModelList(fieldId, wasAdded && filterName === 'reasoning');
}

// === Remove Input Filter ===
function removeInputFilter(fieldId, filterName) {
    const filters = new Set(inputFilters.get(fieldId) || []);
    filters.delete(filterName);
    inputFilters.set(fieldId, Array.from(filters));
    return refreshModelList(fieldId);
}

// === Clear Input Filters for Field ===
function clearInputFilters(fieldId) {
    inputFilters.set(fieldId, []);
    return refreshModelList(fieldId);
}

// === If the model is to capable, switch to default model ===
function selectFallbackModel(fieldId, forceSelection = false) {
    const filters = inputFilters.get(fieldId) || [];
    const filteredModels = filterModels(fieldId);
    const availableModelIds = new Set(filteredModels.map(model => model.id));

    // Define priority order for fallback selection
    const priorityList = [
        { filter: 'reasoning', fallbackKey: 'default_reasoning_model' },
        { filter: 'web_search', fallbackKey: 'default_web_search_model' },
        { filter: 'vision', fallbackKey: 'default_vision_model' },
        { filter: 'file_upload', fallbackKey: 'default_file_upload_model' },
        { filter: null, fallbackKey: 'default_model' },  // default fallback
    ];

    for (const { filter, fallbackKey } of priorityList) {
        // If the filter is present or we're at default, consider this fallback
        if (!filter || filters.includes(filter)) {
            // Check if active model already matches and is compatible
            // Even when forceSelection is true, keep current model if it's compatible
            if(activeModel && activeModel.id && availableModelIds.has(activeModel.id)){
                return true;
            }
            
            // Try configured default model first
            const fallbackModelId = defaultModels[fallbackKey];
            if (availableModelIds.has(fallbackModelId)) {
                setModel(fallbackModelId);
                return true;
            }
            
            // If no default configured (e.g., DB-based mode without explicit default),
            // use first available model that matches the filter
            if (filter && filteredModels.length > 0) {
                // Sort models for reasoning filter to prioritize certain models
                let sortedModels = filteredModels;
                if (filter === 'reasoning') {
                    sortedModels = sortReasoningModels(filteredModels);
                }
                setModel(sortedModels[0].id);
                return true;
            }
        }
    }

    const input = document.querySelector(`.input[id="${fieldId}"]`).closest('.input-container');
    showFeedbackMsg(input,'error', `${translation.Input_Err_FilterConflict} : ${filters.join(',  ')}`);
    return false;

}


function checkFilterCombination(fieldId, newFilter) {
    if (!newFilter) return false;  // Not a supported file type

    const filters = inputFilters.get(fieldId) || [];
    if (filters.includes(newFilter)) {
        // Already allowed, no new constraint
        return filterModels(fieldId).length > 0;
    }

    // Simulate adding the new filter and get eligible models
    const combinedFilters = [...filters, newFilter];
    const candidateModels = modelsList.filter(model => isModelEligible(model, combinedFilters));

    return candidateModels.length > 0;
}


function getFilterFromMime(mime){
    const type = checkFileFormat(mime);
    switch(type){
        case('pdf'):
        case('docx'):
            return 'file_upload';
        case('image'):
            return 'vision';
        default:
            return null;
    }
}

// === Sort reasoning models to prioritize specific models ===
function sortReasoningModels(models) {
    return [...models].sort((a, b) => {
        // First, sort by provider_display_order (lower = higher priority)
        const providerOrderA = a.provider_display_order ?? 999;
        const providerOrderB = b.provider_display_order ?? 999;
        
        if (providerOrderA !== providerOrderB) {
            return providerOrderA - providerOrderB;
        }
        
        // If same provider, sort by display_order (lower = higher priority)
        const displayOrderA = a.display_order ?? 999;
        const displayOrderB = b.display_order ?? 999;
        
        if (displayOrderA !== displayOrderB) {
            return displayOrderA - displayOrderB;
        }
        
        // If both are equal, sort alphabetically by id as final fallback
        return a.id.localeCompare(b.id);
    });
}
