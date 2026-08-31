import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen, waitFor, within } from '@testing-library/svelte';
import userEvent from '@testing-library/user-event';
import { createRawSnippet } from 'svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import SingleSelect from '../SingleSelect.svelte';
import SingleSelectFixture from './SingleSelectFixture.svelte';

// Structural copies of the component's exported types — plain .ts files cannot
// import named type exports from a .svelte module script (only svelte-check can).
interface SelectItemDefinition {
    value: string;
    label: string;
    disabled?: boolean;
    groupLabel?: string;
}

interface ItemSnippetProps {
    item: SelectItemDefinition;
    selected: boolean;
}

type User = Awaited<ReturnType<typeof userEvent.setup>>;

vi.mock('$lib/app/hooks/useTranslator.svelte.js', () => ({
    useTranslator: () => ({ __: (label: string) => label })
}));

const fruits: SelectItemDefinition[] = [
    { value: 'apple', label: 'Apple' },
    { value: 'banana', label: 'Banana' },
    { value: 'cherry', label: 'Cherry', disabled: true }
];

const groupedItems: SelectItemDefinition[] = [
    { value: 'zucchini', label: 'Zucchini', groupLabel: 'Vegetables' },
    { value: 'apple', label: 'Apple', groupLabel: 'Fruits' },
    { value: 'banana', label: 'Banana', groupLabel: 'Fruits' },
    { value: 'carrot', label: 'Carrot', groupLabel: 'Vegetables' }
];

/** Opens the select by clicking the trigger like a real user would. */
async function openSelect(user: User, trigger: HTMLElement): Promise<HTMLElement> {
    await user.click(trigger);
    const listbox = await screen.findByRole('listbox');
    expect(trigger).toHaveAttribute('aria-expanded', 'true');
    return listbox;
}

/** Clicks an option like a real user would and returns the chosen element. */
async function clickOption(user: User, listbox: HTMLElement, name: string | RegExp): Promise<HTMLElement> {
    const option = within(listbox).getByRole('option', { name });
    await user.click(option);
    return option;
}

