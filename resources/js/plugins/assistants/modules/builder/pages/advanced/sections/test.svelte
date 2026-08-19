<script lang="ts">

import Chatbox from "$plugins/assistants/components/chat/Chatbox";
import {useTranslator} from "$lib/app/hooks/useTranslator.svelte.js";


const {__} = useTranslator();
</script>


<div class="page-wrapper">
    <div class="page-content">
        <div class="page-header">
            <h3 class="page-title">{__('assistants.builder.test.title')}</h3>
            <p class="page-description">{__('assistants.builder.test.description')}</p>
        </div>

        <Chatbox/>



    </div>
</div>
