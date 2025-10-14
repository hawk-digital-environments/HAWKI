




function convertChatlogToJson(){
    const thread = document.querySelector('.trunk');
    const messageElements = thread.querySelectorAll('.message');

    let messagesList = [];

    messageElements.forEach(messageElement => {

        let msgObj = {};
        msgObj.id = messageElement.id;
        msgObj.author = messageElement.dataset.author;
        msgObj.role = messageElement.dataset.role;
        msgObj.content = messageElement.dataset.rawMsg;
        msgObj.timestamp = messageElement.dataset.created_at;
        msgObj.model = messageElement.dataset.model ? messageElement.dataset.model : null,

        msgObj.attachments = Array.from(messageElement.querySelectorAll('.attachment')).map(atch => {
            return {
                name: atch.querySelector('.name-tag')?.innerText || "",
                mime: atch.dataset.mime || "",
                imageUrl: atch.querySelector('img')?.getAttribute('src') || null
            };
        });

        messagesList.push(msgObj);
    });

    return messagesList;
}



function exportAsJson() {
    const messages = convertChatlogToJson();  // Get the messages list
    const jsonContent = JSON.stringify(messages, null, 2);  // Convert to JSON string

    // Create a Blob from the JSON string
    const blob = new Blob([jsonContent], { type: "application/json" });
    const url = URL.createObjectURL(blob);

    // Create a temporary anchor element to trigger download
    const a = document.createElement("a");
    a.href = url;
    a.download = "chatlog.json";
    document.body.appendChild(a);  // Append to the DOM to make it clickable
    a.click();  // Trigger the download
    document.body.removeChild(a);  // Clean up by removing the element
    URL.revokeObjectURL(url);  // Release the blob URL
}




