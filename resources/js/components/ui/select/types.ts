export interface SelectItemDefinition {
    /** The value of the select item. This is the value that will be set on the select when the item is selected. */
    value: string;
    /** The label of the select item. This is the text that will be displayed in the select content. */
    label: string;
    /** Whether the item is disabled. Disabled items cannot be selected and have a different style. */
    disabled?: boolean;
    /** Optional group label. If provided, items with the same group label will be grouped together in the select content. */
    groupLabel?: string;
}

export interface ItemSnippetProps {
    /** The item being rendered. */
    item: SelectItemDefinition,
    /** Whether this item is currently selected. */
    selected: boolean
}
