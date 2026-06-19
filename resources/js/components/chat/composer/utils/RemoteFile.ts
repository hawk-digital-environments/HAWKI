export class RemoteFile extends File {
    public readonly previewUrl: string;

    constructor(
        previewUrl: string,
        name: string,
        type: string
    ) {
        super([], name, {type});
        this.previewUrl = previewUrl;
    }
}
