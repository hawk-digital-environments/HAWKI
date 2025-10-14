import path from 'path';
import type {Context} from '@/Context';
import fs from 'fs';

export class ComposerContext {
    private _context: Context;

    constructor(context: Context) {
        this._context = context;
    }

    public get areComposerDependenciesInstalled(): boolean {
        const autoloadPath = path.join(this._context.paths.projectDir, '/app', 'vendor', 'autoload.php');
        return fs.existsSync(autoloadPath);
    }

    public async exec(command: Array<string>): Promise<void> {
        await this._context.docker.executeCommandInService(
            'app',
            ['composer', ...command],
            {interactive: true}
        );
    }

    public install(): Promise<void> {
        return this.exec(['install']);
    }
}
