<?php

namespace App\Services\Frontend\View;

use Illuminate\View\Component;

class EarlyFrontendBridge extends Component
{
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
