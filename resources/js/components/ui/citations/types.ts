export interface UrlCitation {
    url: string;
    title: string | null;
    ranges: Array<[number, number]>;
    startIndex?: number;
    endIndex?: number;
    /**
     * Providers differ in how the range offsets are measured: Google uses
     * UTF-8 byte offsets, OpenAI uses character offsets. When this flag is
     * missing, byte offsets are assumed.
     */
    byteOffset?: boolean;
}

export interface EnrichedUrlCitation extends UrlCitation {
    identifier: string;
}
