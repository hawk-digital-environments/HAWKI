import crypto from 'node:crypto';
import process from 'node:process';
import os from 'node:os';

/**
 * Escapes special characters in a string for use in a regular expression.
 * @param str The string to escape.
 * @returns The escaped string.
 */
export const pregQuote = (str: string) => str.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, '\\$1');

/**
 * Converts a string to a base64 encoded string.
 * @param input
 */
export function base64Encode(input: string): string {
    const buffer = Buffer.from(input, 'utf-8');
    return buffer.toString('base64');
}

/**
 * Creates a real random string of specified length.
 * @param length
 */
function createRandomString(length: number): string {
    if (length < 1) {
        throw new Error('Length must be greater than 0');
    }

    const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!?%&_.';
    let result = '';
    for (let i = 0; i < length; i++) {
        result += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return result;
}

/**
 * Creates a deterministic random string of specified length.
 * Same seed will always produce same output.
 *
 * @param length The length of the string to generate
 * @param seed A seed string to ensure consistent results
 * @returns A deterministic random string
 */
export function createDeterministicRandomString(length: number, seed?: string): string {
    seed = seed || createInstallationSpecificSeed();

    if (length < 1) {
        throw new Error('Length must be greater than 0');
    }

    const charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!?%&_.';
    let result = '';

    // We'll need multiple hashes if length > hash output
    for (let i = 0; result.length < length; i++) {
        // Create a hash of the seed + counter to get different values for each iteration
        const hash = crypto.createHash('sha256')
            .update(`${seed}-${i}`)
            .digest('hex');

        // Use hex digits from hash to generate characters
        for (let j = 0; j < hash.length && result.length < length; j += 2) {
            // Take two hex digits (8 bits) to get a number between 0-255
            const hexPair = hash.substring(j, j + 2);
            const decimalValue = parseInt(hexPair, 16);

            // Map to our charset
            result += charset[decimalValue % charset.length];
        }
    }

    return result;
}

/**
 * Creates a seed based on the current environment.
 * This will create a different seed for each installation,
 * but the same seed will be used for all runs on the same installation.
 *
 * @returns A seed string
 */
export function createInstallationSpecificSeed(): string {
    if (this._seed) {
        return this._seed;
    }

    const filePath = import.meta.url;
    const nodeVersion = process.version;
    const platform = `${os.platform()}-${os.release()}-${os.arch()}`;

    return this._seed = crypto.createHash('sha256')
        .update(`${filePath}|${nodeVersion}|${platform}`)
        .digest('hex');
}
