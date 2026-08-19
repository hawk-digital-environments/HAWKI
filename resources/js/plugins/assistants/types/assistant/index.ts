/**
 * Barrel for the assistant **domain** types — the shapes the UI works with,
 * as opposed to the wire (JSON:API) shapes the backend actually sends.
 *
 * Every module in here follows the same contract, so adding a type is
 * mechanical:
 *
 *   1. Create `MyThing.ts` exporting `MyThingSchema` (a `z.object(...)`) and
 *      `export type MyThing = z.infer<typeof MyThingSchema>;`.
 *   2. Enums stay native TypeScript enums (the UI uses their members as
 *      values) and additionally export `MyEnumSchema = z.enum(MyEnum)`.
 *   3. Re-export both from this file.
 *
 * Import from `$plugins/assistants/types/assistant` for the schema (a plain
 * shape check — useful for things like the `AssistantOptionsStore` cache,
 * which validates already-domain-shaped data) and `import type` for the
 * inferred type.
 *
 * These are **not** what validates real API responses — where a resource's
 * wire shape differs from its domain shape (renamed/reshaped fields,
 * snake_case, nested `include`s), that mapping lives as its own registered
 * schema in `api/schemas/resources/*.schema.ts`, one file per JSON:API
 * resource, each also housing that resource's write side (`xToApi`) for
 * when it's needed. `AssistantCategory`/`AssistantTag` are the exception:
 * their wire and domain shapes are identical, so `api/schemas/resources/`
 * just re-exports the domain schema below rather than duplicating it.
 */

export { AssistantSchema, type Assistant, type AssistantKey } from './Assistant';
export { AssistantAvatarSchema, type AssistantAvatar } from './AssistantAvatar';
export { AssistantFeedbackSchema, type AssistantFeedback } from './AssistantFeedback';
export {
    AssistantSettingSchema,
    AssistantSettingOptionSchema,
    assistantSettingKeys,
    type AssistantSetting,
    type AssistantSettingOption,
    type AssistantSettingKey
} from './AssistantSetting';
export { AssistantCategorySchema, type AssistantCategory } from './AssistantCategory';
export { CreatorSchema, type Creator } from './Creator';
export { ReleaseMode, ReleaseModeSchema } from './ReleaseMode';
export { ReviewSchema, type Review } from './Review';
export { ReviewStage, ReviewStageSchema } from './ReviewStage';
export { RiskLevel, RiskLevelSchema } from './RiskLevel';
export { AssistantTagSchema, type AssistantTag } from './AssistantTag';
export { UserPromptSchema, type UserPrompt } from './UserPrompt';
export { VersionSchema, type Version } from './Version';
