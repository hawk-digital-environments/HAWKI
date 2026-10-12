import {useConfig} from '$lib/app/hooks/useConfig.svelte.js';
import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';

export interface LegalLink {
    key: 'imprint' | 'privacy' | 'terms' | 'accessibility';
    label: string;
    href: string;
}

/**
 * The operator's legal pages an administrator configured (imprint, privacy
 * policy, terms of use, accessibility statement), in display order. Links
 * without a URL are left out, so the list is empty on an unconfigured
 * deployment. Call the returned getter inside a `$derived` to follow locale
 * switches.
 */
export function useLegalLinks(): () => LegalLink[] {
    const config = useConfig();
    const {__} = useTranslator();
    return () => {
        const links: Array<[LegalLink['key'], string, string | null | undefined]> = [
            ['imprint', __('ui.legal.imprint'), config.legal?.imprintUrl],
            ['privacy', __('ui.legal.privacy'), config.legal?.privacyUrl],
            ['terms', __('ui.legal.terms'), config.legal?.termsUrl],
            ['accessibility', __('ui.navigation.accessibilityStatement'), config.accessibility?.statementUrl]
        ];
        return links.flatMap(([key, label, href]) => (href ? [{key, label, href}] : []));
    };
}
