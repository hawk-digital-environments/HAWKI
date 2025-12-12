
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

  // If chunk is an object, something went wrong - skip it
  if (typeof chunk === 'object') {
    console.error('Received object instead of string in formatChunk:', chunk);
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
    const thinkOpenCount = (summedText.match(/<think>/g) || []).length;
    const thinkCloseCount = (summedText.match(/<\/think>/g) || []).length;
    if (thinkOpenCount > thinkCloseCount) {
      formatText += '</think>';
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

/**
 * Replace HTML links in formatted text with inline citation indices
 * @param {HTMLElement} element - The element containing formatted HTML
 * @param {Array} citations - Array of citation objects with url
 * @param {string} messageId - Unique message ID for citation anchors
 */
function replaceHtmlLinksWithCitations(element, citations, messageId, indexMapping = null) {
  if (!citations || citations.length === 0 || !element) {
    return;
  }

  // Build URL to display index mapping
  // If indexMapping is provided, use it to map original indices to display indices
  const urlToDisplayIndex = new Map();
  
  if (indexMapping) {
    // Use provided index mapping (original index → display index)
    citations.forEach((citation, originalIndex) => {
      if (citation && citation.url) {
        const displayIndex = indexMapping[originalIndex];
        if (displayIndex !== undefined) {
          urlToDisplayIndex.set(citation.url, displayIndex + 1); // +1 for 1-based display
        }
      }
    });
  } else {
    // Fallback: deduplicate URLs and assign sequential indices
    citations.forEach((citation, index) => {
      if (citation && citation.url && !urlToDisplayIndex.has(citation.url)) {
        urlToDisplayIndex.set(citation.url, index + 1);
      }
    });
  }

  // Find all <a> elements in the content
  const links = element.querySelectorAll('a[href]');
  
  links.forEach(link => {
    const url = link.getAttribute('href');
    const citationIndex = urlToDisplayIndex.get(url);
    
    if (citationIndex) {
      // Create citation marker
      const citationMarker = document.createElement('sup');
      const span = document.createElement('span');
      const citationLink = document.createElement('a');
      citationLink.className = 'inline-citation';
      citationLink.href = `#source${messageId}:${citationIndex}`;
      citationLink.textContent = citationIndex;
      
      // Note: Click handler is added by initializeInlineCitationHandlers()
      
      span.appendChild(citationLink);
      citationMarker.appendChild(span);
      
      // Check for surrounding parentheses in text nodes
      const prevNode = link.previousSibling;
      const nextNode = link.nextSibling;
      
      // Remove opening parenthesis before link
      if (prevNode && prevNode.nodeType === Node.TEXT_NODE && prevNode.textContent.endsWith('(')) {
        prevNode.textContent = prevNode.textContent.slice(0, -1);
      }
      
      // Remove closing parenthesis after link
      if (nextNode && nextNode.nodeType === Node.TEXT_NODE && nextNode.textContent.startsWith(')')) {
        nextNode.textContent = nextNode.textContent.slice(1);
      }
      
      // Replace the link element with the citation marker
      link.parentNode.replaceChild(citationMarker, link);
      
    }
  });
}

/**
 * Replace markdown links with inline citation indices
 * @param {string} text - The markdown text with links
 * @param {Array} citations - Array of citation objects with url
 * @param {string} messageId - Unique message ID for citation anchors
 * @returns {string} Text with markdown links replaced by citation indices
 * @deprecated Use replaceHtmlLinksWithCitations instead - work with already formatted HTML
 */
function replaceMarkdownLinksWithCitations(text, citations, messageId) {
  if (!citations || citations.length === 0 || !text) {
    return text;
  }

  // Build URL to index mapping (deduplicated URLs get same index)
  const urlToIndex = new Map();
  citations.forEach((citation, index) => {
    if (!urlToIndex.has(citation.url)) {
      urlToIndex.set(citation.url, index + 1);
    }
  });

  // Find all markdown links: [text](url)
  const markdownLinkRegex = /\[([^\]]+)\]\(([^)]+)\)/g;
  
  let modifiedText = text;
  const replacements = [];
  
  // Collect all matches first (to avoid regex state issues)
  let match;
  while ((match = markdownLinkRegex.exec(text)) !== null) {
    replacements.push({
      fullMatch: match[0],
      linkText: match[1],
      url: match[2],
      index: match.index
    });
  }
  
  // Process replacements in reverse order (to maintain indices)
  replacements.reverse().forEach(({ fullMatch, url, index }) => {
    const citationIndex = urlToIndex.get(url);
    if (citationIndex) {
      // Replace [text](url) with citation marker
      const citationMarker = `<sup><span><a class="inline-citation" href="#source${messageId}:${citationIndex}">${citationIndex}</a></span></sup>`;
      modifiedText = modifiedText.substring(0, index) + citationMarker + modifiedText.substring(index + fullMatch.length);
    }
  });

  return modifiedText;
}

/**
 * Insert inline citations into text based on OpenAI Responses API annotations
 * @param {string} text - The text content
 * @param {Array} citations - Array of citation objects with start_index, end_index, url, title
 * @param {string} messageId - Unique message ID for citation anchors
 * @returns {string} Text with inline citation markers inserted
 * @deprecated Use replaceMarkdownLinksWithCitations instead - LLM returns markdown links
 */
