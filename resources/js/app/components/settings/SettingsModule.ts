import type {HawkiModule} from '$lib/kernel/modules/types.js';
import type {ModuleSearchRegistrar} from '$lib/kernel/search/types.js';
import type {Translator} from '$lib/kernel/localization/translator.js';
import type {SettingsSection} from './types.js';
import Settings05Icon from '$lib/components/ui/icons/iconset/Settings05Icon.svelte';
import UserIcon from '$lib/components/ui/icons/iconset/UserIcon.svelte';
import FlaskConicalIcon from '$lib/components/ui/icons/iconset/FlaskConicalIcon.svelte';
import SunIcon from '$lib/components/ui/icons/iconset/SunIcon.svelte';
import MoonIcon from '$lib/components/ui/icons/iconset/MoonIcon.svelte';
import Logout02Icon from '$lib/components/ui/icons/iconset/Logout02Icon.svelte';

declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiSyncEvents {
        settingsRequested: SettingsSection | null;
    }
}

/** Account actions use the existing settings dialog owned by the app shell. */
export class SettingsModule implements HawkiModule {
    readonly name = 'settings';

    public title(translate: Translator['translate']): string {
        return translate('ui.search.settings.label');
    }

    public search({group}: ModuleSearchRegistrar): void {
        group('actions', {kind: 'static', label: t => t('ui.search.settings.label')}).add('actions', {
            items: ({app}) => {
                const __ = app.translator.translate;
                const theme = app.stores.get('theme');
                return [
                    {
                        id: 'general', entityKey: 'action/core:settings/general',
                        title: __('ui.search.settings.general'), icon: Settings05Icon,
                        keywords: [__('ui.settings.nav.general'), __('ui.settings.general.languageLabel')],
                        onSelect: () => app.events.sync.triggerVoid('settingsRequested', 'general')
                    },
                    {
                        id: 'profile', entityKey: 'action/core:settings/profile',
                        title: __('ui.search.settings.profile'), icon: UserIcon,
                        keywords: [__('ui.settings.nav.profile')],
                        onSelect: () => app.events.sync.triggerVoid('settingsRequested', 'profile')
                    },
                    {
                        id: 'experiments', entityKey: 'action/core:settings/experiments',
                        title: __('ui.settings.nav.experiments'), icon: FlaskConicalIcon,
                        onSelect: () => app.events.sync.triggerVoid('settingsRequested', 'experiments')
                    },
                    {
                        id: 'theme', entityKey: 'action/core:settings/theme',
                        title: theme.isDark ? __('ui.profile.lightMode') : __('ui.profile.darkMode'),
                        icon: theme.isDark ? SunIcon : MoonIcon,
                        keywords: [__('ui.settings.general.themeLabel'), __('ui.settings.general.themeLight'), __('ui.settings.general.themeDark')],
                        onSelect: () => {theme.theme = theme.isDark ? 'light' : 'dark';}
                    },
                    {
                        id: 'logout', entityKey: 'action/core:settings/logout',
                        title: __('ui.profile.logout'), icon: Logout02Icon,
                        onSelect: () => {void app.logout();}
                    }
                ];
            }
        });
    }
}
