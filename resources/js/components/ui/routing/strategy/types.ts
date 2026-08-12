export interface RoutingStrategy {
    set(path: string): void;

    get(): string;

    bind?(name: string, basePath: string): () => void;
}
