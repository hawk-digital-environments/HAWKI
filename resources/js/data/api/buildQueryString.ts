interface FetchQuery {
    [key: string]: any;

    /**
     * The list of included objects in this request
     */
    include?: Array<string> | string;

    /**
     * The list of fields that should be requested
     * should be an object representing the resource type and it's fields as arrays
     */
    fields?: Record<string, Array<string>>;
}

export interface FetchCollectionQuery extends FetchQuery {
    /**
     * The filter object for this query
     */
    filter?: Record<string, any>;

    /**
     * The array of sort fields.
     */
    sort?: Array<string> | string | Record<string, 'asc' | 'desc'>;

    /**
     * The pagination constraints
     */
    page?: {
        [key: string]: any;
        number?: number;
        size?: number;
    };
}

export interface FetchResourceQuery extends FetchQuery {
}

export function buildQueryString(query?: FetchCollectionQuery | FetchResourceQuery): string {
    if (!query) {
        return '';
    }

    // Special handling if sort is an object -> Convert it to the format expected by the API (e.g. { name: 'asc', date: 'desc' } -> ['name', '-date'])
    if (query.sort && typeof query.sort === 'object' && !Array.isArray(query.sort)) {
        const sortFields: Array<string> = [];
        for (const [field, direction] of Object.entries(query.sort)) {
            if (direction === 'asc') {
                sortFields.push(field);
            } else if (direction === 'desc') {
                sortFields.push('-' + field);
            }
        }
        query.sort = sortFields as any;
    }

    const format = (query?: FetchCollectionQuery, prefix?: string): string => {
        if (typeof query === 'undefined') {
            return '';
        }

        const output: Array<string> = [];

        for (let [k, v] of Object.entries(query)) {
            if (!v) {
                v = '';
            }
            if (Array.isArray(v)) {
                v = v.join(',');
            }
            if (prefix) {
                k = prefix + '[' + k + ']';
            }
            let pair;
            if (typeof v === 'object') {
                pair = format(v, k);
            } else {
                pair = encodeURIComponent(k) + '=' + encodeURIComponent(v);
            }

            if (pair === '') {
                continue;
            }

            output.push(pair);
        }

        if (output.length === 0) {
            return '';
        }

        return output.join('&');
    };

    const q = format(query);
    if (q) {
        return '?' + q;
    }
    return '';
}