function insertInlineCitations(text, citations, messageId) {
  if (!citations || citations.length === 0 || !text) {
    return text;
  }

  // Sort citations by start_index in reverse order (process from end to start)
  // This way indices don't shift as we insert
  const sortedCitations = [...citations].sort((a, b) => b.start_index - a.start_index);

  // Build URL to index mapping (deduplicated URLs get same index)
  const urlToIndex = new Map();
  const uniqueUrls = [];
  citations.forEach(citation => {
    if (!urlToIndex.has(citation.url)) {
      uniqueUrls.push(citation.url);
      urlToIndex.set(citation.url, uniqueUrls.length);
    }
  });

  let modifiedText = text;

  // Group citations by position (same end_index = group together)
  const positionGroups = new Map();
  sortedCitations.forEach(citation => {
    const key = citation.end_index;
    if (!positionGroups.has(key)) {
      positionGroups.set(key, []);
    }
    positionGroups.get(key).push(citation);
  });

  // Process each position group
  Array.from(positionGroups.entries())
    .sort((a, b) => b[0] - a[0]) // Sort by position descending
    .forEach(([endIndex, groupCitations]) => {
      // Get unique citation numbers for this group
      const citationNumbers = [...new Set(
        groupCitations.map(c => urlToIndex.get(c.url))
      )].sort((a, b) => a - b);

      // Build citation links
      const citationLinks = citationNumbers.map(num => 
        `<a class="inline-citation" href="#source${messageId}:${num}">${num}</a>`
      ).join(', ');

      // Insert citation marker at end_index
      const citationMarker = `<sup><span>${citationLinks}</span></sup>`;
      modifiedText = modifiedText.slice(0, endIndex) + citationMarker + modifiedText.slice(endIndex);
    });

  return modifiedText;
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
  const thinkRegex = /<think>[\s\S]*?<\/think>/g;
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
        // Remove <think> and </think>
        const thinkContent = rawThinkContent.slice(7, -8);

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
      
      // Add unique IDs to each chip for citation linking
      const messageId = messageElement.dataset.messageId || 'msg';
      chips.forEach((chip, index) => {
        chip.setAttribute('target', '_blank');
        const citationNum = index + 1;
        chip.id = `source${messageId}:${citationNum}`;
        // Don't modify chip classes - they are Google's own styling
      });

      // Create a new div wrapper with web-sources class for consistent styling
      let googleWrapper;
      if (!messageElement.querySelector('.google-search')) {
        googleWrapper = document.createElement('div');
        googleWrapper.classList.add('google-search', 'web-sources');
        
        // Add title as h3 using translation
        const title = document.createElement('h3');
        title.classList.add('sources-title');
        title.textContent = translation?.SearchSources || 'Quellen:';
        googleWrapper.appendChild(title);
      } else {
        googleWrapper = messageElement.querySelector('.google-search');
        // Clear existing content but keep the title
        const existingTitle = googleWrapper.querySelector('.sources-title');
        googleWrapper.innerHTML = '';
        if (existingTitle) {
          googleWrapper.appendChild(existingTitle);
        }
      }

      googleWrapper.appendChild(divElement);
      // Append the wrapper to the target element
      messageElement.querySelector('.message-content').appendChild(googleWrapper);
      
      // Initialize click handlers for inline citations (if any exist in the message)
      initializeInlineCitationHandlers(messageElement);
    }
  }
}

/**
 * Initialize click handlers for all inline-citation links in a message
 * This handles both Google and OpenAI/Anthropic citations
 * @param {HTMLElement} messageElement - The message element containing citations
 */
function initializeInlineCitationHandlers(messageElement) {
  const citationLinks = messageElement.querySelectorAll('.inline-citation');
  
  citationLinks.forEach(citationLink => {
    // Remove existing click handler if any (to avoid duplicates)
    const newCitationLink = citationLink.cloneNode(true);
    citationLink.parentNode.replaceChild(newCitationLink, citationLink);
    
    // Add click handler to highlight target source
    newCitationLink.addEventListener('click', (e) => {
      e.preventDefault();
      const targetId = newCitationLink.getAttribute('href').substring(1); // Remove #
      const targetElement = document.getElementById(targetId);
      
      if (targetElement) {
        // Remove existing highlights from all source links
        document.querySelectorAll('.source-link.highlighted').forEach(el => {
          el.classList.remove('highlighted');
        });
        
        // Determine which element to highlight - always the .source-link <a> element
        let elementToHighlight = targetElement;
        
        // If target is a .source-item (OpenAI), find the .source-link inside it
        if (targetElement.classList.contains('source-item')) {
          const sourceLink = targetElement.querySelector('.source-link');
          if (sourceLink) {
            elementToHighlight = sourceLink;
          }
        }
        
        // Scroll to the target (scroll to the li or a, doesn't matter)
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Highlight the source link
        elementToHighlight.classList.add('highlighted');
        
        // Remove highlight after animation
        setTimeout(() => {
          elementToHighlight.classList.remove('highlighted');
        }, 2000);
      }
    });
  });
}

/**
 * Add Responses API (OpenAI) web search citations to a message element as Search Sources
 * @param {HTMLElement} messageElement - The message element to add sources to
 * @param {Array} auxiliaries - Array of auxiliary data including citations
 */
