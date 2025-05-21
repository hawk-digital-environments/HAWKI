import type {DockerContext} from './DockerContext.ts';
import type {Installer} from './installer/Installer.ts';
import type {ConcreteInstaller} from './installer/concrete/types.ts';
import type {EnvFile} from '@/env/EnvFile.ts';

declare module '@/Context.ts' {
    interface Context {
        readonly installer: Installer;
        readonly docker: DockerContext;
    }
}

declare module '@/EventBus.ts' {
    interface AsyncEventTypes {
        'docker:up:before': { args: Set<string> };
        'installer:before': { installer: ConcreteInstaller };
        'installer:dependencies:before': undefined;
        'installer:loopbackIp:before': { ip: string };
        'installer:domain:before': { domain: string, ip: string };
        'installer:certificates:before': undefined;
        'installer:envFile:filter': { envFile: EnvFile };
        'installer:after': undefined;
    }
}
