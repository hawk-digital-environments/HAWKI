export type MessagePart =
    | { type: "text"; text: string }
    | { type: "reasoning"; text: string }
    | { type: "tool-call"; toolCallId: string; toolName: string; input: unknown }
    | { type: "tool-result"; toolCallId: string; toolName: string; output: unknown };

export type ChatMessage = {
    id: string;
    role: "user" | "assistant";
    parts: MessagePart[];
    streaming?: boolean;
};
