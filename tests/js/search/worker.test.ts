import test from 'node:test';
import assert from 'node:assert/strict';
import {SearchWorkerClient} from '$lib/kernel/search/SearchWorkerClient.js';
import {SearchEngine} from '$lib/kernel/search/searchEngine.js';
import {handleSearchWorkerRequest, type SearchWorkerRequest} from '$lib/kernel/search/search.worker.js';
import {SharedSearchIndex, type IndexedProvider} from '$lib/kernel/search/sharedIndex.js';

class FakeWorker extends EventTarget {
    engine = new SearchEngine();
    held: SearchWorkerRequest[] = [];
    hold = false;
    fail = false;
    terminated = false;
    postMessage(request: SearchWorkerRequest) {
        if (this.fail) throw new Error('Worker transport failed');
        if (this.hold && request.type === 'search') {this.held.push(request); return;}
        const response = handleSearchWorkerRequest(this.engine, request);
        queueMicrotask(() => this.dispatchEvent(new MessageEvent('message', {data: response})));
    }
    terminate() {this.terminated = true;}
}
const provider: IndexedProvider = {id:'core:test.worker',groupId:'core:test.worker',pluginId:'core',moduleId:'core:test',kind:'static',matchIn:'worker',order:0};
function fixture() {
    const index = new SharedSearchIndex();
    const workers: FakeWorker[] = [];
    const client = new SearchWorkerClient(index, {createWorker: () => {
        const worker = new FakeWorker(); workers.push(worker); return worker as unknown as Worker;
    }, deadlineMs:50});
    index.setProviderEntries(provider,[{id:'one',entityKey:'one',title:'Design',onSelect(){}}]);
    return {index,client,workers};
}

test('worker startup, incremental changes and retry replay the current corpus', async () => {
    const {index,client,workers}=fixture();
    try {
        assert.equal((await client.search('design',new AbortController().signal)).scores.length,1);
        index.removeProvider(provider.id);
        client.retry();
        assert.equal(workers[0].terminated,true);
        assert.equal((await client.search('design',new AbortController().signal)).scores.length,0);
        assert.equal(workers[1].engine.size,0);
    } finally {client.dispose();}
});

test('worker cancellation settles a query and ignores its late response', async () => {
    const {client,workers}=fixture();
    try {
        await client.search('design',new AbortController().signal);
        workers[0].hold=true;
        const controller=new AbortController();
        const promise=client.search('design',controller.signal);
        controller.abort();
        await assert.rejects(promise,{name:'AbortError'});
        workers[0].hold=false;
        workers[0].postMessage(workers[0].held[0]);
        assert.equal((await client.search('design',new AbortController().signal)).scores.length,1);
    } finally {client.dispose();}
});

test('worker uses Fuse substring matching and reflects renamed entries', async () => {
    const {index, client} = fixture();
    try {
        const {scores} = await client.search('sign', new AbortController().signal);
        assert.equal(scores.length, 1);
        assert.ok(scores[0][1] > 0);

        index.setProviderEntries(provider, [{id: 'one', entityKey: 'one', title: 'Release', onSelect(){}}]);
        assert.deepEqual((await client.search('sign', new AbortController().signal)).scores, []);
        assert.equal((await client.search('lease', new AbortController().signal)).scores.length, 1);
    } finally {client.dispose();}
});

test('worker transport failure settles pending requests and is retryable', async () => {
    const {client,workers}=fixture();
    try {
        await client.search('design',new AbortController().signal);
        workers[0].fail=true;
        await assert.rejects(client.search('design',new AbortController().signal),/could not receive data/);
        client.retry();
        assert.equal((await client.search('design',new AbortController().signal)).scores.length,1);
    } finally {client.dispose();}
});

test('worker timeout and incorrect revisions reject instead of publishing scores', async () => {
    const {client,workers}=fixture();
    try {
        await client.search('design',new AbortController().signal);
        workers[0].hold=true;
        const promise=client.search('design',new AbortController().signal);
        const request=workers[0].held[0];
        if(request.type!=='search') throw new Error('Expected a search request');
        workers[0].dispatchEvent(new MessageEvent('message',{data:{type:'scores',requestId:request.requestId,revision:request.revision-1,scores:[]}}));
        await assert.rejects(promise,/outdated revision/);
        await assert.rejects(client.search('design',new AbortController().signal),/did not answer in time/);
    } finally {client.dispose();}
});
