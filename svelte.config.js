import {componentCssLayerProcessor} from './.svelte/ComponentCssLayerProcessor.js';

/** @type {import("@sveltejs/vite-plugin-svelte").SvelteConfig} */
export default {
    preprocess: [componentCssLayerProcessor()]
};
