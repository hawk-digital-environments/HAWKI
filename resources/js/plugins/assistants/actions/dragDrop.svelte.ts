// $lib/actions/dragDrop.svelte.ts

interface DragDropOptions {
    onDrop: (files: FileList) => void;
    /** Allowed MIME types, e.g. ['application/pdf', 'text/plain']. When set,
     *  the dragged payload is classified as valid/invalid during dragover so
     *  the host can show a type badge. Unknown/empty MIME types default to
     *  valid (the server remains the real enforcer). */
    accept?: string[];
    /** Fired with 'valid' | 'invalid' on drag enter, and 'idle' on leave/drop. */
    onDragState?: (state: "valid" | "invalid" | "idle") => void;
}

export function dragDrop(node: HTMLElement, options: DragDropOptions) {
    let dragCounter = 0;

    // The node needs position:relative so an absolutely-positioned overlay
    // (rendered by the host component) anchors correctly.
    node.style.position = "relative";

    /** Classify the dragged payload against the accept list. Returns 'valid'
     *  when accept is unset or when no concrete MIME type is known (browsers
     *  often report '' during dragover), so legit files are never flagged. */
    function classifyDraggedTypes(e: DragEvent): "valid" | "invalid" {
        if (!options.accept || options.accept.length === 0) return "valid";
        const items = e.dataTransfer?.items;
        if (!items || items.length === 0) return "valid";

        const types: string[] = [];
        for (let i = 0; i < items.length; i++) {
            const it = items[i];
            if (it.kind === "file" && it.type) types.push(it.type);
        }
        if (types.length === 0) return "valid"; // unknown — don't false-flag

        const allowed = new Set(options.accept);
        return types.every((t) => allowed.has(t)) ? "valid" : "invalid";
    }

    function onDragOver(e: DragEvent) {
        e.preventDefault();
        e.stopPropagation();
    }

    function onDragEnter(e: DragEvent) {
        e.preventDefault();
        e.stopPropagation();
        if (++dragCounter === 1) {
            options.onDragState?.(classifyDraggedTypes(e));
        }
    }

    function onDragLeave(e: DragEvent) {
        e.preventDefault();
        e.stopPropagation();
        if (--dragCounter === 0) {
            options.onDragState?.("idle");
        }
    }

    function onDrop(e: DragEvent) {
        e.preventDefault();
        e.stopPropagation();
        dragCounter = 0;
        options.onDragState?.("idle");
        if (e.dataTransfer?.files.length) {
            options.onDrop(e.dataTransfer.files);
        }
    }

    node.addEventListener("dragover", onDragOver);
    node.addEventListener("dragenter", onDragEnter);
    node.addEventListener("dragleave", onDragLeave);
    node.addEventListener("drop", onDrop);

    return {
        update(newOptions: DragDropOptions) {
            options = newOptions;
        },
        destroy() {
            node.removeEventListener("dragover", onDragOver);
            node.removeEventListener("dragenter", onDragEnter);
            node.removeEventListener("dragleave", onDragLeave);
            node.removeEventListener("drop", onDrop);
        },
    };
}
