import type {AiTool} from '$lib/schemas/resources/ai-tools.schema.js';
import type {ModelAspect} from '$lib/components/chat/composer/contexts/aspects/ModelApsect.svelte.js';
import type {AiToolStore} from '$lib/stores/AiToolStore.svelte.js';
import type {AiToolCapability} from '$lib/schemas/resources/ai-tools-capabilities.schema.js';
import type {CheckpointingInterface} from '$lib/components/chat/composer/contexts/utils/CheckpointingInterface.js';

export interface ToolAspectCheckpoint {
    activeToolNames: string[];
}

export class ToolAspect implements CheckpointingInterface<ToolAspectCheckpoint> {
    constructor(
        private model: ModelAspect,
        private toolStore: AiToolStore
    ) {
    }

    /**
     * Tools the user has explicitly enabled for this message. Included in the API request.
     * Managed via {@link add} / {@link remove}; the list is de-duplicated
     * by tool name automatically.
     */
    private _active = $state<AiTool[]>([]);

    public get active(): AiTool[] {
        return this._active;
    }

    /**
     * Returns `true` when the current model supports tool calling AND the given tool
     * is in the model's available tool list. Safe to call without a prior capability check.
     */
    public canUse(tool: AiTool | string): boolean {
        if (!this.model.current || !this.model.allowsToolCalling) {
            return false;
        }
        return this.toolStore.isAvailableForModel(tool, this.model.current);
    }

    /** Enables a tool by name or object. No-op if it's already active. */
    public add(tool: string | AiTool): void {
        const toolObj = this.resolveTool(tool);
        if (!toolObj) {
            return;
        }
        if (!this.active.some(t => t.name === toolObj.name)) {
            this._active = [...this._active, toolObj];
        }
    }

    /** Disables a tool by name or object. No-op if it isn't active. */
    public remove(tool: string | AiTool): void {
        const toolObj = this.resolveTool(tool);
        if (!toolObj) {
            return;
        }
        this._active = this.active.filter(t => t.name !== toolObj.name);
    }

    public clear(): void {
        this._active = [];
    }

    /**
     * Capability definitions supported by the current model, or `false` when tool
     * calling is disabled. Check truthiness before rendering the capabilities UI.
     */
    public availableCapabilities: AiToolCapability[] | null = $derived.by(() => {
        const model = this.model.current;
        if (!model || !this.model.allowsToolCalling) {
            return null;
        }

        return model ? this.toolStore.availableCapabilitiesForModel(model) : [];
    });

    /**
     * Tools supported by the current model, or `false` when tool calling is disabled.
     * This is the full set the model *can* use — `activeTools` is what the user has *chosen* to enable.
     */
    public available: AiTool[] | null = $derived.by(() => {
        const model = this.model.current;
        if (!model || !this.model.allowsToolCalling) {
            return null;
        }

        return model ? this.toolStore.availableToolsForModel(model) : [];
    });

    private resolveTool(toolName: string | AiTool): AiTool | null {
        if (typeof toolName === 'object') {
            return toolName;
        }
        const tool = this.toolStore.tools
            .find(t => t.name === toolName);

        if (!tool) {
            console.warn(`Tool with name ${toolName} not found in store.`);
            return null;
        }
        return tool;
    }

    public createCheckpoint(): ToolAspectCheckpoint {
        return {
            activeToolNames: this.active.map(t => t.name)
        };
    }

    public restoreCheckpoint(checkpoint: ToolAspectCheckpoint): void {
        this._active = checkpoint.activeToolNames
            .map(name => this.resolveTool(name))
            .filter((t): t is AiTool => t !== null);
    }
}
