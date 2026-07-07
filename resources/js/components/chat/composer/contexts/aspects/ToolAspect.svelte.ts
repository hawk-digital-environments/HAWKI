import type {ModelAspect} from '$lib/components/chat/composer/contexts/aspects/ModelApsect.svelte.js';
import type {AiToolStore} from '$lib/stores/AiToolStore.svelte.js';
import type {CheckpointingInterface} from '$lib/components/chat/composer/contexts/utils/CheckpointingInterface.js';
import type {AiToolOrCapability} from '$lib/stores/aiToolStoreData.js';
import {type AiToolOrCapabilityWithState, createToolOrCapabilityWithState, createToolOrCapabilityWithStateFromTransferString} from '$lib/components/chat/composer/contexts/aspects/toolAspectData.js';

export interface ToolAspectCheckpoint {
    active: string[];
    disabled: string[];
}

export class ToolAspect implements CheckpointingInterface<ToolAspectCheckpoint> {
    constructor(
        private model: ModelAspect,
        private toolStore: AiToolStore
    ) {
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
        this._active[tool.name] = createToolOrCapabilityWithState(tool, toolSelection, toolSettings);
        delete this._disabled[tool.name];
    }

    /** Disables a tool by name or object. No-op if it isn't active. */
    public remove(tool: AiToolOrCapability): void {
        delete this._active[tool.name];
        delete this._disabled[tool.name];
    }

    public disable(tool: AiToolOrCapability): void {
        const currentState = this._active[tool.name];
        if (currentState) {
            this._disabled[tool.name] = currentState;
        }
        delete this._active[tool.name];
    }

    public enable(tool: AiToolOrCapability): void {
        const currentState = this._disabled[tool.name];
        if (currentState) {
            this._active[tool.name] = currentState;
            delete this._disabled[tool.name];
        } else {
            this.set(tool);
        }
    }

    public clear(): void {
        this._active = {};
        this._disabled = {};
    }

    public createCheckpoint(): ToolAspectCheckpoint {
        return {
            active: this.active.map(t => t.toTransferString()),
            disabled: Object.values(this._disabled).map(t => t.toTransferString())
        };
    }

    public restoreCheckpoint(checkpoint: ToolAspectCheckpoint): void {
        const newActive: Record<string, AiToolOrCapabilityWithState> = {};
        for (const name of checkpoint.active) {
            const tool = createToolOrCapabilityWithStateFromTransferString(name, this.toolStore);
            if (tool) {
                newActive[tool.name] = tool;
            }
        }
        const newDisabled: Record<string, AiToolOrCapabilityWithState> = {};
        for (const name of checkpoint.disabled) {
            const tool = createToolOrCapabilityWithStateFromTransferString(name, this.toolStore);
            if (tool) {
                newDisabled[tool.name] = tool;
            }
        }

        this._active = newActive;
        this._disabled = newDisabled;
    }
}
