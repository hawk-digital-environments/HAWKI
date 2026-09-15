import {untrack} from 'svelte';
import type {ModelSlice} from '$plugins/core/modules/chat/components/composer/contexts/slices/ModelSlice.svelte.js';
import type {CheckpointingInterface} from '$plugins/core/modules/chat/components/composer/contexts/utils/CheckpointingInterface.js';
import {type AiToolOrCapabilityWithState, createToolOrCapabilityWithState, createToolOrCapabilityWithStateFromTransferString, validatedToolSnapshot} from '$plugins/core/modules/chat/components/composer/contexts/slices/toolSliceData.js';
import {AiToolStore} from '$lib/plugins/core/stores/AiToolStore.svelte';
import {AiToolOrCapability} from '$lib/plugins/core/stores/aiToolStoreData';

export interface ToolSliceCheckpoint {
    active: string[];
    disabled: string[];
}

/**
 * Composer slice that owns the set of AI tools/capabilities enabled or disabled
 * for the next message.
 *
 * Maintains two registries: `_active` (tools the user turned on, included in the
 * request) and `_disabled` (tools temporarily turned off by a mode — e.g. edit
 * mode disabling attachments — that should return to active when the mode exits).
 * `disable()`/`enable()` move tools between the two without losing their
 * selection/settings; `set()`/`remove()` add or drop a tool outright. Tools are
 * keyed by name and wrapped in {@link AiToolOrCapabilityWithState} via the
 * helpers in `toolSliceData.ts`.
 *
 * Checkpoints store tools as transfer strings (so they survive across the
 * snapshot boundary without holding live `AiToolOrCapability` references) and
 * re-resolve them through the {@link AiToolStore} on restore.
 */
export class ToolSlice implements CheckpointingInterface<ToolSliceCheckpoint> {
    private sessionGeneration: number;
    constructor(
        private model: ModelSlice,
        private toolStore: AiToolStore,
        private onAccessChanged: () => void = () => undefined
    ) {
        this.sessionGeneration = toolStore.sessionGeneration;
        $effect(() => {
            this.toolStore.tools;
            const state = this.toolStore.authorizationState;
            const model = this.model.current;
            const sessionGeneration = this.toolStore.sessionGeneration;
            untrack(() => {
                if (sessionGeneration !== this.sessionGeneration) {
                    this.sessionGeneration = sessionGeneration;
                    this.clear();
                } else if (state === 'ready') {
                    this.reconcile(model);
                }
            });
        });
    }

    public get authorizationPending(): boolean {
        return Object.keys(this._active).length > 0 &&
            (this.toolStore.authorizationState !== 'ready' || this.toolStore.authorizationRefreshing);
    }

    /**
     * Tools the user has explicitly enabled for this message. Included in the API request.
     * Managed via {@link set} / {@link remove}; the list is de-duplicated
     * by tool name automatically.
     */
    private _active = $state<Record<string, AiToolOrCapabilityWithState>>({});
    private _disabled = $state<Record<string, AiToolOrCapabilityWithState>>({});

    public readonly active = $derived.by(() => Object.values(this._active));
    public readonly all = $derived.by(() => Object.values(this._active).concat(Object.values(this._disabled)));

    public get(tool: AiToolOrCapability, includeDisabled?: boolean): AiToolOrCapabilityWithState | null {
        return this._active[tool.name] ?? (includeDisabled ? this._disabled[tool.name] ?? null : null);
    }

    public isActive(tool: AiToolOrCapability): boolean {
        return !!this._active[tool.name];
    }

    /** Resolves a tool from its transfer-string encoding (as produced by
     *  `toTransferString()`) and activates it. `onError` fires instead of activating
     *  when the tool no longer exists or isn't available for the current model. */
    public setFromTransferString(
        transferString: string,
        onError?: (reason: 'tool_not_found' | 'tool_not_available', toolName: string) => void
    ): void {
        const tool = createToolOrCapabilityWithStateFromTransferString(transferString, this.toolStore);
        if (!tool) {
            onError?.('tool_not_found', transferString);
            return;
        }
        if (!tool.isAvailableFor(this.model.current)) {
            onError?.('tool_not_available', tool.name);
            return;
        }
        this.set(tool, tool.toolSelection, tool.toolSettings);
    }

