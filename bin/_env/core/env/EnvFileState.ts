import fs from 'node:fs';
import {EnvFileLine} from './EnvFileLine.ts';

export function loadEnvFileState(filename: string): EnvFileState {
    return new EnvFileState(
        filename,
        fs.readFileSync(filename).toString('utf-8').split(/\r?\n/).map(line => new EnvFileLine(line))
    );
}

export class EnvFileState {
    protected _filename: string;
    protected _lines: Set<EnvFileLine>;

    public constructor(
        filename: string,
        lines: EnvFileLine[]
    ) {
        this._filename = filename;
        this._lines = new Set(lines);
    }

    public get filename(): string {
        return this._filename;
    }

    public addLine(line: EnvFileLine): this {
        this._lines.add(line);
        return this;
    }

    public getFirstLineForKey(key: string): EnvFileLine | undefined {
        for (const line of this._lines) {
            if (line.keyMatches(key)) {
                return line;
            }
        }

        return undefined;
    }

    public getFirstLineForCommentedKey(key: string): EnvFileLine | undefined {
        for (const line of this._lines) {
            if (line.commentedKeyMatches(key)) {
                return line;
            }
        }

        return undefined;
    }

    public toString(): string {
        let result = '';
        for (const line of this._lines) {
            result += line.toString() + '\n';
        }
        return result;
    }

    public write(): void {
        fs.writeFileSync(this._filename, this.toString().replace(/^\s+|\s+$/g, ''));
    }
}
