import {useApp} from "$lib/app/hooks/useApp.svelte";

export async function getAvatar(identifier: string): Promise<string> {
    const response = await useApp().restApi.fetch(
        `/proxy/storage/${identifier}`
    );
    const blob = response.data;
    return URL.createObjectURL(blob);
}
