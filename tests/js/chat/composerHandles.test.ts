import assert from 'node:assert/strict';
import {test} from 'node:test';
import {AiHandleStore, type AiAssistant} from '../../../resources/js/plugins/core/stores/AiHandleStore.svelte.js';
import {ComposerContext} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/ComposerContext.svelte.js';
import {ContextCheckpointer} from '../../../resources/js/plugins/core/modules/chat/components/composer/contexts/utils/ContextCheckpointer.js';
import type {HawkiApp} from '../../../resources/js/kernel/HawkiApp.js';

/** The assistants the `aiAssistants` hook contributes in these tests, besides the
 *  store-provided `@hawki` base entry. `@test`/`@test2` form a prefix pair. */
const assistants: AiAssistant[] = [
    {id: 'test:test', handle: '@test', label: 'Test', description: 'A test assistant'},
    {id: 'test:test2', handle: '@test2', label: 'Test 2', description: 'A second test assistant'}
];

/** Feeds the store through a minimal fake app: just the config handle, a hook that
 *  appends the fixtures, and a passthrough translator for the HAWKI base entry. */
async function createHandleStore(): Promise<AiHandleStore> {
    const store = new AiHandleStore();
    await store.loadData({
        config: {get: () => ({ai: {handle: '@hawki'}})},
        hooks: {apply: (_hook: string, base: AiAssistant[]) => [...base, ...assistants]},
        localization: {translator: {translate: (key: string) => key}}
    } as unknown as HawkiApp);
    return store;
}

/** Stand-in for the slice and sender dependencies the constructor stores but these
 *  tests never exercise — they are only reached through mode checkpointing/sending. */
const unused = null as unknown as never;

function createComposer(store: AiHandleStore): ComposerContext {
    return new ComposerContext(
        'room',
        unused, // mode
        unused, // model
        unused, // modelParameters
        unused, // attachments
        unused, // tools
        unused, // modelUsage
        unused, // guard
        new ContextCheckpointer(),
        unused, // sender
        '',
        () => {},
        async () => '',
        (text: string) => store.getHandleMatchesIn(text),
        () => store.assistants
    );
}

test('messageWithoutHandles strips every occurrence of a handle', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = '@test hello @test';
    assert.equal(composer.messageWithoutHandles, 'hello');
});

test('a message that is only repeated handles reduces to empty text', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = '@test @test';
    assert.equal(composer.messageWithoutHandles, '');
});

test('distinct handles are all stripped', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = '@test @hawki hi';
    assert.equal(composer.messageWithoutHandles, 'hi');
});

test('whitespace around stripped handles is normalised', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = '@test  padded  @test';
    assert.equal(composer.messageWithoutHandles, 'padded');
});

test('a known handle that prefixes a longer unknown token leaves that token alone', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = '@test hi @testing';
    assert.equal(composer.messageWithoutHandles, 'hi @testing');
});

test('prefix-paired known handles are both stripped whole', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = '@test hi @test2';
    assert.equal(composer.messageWithoutHandles, 'hi');
});

test('handleCanGrow is true only while a longer known handle extends the token', async () => {
    const store = await createHandleStore();

    assert.equal(store.handleCanGrow('@test'), true); // @test2 extends it
    assert.equal(store.handleCanGrow('@test2'), false);
    assert.equal(store.handleCanGrow('@hawki'), false);
});

test('unknown @tokens stay plain message text', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = 'hi @nonexistingAssistant';
    assert.deepEqual(composer.handlesInMessage, []);
    assert.equal(composer.messageWithoutHandles, 'hi @nonexistingAssistant');
});

test('the message-level handle list stays eager — growth deferral is a textarea concern', async () => {
    const composer = createComposer(await createHandleStore());

    composer.message = '@test'; // could still grow into @test2, but the text is final now
    assert.deepEqual(composer.handlesInMessage, ['@test']);
});
