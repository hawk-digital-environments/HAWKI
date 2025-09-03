
// 0. initializeMessageFormating: resets all variables to start message.(at request function)
// 1. Gets the received Chunk.
// 2. escape HTML to prevent injection or mistaken rendering.
// 3. format text for code blocks.
// 4. replace markdown syntaxes for interface rendering

let summedText = '';
let randomId = '';

function initializeMessageFormating() {
  summedText = '';
}

function formatChunk(chunk, groundingMetadata) {
  // Validate input
  if (chunk === undefined || chunk === null) {
    console.warn('Received empty chunk in formatChunk');
    return summedText ? formatMessage(summedText, groundingMetadata) : '';
  }

  // Ensure chunk is a string
  const chunkStr = String(chunk);
  
  // Append the incoming chunk to the summedText
  summedText += chunkStr;
  
  // Create a temporary copy for formatting
  let formatText = summedText;

  try {
    // Balance code blocks - ensure all blocks are closed
    const backtickCount = (summedText.match(/```/g) || []).length;
    if (backtickCount % 2 !== 0) {
      formatText += '```';
    }

    // Balance thinking blocks - ensure all blocks are closed
    const thinkOpenCount = (summedText.match(/&lt;think&gt;/g) || []).length;
    const thinkCloseCount = (summedText.match(/&lt;\/think&gt;/g) || []).length;
    if (thinkOpenCount > thinkCloseCount) {
      formatText += '&lt;/think&gt;';
    }

    // Render the formatted text using markdown processor
    return formatMessage(formatText, groundingMetadata);
  } catch (error) {
    console.error('Error in formatChunk:', error);
    // Fallback to basic rendering without special processing
    return escapeHTML(summedText);
  }
}

function escapeHTML(text) {
  return text.replace(/[<>&"']/g, function (match) {
    return {
      '&': '&amp;',
      '"': '&quot;',
      "'": '&#039;',
      '<': '&lt;',
      '>': '&gt;',
    }[match];
  });
}

function formatMessage(rawContent, groundingMetadata = '') {
  // Early exit for empty content
  if (!rawContent || rawContent.trim() === '') {
    return '';
  }

  try {
    // Process citations and preserve HTML elements in one step
    const contentToProcess = formatGoogleCitations(rawContent, groundingMetadata);

    // Process content with placeholders for math and think blocks
    const { processedContent, mathReplacements, thinkReplacements } = preprocessContent(contentToProcess);

    // Apply markdown rendering
    const markdownProcessed = md.render(processedContent);

    // Restore math and think block content
    let finalContent = postprocessContent(markdownProcessed, mathReplacements, thinkReplacements);

    // Crucial: Restore preserved HTML elements before manipulating links!
    finalContent = restoreGoogleCitations(finalContent);

    // Convert bare URLs to <a> where appropriate
    finalContent = convertHyperlinksToLinks(finalContent);

    return finalContent;
  } catch (error) {
    console.error('Error in formatMessage:', error);
    // Fallback to basic escaping if something goes wrong
    return escapeHTML(rawContent);
  }
}

function formatHljs(messageElement) {
  messageElement.querySelectorAll('pre code').forEach((block) => {
    if (block.dataset.highlighted != 'true') {
      hljs.highlightElement(block);
    }
    const language = block.result?.language || block.className.match(/language-(\w+)/)?.[1];
    if (language) {
      if (!block.parentElement.querySelector('.hljs-code-header')) {
        const header = document.createElement('div');
        header.classList.add('hljs-code-header');
        header.textContent = language;
        block.parentElement.insertBefore(header, block);
      }
    }
  });
}

// Efficiently preprocess content: Handle math formulas, think blocks, and preserve HTML elements
function preprocessContent(content) {
  if (!content) return { processedContent: '', mathReplacements: [], thinkReplacements: [] };
  
  // RegEx patterns
  const mathRegex = /(\$\$[^0-9].*?\$\$|\$[^0-9].*?\$|\\\(.*?\\\)|\\\[.*?\\\])/gs;
  const thinkRegex = /&lt;think&gt;[\s\S]*?&lt;\/think&gt;/g;
  const codeBlockStartRegex = /^```/;

  const mathReplacements = [];
  const thinkReplacements = [];
  const result = [];

  let inCodeBlock = false;
  let currentSegment = '';
  
  // Process content by lines for better code block detection
  const lines = content.split('\n');
  
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    const trimmedLine = line.trim();
    
    // Detect code block boundaries
    if (codeBlockStartRegex.test(trimmedLine)) {
      // Process current segment before entering/exiting code block
      if (!inCodeBlock && currentSegment) {
        result.push(processNonCodeSegment(currentSegment, mathRegex, thinkRegex, mathReplacements, thinkReplacements));
        currentSegment = '';
      } else if (inCodeBlock && currentSegment) {
        // For code blocks, just add as-is
        result.push(currentSegment);
        currentSegment = '';
      }
      
      // Add the code block marker
      result.push(line);
      inCodeBlock = !inCodeBlock;
      continue;
    }
    
    // Append line to current segment
    currentSegment += line + '\n';
    
    // Process at the end of the content
    if (i === lines.length - 1) {
      if (inCodeBlock) {
        // For code blocks, just add as-is
        result.push(currentSegment);
      } else {
        // For non-code, process with replacements
        result.push(processNonCodeSegment(currentSegment, mathRegex, thinkRegex, mathReplacements, thinkReplacements));
      }
    }
  }
  
  return {
    processedContent: result.join('\n'),
    mathReplacements,
    thinkReplacements,
  };
}

// Helper function to process non-code segments
function processNonCodeSegment(segment, mathRegex, thinkRegex, mathReplacements, thinkReplacements) {
  // Process math formulas first
  let processed = segment.replace(mathRegex, (mathMatch) => {
    // Skip dollar signs followed by numbers (likely currency)
    if (/^\$\d+/.test(mathMatch)) return mathMatch;
    
    mathReplacements.push(mathMatch);
    return `%%%MATH${mathReplacements.length - 1}%%%`;
  });
  
  // Then process think blocks
  processed = processed.replace(thinkRegex, (thinkMatch) => {
    thinkReplacements.push(thinkMatch);
    return `%%%THINK${thinkReplacements.length - 1}%%%`;
  });
  
  return processed;
}

// Improved post-processing of content after Markdown rendering
function postprocessContent(content, mathReplacements, thinkReplacements) {
  if (!content) return '';
  
  try {
    // Replace math placeholders
    let processed = content.replace(/%%%MATH(\d+)%%%/g, (_, index) => {
      const idx = parseInt(index, 10);
      if (isNaN(idx) || idx >= mathReplacements.length) {
        console.warn(`Invalid math replacement index: ${index}`);
        return ''; // Return empty string for invalid indices
      }
      
      const rawMath = mathReplacements[idx];
      const isComplexFormula = rawMath.length > 10;
      
      if (isComplexFormula) {
        return `<div class="math" data-rawMath="${escapeHTML(rawMath)}" data-index="${idx}">${rawMath}</div>`;
      } else {
        return rawMath;
      }
    });

    // Replace think placeholders
    processed = processed.replace(/%%%THINK(\d+)%%%/g, (_, index) => {
      const idx = parseInt(index, 10);
      if (isNaN(idx) || idx >= thinkReplacements.length) {
        console.warn(`Invalid think replacement index: ${index}`);
        return ''; // Return empty string for invalid indices
      }
      
      try {
        const rawThinkContent = thinkReplacements[idx];
        // Remove &lt;think&gt; and &lt;/think&gt;
        const thinkContent = rawThinkContent.slice(12, -9);
        
        const thinkTemp = document.getElementById('think-block-template');
        if (!thinkTemp) {
          console.error('Think block template not found');
          return `<div class="think"><div class="content">${escapeHTML(thinkContent.trim())}</div></div>`;
        }
        
        const thinkClone = thinkTemp.content.cloneNode(true);
        const thinkElement = thinkClone.querySelector('.think');
        thinkElement.querySelector('.content').innerText = thinkContent.trim();
        
        const tempContainer = document.createElement('div');
        tempContainer.appendChild(thinkElement);
        return tempContainer.innerHTML;
      } catch (error) {
        console.error('Error processing think block:', error);
        return ''; // Return empty string on error
      }
    });

    return processed;
  } catch (error) {
    console.error('Error in postprocessContent:', error);
    return content; // Return original content on error
  }
}

// Efficiently convert URLs to links while respecting excluded areas
function convertHyperlinksToLinks(text) {
  const container = document.createElement('div');
  container.innerHTML = text;

  const EXCLUDED_TAGS = ['a', 'pre', 'code'];
  const URL_REGEX = /https?:\/\/[^\s<>"'`]+/g;
  const PLACEHOLDER_REGEX = /%%HTML_PRESERVED_\d+%%/;

  // Process DOM tree to find and convert URLs to links
  function processTextNodes(node) {
    if (!node || !node.childNodes) return;
    
    // Use a separate array to avoid live collection issues during DOM modification
    const childNodes = Array.from(node.childNodes);
    
    for (const child of childNodes) {
      // Skip processing in excluded tags
      if (child.nodeType === Node.ELEMENT_NODE) {
        const tagName = child.nodeName.toLowerCase();
        if (!EXCLUDED_TAGS.includes(tagName)) {
          processTextNodes(child);
        }
        continue;
      }
      
      // Process text nodes containing URLs
      if (child.nodeType === Node.TEXT_NODE && child.nodeValue && child.nodeValue.match(URL_REGEX)) {
        // Skip text nodes containing preserved HTML
        if (PLACEHOLDER_REGEX.test(child.nodeValue)) continue;
        
        const fragment = document.createDocumentFragment();
        let lastIndex = 0;
        let match;
        
        // Create a new regex instance for each execution to avoid lastIndex issues
        const regex = new RegExp(URL_REGEX);
        
        while ((match = regex.exec(child.nodeValue)) !== null) {
          const url = match[0];
          const index = match.index;
          
          // Add text before the URL
          if (index > lastIndex) {
            fragment.appendChild(document.createTextNode(
              child.nodeValue.substring(lastIndex, index)
            ));
          }
          
          // Create link element
          const link = document.createElement('a');
          link.href = url;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = url;
          fragment.appendChild(link);
          
          lastIndex = index + url.length;
        }
        
        // Add any remaining text
        if (lastIndex < child.nodeValue.length) {
          fragment.appendChild(document.createTextNode(
            child.nodeValue.substring(lastIndex)
          ));
        }
        
        // Replace the original text node with our processed fragment
        node.replaceChild(fragment, child);
      }
    }
  }

  processTextNodes(container);
  return container.innerHTML;
}

