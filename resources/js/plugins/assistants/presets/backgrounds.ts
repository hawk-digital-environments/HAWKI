interface BackgroundPreset {
    id: string;
    label: string;
    /** Ready-to-apply CSS: one flat background and one foreground shade. Only
     *  `background` and `color` — both on the backend's IconCss whitelist. */
    value: string;
}

/** Builds a preset's CSS from its two HSL triples (`"h s% l%"`). */
const flat = (background: string, foreground: string) =>
    `background: hsl(${background}); color: hsl(${foreground})`;

/**
 * Flat two-tone avatar colors: each pairs one background with one foreground
 * shade — a deep ink of the same family on light grounds, a bright accent on
 * dark ones — so text set in the foreground always reads on the background.
 */
export const BACKGROUNDS: BackgroundPreset[] = [
    { id: "blush",     label: "Rosé",        value: flat("330 85% 90%", "340 60% 22%") },
    { id: "flamingo",  label: "Flamingo",    value: flat("345 75% 66%", "340 65% 18%") },
    { id: "peach",     label: "Pfirsich",    value: flat("22 95% 82%",  "15 70% 24%") },
    { id: "tangerine", label: "Mandarine",   value: flat("18 92% 56%",  "40 100% 92%") },
    { id: "sunflower", label: "Sonnenblume", value: flat("45 98% 60%",  "355 75% 18%") },
    { id: "lime",      label: "Limette",     value: flat("72 90% 60%",  "155 100% 18%") },
    { id: "forest",    label: "Wald",        value: flat("155 100% 19%", "72 90% 62%") },
    { id: "mint",      label: "Minze",       value: flat("160 55% 80%", "170 70% 18%") },
    { id: "sky",       label: "Himmel",      value: flat("205 90% 84%", "225 70% 25%") },
    { id: "cobalt",    label: "Kobalt",      value: flat("228 80% 52%", "205 100% 88%") },
    { id: "lavender",  label: "Lavendel",    value: flat("260 80% 87%", "265 55% 28%") },
    { id: "ink",       label: "Tinte",       value: flat("230 20% 16%", "72 90% 62%") },
];

/** The retired gradient presets, by the id of the flat preset that replaces each
 *  (one-to-one, so assistants that differed before still differ). */
const LEGACY_GRADIENTS: Record<string, string> = {
    "background: linear-gradient(135deg, hsl(243 65% 55%), hsl(265 70% 45%))": "cobalt",
    "background: linear-gradient(135deg, hsl(150 55% 80%), hsl(55 75% 80%))": "sunflower",
    "background: linear-gradient(135deg, hsl(25 85% 78%), hsl(15 80% 72%))": "peach",
    "background: linear-gradient(135deg, hsl(255 70% 82%), hsl(225 70% 78%))": "lavender",
    "background: linear-gradient(135deg, hsl(75 50% 75%), hsl(95 45% 70%))": "lime",
    "background: linear-gradient(135deg, hsl(190 70% 80%), hsl(280 60% 80%))": "blush",
    "background: linear-gradient(135deg, hsl(15 90% 75%), hsl(340 80% 70%))": "flamingo",
    "background: linear-gradient(135deg, hsl(200 65% 75%), hsl(220 60% 65%))": "sky",
    "background: linear-gradient(135deg, hsl(230 45% 25%), hsl(260 50% 35%))": "ink",
    "background: linear-gradient(135deg, hsl(40 95% 70%), hsl(20 90% 60%))": "tangerine",
    "background: linear-gradient(135deg, hsl(150 40% 35%), hsl(170 45% 45%))": "forest",
    "background: linear-gradient(135deg, hsl(220 10% 30%), hsl(220 8% 50%))": "mint",
};

/** Hue (degrees), saturation and lightness (0–100) of the first `hsl()`/`rgb()` color in `css`. */
function firstColor(css: string): {h: number; s: number; l: number} | null {
    const hsl = css.match(/hsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%/);
    if (hsl) return {h: +hsl[1], s: +hsl[2], l: +hsl[3]};
    const rgb = css.match(/rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
    if (!rgb) return null;
    const [r, g, b] = [+rgb[1] / 255, +rgb[2] / 255, +rgb[3] / 255];
    const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
    let h = 0;
    if (d) h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
    const l = (max + min) / 2;
    const s = d ? d / (1 - Math.abs(2 * l - 1)) : 0;
    return {h: (h * 60 + 360) % 360, s: s * 100, l: l * 100};
}

/**
 * Snaps persisted avatar CSS onto the current flat presets at read time, so
 * avatars saved with a retired gradient render (and are re-saved) as their
 * flat successor. Other CSS — e.g. seeded `rgb()` gradients — maps to the
 * preset whose background is nearest in hue and lightness (near-grays map to
 * the neutral `ink`); CSS with no readable color is left as is.
 */
export function normalizeIconCss(css: string): string {
    if (BACKGROUNDS.some((bg) => bg.value === css)) return css;
    const legacyId = LEGACY_GRADIENTS[css];
    if (legacyId) return BACKGROUNDS.find((bg) => bg.id === legacyId)!.value;

    const color = firstColor(css);
    if (!color) return css;
    if (color.s < 20) return BACKGROUNDS.find((bg) => bg.id === "ink")!.value;
    let best = BACKGROUNDS[0];
    let bestDistance = Infinity;
    for (const bg of BACKGROUNDS) {
        const preset = firstColor(bg.value)!;
        const hueGap = Math.min(Math.abs(preset.h - color.h), 360 - Math.abs(preset.h - color.h));
        const distance = hueGap + Math.abs(preset.l - color.l) * 1.5;
        if (distance < bestDistance) {
            bestDistance = distance;
            best = bg;
        }
    }
    return best.value;
}
