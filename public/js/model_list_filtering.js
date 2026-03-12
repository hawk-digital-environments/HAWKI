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
let regenerationFilters = [];                // Temporary filters for regeneration menu


function initModelFilter(){
    inputFilters = new Map();
    regenerationFilters = [];
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

    // All required capabilities must be present and supported in model.capabilities.
    // A capability is considered supported when its value is a non-empty, non-false,
    // non-'unsupported' entry — covering booleans, 'native', and tool-name strings
    // injected from DB assignments.
    return expandedFilters.every(f => {
        const val = model.capabilities?.[f];
        return val !== undefined && val !== null && val !== false && val !== 'unsupported';
    });
}


// === Filter Models for a Field or Context ===
function filterModels(fieldId = null, filters = null) {
    // If filters are provided directly, use them (for regeneration)
    // Otherwise, get filters from inputFilters Map (for input fields)
    const activeFilters = filters !== null ? filters : (inputFilters.get(fieldId) || []);
    return modelsList.filter(model => isModelEligible(model, activeFilters));
}

// === Refresh UI: Enable/Disable model selectors ===
function refreshModelList(fieldId, context = 'input') {
    const success = selectFallbackModel(fieldId, context);
    if(success) {
        const filters = context === 'regeneration' ? regenerationFilters : (inputFilters.get(fieldId) || []);
        const filteredModels = filterModels(fieldId, filters);
        const allowedIds = new Set(filteredModels.map(m => m.id));

        let container;
        if(context === 'regeneration') {
            container = document.getElementById('regenerate-controls');
        } else {
            container = document.querySelector(`.input[id="${fieldId}"]`)?.closest('.input-container');
        }

        if(container) {
            container.querySelectorAll('.model-selector').forEach(button => {
                if(button.dataset.status === 'offline'){
                    return;
                }
                button.disabled = !allowedIds.has(button.dataset.modelId);
            });
        }
    }
    return success;
}

// === Add Input Filter ===
function addInputFilter(fieldId, filterName) {
    const filters = new Set(inputFilters.get(fieldId) || []);
    filters.add(filterName);
    console.log(filters)
    inputFilters.set(fieldId, Array.from(filters));
    return refreshModelList(fieldId);
}

// === Remove Input Filter ===
function removeInputFilter(fieldId, filterName) {
    const filters = new Set(inputFilters.get(fieldId) || []);
    filters.delete(filterName);
    console.log(filters)

    inputFilters.set(fieldId, Array.from(filters));
    return refreshModelList(fieldId);
}

// === Clear Input Filters for Field ===
function clearInputFilters(fieldId) {
    inputFilters.set(fieldId, []);
    return refreshModelList(fieldId);
}

// === If the model is incapable, switch to default model ===
function selectFallbackModel(fieldId, context = 'input', currentModel = null) {
    const filters = context === 'regeneration' ? regenerationFilters : (inputFilters.get(fieldId) || []);
    const filteredModels = filterModels(fieldId, filters);
    const availableModelIds = new Set(filteredModels.map(m => m.id));

    // For regeneration context, use the provided currentModel; otherwise use activeModel
    const modelToCheck = context === 'regeneration' ? currentModel : activeModel;

    // 1️⃣ keep current model if still valid
    if (isModelUsable(modelToCheck, filters, availableModelIds)) {
        return true;
    }

    // 2️⃣ priority-based fallback
    const priorityList = [
        { filter: 'web_search', fallbackKey: 'default_web_search_model' },
        { filter: 'vision', fallbackKey: 'default_vision_model' },
        { filter: 'file_upload', fallbackKey: 'default_file_upload_model' },
        { filter: null, fallbackKey: 'default_model' }
    ];

    for (const { filter, fallbackKey } of priorityList) {
        if (filter && !filters.includes(filter)) continue;

        let candidateId = defaultModels[fallbackKey];
        let candidate = modelsList.find(m => m.id === candidateId);

        if (isModelUsable(candidate, filters, availableModelIds)) {
            if (context === 'regeneration') {
                return candidate; // Return the model for regeneration context
            } else {
                setModel(candidate.id);
                return true;
            }
        }
    }

    // 3️⃣ final fallback → first active compatible model
    const firstAvailable = filteredModels.find(m =>
        isModelUsable(m, filters, availableModelIds)
    );

    if (firstAvailable) {
        if (context === 'regeneration') {
            return firstAvailable;
        } else {
            setModel(firstAvailable.id);
            return true;
        }
    }

    // 4️⃣ nothing works → error
    if (context === 'input') {
        const input = document
            .querySelector(`.input[id="${fieldId}"]`)
            ?.closest('.input-container');

        showFeedbackMsg(
            input,
            'error',
            `${translation.Input_Err_FilterConflict} : ${
                filters.map(formatFilterName).join(', ')
            }`
        );
    }

    return false;
}

function formatFilterName(filter) {
    return filter
        .replace(/_/g, ' ')
        .replace(/\b\w/g, c => c.toUpperCase());
}

function isModelUsable(model, filters, availableModelIds) {
    if (!model) return false;
    if (!model.active) return false;
    if (!availableModelIds.has(model.id)) return false;

    return isModelEligible(model, filters);
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

// === Regeneration-specific Functions ===

// Set filters for regeneration context
function setRegenerationFilters(tools) {
    regenerationFilters = Array.isArray(tools) ? [...tools] : [];
}

// Add a filter to regeneration context
function addRegenerationFilter(filterName) {
    const filters = new Set(regenerationFilters);
    filters.add(filterName);
    regenerationFilters = Array.from(filters);
}

// Remove a filter from regeneration context
function removeRegenerationFilter(filterName) {
    const filters = new Set(regenerationFilters);
    filters.delete(filterName);
    regenerationFilters = Array.from(filters);
}

// Clear regeneration filters
function clearRegenerationFilters() {
    menu.querySelectorAll(`.tool-selector`).forEach(btn => {btn.classList.remove('active')});

    regenerationFilters = [];
}

// Refresh regeneration model list based on current filters
function refreshRegenerationModelList(currentModel) {
    const result = selectFallbackModel(null, 'regeneration', currentModel);

    if (result && typeof result === 'object') {
        // A fallback model was selected
        return result;
    } else if (result === true) {
        // Current model is still valid
        return currentModel;
    } else {
        // No valid models available
        return null;
    }
}
