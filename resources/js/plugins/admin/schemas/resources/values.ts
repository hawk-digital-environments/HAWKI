import z, { type ZodType } from 'zod';

/**
 * Value helpers for the admin resource schemas. The admin rows are read
 * straight from the database, so a few values arrive in their storage form.
 */

/** A JSON object column; PHP encodes an empty associative array as `[]`. */
export function jsonObject<Value extends ZodType>(value: Value) {
    return z.preprocess(
        (input) => (Array.isArray(input) && input.length === 0 ? {} : input),
        z.record(z.string(), value)
    );
}

/** A boolean column that is not an editor field and therefore reaches the client as `0`/`1`. */
export function dbBoolean() {
    return z.preprocess((input) => (typeof input === 'number' ? input !== 0 : input), z.boolean());
}

/** An aggregate the database driver returns as a numeric string. */
export function dbNumber() {
    return z.coerce.number();
}
