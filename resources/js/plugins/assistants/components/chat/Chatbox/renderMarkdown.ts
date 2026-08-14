import {unified} from "unified";
import remarkParse from "remark-parse";
import remarkGfm from "remark-gfm";
import remarkRehype from "remark-rehype";
import rehypeShiki from "@shikijs/rehype";
import rehypeSanitize, {defaultSchema} from "rehype-sanitize";
import rehypeStringify from "rehype-stringify";

const schema: typeof defaultSchema = {
    ...defaultSchema,
    tagNames: [...(defaultSchema.tagNames ?? []), "span"],
    attributes: {
        ...defaultSchema.attributes,
        code: [...(defaultSchema.attributes?.code ?? []), "className"],
        pre: [...(defaultSchema.attributes?.pre ?? []), "className", "style"],
        span: ["className", "style"],
    },
};

const pipeline = unified()
    .use(remarkParse)
    .use(remarkGfm)
    .use(remarkRehype)
    .use(rehypeShiki, {theme: "github-dark"})
    .use(rehypeSanitize, schema)
    .use(rehypeStringify);

const cache = new Map<string, string>();

export const renderMarkdown = async (markdown: string): Promise<string> => {
    const cached = cache.get(markdown);
    if (cached !== undefined) return cached;
    const file = await pipeline.process(markdown);
    const html = String(file);
    if (cache.size > 1000) cache.clear();
    cache.set(markdown, html);
    return html;
};
