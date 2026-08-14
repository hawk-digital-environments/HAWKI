<!--
  @component The "Begriffe & Konzepte" FAQ, extracted from the former
  BuilderHelpPanel so it can live inside the shared app-layout help aside
  (AppAside) instead of a duplicate builder-only panel. Owns the single-open
  accordion context that HelpElement children coordinate through.
-->
<script lang="ts">
    import {setContext} from 'svelte';
    import HelpElement from './HelpElement.svelte';
    import TaskEdit01Icon from '$lib/components/ui/icons/iconset/TaskEdit01Icon.svelte';
    import ServerStack01Icon from '$lib/components/ui/icons/iconset/ServerStack01Icon.svelte';
    import Database01Icon from '$lib/components/ui/icons/iconset/Database01Icon.svelte';
    import CheckmarkBadge01Icon from '$lib/components/ui/icons/iconset/CheckmarkBadge01Icon.svelte';
    import GitBranchIcon from '$lib/components/ui/icons/iconset/GitBranchIcon.svelte';
    import Wrench01Icon from '$lib/components/ui/icons/iconset/Wrench01Icon.svelte';
    import StarIcon from '$lib/components/ui/icons/iconset/StarIcon.svelte';
    import {HELP_ACCORDION_KEY, type HelpAccordionContext} from './helpAccordion';
    import {__} from "$lib/utils/translator";

    let activeId = $state<string | null>(null);

    setContext<HelpAccordionContext>(HELP_ACCORDION_KEY, {
        isActive: (id) => activeId === id,
        toggle: (id) => { activeId = activeId === id ? null : id; },
        requestInitial: (id) => { if (activeId === null) activeId = id; },
    });
</script>

<div class="help-faq">
    <p class="intro">{__("help.assistants.description")}</p>

    <div class="elements">
        <HelpElement
                icon={TaskEdit01Icon}
                label={__('help.assistants.1.label')}
                description={__("help.assistants.1.description")}
        />
        <HelpElement
                icon={ServerStack01Icon}
                label={__('help.assistants.2.label')}
                description={__("help.assistants.2.description")}
        />
        <HelpElement
                icon={Database01Icon}
                label={__('help.assistants.3.label')}
                description={__("help.assistants.3.description")}
        />
        <HelpElement
                icon={CheckmarkBadge01Icon}
                label={__('help.assistants.4.label')}
                description={__("help.assistants.4.description")}
        />
        <HelpElement
                icon={GitBranchIcon}
                label={__('help.assistants.5.label')}
                description={__("help.assistants.5.description")}
        />
        <HelpElement
                icon={Wrench01Icon}
                label={__('help.assistants.6.label')}
                description={__("help.assistants.6.description")}
        />
        <HelpElement
                icon={StarIcon}
                label={__('help.assistants.7.label')}
                description={__("help.assistants.7.description")}
        />
    </div>
</div>

<style>
    .help-faq {
        display: flex;
        flex-direction: column;
        gap: var(--space-4);
    }

    .intro {
        margin: 0;
        font-size: var(--font-size-xs);
        line-height: var(--line-height-normal);
        color: var(--color-text-muted);
    }

    .elements {
        display: flex;
        flex-direction: column;
        gap: var(--space-1);
    }
</style>
