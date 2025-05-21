import type {AddonConfig} from '@/loadAddons.ts';
import chalk from 'chalk';

export const defineUi: AddonConfig['ui'] = async () => ({
    helpHeader: chalk.blueBright(`
▗▖ ▗▖ ▗▄▖ ▗▖ ▗▖▗▖ ▗▖▗▄▄▄▖
▐▌ ▐▌▐▌ ▐▌▐▌ ▐▌▐▌▗▞▘  █
▐▛▀▜▌▐▛▀▜▌▐▌ ▐▌▐▛▚▖   █
▐▌ ▐▌▐▌ ▐▌▐▙█▟▌▐▌ ▐▌▗▄█▄▖
`),
    helpDescription: `
${chalk.yellow(`This is a command line tool to manage your HAWKI project.
It is designed to run the project inside docker containers, so you don't have to install any dependencies on your host machine.
It will also help you to manage your project, like running migrations, seeding the database, etc.`)}

${chalk.green(`${chalk.bold('For more information please refer to HAWKI documentation at:')}
https://hawk-digital-environments.github.io/HAWKI2-Documentation/`)}
`
});
