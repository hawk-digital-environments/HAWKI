import {useApp} from "$lib/app/hooks/useApp.svelte";

export async function getAvatar(identifier: string): Promise<string> {
    return `/proxy/storage/${identifier}`
    // const response = await useApp().restApi.fetch(
    //     `/proxy/storage/${identifier}`
    // );
    // console.log('avatar', response);
    // const blob = response.data;
    // return URL.createObjectURL(blob);
}