function addResponsesCitations(messageElement, auxiliaries) {
  if (!auxiliaries || !Array.isArray(auxiliaries)) {
    return;
  }

  // Find responsesCitations auxiliary
  const citationsAux = auxiliaries.find(aux => aux.type === 'responsesCitations');
  
  if (!citationsAux || !citationsAux.content) {
    return;
  }

  try {
    const citationsData = JSON.parse(citationsAux.content);
    const citations = citationsData.citations;

    if (!citations || !Array.isArray(citations) || citations.length === 0) {
      return;
    }

    // Deduplicate citations by URL while maintaining index mapping
    const uniqueCitations = [];
    const indexMapping = {}; // Maps original index → new display position
    const seenUrls = new Map(); // Maps URL → display position
    
    citations.forEach((citation, originalIndex) => {
      // Validate citation object
      if (!citation || typeof citation !== 'object') {
        console.warn(`[RESPONSES CITATIONS] Invalid citation at index ${originalIndex}:`, citation);
        return;
      }
      
      // Ensure URL is a string
      const url = typeof citation.url === 'string' ? citation.url : String(citation.url || '');
      
      if (!url) {
        console.warn(`[RESPONSES CITATIONS] Citation without URL at index ${originalIndex}:`, citation);
        return;
      }
      
      // Check if URL already exists
      if (seenUrls.has(url)) {
        // Map this original index to the existing position
        const existingPosition = seenUrls.get(url);
        indexMapping[originalIndex] = existingPosition;
      } else {
        // New unique citation - add to list
        const newPosition = uniqueCitations.length;
        uniqueCitations.push({
          url: url,
          title: typeof citation.title === 'string' ? citation.title : String(citation.title || url),
          originalIndices: [originalIndex] // Track all original indices that map here
        });
        seenUrls.set(url, newPosition);
        indexMapping[originalIndex] = newPosition;
      }
    });


    // Remove any existing responses-sources container first
    const existingSources = messageElement.querySelector('.responses-sources');
    if (existingSources) {
      existingSources.remove();
    }

    // Create new sources container
    const sourcesContainer = document.createElement('div');
    sourcesContainer.classList.add('responses-sources', 'web-sources');

    // Add title as h3
    const title = document.createElement('h3');
    title.classList.add('sources-title');
    title.textContent = translation?.SearchSources || 'Quellen:';
    sourcesContainer.appendChild(title);

    // Add source list as ordered list
    const sourcesList = document.createElement('ol');
    sourcesList.classList.add('sources-list');

    uniqueCitations.forEach((citation, displayIndex) => {
      const listItem = document.createElement('li');
      listItem.classList.add('source-item');
      listItem.setAttribute('data-display-index', displayIndex); // New display position (0-based)
      
      // Remove UTM parameters from URL
      const cleanUrl = citation.url.replace(/[?&]utm_[^&]+/g, '').replace(/[?&]$/, '');
      
      const link = document.createElement('a');
      link.classList.add('source-link');
      link.href = cleanUrl; // Use cleaned URL for actual link
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.title = citation.title; // Keep title as tooltip
      
      // Create bold element for the URL (without https:// and UTM parameters)
      const boldUrl = document.createElement('strong');
      const displayUrl = cleanUrl.replace(/^https?:\/\//, '');
      boldUrl.textContent = displayUrl;
      link.appendChild(boldUrl);
      
      listItem.appendChild(link);
      sourcesList.appendChild(listItem);
    });

    sourcesContainer.appendChild(sourcesList);

    // Append to message content
    const messageContent = messageElement.querySelector('.message-content');
    if (messageContent) {
      // Add source anchors with IDs for inline citations to link to
      sourcesList.querySelectorAll('.source-item').forEach((item, index) => {
        const citationNum = index + 1;
        item.id = `source${messageElement.dataset.messageId || 'msg'}:${citationNum}`;
      });
      
      messageContent.appendChild(sourcesContainer);
      
      // Replace HTML links with inline citations using the index mapping
      const msgTextElement = messageContent.querySelector('.message-text');
      if (msgTextElement) {
        const messageId = messageElement.dataset.messageId || 'msg';
        
        // Replace all <a href> links with citation indices (using mapped indices)
        replaceHtmlLinksWithCitations(msgTextElement, citations, messageId, indexMapping);
        
        // Initialize click handlers for inline citations
        initializeInlineCitationHandlers(messageElement);
      }
      
    } else {
      console.error('[RESPONSES CITATIONS] No .message-content found in messageElement');
    }
  } catch (error) {
    console.error('Error parsing Responses citations:', error);
  }
}

/**
 * Add Anthropic web search sources to a message element
 * @param {HTMLElement} messageElement - The message element to add sources to
 * @param {Array} auxiliaries - Array of auxiliary data including citations
 */
function addAnthropicCitations(messageElement, auxiliaries) {
  if (!auxiliaries || !Array.isArray(auxiliaries)) {
    return;
  }

  // Find anthropicCitations auxiliary
  const citationsAux = auxiliaries.find(aux => aux.type === 'anthropicCitations');
  if (!citationsAux || !citationsAux.content) {
    return;
  }

  try {
    const citationsData = JSON.parse(citationsAux.content);
    const citations = citationsData.citations;

    if (!citations || !Array.isArray(citations) || citations.length === 0) {
      return;
    }

    // Create or get sources container
    let sourcesContainer;
    if (!messageElement.querySelector('.anthropic-sources')) {
      sourcesContainer = document.createElement('div');
      sourcesContainer.classList.add('anthropic-sources', 'web-sources');
    } else {
      sourcesContainer = messageElement.querySelector('.anthropic-sources');
      sourcesContainer.innerHTML = ''; // Clear existing
    }

    // Add title
    const title = document.createElement('div');
    title.classList.add('sources-title');
    title.textContent = 'Quellen:';
    sourcesContainer.appendChild(title);

    // Add source chips
    const chipsContainer = document.createElement('div');
    chipsContainer.classList.add('sources-chips');

    citations.forEach((citation, index) => {
      const chip = document.createElement('a');
      chip.classList.add('source-chip');
      chip.href = citation.url;
      chip.target = '_blank';
      chip.rel = 'noopener noreferrer';
      chip.title = citation.title;

      // Add chip number
      const number = document.createElement('span');
      number.classList.add('chip-number');
      number.textContent = index + 1;
      chip.appendChild(number);

      // Add chip title
      const titleSpan = document.createElement('span');
      titleSpan.classList.add('chip-title');
      titleSpan.textContent = citation.title;
      chip.appendChild(titleSpan);

      // Add page age if available
      if (citation.page_age) {
        const ageSpan = document.createElement('span');
        ageSpan.classList.add('chip-age');
        ageSpan.textContent = citation.page_age;
        chip.appendChild(ageSpan);
      }

      chipsContainer.appendChild(chip);
    });

    sourcesContainer.appendChild(chipsContainer);

    // Append to message content
    messageElement.querySelector('.message-content').appendChild(sourcesContainer);
  } catch (error) {
    console.error('Error parsing Anthropic citations:', error);
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
    const sourcesTitle = translation?.SearchSources || 'Quellen:';
    let sourcesMarkdown = `\n\n### ${sourcesTitle}\n`;
    const initialMarkdown = sourcesMarkdown;

    groundingMetadata.groundingChunks.forEach((chunk, index) => {
      if (chunk.web?.uri && chunk.web?.title) {
        const sourceLink = `${index + 1}. <a id="source${randomId}:${index + 1}" href="${chunk.web.uri}" target="_blank" class="source-link"><b>${chunk.web.title}</b></a>\n`;
        const id = preservedHTML.length;
        preservedHTML.push(sourceLink);
        sourcesMarkdown += `%%HTML_PRESERVED_${id}%%`;
      }
    });

    if (sourcesMarkdown !== initialMarkdown) {
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

/**
 * Insert a status item in the correct sorted order
 * Persistent items (reasoning summaries, web search queries) are sorted by sort-index
 * Temporary items (reasoning in-progress, web_search in-progress) go to the end
 * @param {HTMLElement} statusIndicator - The status indicator container
 * @param {HTMLElement} newItem - The new item to insert
 */
function insertStatusItemInOrder(statusIndicator, newItem) {
  const isPersistent = newItem.getAttribute('data-persistent') === 'true';
  const sortIndex = parseInt(newItem.getAttribute('data-sort-index'), 10);
  
  if (!isPersistent) {
    // Temporary items go to the end
    statusIndicator.appendChild(newItem);
    return;
  }
  
  // Get all persistent items
  const persistentItems = Array.from(statusIndicator.querySelectorAll('[data-persistent="true"]'));
  
  // Find the correct position to insert
  let inserted = false;
  for (const item of persistentItems) {
    const itemSortIndex = parseInt(item.getAttribute('data-sort-index'), 10);
    if (sortIndex < itemSortIndex) {
      statusIndicator.insertBefore(newItem, item);
      inserted = true;
      break;
    }
  }
  
  if (!inserted) {
    // Insert before the first temporary item, or at the end if no temporary items
    const firstTempItem = statusIndicator.querySelector('[data-persistent]:not([data-persistent="true"])');
    if (firstTempItem) {
      statusIndicator.insertBefore(newItem, firstTempItem);
    } else {
      statusIndicator.appendChild(newItem);
    }
  }
}

/**
 * Update AI status indicator for streaming responses (NEW SYSTEM)
 * Shows collapsible status log with current status always visible
 * @param {HTMLElement} messageElement - The message element to add status to
 * @param {Array} auxiliaries - Array of auxiliary data including status updates
 * @param {boolean} isDone - Whether the stream is complete
 */
function updateAiStatusIndicator(messageElement, auxiliaries, isDone = false) {
  
  // First, try to restore status log from auxiliaries (ONLY for messages loaded from DB)
  // During streaming, we build the log incrementally via status auxiliaries
  const hasEmptyStatusLog = !messageElement.dataset.statusLog || messageElement.dataset.statusLog === '{"steps":[],"currentStep":0}';
  const statusLogAux = auxiliaries?.find(aux => aux.type === 'status_log');
  
  // Only restore from status_log if we have NO existing log (DB load case)
  if (hasEmptyStatusLog && statusLogAux && statusLogAux.content) {
    try {
      const logData = JSON.parse(statusLogAux.content);
      
      // Reconstruct status log from persisted data
      const statusLog = {
        steps: [],
        currentStep: 0
      };
      
      // Convert persisted log entries to status log steps
      if (logData.log && Array.isArray(logData.log)) {
        logData.log.forEach((entry, index) => {
          const step = {
            step: index + 1,
            output_index: entry.output_index ?? null,
            status: entry.status,
            type: entry.type,
            // Derive label if message is null/empty, otherwise use message
            label: entry.message || getStatusLabel(entry.status, entry.type, null, null),
            icon: getStatusIcon(entry.status, entry.type), // Pass type for correct icon
            timestamp: entry.timestamp
          };
          
          // Add reasoning summary details if available
          if (entry.summary) {
            step.details = {
              content: entry.summary
            };
          }
          
          statusLog.steps.push(step);
        });
        
        // Check if log ended with error/cancellation
        const hasErrorOrCancelled = statusLog.steps.some(s => 
          s.status === 'error' || s.status === 'cancelled'
        );
        
        // If error/cancelled, mark all in_progress steps as incomplete
        if (hasErrorOrCancelled) {
          statusLog.steps.forEach(step => {
            if (step.status === 'in_progress') {
              step.status = 'incomplete';
            }
          });
        }
        
        statusLog.currentStep = statusLog.steps.length;
      }
      
      messageElement.dataset.statusLog = JSON.stringify(statusLog);
      
      // Render the restored status indicator
      if (statusLog.steps.length > 0) {
        renderStatusIndicator(messageElement);
      }
    } catch (error) {
      console.error('[STATUS LOG] Error restoring from auxiliaries:', error);
    }
  }
  
  // Process auxiliaries BEFORE isDone logic (so final status auxiliary is processed)
  if (auxiliaries && Array.isArray(auxiliaries)) {
    
    // Check if we have a persisted status_log (from DB load)
    const hasPersistedLog = auxiliaries.some(aux => aux.type === 'status_log');
    
    // Process status auxiliary for generic status updates
    // SKIP if we already have a persisted log (avoid duplicates)
    if (!hasPersistedLog) {
      const statusAux = auxiliaries.find(aux => aux.type === 'status');
      if (statusAux && statusAux.content) {
        try {
          const statusData = JSON.parse(statusAux.content);
          const { status, type: backendType, message, query, output_index } = statusData;
          
          
          // Use type from backend if provided, otherwise derive from status
          // Backend now sends explicit type for disambiguation (e.g., "completed" + "reasoning")
          const type = backendType || getStatusType(status);
          const normalizedStatus = status.includes('complete') ? 'completed' : 'in_progress';
          
          // Check if this exact status update already exists in the log
          // This prevents duplicates when auxiliary is processed multiple times during streaming
          const statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
          const alreadyExists = statusLog.steps.some(step =>
            step.type === type &&
            step.status === normalizedStatus &&
            step.output_index === (output_index ?? null)
          );
          
          if (!alreadyExists) {
            const statusUpdate = {
              output_index: output_index ?? null,
              status: normalizedStatus,
              type: type,
              label: getStatusLabel(normalizedStatus, type, message, query),
              icon: getStatusIcon(normalizedStatus, type), // Pass type for correct icon
              timestamp: Date.now()
            };
            
            updateStatusLog(messageElement, statusUpdate);
          }
        } catch (error) {
          console.error('[STATUS] Error parsing status:', error);
        }
      } else {
      }
    } else {
    }
  }
  
  // If stream is done, finalize the log
  if (isDone) {
    const statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
    
    
    if (statusLog.steps.length > 0) {
      // Check if stream ended with error or cancellation
      const hasErrorOrCancelled = statusLog.steps.some(s => 
        s.status === 'error' || s.status === 'cancelled'
      );
      
      // If error/cancelled, mark all in_progress steps as incomplete
      if (hasErrorOrCancelled) {
        statusLog.steps.forEach(step => {
          if (step.status === 'in_progress') {
            step.status = 'incomplete';
          }
        });
        // Save updated log
        messageElement.dataset.statusLog = JSON.stringify(statusLog);
      }
      
      // Check if we already have a "processing completed" step (from status auxiliary)
      const hasCompletedStep = statusLog.steps.some(s => 
        s.type === 'processing' && s.status === 'completed'
      );
      
      
      // Only add "processing completed" if no error/cancellation AND not already present
      if (!hasErrorOrCancelled && !hasCompletedStep) {
        updateStatusLog(messageElement, {
          output_index: null,
          status: 'completed',
          type: 'processing',
          label: translation?.Status_Completed || 'Processing completed',
          icon: 'check2-circle',
          timestamp: Date.now()
        });
      } else {
        // Re-render to update UI with incomplete steps
        renderStatusIndicator(messageElement);
      }
      
    }
    return;
  }

  // Process reasoning summary items - add as details to completed reasoning steps
  // ONLY for streaming (non-streaming already has summaries in status_log)
  const hasPersistedLog = auxiliaries.some(aux => aux.type === 'status_log');
  const reasoningSummaryItems = auxiliaries.filter(aux => aux.type === 'reasoning_summary_item');
  
  if (reasoningSummaryItems.length > 0 && !hasPersistedLog) {
    // Only process reasoning_summary_item for streaming (no persisted log)
    reasoningSummaryItems.forEach(summaryAux => {
      try {
        const summaryData = JSON.parse(summaryAux.content);
        const { index, title, summary, output_index } = summaryData;
        
        
        // Find and update the reasoning step with this output_index
        const statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
        const reasoningStep = statusLog.steps.find(s => 
          s.type === 'reasoning' && s.output_index === output_index
        );
        
        if (reasoningStep) {
          // Update existing reasoning step with summary details
          reasoningStep.status = 'completed';
          reasoningStep.label = title; // Use title directly, not fallback text
          reasoningStep.icon = 'reasoning'; // Keep reasoning icon (CPU)
          reasoningStep.details = {
            content: summary
          };
          
          messageElement.dataset.statusLog = JSON.stringify(statusLog);
          
          // Only re-render if this is NOT the current active step with spinner
          // to avoid spinner flickering during updates
          const currentStepIndex = statusLog.currentStep - 1;
          const isCurrentStep = statusLog.steps[currentStepIndex] === reasoningStep;
          
          if (!isCurrentStep) {
            // Not the current active step, safe to re-render
            renderStatusIndicator(messageElement);
          } else {
            // This is the current step - just update the details without full re-render
            const container = messageElement.querySelector('.ai-status-indicator');
            if (container) {
              // Update the log item details without re-rendering the whole indicator
              const logItem = container.querySelector(`.status-log-item[data-step="${reasoningStep.step}"]`);
              if (logItem && logItem.tagName === 'DETAILS') {
                const contentDiv = logItem.querySelector('.reasoning-summary-content');
                if (contentDiv) {
                  contentDiv.textContent = summary;
                }
              }
            }
          }
          
        } else {
          // Create new reasoning completed step if no in-progress step exists
          updateStatusLog(messageElement, {
            output_index: output_index,
            status: 'completed',
            type: 'reasoning',
            label: title, // Use title directly
            icon: 'reasoning', // Keep reasoning icon (CPU)
            details: {
              content: summary
            },
            timestamp: Date.now()
          });
          
        }
      } catch (error) {
        console.error('[REASONING SUMMARY] Error parsing summary item:', error);
      }
    });
  }

  // Process web search query items - add as completed web_search steps
  const webSearchQueryItems = auxiliaries.filter(aux => aux.type === 'web_search_query');
  if (webSearchQueryItems.length > 0) {
    
    webSearchQueryItems.forEach(searchAux => {
      try {
        const searchData = JSON.parse(searchAux.content);
        const { index, query, output_index } = searchData;
        
        // Ensure query is a string
        const queryString = typeof query === 'string' ? query : (query?.query || JSON.stringify(query));
        
        
        // Find and update the web_search step with this output_index
        const statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
        const webSearchStep = statusLog.steps.find(s => 
          s.type === 'web_search' && s.output_index === output_index
        );
        
        if (webSearchStep) {
          // Update existing web_search step with query
          webSearchStep.status = 'completed';
          webSearchStep.label = (translation?.Status_WebSearchComplete || 'Searched for: {query}').replace('{query}', queryString);
          webSearchStep.icon = 'search'; // Keep globe icon for web search
          
          messageElement.dataset.statusLog = JSON.stringify(statusLog);
          renderStatusIndicator(messageElement);
          
        } else {
          // Create new web_search completed step
          updateStatusLog(messageElement, {
            output_index: output_index,
            status: 'completed',
            type: 'web_search',
            label: (translation?.Status_WebSearchComplete || 'Searched for: {query}').replace('{query}', queryString),
            icon: 'search', // Globe icon for web search
            timestamp: Date.now()
          });
          
        }
      } catch (error) {
        console.error('[WEB SEARCH] Error parsing query item:', error);
      }
    });
  }

  // Legacy: Handle old combined reasoning summary format (for backwards compatibility)
  const reasoningSummaryAux = auxiliaries.find(aux => aux.type === 'reasoning_summary');
  if (reasoningSummaryAux && reasoningSummaryAux.content) {
    try {
      const summaryData = JSON.parse(reasoningSummaryAux.content);
      const summary = summaryData.summary;
      const outputIndex = summaryData.output_index ?? null;  // Extract output_index from auxiliary
      
      if (summary) {
        // Check if we already added this reasoning summary to status log
        // This prevents duplicates when auxiliary is processed multiple times
        const statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
        const alreadyExists = statusLog.steps.some(step => 
          step.type === 'reasoning' && 
          step.status === 'completed' &&
          step.output_index === outputIndex &&  // Match by output_index for multi-output support
          step.details?.content === summary
        );
        
        if (!alreadyExists) {
          // Add as generic reasoning completed step
          updateStatusLog(messageElement, {
            output_index: outputIndex,  // Use output_index from auxiliary
            status: 'completed',
            type: 'reasoning',
            label: translation?.Status_ReasoningComplete || 'Reasoning completed',
            icon: getStatusIcon('completed', 'reasoning'),  // Use getStatusIcon for correct icon (CPU/reasoning)
            details: {
              title: 'Reasoning Summary',
              content: summary
            },
            timestamp: Date.now()
          });
        }
      }
    } catch (error) {
      console.error('[REASONING SUMMARY LEGACY] Error parsing summary:', error);
    }
  }
}

/**
 * Update the global Response status (in_progress / completed)
 * This status shows the overall processing state of the response
 */
function updateResponseStatus(statusIndicator, status, message) {
  const statusType = 'response';
  
  // Create localized status content
  let displayMessage = message;
  if (status === 'in_progress') {
    displayMessage = translation?.Status_Processing || 'Processing...';
  } else if (status === 'completed') {
    displayMessage = translation?.Status_Completed || 'Processing completed';
  }
  
  // Get icon for status
  let icon = '';
  let isComplete = false;
  if (status === 'in_progress') {
    icon = '<svg class="status-icon loading-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/></svg>';
  } else if (status === 'completed') {
    icon = '<svg class="status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    isComplete = true;
  }
  
  // Check if we already have a response status item
  let statusItem = statusIndicator.querySelector(`[data-status-category="response"]`);
  
  if (!statusItem) {
    // Create new response status item
    statusItem = document.createElement('div');
    statusItem.classList.add('ai-status-item');
    statusItem.setAttribute('data-status-category', 'response');
    statusItem.setAttribute('data-status-type', statusType);
    
    // Response status goes at the end (after all persistent items)
    statusIndicator.appendChild(statusItem);
  } else {
    // If status is 'completed', move it to the end (after all other items)
    if (status === 'completed') {
      statusIndicator.appendChild(statusItem);
    }
    // Otherwise keep it at its current position
  }
  
  // Update status item classes
  statusItem.className = 'ai-status-item';
  statusItem.classList.add(`status-${status}`);
  if (isComplete) {
    statusItem.classList.add('status-complete');
  }
  statusItem.setAttribute('data-status-category', 'response');
  statusItem.setAttribute('data-status-type', statusType);
  
  // Update status item content
  statusItem.innerHTML = `${icon}<span class="status-text">${displayMessage}</span>`;
}

/**
 * Update Model status (reasoning / web_search / reasoning_complete / web_search_complete)
 * These are temporary status indicators that show current model activity
 * Each reasoning/web_search item is identified by its output_index to allow multiple concurrent items
 */
function updateModelStatus(statusIndicator, status, message, query, outputIndex) {
  // Determine base status type (reasoning or web_search)
  const baseStatus = status.replace('_complete', '');
  const isComplete = status.includes('_complete');
  
  // Determine the correct category for this status
  const statusCategory = (baseStatus === 'web_search' || baseStatus === 'web_search_complete') ? 'web_search' : 'reasoning';
  
  // Build unique selector using output_index and correct category
  const selector = outputIndex !== undefined 
    ? `[data-status-category="${statusCategory}"][data-status-type="${baseStatus}"][data-output-index="${outputIndex}"]`
    : `[data-status-category="${statusCategory}"][data-status-type="${baseStatus}"]`;
  
  // Find existing status item
  let statusItem = statusIndicator.querySelector(selector);
  
  
  // If this is a complete event
  if (isComplete) {
    if (statusItem) {
      // Special handling for reasoning_complete and web_search_complete without query/summary
      // These temporary items should be removed if no persistent content will replace them
      if (status === 'web_search_complete' && !query) {
        statusItem.remove();
        return;
      }
      
      if (status === 'reasoning_complete') {
        // For reasoning, we always remove the temporary item
        // It will be replaced by a summary (if available) or just disappear
        statusItem.remove();
        return;
      }
      
      // For other complete events, mark as complete with checkmark
      
      // Update to completed state
      statusItem.classList.remove('status-reasoning', 'status-web_search');
      statusItem.classList.add(`status-${status}`, 'status-complete');
      
      // Update icon to checkmark
      const icon = '<svg class="status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
      
      // Update message
      let completedMessage = message;
      if (query) {
        const template = translation?.Status_WebSearchComplete || 'Searched for: {query}';
        completedMessage = template.replace('{query}', query);
      }
      
      statusItem.innerHTML = `${icon}<span class="status-text">${completedMessage}</span>`;
    } else {
    }
    return;
  }
  
  // Create localized status content for in-progress status
  let displayMessage = message;
  if (status === 'reasoning') {
    displayMessage = translation?.Status_Reasoning || 'Model is reasoning...';
  } else if (status === 'web_search') {
    displayMessage = translation?.Status_WebSearch || 'Searching the web...';
  }
  
  // Get icon for in-progress status
  let icon = '';
  if (baseStatus === 'reasoning') {
    icon = '<svg class="status-icon loading-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="14" x2="23" y2="14"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="14" x2="4" y2="14"></line></svg>';
  } else if (baseStatus === 'web_search') {
    icon = '<svg class="status-icon loading-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke-width="2" stroke-linecap="round"/></svg>';
  }
  
  // Check if we already have a status item for this specific output_index
  if (!statusItem) {
    // Create new status item
    statusItem = document.createElement('div');
    statusItem.classList.add('ai-status-item');
    statusItem.setAttribute('data-status-category', statusCategory);
    statusItem.setAttribute('data-status-type', baseStatus);
    if (outputIndex !== undefined) {
      statusItem.setAttribute('data-output-index', outputIndex);
    }
    
    // Status items are persistent like a log - add in order by output_index
    // Find correct position based on output_index to maintain chronological order
    if (outputIndex !== undefined) {
      let inserted = false;
      const allItems = statusIndicator.querySelectorAll('[data-output-index], [data-sort-index]');
      
      for (const existingItem of allItems) {
        const existingOutputIndex = parseInt(existingItem.getAttribute('data-output-index')) || 
                                    parseInt(existingItem.getAttribute('data-sort-index')) || 
                                    999;
        
        if (outputIndex < existingOutputIndex) {
          statusIndicator.insertBefore(statusItem, existingItem);
          inserted = true;
          break;
        }
      }
      
      if (!inserted) {
        statusIndicator.appendChild(statusItem);
      }
    } else {
      statusIndicator.appendChild(statusItem);
    }
    
  } else {
    // Item exists - DO NOT MOVE IT! Just update content
    // Status items are like a log - they stay in their position
  }
  
  // Update status item classes (but don't change position)
  statusItem.className = 'ai-status-item';
  statusItem.classList.add(`status-${status}`);
  statusItem.setAttribute('data-status-category', statusCategory);
  statusItem.setAttribute('data-status-type', baseStatus);
  if (outputIndex !== undefined) {
    statusItem.setAttribute('data-output-index', outputIndex);
  }
  
  // Update status item content
  statusItem.innerHTML = `${icon}<span class="status-text">${displayMessage}</span>`;
}

/**
 * ===== NEW STATUS LOG SYSTEM =====
 * Update status log - add or update a step in the collapsible log
 * @param {HTMLElement} messageElement - The message element
 * @param {Object} statusUpdate - Status update object with step info
 */
function updateStatusLog(messageElement, statusUpdate) {
  // Get or create status log
  let statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
  
  // Special handling for "processing" type:
  // - "processing" with "in_progress" is the start (only add once!)
  // - "processing" with "completed" is the end (add as new step)
  // For other types (reasoning, web_search), update existing in_progress steps
  
  let existingStep = null;
  
  if (statusUpdate.type === 'processing') {
    if (statusUpdate.status === 'in_progress') {
      // Check if we already have a "processing in_progress" step
      existingStep = statusLog.steps.find(s => 
        s.type === 'processing' && s.status === 'in_progress'
      );
      // If found, don't add duplicate - just skip
      if (existingStep) {
        return; // Don't add duplicate
      }
    }
    // For "processing completed", always add new step
    existingStep = null; // Force new step
  } else if (statusUpdate.type === 'completed') {
    // For generic "completed" type, always add new step
    existingStep = null; // Force new step
  } else {
    // For tool activities (reasoning, web_search): Update existing step
    // Find by output_index (for completed steps) OR by type + in_progress status
    existingStep = statusLog.steps.find(s => {
      // Match by output_index if available
      if (statusUpdate.output_index !== null && s.output_index === statusUpdate.output_index && s.type === statusUpdate.type) {
        return true;
      }
      // Match by type + in_progress status as fallback
      if (statusUpdate.output_index === null && s.type === statusUpdate.type && s.status === 'in_progress') {
        return true;
      }
      return false;
    });
  }
  
  if (existingStep) {
    // Update existing step (only for tool activities)
    Object.assign(existingStep, statusUpdate);
  } else {
    // Add new step
    statusUpdate.step = statusLog.steps.length + 1;
    statusUpdate.timestamp = Date.now();
    statusLog.steps.push(statusUpdate);
    statusLog.currentStep = statusUpdate.step;
  }
  
  // Save back to dataset
  messageElement.dataset.statusLog = JSON.stringify(statusLog);
  
  // Re-render status indicator
  renderStatusIndicator(messageElement);
}

/**
 * Render status indicator (current status + collapsible log)
 * @param {HTMLElement} messageElement - The message element
 */
function renderStatusIndicator(messageElement) {
  const statusLog = JSON.parse(messageElement.dataset.statusLog || '{"steps":[],"currentStep":0}');
  
  if (statusLog.steps.length === 0) return;
  
  // Get or create container
  let container = messageElement.querySelector('.ai-status-indicator');
  if (!container) {
    container = document.createElement('div');
    container.classList.add('ai-status-indicator');
    container.dataset.expanded = 'false';
    
    const messageWrapper = messageElement.querySelector('.message-wrapper');
    const messageHeader = messageWrapper?.querySelector('.message-header');
    if (messageHeader?.nextSibling) {
      messageWrapper.insertBefore(container, messageHeader.nextSibling);
    } else if (messageWrapper) {
      messageWrapper.appendChild(container);
    }
  }
  
  // Get current step
  const currentStep = statusLog.steps[statusLog.currentStep - 1];
  if (!currentStep) return;
  
  // Render current status (or reuse existing)
  let currentStatus = container.querySelector('.status-current');
  if (!currentStatus) {
    currentStatus = document.createElement('div');
    currentStatus.className = 'status-current';
    currentStatus.onclick = () => toggleStatusLog(messageElement);
  }
  
  // Add spinner ONLY for tool activities (reasoning, web_search) that are still in progress
  const showSpinner = currentStep.status === 'in_progress' && 
                      (currentStep.type === 'reasoning' || currentStep.type === 'web_search');
  const spinnerHtml = showSpinner
    ? '<svg class="status-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2 A10 10 0 0 1 22 12" stroke-linecap="round"/></svg>'
    : '';
  
  currentStatus.innerHTML = `
    ${spinnerHtml}
    ${getIconSvg(currentStep.icon, false)}
    <span class="status-text">${currentStep.label}</span>
    <svg class="status-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="18 15 12 9 6 15"></polyline>
    </svg>
  `;
  
  // Render full log (or reuse existing)
  let statusLogDiv = container.querySelector('.status-log');
  if (!statusLogDiv) {
    statusLogDiv = document.createElement('div');
    statusLogDiv.className = 'status-log';
  }
  
  // Update visibility based on expanded state
  const isExpanded = container.dataset.expanded === 'true';
  statusLogDiv.style.display = isExpanded ? 'block' : 'none';
  
  // Build log items HTML
  statusLogDiv.innerHTML = statusLog.steps.map(step => {
  // Add spinner ONLY for tool activities (reasoning, web_search) that are still in progress
  const showSpinner = step.status === 'in_progress' && 
                      (step.type === 'reasoning' || step.type === 'web_search');
  const spinnerHtml = showSpinner
    ? '<svg class="status-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"/><path d="M12 2 A10 10 0 0 1 22 12" stroke-linecap="round"/></svg>'
    : '';    // Build HTML based on whether step has details (reasoning summary)
    if (step.details) {
      // For steps with details: use <details> element with clickable summary
      return `
        <div class="status-log-item" data-step="${step.step}" data-status="${step.status}" data-type="${step.type}">
          ${spinnerHtml}
          ${getIconSvg(step.icon, false)}
          <details class="status-details">
            <summary class="status-label-clickable">
              ${step.label}
              <svg class="status-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </summary>
            <div class="status-content">${step.details.content}</div>
          </details>
        </div>
      `;
    } else {
      // For regular steps: simple label
      return `
        <div class="status-log-item" data-step="${step.step}" data-status="${step.status}" data-type="${step.type}">
          ${spinnerHtml}
          ${getIconSvg(step.icon, false)}
          <div class="status-label">
            ${step.label}
          </div>
        </div>
      `;
    }
  }).join('');
  
  // Update DOM
  if (!container.querySelector('.status-current')) {
    container.appendChild(currentStatus);
  }
  if (!container.querySelector('.status-log')) {
    container.appendChild(statusLogDiv);
  }
  
}

/**
 * Toggle status log visibility
 * @param {HTMLElement} messageElement - The message element
 */
function toggleStatusLog(messageElement) {
  const container = messageElement.querySelector('.ai-status-indicator');
  if (!container) return;
  
  const isExpanded = container.dataset.expanded === 'true';
  container.dataset.expanded = isExpanded ? 'false' : 'true';
  
  const statusLog = container.querySelector('.status-log');
  if (statusLog) {
    statusLog.style.display = isExpanded ? 'none' : 'block';
  }
  
}

/**
 * Get icon SVG by type and loading state
 * @param {string} iconType - Icon type (checkmark, loading, search, processing, reasoning)
 * @param {boolean} isLoading - Not used anymore, kept for compatibility
 * @returns {string} SVG HTML string
 */
function getIconSvg(iconType, isLoading = false) {
  // Icons are now static, spinner is added separately
  
  const icons = {
    // Generic checkmark for completed status
    'checkmark': '<svg class="status-icon status-icon-stroke" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
    
    // Check2-circle icon for processing completed (Bootstrap Icons bi-check2-circle)
    'check2-circle': '<svg class="status-icon status-icon-fill" viewBox="0 0 16 16" fill="currentColor"><path d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"/><path d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"/></svg>',
    
    // Checkbox checked icon (legacy, kept for compatibility)
    'checkbox': '<svg class="status-icon status-icon-stroke" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><polyline points="9 11 12 14 15 10"/></svg>',
    
    // Error icon - Alert triangle
    'error': '<svg class="status-icon status-icon-stroke status-icon-error" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><circle cx="12" cy="17" r="0.5" fill="currentColor"/></svg>',
    
    // Cloud Upload icon - Bootstrap Icons bi-cloud-upload (for initial request received)
    'send': '<svg class="status-icon status-icon-fill" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M4.406 1.342A5.53 5.53 0 0 1 8 0c2.69 0 4.923 2 5.166 4.579C14.758 4.804 16 6.137 16 7.773 16 9.569 14.502 11 12.687 11H10a.5.5 0 0 1 0-1h2.688C13.979 10 15 8.988 15 7.773c0-1.216-1.02-2.228-2.313-2.228h-.5v-.5C12.188 2.825 10.328 1 8 1a4.53 4.53 0 0 0-2.941 1.1c-.757.652-1.153 1.438-1.153 2.055v.448l-.445.049C2.064 4.805 1 5.952 1 7.318 1 8.785 2.23 10 3.781 10H6a.5.5 0 0 1 0 1H3.781C1.708 11 0 9.366 0 7.318c0-1.763 1.266-3.223 2.942-3.593.143-.863.698-1.723 1.464-2.383"/><path fill-rule="evenodd" d="M7.646 4.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1-.708.708L8.5 5.707V14.5a.5.5 0 0 1-1 0V5.707L5.354 7.854a.5.5 0 1 1-.708-.708z"/></svg>',
    
    // Generic loading spinner icon (not animated itself)
    'loading': '<svg class="status-icon status-icon-stroke" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>',
    
    // Web Search icon - World icon (same as input field) - uses stroke
    'search': '<svg class="status-icon status-icon-stroke" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3.6 9h16.8"/><path d="M3.6 15h16.8"/><path d="M11.5 3a17 17 0 0 0 0 18"/><path d="M12.5 3a17 17 0 0 1 0 18"/></svg>',
    
    // Processing icon - Bootstrap Terminal (bi-terminal) - uses fill
    'processing': '<svg class="status-icon status-icon-fill" viewBox="0 0 16 16" fill="currentColor"><path d="M6 9a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3A.5.5 0 0 1 6 9zM3.854 4.146a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708l-2-2z"/><path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2H2zm12 1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1h12z"/></svg>',
    
    // Reasoning icon - CPU/Chip (custom) - uses stroke
    'reasoning': '<svg class="status-icon status-icon-stroke" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/><line x1="9" y1="1" x2="9" y2="4"/><line x1="15" y1="1" x2="15" y2="4"/><line x1="9" y1="20" x2="9" y2="23"/><line x1="15" y1="20" x2="15" y2="23"/><line x1="20" y1="9" x2="23" y2="9"/><line x1="20" y1="14" x2="23" y2="14"/><line x1="1" y1="9" x2="4" y2="9"/><line x1="1" y1="14" x2="4" y2="14"/></svg>'
  };
  
  return icons[iconType] || icons.checkmark;
}

/**
 * Get status type from status string
 * @param {string} status - Status string (e.g., 'reasoning', 'web_search_complete')
 * @returns {string} Type (processing, reasoning, web_search, completed)
 */
function getStatusType(status) {
  if (status === 'in_progress') return 'processing';
  if (status === 'completed') return 'processing'; // Final "Processing completed" is type 'processing'
  if (status === 'processing_completed') return 'processing'; // Chat Completions final status
  if (status.includes('reasoning')) return 'reasoning';
  if (status.includes('web_search')) return 'web_search';
  return 'processing';
}

/**
 * Get status label with proper translation
 * @param {string} status - Status string
 * @param {string} type - Status type (processing, reasoning, web_search)
 * @param {string} message - Optional custom message (for Reasoning Summary Titles)
 * @param {string} query - Optional search query
 * @returns {string} Localized label
 */
function getStatusLabel(status, type, message, query) {
  // 1. Custom message has PRIORITY (Reasoning Summary Title)
  if (message) return message;
  
  // 2. Web Search with query (check type + completed status OR original status)
  if ((type === 'web_search' && status === 'completed') || status === 'web_search_complete') {
    if (query) {
      return (translation?.Status_WebSearchComplete || 'Searched for: {query}').replace('{query}', query);
    }
  }
  
  // 3. Derive label from status + type (NO MESSAGE from backend!)
  const labelKey = `${type}_${status}`;
  const labels = {
    // Processing states
    'processing_in_progress': translation?.Status_Processing || 'Processing...',
    'processing_completed': translation?.Status_Completed || 'Processing completed',
    'processing_incomplete': translation?.Status_Incomplete || 'Incomplete',
    'processing_cancelled': translation?.Status_Cancelled || 'Response cancelled by user',
    'processing_error': translation?.Status_ServerError || 'Server connection lost',
    
    // Reasoning states
    'reasoning_reasoning': translation?.Status_Reasoning || 'Model is reasoning...',
    'reasoning_in_progress': translation?.Status_Reasoning || 'Model is reasoning...',
    'reasoning_completed': translation?.Status_ReasoningComplete || 'Reasoning completed',
    'reasoning_incomplete': translation?.Status_Incomplete || 'Incomplete',
    
    // Web Search states
    'web_search_web_search_initiated': translation?.Status_WebSearchInitiated || 'Web search initiated',
    'web_search_initiated': translation?.Status_WebSearchInitiated || 'Web search initiated',
    'web_search_web_search': translation?.Status_WebSearch || 'Searching the web...',
    'web_search_in_progress': translation?.Status_WebSearch || 'Searching the web...',
    'web_search_web_search_success': translation?.Status_WebSearchSuccess || 'Web search successful',
    'web_search_success': translation?.Status_WebSearchSuccess || 'Web search successful',
    'web_search_web_search_complete': translation?.Status_WebSearchNoQuery || 'Web search completed',
    'web_search_completed': translation?.Status_WebSearchNoQuery || 'Web search completed',
    'web_search_incomplete': translation?.Status_Incomplete || 'Incomplete'
  };
  
  // Try composite key first, then fall back to status-only
  return labels[labelKey] || labels[status] || status;
}

/**
 * Get status icon type
 * @param {string} status - Status string
 * @param {string} type - Optional type string (processing, reasoning, web_search)
 * @returns {string} Icon type
 */
function getStatusIcon(status, type = null) {
  // For in_progress status, use type to determine icon
  if (status === 'in_progress') {
    if (type === 'web_search') return 'search';
    if (type === 'reasoning') return 'reasoning';
    if (type === 'processing') return 'send'; // Send icon for initial "Processing..."
    return 'processing';
  }
  
  // For incomplete status (aborted steps)
  if (status === 'incomplete') {
    if (type === 'web_search') return 'search';
    if (type === 'reasoning') return 'reasoning';
    if (type === 'processing') return 'send';
    return 'processing';
  }
  
  // For completed status, use type to determine icon
  if (status === 'completed') {
    if (type === 'web_search') return 'search'; // Globe for completed web search
    if (type === 'reasoning') return 'reasoning'; // CPU for completed reasoning
    if (type === 'processing') return 'check2-circle'; // Check2-circle for completed processing
    return 'check2-circle'; // Default to check2-circle
  }
  
  // Error and cancelled always use error icon
  if (status === 'error' || status === 'cancelled') return 'error'; // Alert triangle for errors
  
  // Legacy status strings (for backward compatibility)
  if (status === 'reasoning' || status === 'reasoning_complete') return 'reasoning'; // CPU icon
  if (status === 'web_search_initiated' || status === 'web_search' || status === 'web_search_success' || status === 'web_search_complete') return 'search'; // Globe icon
  
  return 'check2-circle'; // Default to check2-circle for completed states
}
