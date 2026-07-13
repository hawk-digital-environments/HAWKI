interface BackgroundPreset {
    id: string;
    label: string;
    value: string;
}

export const BACKGROUNDS: BackgroundPreset[] = [
    { id: "deep-violet",     label: "Tiefes Violett",   value: "background: linear-gradient(135deg, hsl(243 65% 55%), hsl(265 70% 45%))" },
    { id: "soft-wave",       label: "Sanfte Welle",     value: "background: linear-gradient(135deg, hsl(150 55% 80%), hsl(55 75% 80%))" },
    { id: "warm-herbs",      label: "Warme Kräuter",    value: "background: linear-gradient(135deg, hsl(25 85% 78%), hsl(15 80% 72%))" },
    { id: "flowing-purple",  label: "Fließendes Lila",  value: "background: linear-gradient(135deg, hsl(255 70% 82%), hsl(225 70% 78%))" },
    { id: "organic-green",   label: "Organisches Grün", value: "background: linear-gradient(135deg, hsl(75 50% 75%), hsl(95 45% 70%))" },
    { id: "aurora",          label: "Aurora",           value: "background: linear-gradient(135deg, hsl(190 70% 80%), hsl(280 60% 80%))" },
    { id: "sunset-rose",     label: "Sonnenuntergang",  value: "background: linear-gradient(135deg, hsl(15 90% 75%), hsl(340 80% 70%))" },
    { id: "ocean-mist",      label: "Meeresnebel",      value: "background: linear-gradient(135deg, hsl(200 65% 75%), hsl(220 60% 65%))" },
    { id: "midnight",        label: "Mitternacht",      value: "background: linear-gradient(135deg, hsl(230 45% 25%), hsl(260 50% 35%))" },
    { id: "amber-glow",      label: "Bernstein",        value: "background: linear-gradient(135deg, hsl(40 95% 70%), hsl(20 90% 60%))" },
    { id: "forest",          label: "Waldlichtung",     value: "background: linear-gradient(135deg, hsl(150 40% 35%), hsl(170 45% 45%))" },
    { id: "graphite",        label: "Graphit",          value: "background: linear-gradient(135deg, hsl(220 10% 30%), hsl(220 8% 50%))" },
];