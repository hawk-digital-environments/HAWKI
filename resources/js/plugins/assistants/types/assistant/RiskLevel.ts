import z from 'zod';

/**
 * Trust & risk classification of an assistant, rendered as a badge on the
 * detail page alongside {@link import('./Assistant').Assistant.riskNote}.
 *
 * Kept as a TypeScript enum so the members can be used as values in the UI;
 * {@link RiskLevelSchema} is the matching Zod validator.
 */
export enum RiskLevel {
    LOW = 'low',
    MEDIUM = 'medium',
    HIGH = 'high'
}

export const RiskLevelSchema = z.enum(RiskLevel);
