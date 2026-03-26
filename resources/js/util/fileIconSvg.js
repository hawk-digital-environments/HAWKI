const template = `
<svg xmlns="http://www.w3.org/2000/svg" id="Ebene_2" viewBox="0 0 449.41 569.36">
  <defs>
    <style>
      .cls-1{fill:#fff}.cls-2{fill:{{COLOR}}}.cls-3{fill:#1d1d1b}
    </style>
  </defs>
  <g id="Capa_1">
    <circle cx="40.54" cy="427.91" r="34.04" class="cls-2"/>
    <path d="M40.54 468.45C18.19 468.45 0 450.26 0 427.91s18.19-40.54 40.54-40.54 40.54 18.19 40.54 40.54-18.19 40.54-40.54 40.54m0-68.08c-15.19 0-27.54 12.35-27.54 27.54s12.35 27.54 27.54 27.54 27.54-12.35 27.54-27.54-12.35-27.54-27.54-27.54" class="cls-3"/>
    <path d="M290.37 6.5H124.74c-45.21 0-81.85 36.65-81.85 81.85v392.66c0 45.21 36.65 81.85 81.85 81.85h236.3c45.21 0 81.85-36.65 81.85-81.85V159.02z" class="cls-1"/>
    <path d="M361.04 569.36h-236.3c-48.72 0-88.35-39.63-88.35-88.35V88.35C36.39 39.63 76.02 0 124.74 0h165.63c1.72 0 3.38.68 4.6 1.9l152.52 152.52a6.5 6.5 0 0 1 1.9 4.6v321.99c0 48.72-39.63 88.35-88.35 88.35M124.74 13c-41.55 0-75.35 33.8-75.35 75.35v392.66c0 41.55 33.8 75.35 75.35 75.35h236.3c41.55 0 75.35-33.8 75.35-75.35v-319.3L287.68 13z" class="cls-3"/>
    <path d="m290.37 6.5 152.52 152.52H322.9c-17.96 0-32.53-14.56-32.53-32.53z" style="fill:#e3e3e3"/>
    <path d="M442.91 165.52H322.9c-21.52 0-39.03-17.51-39.03-39.03V6.5c0-2.63 1.58-5 4.01-6.01a6.485 6.485 0 0 1 7.08 1.41l152.22 152.22a6.5 6.5 0 0 1-4.28 11.39ZM296.87 22.19v104.3c0 14.35 11.67 26.03 26.03 26.03h104.3z" class="cls-3"/>
    <path d="M43.64 231.62c-20.22 0-36.61 16.39-36.61 36.61v153.31h.67c3.26-16.88 18.1-29.64 35.94-29.64h368.67V231.62z" class="cls-2"/>
    <path d="M7.7 428.03h-.67a6.5 6.5 0 0 1-6.5-6.5V268.22c0-23.77 19.34-43.11 43.11-43.11h368.67a6.5 6.5 0 0 1 6.5 6.5v160.28a6.5 6.5 0 0 1-6.5 6.5H43.64c-14.4 0-26.83 10.25-29.56 24.37a6.5 6.5 0 0 1-6.38 5.27m35.94-189.91c-16.6 0-30.11 13.51-30.11 30.11v129.44c7.84-7.64 18.53-12.27 30.11-12.27h362.17V238.12z" class="cls-3"/>
    <text x="224" y="315" text-anchor="middle" dominant-baseline="central" font-family="Arial, Helvetica, sans-serif" font-weight="bold" font-size="{{FONT_SIZE}}" fill="{{TEXT_COLOR}}">{{LABEL}}</text>
  </g>
</svg>`;

function hashExtension(ext) {
    let hash = 0;
    for (let i = 0; i < ext.length; i++) {
        hash = ext.charCodeAt(i) + ((hash << 5) - hash);
        hash = hash & hash;
    }
    return Math.abs(hash);
}

function hslToHex(h, s, l) {
    s /= 100;
    l /= 100;
    const a = s * Math.min(l, 1 - l);
    const f = (n) => {
        const k = (n + h / 30) % 12;
        const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
        return Math.round(255 * color).toString(16).padStart(2, '0');
    };
    return `#${f(0)}${f(8)}${f(4)}`;
}

function getContrastColor(hex) {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;
    const toLinear = (c) => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    const luminance = 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b);
    return luminance > 0.4 ? '#1d1d1b' : '#ffffff';
}

/**
 * Returns an SVG string representing the icon for a given file extension.
 * @param {string} extension the extension of the file (e.g., "pdf", "docx", "jpg")
 * @returns {string} a data URL containing the SVG icon for the file type
 */
export function getFileIconSvg(extension) {
    const label = extension.toUpperCase();
    const hue = hashExtension(label) % 360;
    const color = hslToHex(hue, 70, 45);
    const textColor = getContrastColor(color);

    let fontSize;
    if (label.length <= 4) {
        fontSize = 120;
    } else if (label.length === 5) {
        fontSize = 96;
    } else {
        fontSize = 80;
    }

    const svg = template
        .replace(/\{\{COLOR}}/g, color)
        .replace('{{TEXT_COLOR}}', textColor)
        .replace('{{FONT_SIZE}}', fontSize)
        .replace('{{LABEL}}', label);

    return 'data:image/svg+xml,' + encodeURIComponent(svg);
}
