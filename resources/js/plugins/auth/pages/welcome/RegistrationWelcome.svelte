<!--
  @component Onboarding shown at the start of the registration, before the usage
  policy and the keychain setup. Ported from the legacy registration wizard's
  welcome slides.

  Each step is a route of its own on a nested, in-memory router (transient
  strategy: the browser URL stays on the registration page), all rendered by
  `WelcomePage.svelte`. The steps are linked through their route meta (see
  `welcomeRoutes.ts`); the last step's action calls `onFinish`.
-->
<script lang="ts">
    import RouterView from '$lib/components/ui/routing/RouterView.svelte';
    import {createRouter} from '$lib/components/ui/routing/index.js';
    import {useApp} from '$lib/app/hooks/useApp.svelte.js';
    import {useTranslator} from '$lib/app/hooks/useTranslator.svelte.js';
    import {welcomeSteps} from './welcomeSteps.js';
    import {registerWelcomeRoutes} from './welcomeRoutes.js';
    import {createWelcomeFlow, provideWelcomeFlow} from './welcomeFlow.js';
    import WelcomePage from './WelcomePage.svelte';

    interface Props {
        /** Called when the last step is completed. */
        onFinish: () => void;
    }

    const {onFinish}: Props = $props();
    const app = useApp();
    const {__} = useTranslator();

    const router = createRouter('registration-welcome', (registrar) => {
        registerWelcomeRoutes(registrar, welcomeSteps(app.config.get().security.passkeyAutoGenerate), WelcomePage);
    }, {strategy: 'transient'});

    provideWelcomeFlow(createWelcomeFlow(() => onFinish()));
</script>

<RouterView {router} loadingLabel={__('ui.loading')}/>
