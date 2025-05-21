// #region > google parser
			let decodedDataGoogle;
			if(provider == 'google'){
				//console.log('decodedData 1',provider, typeof decodedData, decodedData);
				decodedDataGoogle = decodedData;

				parsedObjectGoogle = parseStreamedJson(decodedDataGoogle);
				//console.log(parsedObjectGoogle);


				// Plot Messages Google
				if(containsKey(parsedObjectGoogle, 'text')){								
					chunk = getValueForKey(parsedObjectGoogle, 'text');
					rawMsg += chunk;
					// Plotten während des laufenden Streams
					document.querySelector('.message:last-child').querySelector('.message-text > span').innerHTML = FormatWholeMessage(rawMsg);			
				
					if(containsKey(parsedObjectGoogle, 'renderedContent')){
						let googleSearchQuery = getValueForKey(parsedObjectGoogle, 'renderedContent');
						// Plot while the stream is running
							const newSpan = document.createElement('span');
							newSpan.classList.add('google-search-suggestions');
							newSpan.innerHTML = googleSearchQuery;
							newSpan.style.padding = '1rem 0 0';
							newSpan.style.display = 'block';
							document.querySelector('.message:last-child').appendChild(newSpan);

					}

					// Directly insert footnotes into rawMsg, based on groundingSupports and groundingChunkIndices
					if (containsKey(parsedObjectGoogle, 'groundingSupports')) {
						const groundingSupports = getValueForKey(parsedObjectGoogle, 'groundingSupports');
						if (Array.isArray(groundingSupports)) {
						  groundingSupports.forEach((support) => {
							const segmentText = support.segment && support.segment.text ? support.segment.text : '';
							const indices = support.groundingChunkIndices;
							if (segmentText && Array.isArray(indices) && indices.length) {
							  // Generate an anchor for each index, incrementing the numbering by 1 (since the sources list starts at 1)
							  let footnotesRef = indices.map(idx => {
								const footnoteNumber = idx + 1;
								return `<a href="#source${footnoteNumber}">${footnoteNumber}</a>`;
							  }).join(', ');
							  // Adds the footnote reference as a sup element
							  footnotesRef = `<sup>[${footnotesRef}]</sup>`;
							// Replace the first occurrence of segmentText with itself plus the footnote reference
							  rawMsg = rawMsg.replace(segmentText, segmentText + footnotesRef);
							}
						  });
						  // Update the DOM with the modified rawMsg
						  const messageTextElement = document.querySelector('.message:last-child .message-text > span');
						  if (messageTextElement) {
							messageTextElement.innerHTML = FormatWholeMessage(rawMsg);
						  }
						}
					  }
					  
					// Add anchors to source references
					if (containsKey(parsedObjectGoogle, 'groundingChunks')) {
						const googleSearchResults = getValueForKey(parsedObjectGoogle, 'groundingChunks');
						const messageTextElement = document.querySelector('.message:last-child .message-text > span');
						if (messageTextElement && Array.isArray(googleSearchResults)) {
						  let markdownText = '### Search Sources:\n';
						  let i = 1;
						  googleSearchResults.forEach((chunk, index) => {
							if (chunk.web && chunk.web.uri && chunk.web.title) {
							  // Add anchor for each source and add a CSS class
							  markdownText += `${i}. <a id="source${index + 1}" href="${chunk.web.uri}" target="_blank" class="source-link">${chunk.web.title}</a>\n`;
							  i++;
							}
						  });
						  messageTextElement.innerHTML += FormatWholeMessage(markdownText);
						  rawMsg += markdownText;
						}
					  }
					
					
				} else if (containsKey(parsedObjectGoogle, 'error')) {
					chunk = getValueForKey(parsedObjectGoogle, 'message');
					rawMsg += chunk;
					// Plotten während des laufenden Streams
					document.querySelector('.message:last-child').querySelector('.message-text > span').innerHTML = FormatWholeMessage(rawMsg);	
				} else {
					if (debugResponse) {
						console.log('Parsed',provider, typeof parsedObjectGoogle,'has no content');
					}
				}	

				
			}
//#region > error handling