import process from 'node:process';

interface PlatformDefinition {
    darwin?: any;
    linux?: any;
    linuxWsl?: any;
}

export class Platform {
    private readonly _os: 'linux' | 'darwin'; // 'win32' is not supported, use the script directly in WSL
    private readonly _arch: 'x64' | 'arm64';
    private readonly _isWsl: boolean;

    public constructor(
        os: 'linux' | 'darwin',
        arch: 'x64' | 'arm64',
        isWsl: boolean
    ) {
        this._os = os;
        this._arch = arch;
        this._isWsl = isWsl;
    }

    public get os(): string {
        return this._os;
    }

    public get arch(): string {
        return this._arch;
    }

    public get isWsl(): boolean {
        return this._isWsl;
    }

    /**
     * Returns true if the current operating system is linux
     */
    public get isLinux(): boolean {
        return this._os === 'linux';
    };

    /**
     * Returns true if the current operating system is darwin / OSX
     */
    public get isDarwin(): boolean {
        return this._os === 'darwin';
    }

    /**
     * Returns true if the current operating system is a linux running in WSL
     */
    public get isLinuxWsl(): boolean {
        return this.isLinux && this._isWsl;
    }

    /**
     * Chooses one of the given values/callbacks for the current operating system.
     *
     * @param definition
     * @param useLinuxAsDarwinFallback Set this false, to disable automatic fallback of OSX (darwin)
     * definitions to Linux definitions if not explicitly set
     * @return Function
     */
    public choose(definition: PlatformDefinition, useLinuxAsDarwinFallback?: boolean) {
        if (this.isLinuxWsl && typeof definition.linuxWsl !== 'undefined') {
            return definition.linuxWsl;
        } else if (this.isDarwin && typeof definition.darwin !== 'undefined') {
            return definition.darwin;
        } else if (useLinuxAsDarwinFallback !== false && this.isDarwin && typeof definition.linux !== 'undefined') {
            return definition.linux;
        } else if (this.isLinux && typeof definition.linux !== 'undefined') {
            return definition.linux;
        }
        throw new Error('Function definition missing for platform: ' + this._os);
    }
}

export function createPlatform(): Platform {
    const os = process.env.HOST_OS;
    if (os !== 'linux' && os !== 'darwin') {
        throw new Error(`Unsupported operating system: ${os}`);
    }

    const arch = process.env.HOST_ARCH;
    if (arch !== 'x64' && arch !== 'arm64') {
        throw new Error(`Unsupported architecture: ${arch}`);
    }

    const isWsl = process.env.HOST_IS_WSL === '1';

    return new Platform(os, arch, isWsl);
}