function exportAsCsv() {
    const messages = convertChatlogToJson();  // Get the messages list

    if (messages.length === 0) {
        console.log("No data to export");
        return;
    }

    // Define headers explicitly
    const headers = ["id", "author", "role", "content", "timestamp", "model", "attachments"];
    const headerRow = headers.join(",") + "\n";

    // Convert messages to CSV rows
    const csvRows = messages.map(msg => {
        // Only keep attachment names
        let attachmentsStr = "";
        if (msg.attachments && msg.attachments.length > 0) {
            attachmentsStr = msg.attachments.map(atch => atch.name).join("; ");
        }

        // Escape quotes in content
        const row = [
            msg.id,
            msg.author,
            msg.role,
            msg.content?.replace(/"/g, '""') || "",
            msg.timestamp,
            msg.model || "",
            attachmentsStr
        ].map(v => `"${v}"`).join(",");

        return row;
    }).join("\n");

    const csvContent = headerRow + csvRows;

    // Download as CSV
    const blob = new Blob([csvContent], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);

    const a = document.createElement("a");
    a.href = url;
    a.download = "chatlog.csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}




async function exportAsPDF() {

    //disable button
    const btn = document.getElementById('export-btn-pdf');
    btn.disabled = true;
    btn.querySelector('.loading').style.display = 'flex';



    const messages = convertChatlogToJson(); // Get the messages list

    if (messages.length === 0) {
        console.log("No data to export");
        return;
    }


    // summery
    const summeryMsg = convertMsgObjToLog(Array.from(messages).slice(-100));
    const summery = await requestChatlogSummery(summeryMsg);



    const doc = new jsPDF();

    const maxPageHeight = 270; // Maximum height before adding a new page
    const lineHeight = doc.getLineHeight() / doc.internal.scaleFactor; // Get actual line height
    const threshold = 0.3 * maxPageHeight; // Define the threshold as 30% of the page height
    const margin = 25;
    const maxWidth = 210 - (margin * 2); // Maximum width for the text

    const sectionFS = 18;
    const titleFS = 14;
    const textFS = 12;
    const smallFS = 10;
    const font = 'helvetica'
    let yOffset = 20; // Start below the header


    // Add a header with title and date
    const date = new Date();
    const formattedDate = `${date.getDate()}.${date.getMonth()+1}.${date.getFullYear()}`

    doc.setFont(font, 'normal');

    // doc.setFontSize(16);
    // doc.text("Chatlog Export", 10, 15); // x, y
    doc.setFontSize(textFS);
    doc.text(`${translation.Exported_At} ${formattedDate} ${translation.By} ${userInfo.name}`, margin, yOffset); // x, y

    yOffset += 20;
    doc.setFontSize(sectionFS);
    doc.setFont(font, 'bold');
    doc.text(translation.Summery, margin, yOffset);

    const textLenght = translation.Summery.length;
    doc.setFont(font, 'italic');
    doc.setFontSize(titleFS);
    doc.text(` (${translation.Auto_Generated})`, margin + (textLenght * 4) + 0, yOffset);
    doc.setFont(font, 'normal');


    yOffset += 10;
    doc.setFont(font, 'normal');
    doc.setFontSize(textFS);
    // Create summery
    const wrappedContent = doc.splitTextToSize(summery, maxWidth);
    wrappedContent.forEach(line => {
        // Check if the line will fit on the current page
        if (yOffset + lineHeight > maxPageHeight) {
            doc.addPage();
            yOffset = 20; // Reset yOffset for the new page
        }
        doc.text(line, margin, yOffset); // Indent content slightly
        yOffset += lineHeight; // Increment yOffset after each line
    });


    yOffset += 20;
    doc.setFontSize(sectionFS);
    doc.setFont(font, 'bold');
    doc.text(translation.SystemPrompt, margin, yOffset);

    yOffset += 15;
    doc.setFont(font, 'normal');
    doc.setFontSize(textFS);
    // Create summery
    const systemPromptTxt = document.querySelector('#system_prompt_field').textContent;
    const wrappedSP = doc.splitTextToSize(systemPromptTxt, maxWidth);
    wrappedSP.forEach(line => {
        // Check if the line will fit on the current page
        if (yOffset + lineHeight > maxPageHeight) {
            doc.addPage();
            yOffset = 20; // Reset yOffset for the new page
        }
        doc.text(line, margin, yOffset); // Indent content slightly
        yOffset += lineHeight; // Increment yOffset after each line
    });

    doc.addPage();
    yOffset = 20;

    //START OF CONVERSATION
    doc.setFontSize(sectionFS);
    doc.setFont(font, 'bold');
    doc.text(`${translation.Chatlog}:`, margin, yOffset);
    doc.setFont(font, 'normal');

    yOffset += 10;

    for(let i = 0; i < messages.length; i++){
        const msg = messages[i];
    // messages.forEach((msg, index) => {
        // Calculate the height required for the full message
        const metadataHeight = lineHeight * 3; // Header (Message #, Author, Role, etc.)

        if(isValidJson(msg.content)){
            msg.content = JSON.parse(msg.content).text;
        }
        else{
            msg.content = msg.content;
        }

        const wrappedContent = doc.splitTextToSize(msg.content, maxWidth); // Split text into lines
        const contentHeight = wrappedContent.length * lineHeight;
        const totalMessageHeight = metadataHeight + contentHeight;

        // Check if the message fits on the current page
        if (yOffset + totalMessageHeight > maxPageHeight) {
            // Check if the message is small enough to move entirely to the next page
            if (totalMessageHeight < threshold) {
                doc.addPage(); // Add a new page
                yOffset = 20; // Reset yOffset for the new page
            }
        }

        // Add message details
        doc.setFontSize(textFS);
        doc.setFont(font, 'bold');

        if(msg.model){
            doc.text(`${msg.author}`, margin, yOffset);
            doc.setFontSize(smallFS);
            const textLenght = msg.model.length;
            doc.text(`(${msg.model}):`, margin + (textLenght * 2.5) + 3, yOffset);
        }
        else{
            doc.text(`${msg.author}:`, margin, yOffset);
        }
        yOffset += 10;

        if (msg.attachments.length > 0) {
            for (const atch of msg.attachments) {
                // Fetch the image
                const response = await fetch(atch.imageUrl);
                const blob = await response.blob();

                // Convert to base64
                const base64data = await new Promise((resolve) => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.readAsDataURL(blob);
                });

                // Add image to PDF
                doc.addImage(base64data, 'JPEG', 25, yOffset, 8, 8);
                doc.setFont(font, 'normal');
                doc.setFontSize(smallFS);

                // Add text below/next to the image
                doc.text(`${atch.name} (${checkFileFormat(atch.mime)})`, 37, yOffset + 5);
                yOffset += 20; // move down for next image
            }
        }

        doc.setFont(font, 'normal');
        doc.setFontSize(textFS);
        wrappedContent.forEach(line => {
            // Check if the line will fit on the current page
            if (yOffset + lineHeight > maxPageHeight) {
                doc.addPage();
                yOffset = 20; // Reset yOffset for the new page
            }
            doc.text(line, margin, yOffset); // Indent content slightly
            yOffset += lineHeight; // Increment yOffset after each line
        });
        yOffset += 10
    };

    // Save the PDF
    btn.disabled = false;
    btn.querySelector('.loading').style.display = 'none';

    doc.save(`${translation.Chatlog}_${formattedDate}.pdf`);
}






function transformMarkdownToDocxContent(text) {
    const markdownPatterns = [
        { regex: /\*\*(.*?)\*\*/g, tag: 'bold' },      // Bold: **text**
        { regex: /\*(.*?)\*/g, tag: 'italics' },       // Italic: *text*
        { regex: /__(.*?)__/g, tag: 'underline' },     // Underline: __text__
        { regex: /`([^`]+)`/g, tag: 'code' }           // Code: `text`
        // More patterns can be added here if needed
    ];

    // Split the text by newlines to handle each line as a separate paragraph
    const lines = text.split('\n');
    const transformedParagraphs = [];

    lines.forEach(line => {
        const transformedRuns = [];
        let currentIndex = 0;

        // Process each line for markdown formatting
        markdownPatterns.forEach(({ regex, tag }) => {
            let match;
            while ((match = regex.exec(line)) !== null) {
                // Add any preceding text as a normal text run
                if (match.index > currentIndex) {
                    transformedRuns.push(new docx.TextRun({
                        text: line.substring(currentIndex, match.index),
                        size: 24,
                    }));
                }

                // Add the matched markdown content with appropriate styling
                transformedRuns.push(new docx.TextRun({
                    text: match[1],
                    size: 24,
                    bold: tag === 'bold',
                    italics: tag === 'italics',
                    underline: tag === 'underline' ? {} : undefined,
                    font: tag === 'code' ? 'Courier New' : undefined,
                }));

                currentIndex = regex.lastIndex;
            }
        });

        // Add any remaining text in the line after the last markdown match
        if (currentIndex < line.length) {
            transformedRuns.push(new docx.TextRun({
                text: line.substring(currentIndex),
                size: 24,
            }));
        }

        // Add this line's content as a paragraph
        transformedParagraphs.push(new docx.Paragraph({
            children: transformedRuns,
            spacing: { after: 200 }, // Adjust spacing between paragraphs as needed
        }));
    });

    return transformedParagraphs;
}

// In your main export function, adapt to handle multiple paragraphs per message:
async function exportAsWord() {
    // Disable button
    const btn = document.getElementById('export-btn-word');
    btn.disabled = true;
    btn.querySelector('.loading').style.display = 'flex';

    const messages = convertChatlogToJson();
    if (messages.length === 0) {
        console.log("No data to export");
        btn.disabled = false;
        btn.querySelector('.loading').style.display = 'none';
        return;
    }

    const summeryMsg = convertMsgObjToLog(Array.from(messages).slice(-100));
    const summery = await requestChatlogSummery(summeryMsg);

    const chatLogChildren = [];
    const date = new Date();
    const formattedDate = `${date.getDate()}.${date.getMonth() + 1}.${date.getFullYear()}`;

    chatLogChildren.push(
        new docx.Paragraph({
            children: [
                new docx.TextRun({
                    text: `${translation.Exported_At} ${formattedDate} ${translation.By} ${userInfo.name}`,
                    size: 24,
                }),
            ],
            spacing: { after: 400 },
        })
    );

    chatLogChildren.push(
        new docx.Paragraph({
            children: [
                new docx.TextRun({
                    text: translation.Summery,
                    bold: true,
                    size: 36,
                }),
                new docx.TextRun({
                    text: ` (${translation.Auto_Generated})`,
                    italics: true,
                    size: 28,
                }),
            ],
            spacing: { after: 200 },
        })
    );

    chatLogChildren.push(...transformMarkdownToDocxContent(summery));


    const systemPromptTxt = document.querySelector('#system_prompt_field').textContent;
    chatLogChildren.push(
        new docx.Paragraph({
            children: [
                new docx.TextRun({
                    text: `${translation.SystemPrompt}:`,
                    bold: true,
                    size: 36,
                }),
            ],
            spacing: { after: 200 },
        })
    );
    chatLogChildren.push(
        new docx.Paragraph({
            children: [
                new docx.TextRun({
                    text: systemPromptTxt,
                    bold: false,
                    size: 24,
                }),
            ],
            spacing: { after: 200 },
        })
    );



    chatLogChildren.push(
        new docx.Paragraph({
            children: [
                new docx.TextRun({
                    text: `${translation.Chatlog}:`,
                    bold: true,
                    size: 36,
                }),
            ],
            spacing: { after: 200 },
        })
    );

    for (const message of messages) {
        let authorText = message.model ? `${message.author} (${message.model})` : `${message.author}`;

        chatLogChildren.push(
            new docx.Paragraph({
                children: [
                    new docx.TextRun({
                        text: authorText,
                        bold: true,
                        size: 24,
                    }),
                ],
                spacing: {
                    before: 200,
                    after: 200
                },
            })
        );

        // Handle Attachment Files
        if (message.attachments && message.attachments.length > 0) {
            for (const atch of message.attachments) {
                const imageData = await fetchImageAsUint8Array(atch.imageUrl);

                chatLogChildren.push(
                    new docx.Paragraph({
                        children: [
                            new docx.ImageRun({
                                data: imageData,
                                transformation: {
                                    width: 25, // px
                                    height: 25,
                                },
                            }),
                            new docx.TextRun({
                                text: `   ${atch.name} (${checkFileFormat(atch.mime)})`,
                                size: 20,
                            }),
                        ],
                        spacing: { after: 200 },
                    })
                );
            }
        }
        chatLogChildren.push(...transformMarkdownToDocxContent(message.content));

    };

    const doc = new docx.Document({
        sections: [
            {
                headers: {
                    default: new docx.Header({
                        children: [new docx.Paragraph("Chat Log Export")],
                    }),
                },
                properties: {
                    type: docx.SectionType.CONTINUOUS,
                },
                children: chatLogChildren,
            },
        ],
    });

    docx.Packer.toBlob(doc).then((blob) => {
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = `${translation.Chatlog}_${formattedDate}.docx`;
        link.click();
        URL.revokeObjectURL(url);
    });

    btn.disabled = false;
    btn.querySelector('.loading').style.display = 'none';
}



// Helper to fetch an image URL and convert it into Uint8Array for docx
async function fetchImageAsUint8Array(url) {
    const response = await fetch(url);
    const blob = await response.blob();
    const arrayBuffer = await blob.arrayBuffer();
    return new Uint8Array(arrayBuffer);
}


function exportPrintPage(){

    if(!activeModule) return;

    let slug;
    if(activeModule === 'chat'){
        if(!activeConv) return;
        slug = activeConv.slug;
    }
    else{
        if(!activeRoom) return;
        slug = activeRoom.slug;
    }

    history.replaceState(null, '', '/');

    const url = `print/${activeModule}/${slug}`;
    window.open(url, '_blank');

}

async function preparePrintPage(){

    let systemPrompt;
    let key;
    let aiKey;
    let messages;


    if(activeModule === 'chat'){
        //data is received from the server

        key = await keychainGet('aiConvKey');
        const systemPromptObj = JSON.parse(chatData.system_prompt);
        systemPrompt = await decryptWithSymKey(key, systemPromptObj.ciphertext, systemPromptObj.iv, systemPromptObj.tag, false);
        messages = chatData.messages;

        for (const msg of messages) {
            const decryptedContent =  await decryptWithSymKey(key, msg.content.text.ciphertext, msg.content.text.iv, msg.content.text.tag);
            if(isValidJson(decryptedContent)){
                msg.content.text = JSON.parse(decryptedContent).text;
            }
            else{
                msg.content.text = decryptedContent;
            }
        };

    }
    else{

        key = await keychainGet(chatData.slug);
        const aiCryptoSalt = await fetchServerSalt('AI_CRYPTO_SALT');
        aiKey = await deriveKey(key, chatData.slug, aiCryptoSalt);

        if(chatData.system_prompt){
            const systemPromptObj = JSON.parse(chatData.system_prompt);
            systemPrompt = await decryptWithSymKey(key, systemPromptObj.ciphertext, systemPromptObj.iv, systemPromptObj.tag, false);
        }
        messages = chatData.messagesData;
        //extract messages
        let msgKey = key;
        for (const msg of messages) {
            msgKey = msg.message_role === 'assistant' ? aiKey : key;
            const decryptedContent =  await decryptWithSymKey(msgKey, msg.content.text, msg.content.text.iv, msg.content.text.tag);
            msg.content.text = decryptedContent;
        };
    }

    const scrollPanel = document.querySelector('.scroll-panel');
    const date = new Date();
    const formattedDate = `${date.getDate()}.${date.getMonth()+1}.${date.getFullYear()}`

    const summeryMsg = convertMsgObjToLog(Array.from(messages).slice(-100));
    const summery = await requestChatlogSummery(summeryMsg);

    scrollPanel.innerHTML =
    `
        <p>${translation.Exported_At} ${formattedDate} ${translation.By} ${userInfo.name}</p>
        <h1>${translation.Summery}:</h1>
        <p>${summery}</p>
        <h3>System Prompt</h3>
        <p>${systemPrompt}</p>
        <h1>${translation.Chatlog}</h1>
        <div class="thread trunk" id="0">
        </div>
    `;

    messages.sort((a, b) => {
        return +a.message_id - +b.message_id;
    });

    // First, add all main messages
    activeThreadIndex = 0;
    messages.forEach(messageObj => {
        generateMessageElements(messageObj, true);
    });
    // window.print();
}

function generateMessageElements(messageObj){

    // clone message element
    const messageTemp = document.getElementById('message-template')
    const messageClone = messageTemp.content.cloneNode(true);
    const messageElement = messageClone.querySelector(".message");

    if(messageObj.model && messageObj.message_role === 'assistant'){
        model = modelsList.find(m => m.id === messageObj.model);
        messageElement.querySelector('.message-author').innerHTML =
            model ?
            `<span>${messageObj.author.username} </span><span class="message-author-model">(${model.label})</span>`:
            `<span>${messageObj.author.username} </span><span class="message-author-model">(${messageObj.model}) !!! Obsolete !!!</span>`;
    }
    else{
        messageElement.querySelector('.message-author').innerText = messageObj.author.name;
    }

    const id =  messageObj.message_id.split('.');
    const wholeNum = Number(id[0]);
    const deciNum = Number(id[1]);

    let threadIndex;
    if(deciNum === 0){
        threadIndex = 0;
    }
    else{
        threadIndex = wholeNum;
    }
    let activeThread = document.querySelector(`.thread#${CSS.escape(threadIndex)}`);

    // if message has a date it's already submitted and comes from the server.
    // if not, it has been created by user and does not have a date stamp -> today is the date
    let msgDate;
    if(messageObj.created_at){
        msgDate = messageObj.created_at.split('+')[0];
    }
    else{
        todayDate = new Date();
        msgDate = `${todayDate.getFullYear()}-${(todayDate.getMonth() + 1).toString().padStart(2, '0')}-${todayDate.getDate().toString().padStart(2, '0')}`;
    }
    setDateSpan(activeThread, msgDate, false);

    // Setup Message Content
    const msgTxtElement = messageElement.querySelector(".message-text");


    if(messageObj.content.attachments && messageObj.content.attachments.length != 0){
        const attachmentContainer = messageElement.querySelector('.attachments');

        messageObj.content.attachments.forEach(attachment => {

            const thumbnail = createAttachmentPrintIcon(attachment.fileData);
            // Add to file preview container
            attachmentContainer.appendChild(thumbnail);
        });
    }


    if(!messageObj.message_role === "assistant"){
        msgTxtElement.innerHTML = detectMentioning(messageObj.content.text).modifiedText;
    }
    else{
        let markdownProcessed = formatMessage(messageObj.content.text);
        msgTxtElement.innerHTML = markdownProcessed;
        formatMathFormulas(msgTxtElement);
    }

    // insert into target thread
    if(threadIndex === 0){
        // if message is a main message then it needs a thread inside
        const threadTemplate = document.getElementById('thread-template');
        const threadElement = threadTemplate.content.cloneNode(true);
        threadDiv = threadElement.querySelector('.thread');
        threadDiv.classList.add('branch');
        threadDiv.id = wholeNum;
        messageElement.appendChild(threadDiv);
        activeThread.appendChild(messageElement);
    }
    else{
        activeThread.appendChild(messageElement);
    }
    formatHljs(messageElement);
    return  messageElement;
}

// Add file to the UI for display
function createAttachmentPrintIcon(fileData) {

    const attachTemp = document.getElementById('attachment-thumbnail-template')
    const attachClone = attachTemp.content.cloneNode(true);
    const attachment = attachClone.querySelector(".attachment");
    attachment.querySelector('.name-tag').innerText = fileData.name;
    const iconImg = attachment.querySelector('img');
    let imgPreview = '';

    const type = checkFileFormat(fileData.mime);
    switch(type){
        case('img'):
        if(fileData.url){
            imgPreview = fileData.url;
        }
        if (fileData.file) {
            imgPreview = URL.createObjectURL(fileData.file);
        }

        attachment.querySelector('.attachment-icon').classList.add('boarder');
        break;
        case('pdf'):
            imgPreview = '/img/fileformat/pdf.png';
        break;
        case('docx'):
            imgPreview = '/img/fileformat/doc.png';
        break;
    }

    attachment.querySelector('.controls').remove();
    attachment.querySelector('.status-indicator').remove();
    iconImg.setAttribute('src', imgPreview);
    return attachment;
}


