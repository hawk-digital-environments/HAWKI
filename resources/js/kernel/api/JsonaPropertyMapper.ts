import {JsonPropertiesMapper} from 'jsona';

type TJsonaModel = { [propertyName: string]: any };
type TAnyKeyValueObject = { [key: string]: any };
type TResourceIdObj = {
    type: string;
    id: string | number;
    meta?: TAnyKeyValueObject
    [propertyName: string]: any
};

/**
 * Custom `jsona` property mapper that stashes JSON:API `meta`, `links`, and
 * resource-level `meta` onto the decoded model under `_meta` / `_links` /
 * `_globalMeta` instead of dropping them (the `jsona` default strips these).
 *
 * Wired into the shared `Jsona` encoder/decoder in `jsonApiEncoding.ts`; the
 * `RestApi` runs every JSON:API response through that encoder, so consumers
 * can read `_meta`/`_links` off any decoded resource without touching `jsona`
 * themselves. You normally never instantiate this directly — it's a singleton
 * inside `jsonApiEncoding.ts`.
 */
export class JsonaPropertyMapper extends JsonPropertiesMapper {
    public setMeta(model: TJsonaModel, meta: TAnyKeyValueObject) {
        model._meta = meta;
    }

    public setLinks(model: TJsonaModel, links: TAnyKeyValueObject) {
        model._links = links;
    }

    public setResourceIdObjMeta(model: TJsonaModel, resourceIdObjMeta: TResourceIdObj) {
        model._globalMeta = resourceIdObjMeta;
    }
}
