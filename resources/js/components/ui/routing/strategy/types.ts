export interface RoutingStrategy {
    set(path: string): void;

    get(): string;

    // Triggered when the routing view is removed from the DOM (should reset the routing state to the default state)
    clear(): void;
}
