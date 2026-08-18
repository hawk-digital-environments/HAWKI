import type {DataStore} from '$lib/kernel/stores/types.js';

/** Union of all known experiment ids. Extend when adding a new experiment. */
export type ExperimentId = 'modelPickerV2';

export interface ExperimentDefinition {
    id: ExperimentId;
    /** Translator key for the toggle row title on the experiments settings page. */
    titleKey: string;
    /** Translator key for the toggle row description on the experiments settings page. */
    descriptionKey: string;
}

/** Registry of all available experiments, rendered by `ExperimentsSettings`. */
const EXPERIMENTS: ExperimentDefinition[] = [
    {
        id: 'modelPickerV2',
        titleKey: 'ui.settings.experiments.flags.modelPickerV2.title',
        descriptionKey: 'ui.settings.experiments.flags.modelPickerV2.description'
    }
];

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        'experiments': ExperimentsStore;
    }
}

const STORAGE_KEY = 'hawkiExperiments';

/**
 * Reactive store for client-side experiment flags (opt-in feature toggles).
 *
 * Flags are persisted in `localStorage` under a single JSON object, so they
 * are per-browser preferences without any server round trip. Unknown or
 * unset flags are always off.
 *
 * The registry of available experiments lives in this module ({@link EXPERIMENTS});
 * the settings page renders it, feature code only asks `isEnabled(...)`.
 *
 * @example
 * import {useStore} from '$lib/app/hooks/useStore.svelte.js';
 * const experiments = useStore('experiments');
 * // Read (reactive — tracks inside $derived/templates)
 * const useNewPicker = $derived(experiments.isEnabled('modelPickerV2'));
 * // Write
 * experiments.setEnabled('modelPickerV2', true);
 */
export class ExperimentsStore implements DataStore {
    public readonly name = 'experiments';

    private _flags = $state<Partial<Record<ExperimentId, boolean>>>(readPersistedFlags());

    /** All registered experiments, for the settings page. */
    public get list(): ExperimentDefinition[] {
        return EXPERIMENTS;
    }

    /** Whether the given experiment is enabled. Reactive — reading it inside a
     *  `$derived` or component template tracks it automatically. */
    public isEnabled(id: ExperimentId): boolean {
        return this._flags[id] === true;
    }

    /** Enables/disables an experiment and persists the preference. */
    public setEnabled(id: ExperimentId, enabled: boolean): void {
        this._flags = {...this._flags, [id]: enabled};
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(this._flags));
        } catch (e) {
            // Storage may be unavailable (private mode/quota) — the flag still applies for this page.
        }
    }
}

function readPersistedFlags(): Partial<Record<ExperimentId, boolean>> {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return {};
        }
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch (e) {
        // Storage unavailable or corrupt value — start with everything off.
        return {};
    }
}
