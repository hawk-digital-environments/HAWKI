import {DataStore} from "$lib/kernel/stores/types";
import {HawkiApp} from "$lib/kernel/HawkiApp";
declare module '$lib/kernel/extendableTypes.js' {
    interface HawkiDataStores {
        test: TestStore;
    }
}
export class TestStore implements DataStore {
    public readonly name = "test";

    private _state = $state(false);

    public get state (){
        return this._state;
    }

}
