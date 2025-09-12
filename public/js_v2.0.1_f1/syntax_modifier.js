
//0. initializeMessageFormating: resets all variables to start message.(at request function)
//1. Gets the received Chunk.
//2. escape HTML to prevent injection or mistaken rendering.
//3. format text for code blocks.
//4. replace markdown sytaxes for interface rendering

let summedText = '';

function initializeMessageFormating() {
    summedText = '';
}

function formatChunk(chunk, groundingMetadata) {
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

    // Render the summedText using markdown processor WITHOUT sources during streaming
    const markdownReplaced = formatMessage(formatText, groundingMetadata, true); // skipSources = true
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



function formatMessage(rawContent, groundingMetadata = '', skipSources = false) {
    // Process citations and preserve HTML elements in one step
    let contentToProcess = formatCitations(rawContent, groundingMetadata, skipSources);
    
    // Process content with placeholders for math and think blocks
    const { processedContent, mathReplacements, thinkReplacements } = preprocessContent(contentToProcess);
    
    // Apply markdown rendering
    const markdownProcessed = md.render(processedContent);
    
    // Restore math and think block content
    let finalContent = postprocessContent(markdownProcessed, mathReplacements, thinkReplacements);
    finalContent = convertHyperlinksToLinks(finalContent);
    
    // Restore preserved HTML elements
    finalContent = restoreCitations(finalContent);

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
    
    // Activate citation functionality
    activateCitations(messageElement);
}



// Preprocess content: Handle math formulas, think blocks, and preserve HTML elements
function preprocessContent(content) {
    const mathRegex = /(\$\$[^0-9].*?\$\$|\$[^0-9].*?\$|\\\(.*?\\\)|\\\[.*?\\\])/gs;
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
            if (/^\$\d+/.test(mathMatch)) { 
                return mathMatch; // Leave currency values untouched
            }
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



// Process content after Markdown rendering
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
    const parser = new DOMParser();
    // HTML-Fragment sicher einbetten
    const doc = parser.parseFromString(`<div>${text}</div>`, 'text/html');

    // Liste von Tags, in deren Innerem nichts ersetzt/verlinkt werden soll
    const EXCLUDED_TAGS = ['a', 'pre', 'code'];

    function processNode(node) {
        // Falls wir in einem auszuschließenden Tag sind: nichts weiter tun außer ggf. target bei <a>
        if (node.nodeType === Node.ELEMENT_NODE) {
            const tag = node.nodeName.toLowerCase();
            if (tag === 'a') {
                // target überprüfen/setzen
                if (node.getAttribute('target') !== '_blank') {
                    node.setAttribute('target', '_blank');
                }
            }
            if (EXCLUDED_TAGS.includes(tag)) return; // Inneren Inhalt nicht verlinken!
        }

        // Alle Kindknoten prüfen/verarbeiten
        for (let child of Array.from(node.childNodes)) {
            if (child.nodeType === Node.TEXT_NODE) {
                // URLs in Textknoten ersetzen (außer in excluded tags, was aber hier verboten ist)
                const urlRegex = /https?:\/\/[^\s<>"']+/g;
                if (urlRegex.test(child.textContent)) {
                    const frag = document.createDocumentFragment();
                    let lastIndex = 0;
                    child.textContent.replace(urlRegex, (url, index) => {
                        if (index > lastIndex) {
                            frag.appendChild(document.createTextNode(child.textContent.slice(lastIndex, index)));
                        }
                        const a = document.createElement('a');
                        a.href = url;
                        a.target = "_blank";
                        a.textContent = url;
                        frag.appendChild(a);
                        lastIndex = index + url.length;
                        return url;
                    });
                    if (lastIndex < child.textContent.length) {
                        frag.appendChild(document.createTextNode(child.textContent.slice(lastIndex)));
                    }
                    node.replaceChild(frag, child);
                }
            } else if (child.nodeType === Node.ELEMENT_NODE) {
                processNode(child); // rekursiv
            }
        }
    }

    processNode(doc.body.firstChild);

    return doc.body.firstChild.innerHTML;
}

// Helper function to escape special characters in regular expressions
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
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


function addSearchRenderedContent(messageElement, groundingMetadata){
    // Handle search suggestions/rendered content from searchMetadata
    if (groundingMetadata && typeof groundingMetadata === 'object' &&
        groundingMetadata.searchMetadata &&
        groundingMetadata.searchMetadata.renderedContent) {
                
        const render = groundingMetadata.searchMetadata.renderedContent;
        // Extract the HTML Tag (Styles already defined in CSS file)
        const parser = new DOMParser();
        const doc = parser.parseFromString(render, 'text/html');
        const divElement = doc.querySelector('.container');

        if (divElement) {
            const chips = divElement.querySelectorAll('a');
            chips.forEach(chip => {
                chip.setAttribute('target', "_blank");
            });

            // Create a new span to hold the content
            let googleSpan;
            if(!messageElement.querySelector(".google-search")){
                googleSpan = document.createElement('span');
                googleSpan.classList.add('google-search');
            }
            else{
                googleSpan = messageElement.querySelector(".google-search");
            }

            googleSpan.innerHTML = divElement.outerHTML; 
            
            // Check if message-content exists
            const messageContent = messageElement.querySelector(".message-content");
            
            if (messageContent) {
                messageContent.appendChild(googleSpan);
            }
        }
    }
}


// Temporary storage for HTML elements to preserve
const preservedHTML = [];

function formatCitations(content, groundingMetadata = '', skipSources = false) {
    // Return early if no citation metadata
    if (!groundingMetadata || typeof groundingMetadata !== 'object') {
        return content;
    }

    console.log('formatCitations called with:', groundingMetadata, 'skipSources:', skipSources); // Debug

    // Check for new unified HAWKI citation format v1
    if (groundingMetadata.format === 'hawki_v1') {
        console.log('Using unified citation format v1'); // Debug
        return processUnifiedCitations(content, groundingMetadata, skipSources);
    }

    console.log('Using legacy citation format'); // Debug
    // Fallback to legacy citation processing
    return processLegacyCitations(content, groundingMetadata, skipSources);
}

function processUnifiedCitations(content, citationData, skipSources = false) {
    const codeBlocks = [];
    const footnoteReplacements = [];

    // 1. Preserve code blocks
    const codePattern = /(<pre[\s\S]*?<\/pre>|<code[\s\S]*?<\/code>)/gi;
    let tempContent = content.replace(codePattern, (match) => {
        codeBlocks.push(match);
        return `%%CODE_BLOCK_${codeBlocks.length - 1}%%`;
    });

    let processedContent = tempContent;

    // 2. Process citations based on mode
    const processingMode = citationData.text_processing.mode;
    
    if (processingMode === 'inline') {
        // Anthropic-style: Replace [1], [2] patterns
        processedContent = processInlineCitations(processedContent, citationData, footnoteReplacements);
    } else if (processingMode === 'segments') {
        // Google-style: Process text segments
        processedContent = processSegmentCitations(processedContent, citationData, footnoteReplacements);
    }

    // 3. Apply footnote replacements
    footnoteReplacements.forEach((replacement, index) => {
        processedContent = processedContent.replace(`%%FOOTNOTE_${index}%%`, replacement);
    });

    // 4. Add sources list (only if not skipping for streaming)
    if (!skipSources) {
        processedContent = addUnifiedSourcesList(processedContent, citationData);
    }

    // 5. Restore code blocks
    codeBlocks.forEach((block, index) => {
        processedContent = processedContent.replace(`%%CODE_BLOCK_${index}%%`, block);
    });

    return processedContent;
}

function addUnifiedSourcesList(content, citationData) {
    let sourcesMarkdown = '';

    console.log('Adding unified sources list:', citationData); // Debug

    if (citationData.citations && Array.isArray(citationData.citations) && citationData.citations.length > 0) {
        console.log('Found citations:', citationData.citations.length); // Debug
        
        // Check if sources are already in the content to avoid duplication during streaming
        if (!content.includes('### Search Sources:')) {
            sourcesMarkdown = `\n\n### Search Sources:\n`;

            citationData.citations.forEach((citation) => {
                if (citation.url && citation.title) {
                    sourcesMarkdown += `${citation.id}. <a href="${citation.url}" target="_blank" class="source-link" data-source-id="${citation.id}">${citation.title}</a>\n`;
                }
            });

            if (sourcesMarkdown !== '\n\n### Search Sources:\n') {
                console.log('Adding sources markdown:', sourcesMarkdown); // Debug
                content += sourcesMarkdown;
            }
        } else {
            console.log('Sources already exist in content'); // Debug
        }
    } else {
        console.log('No citations found in citationData'); // Debug
    }

    return content;
}

function processInlineCitations(content, citationData, footnoteReplacements) {
    // Anthropic-style: replace inline citation brackets [1], [2], [1,2] with proper citation links
    return content.replace(/\[(\d+(?:,\s*\d+)*)\]/g, (match, citationString) => {
        const citationIds = citationString.includes(',') 
            ? citationString.split(',').map(id => parseInt(id.trim()))
            : [parseInt(citationString.trim())];
        
        // Create a placeholder for the footnote
        const footnoteId = footnoteReplacements.length;
        const footnotePlaceholder = `%%FOOTNOTE_${footnoteId}%%`;
        
        // Store the actual HTML for later replacement
        const footnoteHTML = createCitationLink(citationIds, citationData.citations);
        footnoteReplacements.push(footnoteHTML);
        
        return footnotePlaceholder;
    });
}

function processSegmentCitations(content, citationData, footnoteReplacements) {
    let processedContent = content;
    
    // Google-style: process textSegments with separate text and citation IDs
    citationData.text_processing.text_segments.forEach((segment) => {
        const segmentText = segment.text || '';
        const citationIds = segment.citationIds;

        if (segmentText && Array.isArray(citationIds) && citationIds.length) {
            // Create a placeholder for the footnote
            const footnoteId = footnoteReplacements.length;
            const footnotePlaceholder = `%%FOOTNOTE_${footnoteId}%%`;
            
            // Store the actual HTML for later replacement
            const footnoteHTML = createCitationLink(citationIds, citationData.citations);
            footnoteReplacements.push(footnoteHTML);

            // Replace the segment text with itself plus footnote placeholder
            processedContent = processedContent.replace(
                new RegExp(escapeRegExp(segmentText), 'g'),
                match => match + footnotePlaceholder
            );
        }
    });
    
    return processedContent;
}

function createCitationLink(citationIds, citationsData) {
    const validCitations = citationIds.filter(id => citationsData[id-1]);
    if (validCitations.length === 0) return '';
    
    return `<sup><a href="#sources" class="citation-link" 
                data-sources="${validCitations.map(id => id-1).join(',')}"
                data-citations='${JSON.stringify(validCitations.map(id => citationsData[id-1]))}'
            >${validCitations.join(', ')}</a></sup>`;
}

function processLegacyCitations(content, groundingMetadata, skipSources = false) {
    // Hilfsarrays zum Zwischenspeichern
    const codeBlocks = [];
    const footnoteReplacements = [];

    // 1. Zuerst alle <pre> und <code>-Blöcke durch Platzhalter ersetzen und merken
    const codePattern = /(<pre[\s\S]*?<\/pre>|<code[\s\S]*?<\/code>)/gi;
    let tempContent = content.replace(codePattern, (match) => {
        codeBlocks.push(match);
        return `%%CODE_BLOCK_${codeBlocks.length - 1}%%`;
    });

    let processedContent = tempContent;

    // 2. HAWKI Unified Citation Format processing
    if (groundingMetadata.textSegments && Array.isArray(groundingMetadata.textSegments)) {
        // Check if this is Anthropic-style content with inline citations
        const hasInlineCitations = /\[\d+/.test(processedContent);
        
        if (hasInlineCitations) {
            // For Anthropic: replace inline citation brackets [1], [2], [1,2] with proper citation links
            // Handle both single citations [1] and multiple citations [1,2,3]
            processedContent = processedContent.replace(/\[(\d+(?:,\s*\d+)*)\]/g, (match, citationString) => {
                // Parse citation IDs - handle both single numbers and comma-separated lists
                const citationIds = citationString.includes(',') 
                    ? citationString.split(',').map(id => parseInt(id.trim()))
                    : [parseInt(citationString.trim())];
                
                // Create a placeholder for the footnote
                const footnoteId = footnoteReplacements.length;
                const footnotePlaceholder = `%%FOOTNOTE_${footnoteId}%%`;
                
                // Store the actual HTML for later replacement
                const footnoteHTML = `<sup><a href="#sources" class="citation-link" data-sources="${citationIds.map(id => id - 1).join(',')}">${citationIds.join(', ')}</a></sup>`;
                footnoteReplacements.push(footnoteHTML);
                
                return footnotePlaceholder;
            });
        } else {
            // For Google: process textSegments with separate text and citation IDs
            groundingMetadata.textSegments.forEach((segment) => {
                const segmentText = segment.text || '';
                const citationIds = segment.citationIds;

                if (segmentText && Array.isArray(citationIds) && citationIds.length) {
                    // Create a placeholder for the footnote
                    const footnoteId = footnoteReplacements.length;
                    const footnotePlaceholder = `%%FOOTNOTE_${footnoteId}%%`;
                    
                    // Store the actual HTML for later replacement
                    const footnoteHTML = `<sup><a href="#sources" class="citation-link" data-sources="${citationIds.map(id => id - 1).join(',')}">${citationIds.join(', ')}</a></sup>`;
                    footnoteReplacements.push(footnoteHTML);

                    // Replace the segment text with itself plus footnote placeholder
                    processedContent = processedContent.replace(
                        new RegExp(escapeRegExp(segmentText), 'g'),
                        match => match + footnotePlaceholder
                    );
                }
            });
        }
    }

    // 3. Literatur/Quellen anhängen (only if not skipping for streaming)
    let sourcesMarkdown = '';

    if (!skipSources && groundingMetadata.citations && Array.isArray(groundingMetadata.citations)) {
        // Check if sources are already in the content to avoid duplication during streaming
        if (!processedContent.includes('### Search Sources:')) {
            sourcesMarkdown = `\n\n### Search Sources:\n`;

            groundingMetadata.citations.forEach((citation) => {
                if (citation.url && citation.title) {
                    sourcesMarkdown += `${citation.id}. <a href="${citation.url}" target="_blank" class="source-link" data-source-id="${citation.id}">${citation.title}</a>\n`;
                }
            });

            if (sourcesMarkdown !== '\n\n### Search Sources:\n') {
                processedContent += sourcesMarkdown;
            }
        }
    }

    // 4. Codeblöcke wieder zurückersetzen
    processedContent = processedContent.replace(/%%CODE_BLOCK_(\d+)%%/g, (_, idx) => codeBlocks[idx]);

    // 5. Store footnote replacements globally for later use
    window.footnoteReplacements = footnoteReplacements;

    return processedContent;
}

// Hilfsfunktion, falls nicht im Scope vorhanden:
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}


// Restore the preserved HTML after markdown processing
function restoreCitations(content) {
    let result = content;
    
    // Replace footnote placeholders with actual HTML
    if (window.footnoteReplacements && window.footnoteReplacements.length > 0) {
        for (let i = 0; i < window.footnoteReplacements.length; i++) {
            const placeholder = new RegExp(`%%FOOTNOTE_${i}%%`, 'g');
            result = result.replace(placeholder, window.footnoteReplacements[i]);
        }
        
        // Clean up global variable
        delete window.footnoteReplacements;
    }
    
    return result;
}

// Activate citation functionality after message is rendered
function activateCitations(messageElement) {
    // Add click handlers for citation links
    const citationLinks = messageElement.querySelectorAll('.citation-link');
    citationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get source IDs from data attribute
            const sourceIds = this.getAttribute('data-sources').split(',');
            
            // Remove highlighting from all sources
            const allSourceLinks = messageElement.querySelectorAll('.source-link');
            allSourceLinks.forEach(sourceLink => {
                sourceLink.classList.remove('highlighted');
            });
            
            // Highlight corresponding sources
            sourceIds.forEach(sourceId => {
                const sourceLink = messageElement.querySelector(`.source-link[data-source-id="${parseInt(sourceId) + 1}"]`);
                if (sourceLink) {
                    sourceLink.classList.add('highlighted');
                    
                    // Scroll to sources section if not already visible
                    setTimeout(() => {
                        const sourcesSection = messageElement.querySelector('h3');
                        if (sourcesSection && sourcesSection.textContent.includes('Search Sources')) {
                            sourcesSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }, 100);
                }
            });
        });
    });
}

// Helper function to escape special characters in regular expressions
