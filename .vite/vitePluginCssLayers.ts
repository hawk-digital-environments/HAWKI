import type {Plugin} from 'vite';

interface CssLayerEntry {
    path: string;
    layer: string;
}

export function vitePluginCssLayers(entries: CssLayerEntry[]): Plugin {
    return {
        name: 'vite-plugin-css-layers',
        enforce: 'pre',
        transform(code, id) {
            const cleanId = id.split('?')[0];

            if (!cleanId.endsWith('.css')) return null;

            const match = entries.find(e => cleanId.endsWith(e.path));
            if (!match) return null;

            return `@layer ${match.layer} {\n${code}\n}`;
        }
    };
}
