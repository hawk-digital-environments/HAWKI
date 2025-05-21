import fs from 'fs';

export class IpAddressStorage {
    private readonly _storageFile: string;
    private _ipToPersist: string | undefined;

    constructor(storageFile: string) {
        this._storageFile = storageFile;
    }

    public getNextIpAddress(): string {
        let storedIp: string = '2136473601';
        if (fs.existsSync(this._storageFile)) {
            const content = fs.readFileSync(this._storageFile).toString('utf-8');
            storedIp = content.trim();
        }

        const nextIp = parseInt(storedIp) + 1;
        this._ipToPersist = '' + nextIp;
        return this.longToIp(nextIp);
    }

    public persistNextIpAddress(): void {
        if (this._ipToPersist) {
            fs.writeFileSync(this._storageFile, this._ipToPersist);
        }
        this._ipToPersist = undefined;
    }

    private longToIp(ip: number): string {
        const ipString = ip.toString(16);
        const parts = [];
        for (let i = 0; i < 8 - ipString.length; i++) {
            parts.push('0');
        }
        for (let i = 0; i < ipString.length; i += 2) {
            parts.push(parseInt(ipString.substr(i, 2), 16).toString());
        }
        return parts.join('.');
    }
}
