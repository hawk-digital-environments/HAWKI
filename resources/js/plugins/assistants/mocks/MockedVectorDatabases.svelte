<script lang="ts">
    import FullWidthToggle from "$plugins/assistants/components/toggle/FullWidthToggle.svelte";
    import Database01Icon from "$lib/components/ui/icons/iconset/Database01Icon.svelte";
    import {
        MOCK_VECTOR_DATABASES,
        isMockVectorDatabaseSelected,
        toggleMockVectorDatabase
    } from "$plugins/assistants/mocks/mockVectorDatabases.svelte.js";

    // Mock-only UI strings are hardcoded here on purpose: this component and
    // its labels ship only in mock-enabled builds (see mockVectorDatabases),
    // so they deliberately bypass the translator and the language files.
    const label = 'Vektordatenbanken';
    const documentsUnit = 'Dokumente';
</script>

<div class="input-container renderBlock">
    <label for="vdb-list">{label}</label>

    <div class="vdb-list" id="vdb-list">
        {#each MOCK_VECTOR_DATABASES as db (db.id)}
            <FullWidthToggle
                    icon={Database01Icon}
                    label={db.name}
                    description={db.documents.toLocaleString('de-DE') + ' ' + documentsUnit}
                    defaultValue={isMockVectorDatabaseSelected(db.id)}
                    onchange={(value) => toggleMockVectorDatabase(db.id, value)}
            />
        {/each}
    </div>
</div>

<style>
    .vdb-list {
        display: flex;
        flex-direction: column;
        gap: .5rem;
    }
</style>
