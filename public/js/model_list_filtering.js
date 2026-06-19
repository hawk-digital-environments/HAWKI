// === FILTER RULES ===
const FILTER_RULES = {
    vision: {
        implies: ['file_upload'],
        onlyIf: [],
        prohibits: []
    }
    // Add more rules as needed
};

// === Global Filter State ===
let inputFilters = new Map();                // Per fieldId => [user filters]
let regenerationFilters = [];                // Temporary filters for regeneration menu


function initModelFilter() {
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
    const capabilitiesOfModel = hawkiConnection('ai.legacy.capabilitiesOfModels')[model.modelId] || [];
    return expandedFilters.every(f => {
        return capabilitiesOfModel.includes(f);
    });
}


// === Filter Models for a Field or Context ===
function filterModels(fieldId = null, filters = null) {
    // If filters are provided directly, use them (for regeneration)
    // Otherwise, get filters from inputFilters Map (for input fields)
    const activeFilters = filters !== null ? filters : (inputFilters.get(fieldId) || []);
    return hawkiConnection('ai.legacy.models').filter(model => isModelEligible(model, activeFilters));
}

// === Refresh UI: Enable/Disable model selectors ===
function refreshModelList(fieldId, context = 'input') {
    const success = selectFallbackModel(fieldId, context);
    if (success) {
        const filters = context === 'regeneration' ? regenerationFilters : (inputFilters.get(fieldId) || []);
        const filteredModels = filterModels(fieldId, filters);
        const allowedIds = new Set(filteredModels.map(m => m.modelId));

        let container;
        if (context === 'regeneration') {
            container = document.getElementById('regenerate-controls');
        } else {
            container = document.querySelector(`.input[id="${fieldId}"]`)?.closest('.input-container');
        }

        if (container) {
            container.querySelectorAll('.model-selector').forEach(button => {
                if (button.dataset.status === 'offline') {
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
    if (!filterName) {
        throw new Error('Filter name must be provided to addInputFilter');
    }
    const filters = new Set(inputFilters.get(fieldId) || []);
    filters.add(filterName);
    inputFilters.set(fieldId, Array.from(filters));
    dispatchModelStateChange();
    return true;
}

function getInputFilters(fieldId) {
    return inputFilters.get(fieldId) || [];
}

// === Remove Input Filter ===
function removeInputFilter(fieldId, filterName) {
    const filters = new Set(inputFilters.get(fieldId) || []);
    filters.delete(filterName);

    inputFilters.set(fieldId, Array.from(filters));
    dispatchModelStateChange();
    return true;
}

// === Clear Input Filters for Field ===
function clearInputFilters(fieldId) {
    inputFilters.set(fieldId, []);
    dispatchModelStateChange();
    return true;
}

// === If the model is incapable, switch to default model ===
function selectFallbackModel(fieldId, context = 'input', currentModel = null) {
    const filters = context === 'regeneration' ? regenerationFilters : (inputFilters.get(fieldId) || []);
    const filteredModels = filterModels(fieldId, filters);
    const availableModelIds = new Set(filteredModels.map(m => m.modelId));

    // For regeneration context, use the provided currentModel; otherwise use activeModel
    const modelToCheck = context === 'regeneration' ? currentModel : activeModel;

    // 1️⃣ keep current model if still valid
    if (isModelUsable(modelToCheck, filters, availableModelIds)) {
        return true;
    }

    // 2️⃣ priority-based fallback
    const systemModels = hawkiConnection('ai.legacy.systemModels');
    const systemModelKeys = Object.keys(systemModels);
    for (const systemModelKey of systemModelKeys) {
        if (systemModelKey && !filters.includes(systemModelKey)) {
            continue;
        }

        const candidateId = systemModels[systemModelKey];
        const candidate = hawkiConnection('ai.legacy.models').find(m => m.modelId === candidateId);
        if (isModelUsable(candidate, filters, availableModelIds)) {
            if (context === 'regeneration') {
                return candidate; // Return the model for regeneration context
            } else {
                setModel(candidate.modelId);
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
            `${__('Input_Err_FilterConflict')} : ${
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
    if (!availableModelIds.has(model.modelId)) return false;

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
    const candidateModels = hawkiConnection('ai.legacy.models').filter(model => isModelEligible(model, combinedFilters));

    return candidateModels.length > 0;
}


function getFilterFromMime(mime) {
    const type = checkFileFormat(mime);
    switch (type) {
        case('image'):
            return 'vision';
        default:
            return 'file_upload';
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
    menu.querySelectorAll(`.tool-selector`).forEach(btn => {
        btn.classList.remove('active');
    });

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

/**
 * @param {string|HTMLButtonElement} inputId
 * @param {string|string[]|null} [capabilities]
 * @param {string|string[]|null} [tools]
 */
function getListOfModelsSupporting(inputId, capabilities, tools) {
    return [];
    if (inputId instanceof HTMLButtonElement) {
        let resolvedId = inputId.closest('.input')?.id;
        // If we do not find an input parent, it might be because the button is outside the input element,
        // but still within the input container. In that case, we should look for the closest input container and then find the input within it.
        if (!resolvedId) {
            const closestInputContainer = inputId.closest('.input-container');
            resolvedId = closestInputContainer?.querySelector('.input')?.id;
            if (!resolvedId) {
                console.error('Could not find parent input element for button', inputId, 'falling back to empty model list');
                return [];
            }
        }
        inputId = resolvedId;
    }

    const models = hawkiConnection('ai.legacy.models');
    const capabilitiesOfModels = hawkiConnection('ai.legacy.capabilitiesOfModels');
    const toolsOfModels = hawkiConnection('ai.legacy.toolsOfModels');

    // Filters do not differentiate between capabilities and tools, so we need to separate them based on known lists
    const knownTools = Object.keys(hawkiConnection('ai.legacy.toolLabels'));
    const knownCapabilities = Object.keys(hawkiConnection('ai.legacy.capabilityLabels'));
    const activeFilters = getInputFilters(inputId);
    const activeCapabilityConstraints = activeFilters.filter(f => knownCapabilities.includes(f));
    const activeToolConstraints = activeFilters.filter(f => knownTools.includes(f));

    // We must intelligently merge the active constraints with the provided ones, ensuring we don't lose any existing constraints while also adding new ones.
    function arrayFormMergeUnique(active, provided) {
        const providedArray = Array.isArray(provided) ? provided : (provided ? [provided] : []);
        const mergedSet = new Set([...active, ...providedArray]);
        return Array.from(mergedSet);
    }

    const capabilityConstraints = arrayFormMergeUnique(activeCapabilityConstraints, capabilities);
    const toolConstraints = arrayFormMergeUnique(activeToolConstraints, tools);

    console.log('Filtering models for input', inputId, 'with capability constraints', capabilityConstraints, 'and tool constraints', toolConstraints);

    return models.filter(model => {
        const modelCapabilities = capabilitiesOfModels[model.modelId] || [];
        const modelTools = toolsOfModels[model.modelId] || [];

        const hasCapabilities = capabilityConstraints.every(c => modelCapabilities.includes(c));
        const hasTools = toolConstraints.every(t => modelTools.includes(t));

        return hasCapabilities && hasTools;
    });
}

function canSupportAdding(inputId, newCapability, newTool, activeModel) {
    const models = getListOfModelsSupporting(inputId, newCapability, newTool);
    if (activeModel) {
        const activeModelId = typeof activeModel === 'string' ? activeModel : activeModel.modelId;
        console.log('SUPPORTING IDS', models.map(m => m.modelId), 'ACTIVE MODEL ID', activeModelId);
        return models.some(m => m.modelId === activeModelId);
    }
    return models.length > 0;
}
