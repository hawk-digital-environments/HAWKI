import {SearchEngine, type SearchDocument} from './searchEngine.js';

/**
 * Web Worker wrapping `SearchEngine`, so indexing thousands of palette rows
 * (e.g. the chat message index) and fuzzy-matching each keystroke never
 * block the input. Spoken to by `SearchMatcher`; the message shapes below are
 * the whole protocol.
 */

export type SearchWorkerRequest =
    | {type: 'replace'; documents: SearchDocument[]}
    | {type: 'search'; requestId: number; query: string};

export type SearchWorkerResponse =
    {type: 'scores'; requestId: number; scores: Array<[string, number]>};

const engine = new SearchEngine();

self.onmessage = (event: MessageEvent<SearchWorkerRequest>) => {
    const message = event.data;
    if (message.type === 'replace') {
        engine.replace(message.documents);
        return;
    }
    const response: SearchWorkerResponse = {
        type: 'scores',
        requestId: message.requestId,
        scores: engine.search(message.query)
    };
    self.postMessage(response);
};
