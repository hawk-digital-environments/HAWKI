import chalk from 'chalk';
import PrettyError from 'pretty-error';
import type {EventBus} from './EventBus.ts';
import type {PackageInfo} from './PackageInfo.ts';

export class CommonUi {
    private _events: EventBus;

    constructor(events: EventBus) {
        this._events = events;
    }

    public get helpHeader(): string {
        const value = chalk.cyan(`
|    .        /
|---..,---.  / ,---.,---..    ,
|   |||   | /  |---'|   | \\  /
\`---'\`\`   '/   \`---'\`   '  \`'
`);
        return this._events.triggerSync('ui:filter:helpHeader', {value: value.trim()}).value;
    }

    public get helpDescription(): string {
        return this._events.triggerSync('ui:filter:helpDescription', {value: ''}).value;
    }

    public get errorHeader(): string {
        const value = chalk.red(`
|    .        /    |              |
|---..,---.  / ,---|,---.,---.,---|
|   |||   | /  |   ||---',---||   |
\`---'\`\`   '/   \`---'\`---'\`---^\`---'
`);
        return this._events.triggerSync('ui:filter:errorHeader', {value: value.trim()}).value;
    }

    public renderPackageInfo(pkg: PackageInfo): string {
        const lang = [
            ['Guten Morgen', 'Guten Tag', 'Guten Abend'], // German
            ['Good morning', 'Good day', 'Good evening'], // English
            ['Buenos días', 'Buenos días', 'Buenas noches'], // Spanish
            ['Bonjour', 'Bonne journée', 'Bonsoir'], // French
            ['Godmorgen', 'God dag', 'God aften'], // Danish
            ['Buongiorno', 'Buona giornata', 'Buonasera'], // Italian
            ['Dobro jutro', 'Dobar dan', 'Dobra večer'], // Croatian
            ['Maidin mhaith', 'Dea-lá', 'Dea-oíche'], // Irish
            ['Günaydın', 'Iyi günler', 'İyi aksamlar'], // Turkish
            ['Dobroho ranku', 'Dobroho dnya', 'Dobroho vechora'], // Ukrainian
            ['Dobroye utro', 'Dobryy den\'', 'Dobryy vecher'], // Russian (save for CLI without cyrillic font),
            ['Zǎoshang hǎo', 'měihǎo de yītiān', 'wǎnshàng hǎo'], // Chinese simplified (save for CLI without chinese font),
            ['Bonum mane', 'Bonus dies', 'Bonum vesperam'], // Latin
            ['Sawubona', 'Usuku oluhle', 'Sawubona'], // Zulu
            ['Madainn mhath', 'Latha math', 'Feasgar math'], // Scots Gaelic
            ['Hyvää huomenta', 'Hyvää päivää', 'Hyvää iltaa'], // Finnish
            ['Kaliméra', 'Kalíméra', 'Kaló apógevma'], // Greek
            ['Goeie more', 'Goeie dag', 'Goeienaand'], // Afrikaans,
            ['صبح بخیر', 'روز بخیر', 'عصر بخیر'], // Persian
            ['صباح الخير', 'نهارك سعيد', 'مساء الخير'], // Arabic
            ['おはようございます', 'こんにちは', 'こんばんは'], // Japanese
            ['좋은 아침입니다', '안녕하세요', '안녕하세요'], // Korean
            ['God morgon', 'God dag', 'God kväll'], // Swedish
            ['Bom dia', 'Boa tarde', 'Boa noite'], // Portuguese
            ['Goedemorgen', 'Goedendag', 'Goedenavond'], // Dutch
            ['Dzień dobry', 'Dzień dobry', 'Dobry wieczór'], // Polish
            ['सुप्रभात', 'नमस्ते', 'शुभ संध्या'], // Hindi
            ['בוקר טוב', 'יום טוב', 'ערב טוב'] // Hebrew
        ];
        const h = new Date().getHours();
        const timeKey = h < 12 ? 0 : (h < 18 ? 1 : 2);
        const langKey = (Math.floor(Math.random() * lang.length));
        const prefix = lang[langKey][timeKey];

        return chalk.bold(prefix) + '! You are using ' + pkg.name + ' v' + pkg.version;
    }

    public renderHelpIntro(pkg: PackageInfo): string {
        return `${this.helpHeader}${this.helpDescription}
${this.renderPackageInfo(pkg)}
`;
    }

    public renderError(error: Error): string {
        if (error.message.includes('User force closed the prompt')) {
            return '';
        }

        return [
            this.errorHeader,
            (new PrettyError()).render(error)
        ].join('\n');
    }
}
