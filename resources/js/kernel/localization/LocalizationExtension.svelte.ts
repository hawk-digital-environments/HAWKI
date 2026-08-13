import type {HawkiAppExtension, UnfinishedHawkiApp, WithoutAppExtensionInternals} from '$lib/kernel/HawkiApp.js';
import type {TranslationLabels} from '$lib/app/schemas/resources/translation-labels.schema.js';
import type {Locale} from '$lib/app/schemas/resources/compound/locales.schema.js';
import type {Bootstrapper} from '$lib/kernel/Bootstrapper.js';
import type {RestApi} from '$lib/kernel/api/RestApi.js';
import {createTranslator} from '$lib/kernel/localization/translator.js';

/**
 * Declaration merging that exposes this extension on the app object as
 * `app.localization` and the ready-to-use {@link Translator} as `app.translator`
 * (see {@link HawkiAppExtension} / `createApp()` in `kernel/HawkiApp.ts`).
 */
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiAppExtensions {
        localization: WithoutAppExtensionInternals<LocalizationExtension>;
        translator: LocalizationExtension['translator'];
    }
}

/**
 * App extension that loads and exposes the active UI locale and its translation
 * labels.
 *
 * On the bootstrapper's `preparation` stage it reads the available/default
 * locales from the server config and the connection's chosen locale; on the
 * `main` stage it loads the labels for the active locale (falling back to the
 * default locale when the requested labels fail to load). Locales are
 * `.svelte.ts` runes (`$state`), so consumers that read `app.localization.labels`
 * or `app.translator.translate(...)` re-render automatically when
 * {@link setLocale} swaps locales. Loaded label sets are memoised per locale
 * so switching back is instant.
 *
 * `app.translator` is the single entry point most code should reach for — it
 * reads off this extension internally so callers don't need to.
 */
export class LocalizationExtension implements HawkiAppExtension {
    private _restApi: RestApi | null = null;
    private _labels = $state(null as TranslationLabels | null);
    private _locale = $state(null as Locale | null);
    private _locales: Locale[] | null = null;
    private _loadedLabels = new Map<string, TranslationLabels>();
    private _defaultLocale: Locale | null = null;

    public readonly translator = createTranslator(() => this.labels);

    public get locale(): Locale {
        if (!this._locale) {
            throw new Error('Locale has not been loaded yet');
        }
        return this._locale;
    }

    public get defaultLocale(): Locale {
        if (!this._defaultLocale) {
            throw new Error('Default locale has not been loaded yet');
        }
        return this._defaultLocale;
    }

    public get labels(): TranslationLabels['labels'] {
        if (!this._labels) {
            throw new Error('Labels have not been loaded yet');
        }
        return this._labels.labels;
    }

    public async setLocale(lang: string | Locale) {
        if (typeof lang === 'string') {
            this._locale = this.findLocale(lang);
        } else {
            this._locale = lang;
        }
        if (typeof document !== 'undefined') {
            document.documentElement.lang = this._locale.lang.replaceAll('_', '-');
        }
        await this.loadLabels(this._locale);
    }

    private findLocale(lang: string): Locale {
        if (!this._locales) {
            throw new Error('Locales have not been loaded yet');
        }
        const found = this._locales.find(locale => locale.lang === lang) || null;
        if (!found) {
            throw new Error(`Locale "${lang}" is not available`);
        }
        return found;
    }

    private async loadLabels(locale: Locale) {
        if (!this._restApi) {
            throw new Error('RestApi is not initialized yet');
        }
        const restApi = this._restApi;
        const lang = locale.lang;
        const defaultLang = this.defaultLocale.lang;

        if (!this._loadedLabels.has(lang)) {
            let labels: TranslationLabels = {locale: lang, labels: {}};

            try {
                labels = await restApi.getResource('translation-labels', lang);
            } catch (error) {
                console.error('Failed to load translation labels for locale', lang, error);
                if (lang !== defaultLang) {
                    console.warn(`Falling back to default locale "${defaultLang}".`);
                    try {
                        labels = await restApi.getResource('translation-labels', defaultLang);
                    } catch (fallbackError) {
                        console.error('Failed to load translation labels for default locale as well', defaultLang, fallbackError);
                    }
                }
            }

            this._loadedLabels.set(lang, labels);
        }

        this._labels = this._loadedLabels.get(lang)!;
    }

    public init(app: UnfinishedHawkiApp, bootstrapper: Bootstrapper): void | Promise<void> {
        bootstrapper.onStagePassed('preparation', async () => {
            const connection = app.getOrFail('connection');
            const config = app.getOrFail('config');
            const localeConfig = config.get().locale;
            this._restApi = app.getOrFail('restApi');
            this._locales = localeConfig.available.map(locale => ({...locale}));
            this._defaultLocale = this.findLocale(localeConfig.default);
            try {
                this._locale = this.findLocale(connection.locale);
            } catch (e) {
                console.warn(`Locale "${connection.locale}" is not available, falling back to default locale "${localeConfig.default}".`);
                this._locale = this._defaultLocale;
            }
        });
        bootstrapper.onMainStage(() => this.setLocale(this.locale));
    }

    public provideProperties(): Record<string, any> {
        const extension = this;
        return {
            get translator() {
                return extension.translator;
            },
            get localization() {
                return extension;
            }
        };
    }

}
