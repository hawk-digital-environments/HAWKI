/**
 * Logos come with very different amounts of empty space around the artwork (one fills
 * its whole viewBox, another sits in the middle third of it), so at the same box size
 * they look wildly different in size. This crops an SVG data URL's viewBox to the
 * bounding box of what it actually draws, so every logo fills its box the same way.
 *
 * Anything that is not an SVG data URL, or can't be measured, is returned unchanged.
 */
const cache = new Map<string, string>();

const SVG_DATA_URL = /^data:image\/svg\+xml(;[^,]*)?,/i;
const UNSAFE_ELEMENTS = 'script, foreignObject, iframe, object, embed';

export function trimSvgIcon(src: string): string {
    if (!SVG_DATA_URL.test(src) || typeof document === 'undefined') {
        return src;
    }
    let trimmed = cache.get(src);
    if (trimmed === undefined) {
        trimmed = trim(src) ?? src;
        cache.set(src, trimmed);
    }
    return trimmed;
}

function decode(src: string): string {
    const [header, data] = [src.slice(0, src.indexOf(',')), src.slice(src.indexOf(',') + 1)];
    if (!/;base64$/i.test(header)) {
        return decodeURIComponent(data);
    }
    const binary = atob(data);
    return new TextDecoder().decode(Uint8Array.from(binary, c => c.charCodeAt(0)));
}

function trim(src: string): string | null {
    let host: HTMLDivElement | null = null;
    try {
        const doc = new DOMParser().parseFromString(decode(src), 'image/svg+xml');
        const svg = doc.documentElement;
        if (!(svg instanceof SVGSVGElement)) {
            return null;
        }

        // The SVG has to be in the live document to be measured; strip anything that
        // could run or embed content on insertion.
        svg.querySelectorAll(UNSAFE_ELEMENTS).forEach(el => el.remove());
        [svg, ...svg.querySelectorAll('*')].forEach(el => {
            for (const attr of [...el.attributes]) {
                if (attr.name.toLowerCase().startsWith('on')) {
                    el.removeAttribute(attr.name);
                }
            }
        });

        host = document.createElement('div');
        host.style.cssText = 'position:fixed;left:-10000px;top:0;visibility:hidden;pointer-events:none;';
        const live = document.importNode(svg, true);
        host.append(live);
        document.body.append(host);

        const box = live.getBBox();
        if (!(box.width > 0 && box.height > 0)) {
            return null;
        }

        // getBBox ignores strokes; leave room for half of the widest one.
        let stroke = 0;
        live.querySelectorAll<SVGGraphicsElement>('path, rect, circle, ellipse, line, polyline, polygon, text').forEach(el => {
            const style = getComputedStyle(el);
            if (style.stroke !== 'none') {
                stroke = Math.max(stroke, parseFloat(style.strokeWidth) || 0);
            }
        });
        const pad = stroke / 2;

        // Only ever crop, never grow: masked or clipped artwork (e.g. blurred blobs behind
        // a star mask) can reach far past the original frame, which clips it anyway.
        const frame = live.viewBox.baseVal;
        const hasFrame = frame && frame.width > 0 && frame.height > 0;
        const x1 = Math.max(box.x - pad, hasFrame ? frame.x : -Infinity);
        const y1 = Math.max(box.y - pad, hasFrame ? frame.y : -Infinity);
        const x2 = Math.min(box.x + box.width + pad, hasFrame ? frame.x + frame.width : Infinity);
        const y2 = Math.min(box.y + box.height + pad, hasFrame ? frame.y + frame.height : Infinity);
        if (!(x2 > x1 && y2 > y1)) {
            return null;
        }

        svg.setAttribute('viewBox', [x1, y1, x2 - x1, y2 - y1].join(' '));
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        return `data:image/svg+xml,${encodeURIComponent(new XMLSerializer().serializeToString(svg))}`;
    } catch {
        return null;
    } finally {
        host?.remove();
    }
}