    /** Enables a tool by name or object. No-op if it's already active. */
    public set(
        tool: AiToolOrCapability,
        toolSelection?: AiToolOrCapabilityWithState['toolSelection'],
        toolSettings?: AiToolOrCapabilityWithState['toolSettings']
    ): void {
        if (this.toolStore.authorizationState !== 'ready') return;
        const requested = createToolOrCapabilityWithState(tool, toolSelection, toolSettings);
        const current = createToolOrCapabilityWithStateFromTransferString(requested.toTransferString(), this.toolStore);
        if (!current || !current.isAvailableFor(this.model.current, true)) return;
        this._active[current.name] = current;
        delete this._disabled[tool.name];
    }

    /** Disables a tool by name or object. No-op if it isn't active. */
    public remove(tool: AiToolOrCapability): void {
        delete this._active[tool.name];
        delete this._disabled[tool.name];
    }

    /** Moves an active tool into the disabled registry, preserving its selection/settings
     *  so {@link enable} can restore it later. No-op if the tool isn't active. */
    public disable(tool: AiToolOrCapability): void {
        const currentState = this._active[tool.name];
        if (currentState) {
            this._disabled[tool.name] = currentState;
        }
        delete this._active[tool.name];
    }

    /** Reactivates a previously {@link disable}d tool, restoring its saved selection/settings.
     *  If the tool was never disabled, activates it fresh via {@link set}. */
    public enable(tool: AiToolOrCapability): void {
        const currentState = this._disabled[tool.name];
        if (currentState) {
            this.reconcile();
            if (this._disabled[tool.name]) this.set(currentState, currentState.toolSelection, currentState.toolSettings);
        } else {
            this.set(tool);
        }
    }

    /** Shared submission gate for the current and legacy transports. */
    public validatedActive(): AiToolOrCapabilityWithState[] {
        const requested = this.active.map(tool => tool.toTransferString());
        if (this.reconcile(this.model.current, false)) throw new Error('TOOL_ACCESS_DENIED');
        return validatedToolSnapshot(requested, this.toolStore, this.model.current)
            .map(value => createToolOrCapabilityWithStateFromTransferString(value, this.toolStore)!);
    }

    public clear(): void {
        this._active = {};
        this._disabled = {};
    }

    public createCheckpoint(): ToolSliceCheckpoint {
        return {
            active: this.active.map(t => t.toTransferString()),
            disabled: Object.values(this._disabled).map(t => t.toTransferString())
        };
    }

    public restoreCheckpoint(checkpoint: ToolSliceCheckpoint): void {
        const newActive: Record<string, AiToolOrCapabilityWithState> = {};
        for (const name of checkpoint.active) {
            const tool = createToolOrCapabilityWithStateFromTransferString(name, this.toolStore);
            if (tool && (!tool.is_capability || tool.toolSelection !== 'native' || tool.hasNativeCapabilityFor(this.model.current))) {
                newActive[tool.name] = tool;
            }
        }
        const newDisabled: Record<string, AiToolOrCapabilityWithState> = {};
        for (const name of checkpoint.disabled) {
            const tool = createToolOrCapabilityWithStateFromTransferString(name, this.toolStore);
            if (tool && (!tool.is_capability || tool.toolSelection !== 'native' || tool.hasNativeCapabilityFor(this.model.current))) {
                newDisabled[tool.name] = tool;
            }
        }

        this._active = newActive;
        const removed = Object.keys(newActive).length + Object.keys(newDisabled).length < checkpoint.active.length + checkpoint.disabled.length;
        this._disabled = newDisabled;
        if (removed) this.onAccessChanged();
    }

    /** Rebuild wrappers, including disabled selections, without changing the draft or settings. */
    public reconcile(model = this.model.current, notify = true): boolean {
        if (this.toolStore.authorizationState !== 'ready') return false;
        let removed = false;
        let activeRemoved = false;
        for (const registry of [this._active, this._disabled]) {
            for (const [name, previous] of Object.entries(registry)) {
                const current = createToolOrCapabilityWithStateFromTransferString(previous.toTransferString(), this.toolStore);
                if (!current || (current.is_capability && current.toolSelection === 'native' &&
                    (!model || !current.hasNativeCapabilityFor(model)))) {
                    delete registry[name];
                    removed = true;
                    if (registry === this._active) activeRemoved = true;
                } else {
                    registry[name] = current;
                }
            }
        }
        if (removed && (notify || !activeRemoved)) this.onAccessChanged();
        return activeRemoved;
    }
}