describe('SingleSelect', () => {
    afterEach(() => {
        cleanup();
        (window as any).happyDOM?.setInnerWidth(1024);
        // Unmounting does not undo bits-ui's dialog scroll-lock styles applied
        // to <body>; reset them so they don't leak into the next test.
        document.body.style.pointerEvents = '';
        document.body.style.overflow = '';
    });

    describe('initial render', () => {
        it('renders initially with only the required items prop', () => {
            // GIVEN nothing but the required items prop — no value, no placeholder
            render(SingleSelect, { props: { items: fruits } });

            // WHEN the component renders
            // -> covered by the render above; the queries below run against the rendered DOM

            // THEN a single empty trigger is present and carries the select semantics
            const trigger = screen.getByRole('button', { name: '' });
            expect(trigger).toHaveClass('select-trigger');
            expect(trigger).toHaveAttribute('aria-haspopup', 'listbox');
            expect(trigger).toHaveAttribute('aria-expanded', 'false');
            expect(trigger).toHaveAttribute('data-placeholder');
            expect(screen.getAllByRole('button')).toHaveLength(1);
        });

        it('renders initially while the item data has not loaded yet (empty list)', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND an empty items list, as while backend data is still loading
            render(SingleSelect, { props: { items: [] } });

            // WHEN the component renders
            expect(screen.getByRole('button', { name: '' })).toBeInTheDocument();
            expect(screen.queryByRole('option')).not.toBeInTheDocument();

            // AND the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button', { name: '' }));

            // THEN it presents an empty listbox without errors
            expect(within(listbox).queryAllByRole('option')).toHaveLength(0);
        });

        it('renders initially while the item data is still undefined', () => {
            // GIVEN items is still undefined, as before the first data arrives
            render(SingleSelect, { props: {} });

            // WHEN the component renders
            // -> covered by the render above; the queries below run against the rendered DOM

            // THEN the trigger renders without errors and nothing is selected
            const trigger = screen.getByRole('button', { name: '' });
            expect(trigger).toBeInTheDocument();
            expect(trigger).toHaveAttribute('data-placeholder');
            expect(screen.queryByRole('option')).not.toBeInTheDocument();
            expect(document.querySelector('input[name]')).toBeNull();
        });
    });

    describe('items', () => {
        it('renders every item as an option with its label as accessible name', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND three fruit items and a placeholder
            render(SingleSelect, { props: { items: fruits, placeholder: 'Pick a fruit' } });

            // WHEN the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button', { name: 'Pick a fruit' }));

            // THEN every item is exposed as an option named by its label
            for (const item of fruits) {
                expect(screen.getByRole('option', { name: item.label })).toBeInTheDocument();
            }
            expect(within(listbox).getAllByRole('option')).toHaveLength(3);
        });

        it('marks disabled items with data-disabled and refuses to select them', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND an onValueChange handler to observe selections
            const onValueChange = vi.fn();
            render(SingleSelect, { props: { items: fruits, onValueChange } });

            // WHEN the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button'));

            // AND the disabled "Cherry" item comes into view
            const cherry = within(listbox).getByRole('option', { name: 'Cherry' });
            expect(cherry).toHaveAttribute('data-disabled');

            // AND the user clicks the disabled item
            await user.click(cherry);

            // THEN no selection is emitted and the listbox stays open
            expect(onValueChange).not.toHaveBeenCalled();
            expect(screen.getByRole('listbox')).toBeInTheDocument();
        });
    });

    describe('placeholder', () => {
        it('shows the placeholder as trigger name and flags it via data-placeholder', () => {
            // GIVEN a placeholder but no value
            render(SingleSelect, { props: { items: fruits, placeholder: 'Pick a fruit' } });

            // WHEN the component renders
            const trigger = screen.getByRole('button', { name: 'Pick a fruit' });

            // THEN the placeholder names the trigger and marks it via data-placeholder
            expect(trigger).toHaveAttribute('data-placeholder');
            expect(trigger).toHaveAttribute('aria-haspopup', 'listbox');
            expect(trigger).toHaveAttribute('aria-expanded', 'false');
        });

        it('renders an empty trigger when no value and no placeholder are given', () => {
            // GIVEN neither a value nor a placeholder
            render(SingleSelect, { props: { items: fruits } });

            // WHEN the component renders
            const trigger = screen.getByRole('button', { name: '' });

            // THEN the trigger renders with an empty accessible name but stays flagged as placeholder
            expect(trigger).toHaveAttribute('data-placeholder');
        });
    });

    describe('value', () => {
        it('displays the label of the initially selected item', () => {
            // GIVEN the value "banana"
            render(SingleSelect, { props: { items: fruits, value: 'banana' } });

            // WHEN the component renders
            const trigger = screen.getByRole('button', { name: 'Banana' });

            // THEN the selected item's label names the trigger and data-placeholder is cleared
            expect(trigger).not.toHaveAttribute('data-placeholder');
        });

        it('binds two-way and announces the new selection (combined with items + placeholder)', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND the select bound to a host value through a label association
            render(SingleSelectFixture, { props: { label: 'Fruit', items: fruits, placeholder: 'Pick a fruit' } });
            const trigger = screen.getByLabelText('Fruit');
            expect(trigger).toHaveAttribute('id', 'single-select-trigger');

            // WHEN the user opens the select
            const listbox = await openSelect(user, trigger);

            // AND the user clicks the "Apple" option
            await clickOption(user, listbox, 'Apple');

            // THEN the listbox closes
            await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());
            expect(trigger).toHaveAttribute('aria-expanded', 'false');

            // AND the trigger text updates while the label keeps providing the accessible name
            expect(trigger).toHaveTextContent('Apple');
            expect(screen.getByRole('button', { name: 'Fruit' })).toBe(trigger);

            // AND the bound value becomes "apple"
            expect(document.querySelector('.host-value')).toHaveTextContent('"apple"');
        });

        it('emits onValueChange with the selected value', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND an onValueChange handler
            const onValueChange = vi.fn();
            render(SingleSelect, { props: { items: fruits, onValueChange } });

            // WHEN the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button'));

            // AND the user clicks the "Banana" option
            await clickOption(user, listbox, 'Banana');

            // THEN the handler is called with "banana"
            expect(onValueChange).toHaveBeenCalledWith('banana');
        });
    });

    describe('disabled', () => {
        it('disables the trigger and refuses to open', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a disabled select with a placeholder
            render(SingleSelect, { props: { items: fruits, placeholder: 'Pick a fruit', disabled: true } });

            // WHEN the component renders
            const trigger = screen.getByRole('button', { name: 'Pick a fruit' });
            expect(trigger).toBeDisabled();
            expect(trigger).toHaveAttribute('data-disabled');

            // AND the user clicks the trigger
            // (browsers and therefore user-event dispatch no events on disabled controls)
            await user.click(trigger);

            // THEN the listbox never opens
            expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
            expect(trigger).toHaveAttribute('aria-expanded', 'false');
        });
    });

    describe('rest props forwarded to the select root (name / required)', () => {
        it('renders a screen-reader-only input reflecting name, required, value and disabled state', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND name and required rest props forwarded to the select root
            render(SingleSelect, {
                props: { items: fruits, name: 'fruit', required: true, placeholder: 'Pick a fruit' }
            });

            // WHEN the component renders
            const input = () => document.querySelector('input[name="fruit"]');
            expect(input()).not.toBeNull();
            expect(input()).toHaveAttribute('aria-hidden', 'true');
            expect(input()).toHaveAttribute('required');

            // AND the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button', { name: 'Pick a fruit' }));

            // AND the user clicks the "Apple" option
            await clickOption(user, listbox, 'Apple');

            // THEN the hidden input tracks the selected value
            await waitFor(() => expect(input()).toHaveValue('apple'));
        });

        it('omits the hidden input when no name is given', () => {
            // GIVEN no name rest prop
            render(SingleSelect, { props: { items: fruits } });

            // WHEN the component renders

            // THEN no hidden input is rendered
            expect(document.querySelector('input[name]')).toBeNull();
        });
    });

    describe('triggerProps', () => {
        it('forwards props to the trigger element while keeping the default trigger styling', () => {
            // GIVEN a custom class via triggerProps
            render(SingleSelect, {
                props: { items: fruits, triggerProps: { class: 'custom-trigger' }, placeholder: 'Pick' }
            });

            // WHEN the component renders
            const trigger = screen.getByRole('button', { name: 'Pick' });

            // THEN the class is merged onto the default trigger styling
            expect(trigger).toHaveClass('select-trigger', 'custom-trigger');
        });
    });

    describe('trigger', () => {
        it('replaces the default trigger with a labelled button that still carries the select semantics', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a plain string as custom trigger
            render(SingleSelect, { props: { items: fruits, trigger: 'Pick a fruit' } });

            // WHEN the component renders
            const trigger = screen.getByRole('button', { name: 'Pick a fruit' });
            expect(trigger).not.toHaveClass('select-trigger');
            expect(trigger).toHaveAttribute('aria-haspopup', 'listbox');

            // AND the user opens the select
            await openSelect(user, trigger);

            // THEN the options are still presented
            expect(screen.getByRole('option', { name: 'Apple' })).toBeInTheDocument();
        });
    });

    describe('triggerValue', () => {
        it('renders the selected label through the provided snippet', () => {
            // GIVEN a custom triggerValue snippet
            const customValue = createRawSnippet<[{ selection: { selected?: { label: string } } }]>((arg) => ({
                render: () => `<strong class="custom-value">chosen:${arg().selection.selected?.label}</strong>`
            }));

            // AND the value "banana"
            render(SingleSelect, { props: { items: fruits, value: 'banana', triggerValue: customValue } });

            // WHEN the component renders
            const trigger = screen.getByRole('button', { name: 'chosen:Banana' });

            // THEN the trigger label is produced by the snippet instead of the default value rendering
            expect(trigger.querySelector('.custom-value')).not.toBeNull();
        });
    });

    describe('itemSnippet', () => {
        it('renders every item through the provided snippet', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a custom itemSnippet and the value "banana"
            const customItem = createRawSnippet<[ItemSnippetProps]>((getProps) => ({
                render: () =>
                    `<span class="custom-item">${getProps().item.label.toUpperCase()}${getProps().selected ? '*' : ''}</span>`
            }));
            render(SingleSelect, { props: { items: fruits, value: 'banana', itemSnippet: customItem } });

            // WHEN the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button', { name: 'Banana' }));

            // THEN every option label is produced by the snippet, marked as selected where applicable
            const banana = within(listbox).getByRole('option', { name: 'BANANA*' });
            expect(banana.querySelector('.custom-item')).not.toBeNull();
            expect(within(listbox).getByRole('option', { name: 'APPLE' })).toBeInTheDocument();
        });
    });

    describe('contentProps', () => {
        it('merges custom classes into the floating content', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a custom class via contentProps
            render(SingleSelect, { props: { items: fruits, contentProps: { class: 'custom-content' } } });

            // WHEN the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button'));

            // THEN the class is merged into the floating content styling
            expect(listbox).toHaveClass('select-content', 'select-content--dropdown', 'custom-content');
        });
    });

    describe('content alignment', () => {
        it('aligns the dropdown to the start when the trigger sits in the left half of the viewport', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a trigger positioned in the left half of the 1024px wide test viewport
            render(SingleSelect, { props: { items: fruits, placeholder: 'Pick a fruit' } });
            const trigger = screen.getByRole('button', { name: 'Pick a fruit' });
            trigger.getBoundingClientRect = () => new DOMRect(10, 0, 100, 32);

            // WHEN the user opens the select
            const listbox = await openSelect(user, trigger);

            // THEN the content is aligned to the trigger's start edge
            expect(listbox).toHaveAttribute('data-align', 'start');
        });

        it('aligns the dropdown to the end when the trigger sits in the right half of the viewport', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a trigger positioned in the right half of the 1024px wide test viewport
            render(SingleSelect, { props: { items: fruits, placeholder: 'Pick a fruit' } });
            const trigger = screen.getByRole('button', { name: 'Pick a fruit' });
            trigger.getBoundingClientRect = () => new DOMRect(900, 0, 100, 32);

            // WHEN the user opens the select
            const listbox = await openSelect(user, trigger);

            // THEN the content is aligned to the trigger's end edge so it grows leftwards
            // instead of overflowing the right viewport edge
            expect(listbox).toHaveAttribute('data-align', 'end');
        });
    });

    describe('grouped items', () => {
        it('sorts groups and their items and marks grouped styling (combined with selection)', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND items carrying unsorted groupLabels and an unsorted item order
            render(SingleSelect, { props: { items: groupedItems, placeholder: 'Pick one' } });

            // WHEN the user opens the select
            const listbox = await openSelect(user, screen.getByRole('button', { name: 'Pick one' }));

            // THEN groups and their items appear sorted by label with grouped styling
            const groups = listbox.querySelectorAll('.select-group');
            expect(Array.from(groups).map((g) => g.getAttribute('data-group'))).toEqual(['Fruits', 'Vegetables']);
            expect(
                within(groups[0] as HTMLElement)
                    .getAllByRole('option')
                    .map((o) => o.textContent?.trim())
            ).toEqual(['Apple', 'Banana']);
            expect(
                within(groups[1] as HTMLElement)
                    .getAllByRole('option')
                    .map((o) => o.textContent?.trim())
            ).toEqual(['Carrot', 'Zucchini']);
            expect(within(listbox).getByRole('option', { name: 'Banana' })).toHaveClass('select-item-grouped');

            // AND the user clicks the "Banana" option
            await clickOption(user, listbox, 'Banana');

            // THEN the listbox closes
            await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument());

            // AND the user reopens the select
            const reopened = await openSelect(user, screen.getByRole('button', { name: 'Banana' }));

            // THEN "Banana" is marked selected via aria-selected and the "(x)" marker
            const banana = within(reopened).getByRole('option', { name: /Banana/ });
            expect(banana).toHaveAttribute('aria-selected', 'true');
            expect(banana.textContent).toContain('(x)');
        });
    });

    describe('narrow viewports', () => {
        it('renders the options inside a bottom sheet dialog titled with the placeholder', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a viewport narrower than the md breakpoint
            (window as any).happyDOM.setInnerWidth(375);

            // AND a placeholder serving as the sheet title
            render(SingleSelect, { props: { items: fruits, placeholder: 'Pick a fruit' } });

            // WHEN the user opens the select
            await user.click(screen.getByRole('button', { name: 'Pick a fruit' }));

            // THEN the options are presented inside a bottom sheet dialog titled with the placeholder
            await waitFor(() => expect(screen.getAllByRole('dialog').length).toBeGreaterThan(0));
            expect(screen.getByRole('heading', { name: 'Pick a fruit' })).toHaveClass('sheet-title');
            expect(screen.getByRole('option', { name: 'Apple' })).toBeInTheDocument();
        });

        it('keeps focus on the trigger when the bottom sheet opens, preserving keyboard navigation', async () => {
            // GIVEN a real user
            const user = await userEvent.setup();

            // AND a viewport narrower than the md breakpoint and a selection observer
            (window as any).happyDOM.setInnerWidth(375);
            const onValueChange = vi.fn();
            render(SingleSelect, { props: { items: fruits, placeholder: 'Pick a fruit', onValueChange } });
            const trigger = screen.getByRole('button', { name: 'Pick a fruit' });

            // WHEN the user opens the select
            await user.click(trigger);

            // THEN the sheet is open AND focus stays on the trigger
            // (the sheet's onOpenAutoFocus prevents focusing the content and refocuses the trigger,
            // which is what keeps keyboard navigation working on mobile)
            await waitFor(() => expect(screen.getAllByRole('dialog').length).toBeGreaterThan(0));
            await waitFor(() => expect(document.activeElement).toBe(trigger));

            // AND the first option is initially highlighted
            const listbox = screen.getByRole('listbox');
            expect(within(listbox).getByRole('option', { name: 'Apple' })).toHaveAttribute('data-highlighted');

            // AND the user selects purely via keyboard
            await user.keyboard('{ArrowDown}');
            await user.keyboard('{Enter}');

            // THEN keyboard navigation still works through the retained focus
            // (ArrowDown moved the highlight from "Apple" to "Banana", Enter confirmed it)
            expect(onValueChange).toHaveBeenCalledWith('banana');
        });
    });
});
