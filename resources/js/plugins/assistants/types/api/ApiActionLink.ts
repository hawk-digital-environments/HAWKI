interface ApiActionLink {
    href: string;
    meta?: {
        message?: string;
    };
}
export type ApiLinks = Record<string, ApiActionLink | string | undefined>;