function escapeRegExp(string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function formatMathFormulas(element) {
  renderMathInElement(element, {
    delimiters: [
      { left: '$$', right: '$$', display: true },
      { left: '$', right: '$', display: false },
      { left: '\\(', right: '\\)', display: false },
      { left: '\\[', right: '\\]', display: true },
    ],
    displayMode: true, // sets a global setting for display mode; use delimiters for specific mode handling
    ignoredClasses: ['ignore_Format'],
    throwOnError: true,
  });
}

function addGoogleRenderedContent(messageElement, groundingMetadata) {
  // Handle search suggestions/rendered content
  if (
    groundingMetadata &&
    typeof groundingMetadata === 'object' &&
    groundingMetadata.searchEntryPoint &&
    groundingMetadata.searchEntryPoint.renderedContent
  ) {
    const render = groundingMetadata.searchEntryPoint.renderedContent;
    // Extract the HTML Tag (Styles already defined in CSS file)
    const parser = new DOMParser();
    const doc = parser.parseFromString(render, 'text/html');
    const divElement = doc.querySelector('.container');

    if (divElement) {
      const chips = divElement.querySelectorAll('a');
      chips.forEach((chip) => {
        chip.setAttribute('target', '_blank');
      });

      // Create a new span to hold the content
      let googleSpan;
      if (!messageElement.querySelector('.google-search')) {
        googleSpan = document.createElement('span');
        googleSpan.classList.add('google-search');
      } else {
        googleSpan = messageElement.querySelector('.google-search');
      }

      googleSpan.innerHTML = divElement.outerHTML;
      // Append the new span to the target element
      messageElement.querySelector('.message-content').appendChild(googleSpan);
    }
  }
}

// Temporary storage for HTML elements to preserve
const preservedHTML = [];
function formatGoogleCitations(content, groundingMetadata = '') {
  preservedHTML.length = 0;

  // Split the content on triple backtick code blocks
  const codeBlockRegex = /```[\s\S]*?```/g;
  let segments = [];
  let lastIndex = 0;
  let match;

  while ((match = codeBlockRegex.exec(content)) !== null) {
    // Text before the code block
    if (match.index > lastIndex) {
      segments.push({ type: 'text', value: content.slice(lastIndex, match.index) });
    }
    // The code block itself
    segments.push({ type: 'code', value: match[0] });
    lastIndex = codeBlockRegex.lastIndex;
  }

  // Remaining content after the last code block
  if (lastIndex < content.length) {
    segments.push({ type: 'text', value: content.slice(lastIndex) });
  }

  // Process text segments only
  segments = segments.map((segment) => {
    if (segment.type === 'code') {
      return segment.value; // skip processing inside code block
    }

    let text = segment.value;
    
    randomId = "";
    randomId = Math.random().toString(36).substring(2, 15);

    // Insert footnotes
    if (groundingMetadata?.groundingSupports?.length) {
      groundingMetadata.groundingSupports.forEach((support) => {
        const segmentText = support.segment?.text || '';
        const indices = support.groundingChunkIndices;

        if (segmentText && Array.isArray(indices) && indices.length) {
          // Create footnote reference HTML
          const footnotesRef =
            `<sup><span>` +
            indices
              .map(
                (idx) =>
                  `<a class="inline-citation" href="#source${randomId}:${idx + 1}">${idx + 1}</a>`
              )
              .join(', ') +
            `</span></sup>\n`;

          // Store the HTML in our preservation array
          const id = preservedHTML.length;
          preservedHTML.push(footnotesRef);
          
          // Replace text with placeholder
          const escapedText = escapeRegExp(segmentText);
          text = text.replace(new RegExp(escapedText, 'g'), (match) => 
            match + `%%HTML_PRESERVED_${id}%%`
          );
        }
      });
    }

    // Additional HTML preservation for any other HTML that might be in the text
    const htmlPattern = /<sup>.*?<\/sup>|<a\s+.*?<\/a>/g;
    text = text.replace(htmlPattern, (match) => {
      const id = preservedHTML.length;
      preservedHTML.push(match);
      return `%%HTML_PRESERVED_${id}%%`;
    });

    return text;
  });

  let processedContent = segments.join('');

  // Add sources if available
  if (groundingMetadata?.groundingChunks?.length) {
    let sourcesMarkdown = `\n\n### Search Sources:\n`;

    groundingMetadata.groundingChunks.forEach((chunk, index) => {
      if (chunk.web?.uri && chunk.web?.title) {
        const sourceLink = `${index + 1}. <a id="source${randomId}:${index + 1}" href="${chunk.web.uri}" target="_blank" class="source-link"><b>${chunk.web.title}</b></a>\n`;
        const id = preservedHTML.length;
        preservedHTML.push(sourceLink);
        sourcesMarkdown += `%%HTML_PRESERVED_${id}%%`;
      }
    });

    if (sourcesMarkdown !== '\n\n### Search Sources:\n') {
      processedContent += sourcesMarkdown;
    }
  }

  return processedContent;
}

// Restore the preserved HTML after markdown processing
function restoreGoogleCitations(content) {
  let result = content;
  for (let i = 0; i < preservedHTML.length; i++) {
    const placeholder = new RegExp(`%%HTML_PRESERVED_${i}%%`, 'g');
    result = result.replace(placeholder, preservedHTML[i]);
  }
  return result;
}
