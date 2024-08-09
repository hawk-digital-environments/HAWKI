//#region FORMAT MODIFIERS
	//0. InitializeMessage: resets all variables to start message.(at request function)
	//1. Gets the received Chunk.
	//2. escape HTML to prevent injection or mistaken rendering.
	//3. format text for code blocks.
	//4. replace markdown sytaxes for interface rendering

	let isInCodeBlock = false;
	let lastClosingIndex = -1;
	let lastChunk = '';
	let summedText = '';

	function InitializeMessage() {
		isInCodeBlock = false;
		lastClosingIndex = -1;
		lastChunk = '';
		summedText = '';
	}

	function FormatChunk(chunk) {
		chunk = escapeHTML(chunk);

		let formattedText = '';
		let prevText = '';
		if (lastClosingIndex != -1) {
			prevText = summedText.substring(0, lastClosingIndex);
			formattedText = summedText.substring(lastClosingIndex);
		} else {
			formattedText = summedText;
		}

		if (isInCodeBlock) {
			// END OF CODE BLOCK
			if (chunk === '``') {
				isInCodeBlock = false;
				formattedText += '</code></pre>';
			} else {
				formattedText = formattedText.replace('</code></pre>', '');
				formattedText += (chunk + '</code></pre>');
			}
		} else {
			// START OF CODE BLOCK
			if (chunk === '```') {
				isInCodeBlock = true;
				formattedText += '<pre><code ignore_Format>';
			} else {
				if (chunk.includes('`') && lastChunk === '``') {
					chunk = chunk.replace('`', '');
					lastClosingIndex = summedText.length;
				}
				// Plain Text
				formattedText += chunk;
			}
		}
		lastChunk = chunk;
		summedText = prevText + formattedText;
		return ReplaceMarkdownSyntax(summedText);
	}

	function ReplaceMarkdownSyntax(text) {
		const codeBlockRegex = /<pre><code\s+ignore_Format>([\s\S]+?)<\/code><\/pre>/g;
		const codeBlocks = [];

		// Replace Markdown code blocks with placeholders
		text = text.replace(codeBlockRegex, (match, content) => {
			codeBlocks.push(content);
			return `[[[[CODEBLOCK_${codeBlocks.length - 1}]]]]`;
		});

		// Replace bold and italic (*text* or ___text___)
		text = text.replace(/\*\*\*(.*?)\*\*\*/g, '<b><i>$1</i></b>');
		text = text.replace(/___(.*?)___/g, '<b><i>$1</i></b>');

		// Replace only bold (**text** or __text__)
		text = text.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
		text = text.replace(/__(.*?)__/g, '<b>$1</b>');

		// Replace only italic (*text* or _text_)
		text = text.replace(/\*(.*?)\*/g, '<i>$1</i>');
		text = text.replace(/_(.*?)_/g, '<i>$1</i>');

		// Replace Strikethrough
		text = text.replace(/~~(.*?)~~/g, '<del>$1</del>');

		// Links
		text = text.replace(/\[([^\]]+)\]\(([^\)]+)\)/g, '<a href="$2">$1</a>');

		// Headings
		text = text.replace(/(?<![^\s])(######\s?(.*))/g, '<h3>$2</h3>');
		text = text.replace(/(?<![^\s])(#####\s?(.*))/g, '<h3>$2</h3>');
		text = text.replace(/(?<![^\s])(####\s?(.*))/g, '<h3>$2</h3>');
		text = text.replace(/(?<![^\s])(###\s?(.*))/g, '<h3>$2</h3>');
		text = text.replace(/(?<![^\s])(##\s?(.*))/g, '<h3>$2</h3>');
		text = text.replace(/(?<![^\s])(#\s?(.*))/g, '<h3>$2</h3>');

		// HANDLE MARKDOWN TABLES
		const tableRegex = /(\|.*\|)\n(\|.*\|)(\n\|\s*:?-+:?\s*)*\n((\|.*\|)\n*)+/g;
		text = text.replace(tableRegex, (match) => {
			if (match.includes('[[[[CODEBLOCK_')) {
				return match;
			}

			const rows = match.split('\n').filter(Boolean);
			const cells = rows.map(row => row.replace(/^\||\|$/g, '').split('|').map(cell => cell.trim()));
			const filteredCells = cells.filter(row => !row.every(cell => /^-+$/.test(cell)));
			const headerRow = filteredCells.shift();

			let html = '<table>\n<thead>\n<tr>\n';
			html += headerRow.map(cell => `<th>${cell}</th>`).join('\n');
			html += '\n</tr>\n</thead>\n<tbody>\n';

			filteredCells.forEach(row => {
				html += '<tr>\n';
				row.forEach(cell => {
					html += `<td>${cell}</td>\n`;
				});
				html += '</tr>\n';
			});

			html += '</tbody>\n</table>\n';

			return html;
		});

		// Restore code blocks
		codeBlocks.forEach((codeBlock, index) => {
			text = text.replace(`[[[[CODEBLOCK_${index}]]]]`, `<pre><code ignore_Format>${codeBlock}</code></pre>`);
		});

		return text;
	}

	function escapeHTML(text) {
		return text.replace(/["&'<>]/g, function (match) {
			return {
				'"': '&quot;',
				'&': '&amp;',
				"'": '&#039;',
				'<': '&lt;',
				'>': '&gt;'
			}[match];
		});
	}

	function FormatMathFormulas() {
		const element = document.querySelector(".message:last-child").querySelector(".message-text");

		renderMathInElement(element, {
			delimiters: [
				{ left: '$$', right: '$$', display: true },
				{ left: '$', right: '$', display: false },
				{ left: '\\(', right: '\\)', display: false },
				{ left: '\\[', right: '\\]', display: true }
			],
			displayMode: true,
			ignoredClasses: ["ignore_Format"],
			throwOnError: true
		});
	}

	function isJSON(str) {
		try {
			JSON.parse(str);
			return true;
		} catch (e) {
			return false;
		}
	}

	function FormatWholeMessage(message){
		const codeBlockRegex = /```(.*?)```/gs;
		const html = message.replace(codeBlockRegex, (match, p1) => {
		  return `<pre><code ignore_Format>${p1}</code></pre>`;
		});
		return ReplaceMarkdownSyntax(html);
	}
//#endregion
