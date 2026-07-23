<?php

namespace App\Services\Frontend\View;

use Illuminate\View\Component;

/**
 * Blade component `<x-early-frontend-bridge />` that installs lightweight stub functions
 * before the main JavaScript bundle has loaded.
 *
 * Third-party scripts or inline snippets injected into the page head may call
 * `window.waitUntilReady()` or `window.waitUntilBootstrap()` before the HAWKI JS bundle
 * is available. Without this bridge those calls would throw a `TypeError`. This component
 * sets up queue arrays and stub implementations that collect callbacks early and hand them
 * off to the real implementations once the bundle initialises.
 *
 * Place this component as early in the `<head>` as possible, before any external scripts.
 */
class EarlyFrontendBridge extends Component
{
    /**
     * Renders the inline `<script>` tag that sets up the early queues.
     * Returns a raw string because there is no dynamic data.
     */
    public function render(): string
    {
        return <<<'blade'
<script>
window.hawkiEarlyWaitUntilReadyQueue = [];
if (typeof window.waitUntilReady !== 'function') {
    window.waitUntilReady = function (callback) {
        if(window.hawkiIsReady){
            console.warn('Someone came late to the party and called waitUntilReady after ready was already ready. This is not recommended, but we will call the callback immediately.');
            callback();
            return;
        }
        window.hawkiEarlyWaitUntilReadyQueue.push(callback);
    };
}
window.hawkiEarlyWaitUntilBootstrapQueue = [];
if (typeof window.waitUntilBootstrap !== 'function') {
    window.waitUntilBootstrap = function (callback) {
        if(window.hawkiBootstrap){
            console.warn('Someone came late to the party and called waitUntilBootstrap after bootstrap was already ready. This is not recommended, but we will call the callback immediately.');
            callback(window.hawkiBootstrap);
            return;
        }
        window.hawkiEarlyWaitUntilBootstrapQueue.push(callback);
    };
}
</script>
blade;
    }
}
