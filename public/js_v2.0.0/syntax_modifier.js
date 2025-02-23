//#region FORMAT MODIFIERS
//0. initializeMessageFormating: resets all variables to start message.(at request function)
//1. Gets the received Chunk.
//2. escape HTML to prevent injection or mistaken rendering.
//3. format text for code blocks.
//4. replace markdown sytaxes for interface rendering

let summedText = '';

function initializeMessageFormating() {
    summedText = '';
}

function formatChunk(chunk) {
    // Append the incoming chunk to the summedText
    summedText += chunk;
    let formatText = summedText;

    // Count how many triple backticks are currently in the summedText
    const backtickCount = (summedText.match(/```/g) || []).length;
    // Check if there is an unclosed code block (odd number of backticks)
    if (backtickCount % 2 !== 0) {
        // Add a closing triple backtick to close the unclosed code block
        formatText += '```';
    }
    

    
    // Count how many <think> and </think> tags are currently in the summedText
    const thinkOpenCount = (summedText.match(/<think>/g) || []).length;
    const thinkCloseCount = (summedText.match(/<\/think>/g) || []).length;

    // Check if there is an unclosed <think> block (more open than close tags)
    if (thinkOpenCount > thinkCloseCount) {
        // Add a closing </think> to close the unclosed think block
        formatText += '</think>';
    }

    // Render the summedText using markdown processor
    const markdownReplaced = formatMessage(formatText);
    return markdownReplaced;
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



function formatMessage(rawContent) {    
    const { processedContent, mathReplacements, thinkReplacements } = preprocessContent(rawContent);
    const markdownProcessed = md.render(processedContent);
    let finalContent = postprocessContent(markdownProcessed, mathReplacements, thinkReplacements);
    return finalContent;
}


function formatHljs(messageElement){
    messageElement.querySelectorAll('pre code').forEach((block) => {

        if(block.dataset.highlighted != 'true'){
            hljs.highlightElement(block);
        }
        const language = block.result?.language || block.className.match(/language-(\w+)/)?.[1];
        if (language) {
            if(!block.parentElement.querySelector('.hljs-code-header')){
                const header = document.createElement('div');
                header.classList.add('hljs-code-header');
                header.textContent = language;
                block.parentElement.insertBefore(header, block);
            }
        }
    });
}



// Step 1: Preprocess math formulas with placeholders
// Step 1: Preprocess math formulas and think blocks with placeholders
function preprocessContent(content) {
    const mathRegex = /(\$\$.*?\$\$|\$.*?\$|\\\(.*?\\\)|\\\[.*?\\\])/gs;
    const thinkRegex = /<think>[\s\S]*?<\/think>/g;
    const codeBlockRegex = /(```[\s\S]*?```)/g;

    const mathReplacements = [];
    const thinkReplacements = [];

    let splitContent = [];
    let lastIndex = 0;

    // Split the content on code blocks and process only non-code segments
    content.replace(codeBlockRegex, (match, codeBlock, offset) => {
        const nonCodeSegment = content.slice(lastIndex, offset);
        
        // Process and replace math expressions
        const processedSegment = nonCodeSegment.replace(mathRegex, (mathMatch) => {
            mathReplacements.push(mathMatch);
            return `%%%MATH${mathReplacements.length - 1}%%%`;
        });

        // Process and replace think blocks
        splitContent.push(processedSegment.replace(thinkRegex, (thinkMatch) => {
            thinkReplacements.push(thinkMatch);
            return `%%%THINK${thinkReplacements.length - 1}%%%`;
        }));

        // Add the code block segment unchanged
        splitContent.push(codeBlock);

        lastIndex = offset + codeBlock.length;
        return match;
    });

    // Add any remaining content after the last code block
    if (lastIndex < content.length) {
        const nonCodeSegment = content.slice(lastIndex);

        // Process and replace math expressions
        const processedSegment = nonCodeSegment.replace(mathRegex, (mathMatch) => {
            mathReplacements.push(mathMatch);
            return `%%%MATH${mathReplacements.length - 1}%%%`;
        });

        // Process and replace think blocks
        splitContent.push(processedSegment.replace(thinkRegex, (thinkMatch) => {
            thinkReplacements.push(thinkMatch);
            return `%%%THINK${thinkReplacements.length - 1}%%%`;
        }));
    }

    const processedContent = splitContent.join('');
    return { processedContent, mathReplacements, thinkReplacements };
}



// Step 2: Replace placeholders after Markdown-it
// Step 2: Replace placeholders after processing
function postprocessContent(content, mathReplacements, thinkReplacements) {
    // Replace math placeholders
    content = content.replace(/%%%MATH(\d+)%%%/g, (_, index) => {
        const rawMath = mathReplacements[index];
        const isComplexFormula = rawMath.length > 10;
        if (isComplexFormula) {
            return `<div class="math" data-rawMath="${rawMath}" data-index="${index}">${rawMath}</div>`;
        } else {
            return rawMath;
        }
    });

    // Replace think placeholders
    content = content.replace(/%%%THINK(\d+)%%%/g, (_, index) => {
        const rawThinkContent = thinkReplacements[index];
        const thinkContent = rawThinkContent.slice(7, -8); // Remove <think> and </think> tags

        const thinkTemp = document.getElementById('think-block-template');
        const thinkClone = thinkTemp.content.cloneNode(true);
        const thinkElement = thinkClone.querySelector(".think");
        thinkElement.querySelector('.content').innerText = thinkContent.trim()

        const tempContainer = document.createElement('div');
        tempContainer.appendChild(thinkElement);
        return tempContainer.innerHTML;
    });

    return content;
}

function convertHyperlinksToLinks(text) {
    // Regular expression to match URLs
    const urlRegex = /https?:\/\/[^\s]+/g;

    // Replace each URL match with an <a> tag
    const processedText = text.replace(urlRegex, function(url) {
        return `<a href="${url}" target="_blank">${url}</a>`;
    });

    // Return the processed text
    return processedText;
}





function formatMathFormulas(element) {
    renderMathInElement(element, {
        delimiters: [
            { left: '$$', right: '$$', display: true },
            { left: '$', right: '$', display: false },
            { left: '\\(', right: '\\)', display: false },
            { left: '\\[', right: '\\]', display: true }
        ],
        displayMode: true, // This sets a global setting for display mode; use delimiters for specific mode handling
        ignoredClasses: ["ignore_Format"],
        throwOnError: true // Whether to throw an error or render invalid syntax as red text
    });
}



//#endregion
