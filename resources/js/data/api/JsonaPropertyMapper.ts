import {JsonPropertiesMapper} from 'jsona';

type TJsonaModel = { [propertyName: string]: any };
type TAnyKeyValueObject = { [key: string]: any };
type TResourceIdObj = {
    type: string;
    id: string | number;
    meta?: TAnyKeyValueObject
    [propertyName: string]: any
};

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
