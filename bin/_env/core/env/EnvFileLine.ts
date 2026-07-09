import {pregQuote} from '../util.ts';
import {parse as parseEnv} from 'dotenv';

export class EnvFileLine {
    private _type?: 'value' | 'comment' | 'empty';
    private _template?: string;
    private _key: string | undefined;
    private _value: string | undefined;

    public constructor(line: string) {
        this.parseLine(line);
    }

    public get isValue(): boolean {
        return this._type === 'value';
    }

    public get isComment(): boolean {
        return this._type === 'comment';
    }

    public get key(): string | undefined {
        return this._key;
    }

    public keyMatches(key: string): boolean {
        return this.isValue && this._key === key;
    }

    public get value(): string | undefined {
        return this._value;
    }

    public set value(value: string | undefined) {
        if (this.isValue) {
            this._value = value;
        } else {
            throw new Error('Line is not a value');
        }
    }

    public commentMatches(search: string | RegExp): boolean {
        return this.isComment && !!this._template && this._template.match(search) !== null;
    }

    public commentedKeyMatches(key: string): boolean {
        return this.commentMatches(new RegExp('^\s*#\s*[\'"]?' + pregQuote(key) + '[\'"]?\s*=\s*'));
    }

    public comment(): this {
        if (this._type === 'value') {
            this.parseLine('#' + this.toString());
        } else {
            throw new Error('Line is not a value');
        }

        return this;
    }

    public uncomment(): this {
        if (this._type === 'comment') {
            this.parseLine(this.toString().replace(/^#/, ''));
        } else {
            throw new Error('Line is not a comment');
        }

        return this;
    }

    public toString(): string {
        if (this.isValue && this._template) {
            let quoteChar = '"';
            if (this._value && this._value.includes('"')) {
                if (this._value.includes('\'')) {
                    // The value contains both single and double quotes,
                    // there is no way to quote it properly, so we will just return the unquoted value and hope for the best.
                    quoteChar = '';
                } else {
                    quoteChar = '\'';
                }
            }
            return this._template
                .replace(/%%%KEY%%%/g, this._key || '')
                .replace(/%%%VALUE%%%/g, quoteChar + this._value + quoteChar || '');
        }

        return this._template || '';
    }

    private parseLine(line: string): void {
        this._template = line;
        this._key = undefined;
        this._value = undefined;

        let trimmedLine = line.trim();
        if (trimmedLine.length === 0) {
            this._type = 'empty';
            return;
        }

        if (trimmedLine.charAt(0) === '#') {
            this._type = 'comment';
            return;
        }

        const parsedLine = parseEnv(trimmedLine);
        const keys = Object.keys(parsedLine);
        if (keys.length === 0) {
            this._type = 'empty';
            return;
        }

        this._type = 'value';
        this._key = keys[0];
        this._value = parsedLine[this._key];

        const regexify = (str: string) => new RegExp('\\s*["\']*' + pregQuote(str) + '["\']*');
        const templateParts = this._template ? this._template.split('=') : [];
        const templateKey = (templateParts.shift() || '').replace(regexify(this._key), '%%%KEY%%%');
        const templateValue = templateParts.join('=').replace(regexify(this._value), '%%%VALUE%%%');
        this._template = templateKey + '=' + templateValue;
    }
}
