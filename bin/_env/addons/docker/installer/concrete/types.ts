export interface ConcreteInstaller {
    /**
     * If true, the installer will always call the "registerLoopbackIp" method when the docker containers are brought up.
     * This is required for MacOS where the loopback IP address is not persistent.
     */
    requiresLoopbackMonitoring?: boolean;

    checkDependencies(): Promise<void>;

    registerLoopbackIp(ip: string): Promise<void>;

    registerDomainToIp(domain: string, ip: string): Promise<void>;

    buildCertificate(domain: string, storageDir: string): Promise<void>;
}
