import './echo.js';


import jQuery from 'jquery';
window.$ = jQuery;


import 'cropperjs/dist/cropper.min.css';
import Cropper from 'cropperjs';
window.Cropper = Cropper;





import jsPDF from 'jspdf';
window.jsPDF = jsPDF;

import * as pdfjsLib from 'pdfjs-dist';
window.pdfjsLib = pdfjsLib;
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = pdfWorker;

// import { TextLayerBuilder } from 'pdfjs-dist/web/pdf_viewer';
// window.TextLayerBuilder = TextLayerBuilder;

import {
  PDFPageView,
  EventBus
} from 'pdfjs-dist/web/pdf_viewer';
window.PDFPageView = PDFPageView;
window.EventBus = EventBus;


import * as docx from "docx";
window.docx = docx;

import * as docxPreview from "docx-preview"
window.docxPreview = docxPreview;


import hljs from "highlight.js"
window.hljs = hljs;


import 'katex/dist/katex.min.css';
import katex from 'katex';
window.katex = katex;
import renderMathInElement from 'katex/contrib/auto-render/auto-render.js';
window.renderMathInElement = renderMathInElement;

import pako from 'pako';
window.pako = pako;

import markdownit from 'markdown-it'

const md = markdownit({
    // Enable HTML tags in source
    html:         false,
  
    // Use '/' to close single tags (<br />).
    // This is only for full CommonMark compatibility.
    xhtmlOut:     false,
  
    // Convert '\n' in paragraphs into <br>
    breaks:       false,
  
    // CSS language prefix for fenced blocks. Can be
    // useful for external highlighters.
    langPrefix:   'language-',
  
    // Autoconvert URL-like text to links
    linkify:      false,
  
    // Enable some language-neutral replacement + quotes beautification
    // For the full list of replacements, see https://github.com/markdown-it/markdown-it/blob/master/lib/rules_core/replacements.mjs
    typographer:  false,
  
    // Double + single quotes replacement pairs, when typographer enabled,
    // and smartquotes on. Could be either a String or an Array.
    //
    // For example, you can use '«»„“' for Russian, '„“‚‘' for German,
    // and ['«\xA0', '\xA0»', '‹\xA0', '\xA0›'] for French (including nbsp).
    quotes: '“”‘’',
  
    // Highlighter function. Should return escaped HTML,
    // or '' if the source string is not changed and should be escaped externally.
    // If result starts with <pre... internal wrapper is skipped.
    highlight: function (/*str, lang*/) { return ''; }
  });
  window.md = md;
