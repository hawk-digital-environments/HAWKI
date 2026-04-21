<?php

namespace App\Services\Storage\Values;

enum PlainTextLanguageType: string
{
    case CUCUMBER = 'cucumber';
    case ABAP = 'abap';
    case ADA = 'ada';
    case AHK = 'ahk';
    case APACHECONF = 'apacheconf';
    case APPLESCRIPT = 'applescript';
    case AS = 'as';
    case AS3 = 'as3';
    case ASY = 'asy';
    case BASH = 'bash';
    case BAT = 'bat';
    case BEFUNGE = 'befunge';
    case BLITZMAX = 'blitzmax';
    case BOO = 'boo';
    case BRAINFUCK = 'brainfuck';
    case C = 'c';
    case CFM = 'cfm';
    case CHEETAH = 'cheetah';
    case CL = 'cl';
    case CLOJURE = 'clojure';
    case CMAKE = 'cmake';
    case COFFEESCRIPT = 'coffeescript';
    case CONSOLE = 'console';
    case CONTROL = 'control';
    case CPP = 'cpp';
    case CSHARP = 'csharp';
    case CSS = 'css';
    case CYTHON = 'cython';
    case D = 'd';
    case DELPHI = 'delphi';
    case DIFF = 'diff';
    case DPATCH = 'dpatch';
    case DUEL = 'duel';
    case DYLAN = 'dylan';
    case ERB = 'erb';
    case ERL = 'erl';
    case ERLANG = 'erlang';
    case EVOQUE = 'evoque';
    case FACTOR = 'factor';
    case FELIX = 'felix';
    case FORTRAN = 'fortran';
    case GAS = 'gas';
    case GENSHI = 'genshi';
    case GITIGNORE = 'gitignore';
    case GLSL = 'glsl';
    case GNUPLOT = 'gnuplot';
    case GO = 'go';
    case GROFF = 'groff';
    case HAML = 'haml';
    case HASKELL = 'haskell';
    case HTML = 'html';
    case HX = 'hx';
    case HYBRIS = 'hybris';
    case INI = 'ini';
    case IO = 'io';
    case IOKE = 'ioke';
    case IRC = 'irc';
    case JADE = 'jade';
    case JAVA = 'java';
    case JS = 'js';
    case JSP = 'jsp';
    case JSON = 'json';
    case LESS = 'less';
    case LHS = 'lhs';
    case LLVM = 'llvm';
    case LOGTALK = 'logtalk';
    case LUA = 'lua';
    case MAKE = 'make';
    case MAKO = 'mako';
    case MAQL = 'maql';
    case MASON = 'mason';
    case MARKDOWN = 'markdown';
    case MODELICA = 'modelica';
    case MODULA2 = 'modula2';
    case MOOCODE = 'moocode';
    case MUPAD = 'mupad';
    case MXML = 'mxml';
    case MYGHTY = 'myghty';
    case NASM = 'nasm';
    case NEWSPEAK = 'newspeak';
    case OBJDUMP = 'objdump';
    case OBJECTIVEC = 'objectivec';
    case OBJECTIVEJ = 'objectivej';
    case OCAML = 'ocaml';
    case OOC = 'ooc';
    case PERL = 'perl';
    case PHP = 'php';
    case POSTSCRIPT = 'postscript';
    case POT = 'pot';
    case POV = 'pov';
    case PROLOG = 'prolog';
    case PROPERTIES = 'properties';
    case PROTOBUF = 'protobuf';
    case PY3TB = 'py3tb';
    case PYTB = 'pytb';
    case PYTHON = 'python';
    case R = 'r';
    case RB = 'rb';
    case RCONSOLE = 'rconsole';
    case REBOL = 'rebol';
    case REDCODE = 'redcode';
    case RHTML = 'rhtml';
    case RST = 'rst';
    case SASS = 'sass';
    case SCALA = 'scala';
    case SCAML = 'scaml';
    case SCHEME = 'scheme';
    case SCSS = 'scss';
    case SMALLTALK = 'smalltalk';
    case SMARTY = 'smarty';
    case SOURCESLIST = 'sourceslist';
    case SPLUS = 'splus';
    case SQL = 'sql';
    case SQLITE3 = 'sqlite3';
    case SQUIDCONF = 'squidconf';
    case SSP = 'ssp';
    case TCL = 'tcl';
    case TCSH = 'tcsh';
    case TEX = 'tex';
    case TEXT = 'text';
    case TYPESCRIPT = 'typescript';
    case V = 'v';
    case VALA = 'vala';
    case VBNET = 'vbnet';
    case VELOCITY = 'velocity';
    case VIM = 'vim';
    case XML = 'xml';
    case XQUERY = 'xquery';
    case XSLT = 'xslt';
    case YAML = 'yaml';
    case DOCKERFILE = 'dockerfile';
    case KAITAI = 'kaitai';
    case KOTLIN = 'kotlin';
    case MDX = 'mdx';
    case RICHTEXT = 'richtext';
    case RTF = 'rtf';
    case RUST = 'rust';
    case SAGEMATH = 'sagemath';
    case STYLUS = 'stylus';
    case VBS = 'vbs';
    case VCALENDAR = 'vcalendar';
    case VCARD = 'vcard';

    /**
     * @var array<string, self>
     */
    private const TYPE_BY_EXTENSION = [
        // CUCUMBER
        'feature' => self::CUCUMBER,
        // ABAP
        'abap' => self::ABAP,
        // ADA
        'adb' => self::ADA,
        'ads' => self::ADA,
        'ada' => self::ADA,
        // AHK
        'ahk' => self::AHK,
        'ahkl' => self::AHK,
        // APACHECONF — apache.conf/apache2.conf handled by full-filename loop
        'htaccess' => self::APACHECONF,   // pathinfo('.htaccess') = 'htaccess'
        // APPLESCRIPT
        'applescript' => self::APPLESCRIPT,
        // AS — conflict with AS3, AS wins
        'as' => self::AS,
        // ASY
        'asy' => self::ASY,
        // BASH
        'sh' => self::BASH,
        'ksh' => self::BASH,
        'bash' => self::BASH,
        'ebuild' => self::BASH,
        'eclass' => self::BASH,
        // BAT
        'bat' => self::BAT,
        'cmd' => self::BAT,
        // BEFUNGE
        'befunge' => self::BEFUNGE,
        // BLITZMAX
        'bmx' => self::BLITZMAX,
        // BOO
        'boo' => self::BOO,
        // BRAINFUCK
        'bf' => self::BRAINFUCK,
        'b' => self::BRAINFUCK,
        // C — h mapped here, more commonly a C header than C++
        'c' => self::C,
        'h' => self::C,
        // CFM
        'cfm' => self::CFM,
        'cfml' => self::CFM,
        'cfc' => self::CFM,
        // CHEETAH
        'tmpl' => self::CHEETAH,
        'spt' => self::CHEETAH,
        // CL
        'cl' => self::CL,
        'lisp' => self::CL,
        'el' => self::CL,
        // CLOJURE
        'clj' => self::CLOJURE,
        'cljs' => self::CLOJURE,
        // CMAKE — CMakeLists.txt handled by full-filename loop
        'cmake' => self::CMAKE,
        // COFFEESCRIPT
        'coffee' => self::COFFEESCRIPT,
        // CONSOLE
        'sh-session' => self::CONSOLE,
        // CONTROL
        'control' => self::CONTROL,
        // CPP
        'cpp' => self::CPP,
        'hpp' => self::CPP,
        'cc' => self::CPP,
        'hh' => self::CPP,
        'cxx' => self::CPP,
        'hxx' => self::CPP,
        'pde' => self::CPP,
        // CSHARP
        'cs' => self::CSHARP,
        // CSS
        'css' => self::CSS,
        // CYTHON
        'pyx' => self::CYTHON,
        'pxd' => self::CYTHON,
        'pxi' => self::CYTHON,
        // D
        'd' => self::D,
        'di' => self::D,
        // DELPHI
        'pas' => self::DELPHI,
        // DIFF
        'diff' => self::DIFF,
        'patch' => self::DIFF,
        // DPATCH
        'dpatch' => self::DPATCH,
        'darcspatch' => self::DPATCH,
        // DUEL
        'duel' => self::DUEL,
        'jbst' => self::DUEL,
        // DYLAN
        'dylan' => self::DYLAN,
        'dyl' => self::DYLAN,
        // ERB
        'erb' => self::ERB,
        // ERL — erl-sh is a pseudo-extension, not a real file type
        // ERLANG
        'erl' => self::ERLANG,
        'hrl' => self::ERLANG,
        // EVOQUE
        'evoque' => self::EVOQUE,
        // FACTOR
        'factor' => self::FACTOR,
        // FELIX
        'flx' => self::FELIX,
        'flxh' => self::FELIX,
        // FORTRAN
        'f' => self::FORTRAN,
        'f90' => self::FORTRAN,
        // GAS — conflict with SPLUS (.s), GAS wins
        's' => self::GAS,
        // GENSHI
        'kid' => self::GENSHI,
        // GITIGNORE — pathinfo('.gitignore') = 'gitignore'
        'gitignore' => self::GITIGNORE,
        // GLSL — geo also used by DynaGeo (binary), GLSL wins for text
        'vert' => self::GLSL,
        'frag' => self::GLSL,
        'geo' => self::GLSL,
        // GNUPLOT
        'plot' => self::GNUPLOT,
        'plt' => self::GNUPLOT,
        // GO
        'go' => self::GO,
        // GROFF
        'man' => self::GROFF,
        // HAML
        'haml' => self::HAML,
        // HASKELL
        'hs' => self::HASKELL,
        // HTML
        'html' => self::HTML,
        'htm' => self::HTML,
        'xhtml' => self::HTML,
        // HX (Haxe)
        'hx' => self::HX,
        // HYBRIS
        'hy' => self::HYBRIS,
        'hyb' => self::HYBRIS,
        // INI
        'ini' => self::INI,
        'cfg' => self::INI,
        'env' => self::INI,
        // IO
        'io' => self::IO,
        // IOKE
        'ik' => self::IOKE,
        // IRC
        'weechatlog' => self::IRC,
        // JADE
        'jade' => self::JADE,
        // JAVA
        'java' => self::JAVA,
        // JSON
        'json' => self::JSON,
        'jsonl' => self::JSON,
        // JS
        'js' => self::JS,
        'jsx' => self::JS,
        // LESS
        'less' => self::LESS,
        // JSP
        'jsp' => self::JSP,
        // LHS
        'lhs' => self::LHS,
        // LLVM
        'll' => self::LLVM,
        // LOGTALK
        'lgt' => self::LOGTALK,
        // LUA
        'lua' => self::LUA,
        'wlua' => self::LUA,
        // MAKE — makefile/gnumakefile handled by full-filename loop
        'mak' => self::MAKE,
        // MAKO
        'mao' => self::MAKO,
        // MAQL
        'maql' => self::MAQL,
        // MARKDOWN
        'md' => self::MARKDOWN,
        'markdown' => self::MARKDOWN,
        // MASON — autohandler/dhandler are full filenames, handled by loop
        'mhtml' => self::MASON,
        'mc' => self::MASON,
        'mi' => self::MASON,
        // MODELICA — mo also used for compiled gettext (binary), text wins
        'mo' => self::MODELICA,
        // MODULA2 — mod also used for audio/x-mod (binary), text wins
        'def' => self::MODULA2,
        'mod' => self::MODULA2,
        // MOOCODE
        'moo' => self::MOOCODE,
        // MUPAD
        'mu' => self::MUPAD,
        // MXML
        'mxml' => self::MXML,
        // MYGHTY — autodelegate is a full filename, handled by loop
        'myt' => self::MYGHTY,
        // NASM
        'asm' => self::NASM,
        // NEWSPEAK
        'ns2' => self::NEWSPEAK,
        // OBJDUMP
        'objdump' => self::OBJDUMP,
        // OBJECTIVEC — conflict with Matlab/Octave (.m), OBJECTIVEC wins (it's in the enum)
        'm' => self::OBJECTIVEC,
        // OBJECTIVEJ
        'j' => self::OBJECTIVEJ,
        // OCAML
        'ml' => self::OCAML,
        'mli' => self::OCAML,
        'mll' => self::OCAML,
        'mly' => self::OCAML,
        // OOC
        'ooc' => self::OOC,
        // PERL — conflict with PROLOG (.pl), PERL wins
        'pl' => self::PERL,
        'pm' => self::PERL,
        // PHP
        'php' => self::PHP,
        // POSTSCRIPT
        'ps' => self::POSTSCRIPT,
        'eps' => self::POSTSCRIPT,
        // POT
        'pot' => self::POT,
        'po' => self::POT,
        // POV
        'pov' => self::POV,
        'inc' => self::POV,
        // PROLOG — .pl taken by PERL
        'prolog' => self::PROLOG,
        'pro' => self::PROLOG,
        // PROPERTIES
        'properties' => self::PROPERTIES,
        // PROTOBUF
        'proto' => self::PROTOBUF,
        // PY3TB
        'py3tb' => self::PY3TB,
        // PYTB
        'pytb' => self::PYTB,
        // PYTHON — sc conflict with Scala, Python wins per original mapping
        'py' => self::PYTHON,
        'pyw' => self::PYTHON,
        'sc' => self::PYTHON,
        'tac' => self::PYTHON,
        // R — conflict with REBOL (.r), R wins
        'r' => self::R,
        // RB — rakefile is a full filename, handled by loop
        'rb' => self::RB,
        'rbw' => self::RB,
        'rake' => self::RB,
        'gemspec' => self::RB,
        'rbx' => self::RB,
        'duby' => self::RB,
        // RCONSOLE
        'rout' => self::RCONSOLE,
        // REBOL — .r taken by R
        'r3' => self::REBOL,
        // REDCODE
        'cw' => self::REDCODE,
        // RHTML
        'rhtml' => self::RHTML,
        // RST
        'rst' => self::RST,
        'rest' => self::RST,
        // SASS
        'sass' => self::SASS,
        // SCALA
        'scala' => self::SCALA,
        // SCAML
        'scaml' => self::SCAML,
        // SCHEME
        'scm' => self::SCHEME,
        // SCSS
        'scss' => self::SCSS,
        // SMALLTALK
        'st' => self::SMALLTALK,
        // SMARTY
        'tpl' => self::SMARTY,
        // SOURCESLIST — sources.list is a full filename, handled by loop
        // SPLUS — .s taken by GAS, .r taken by R; no unambiguous extension remains
        // SQL
        'sql' => self::SQL,
        // SQLITE3
        'sqlite3-console' => self::SQLITE3,
        // SQUIDCONF — squid.conf is a full filename, handled by loop
        // SSP
        'ssp' => self::SSP,
        // TCL
        'tcl' => self::TCL,
        // TCSH
        'tcsh' => self::TCSH,
        'csh' => self::TCSH,
        // TEX
        'tex' => self::TEX,
        'aux' => self::TEX,
        'toc' => self::TEX,
        // TEXT
        'txt' => self::TEXT,
        'log' => self::TEXT,
        'csv' => self::TEXT,
        'tsv' => self::TEXT,
        // TYPESCRIPT — ts also used by Qt Linguist and MPEG-2 (video); TypeScript wins for text context
        'ts' => self::TYPESCRIPT,
        'tsx' => self::TYPESCRIPT,
        // V (Verilog)
        'v' => self::V,
        'sv' => self::V,
        // VALA
        'vala' => self::VALA,
        'vapi' => self::VALA,
        // VBNET
        'vb' => self::VBNET,
        'bas' => self::VBNET,
        // VELOCITY
        'vm' => self::VELOCITY,
        'fhtml' => self::VELOCITY,
        // VIM — pathinfo('.vimrc') = 'vimrc'
        'vim' => self::VIM,
        'vimrc' => self::VIM,
        // XML
        'xml' => self::XML,
        'rss' => self::XML,
        'xsd' => self::XML,
        'wsdl' => self::XML,
        // XQUERY
        'xqy' => self::XQUERY,
        'xquery' => self::XQUERY,
        // XSLT — xsl goes to XSLT as the more specific type
        'xsl' => self::XSLT,
        'xslt' => self::XSLT,
        // YAML
        'yaml' => self::YAML,
        'yml' => self::YAML,
        // INI — additional config extension (conf → INI; ini already mapped above)
        'conf' => self::INI,
        // TEXT — additional plain-text extensions
        'text' => self::TEXT,
        // MDX
        'mdx' => self::MDX,
        // MARKDOWN — additional extensions
        'mkd' => self::MARKDOWN,
        // RICHTEXT
        'rtx' => self::RICHTEXT,
        // RTF
        'rtf' => self::RTF,
        // RUST
        'rs' => self::RUST,
        // STYLUS
        'stylus' => self::STYLUS,
        'styl' => self::STYLUS,
        // VBS (VBScript)
        'vbs' => self::VBS,
        'vbe' => self::VBS,
        // VCARD
        'vcard' => self::VCARD,
        'vcf' => self::VCARD,
        'vct' => self::VCARD,
        'gcrd' => self::VCARD,
        // VCALENDAR
        'vcs' => self::VCALENDAR,
        'ics' => self::VCALENDAR,
        'ifb' => self::VCALENDAR,
        'icalendar' => self::VCALENDAR,
        // SAGEMATH
        'sage' => self::SAGEMATH,
        // KAITAI STRUCT
        'ksy' => self::KAITAI,
        // KOTLIN
        'kt' => self::KOTLIN,
        // SCHEME — additional extension
        'ss' => self::SCHEME,
        // MAKE — additional extension
        'mk' => self::MAKE,
    ];

    /**
     * @var array<string, self>
     */
    private const TYPE_BY_MIME = [
        // CUCUMBER
        'text/x-gherkin' => self::CUCUMBER,
        // ADA
        'text/x-adasrc' => self::ADA,
        // BASH
        'application/x-sh' => self::BASH,
        'application/x-shellscript' => self::BASH,
        'text/x-sh' => self::BASH,
        // BAT — application/x-msdownload skipped, too broad (also used for .exe)
        'application/bat' => self::BAT,
        'application/x-bat' => self::BAT,
        // C — text/x-c is shared with C++ files; C wins as the base language
        'text/x-c' => self::C,
        'text/x-csrc' => self::C,
        'text/x-chdr' => self::C,
        // CL — OpenCL shares the .cl extension with Common Lisp
        'text/x-opencl-csrc' => self::CL,
        'text/x-opencl-src' => self::CL,
        'text/x-common-lisp' => self::CL,
        'text/x-emacs-lisp' => self::CL,
        // CMAKE
        'text/x-cmake' => self::CMAKE,
        // COFFEESCRIPT
        'application/vnd.coffeescript' => self::COFFEESCRIPT,
        'text/coffeescript' => self::COFFEESCRIPT,
        // CPP
        'text/x-c++src' => self::CPP,
        'text/x-c++hdr' => self::CPP,
        'text/x-processing' => self::CPP,
        // CSHARP
        'text/x-csharp' => self::CSHARP,
        // CSS
        'text/css' => self::CSS,
        // CYTHON
        'text/x-cython' => self::CYTHON,
        // D
        'text/x-dsrc' => self::D,
        // DELPHI
        'text/x-pascal' => self::DELPHI,
        // DIFF
        'text/x-diff' => self::DIFF,
        'text/x-patch' => self::DIFF,
        // ERLANG
        'text/x-erlang' => self::ERLANG,
        // FELIX
        'text/vnd.fmi.flexstor' => self::FELIX,
        // FORTRAN
        'text/x-fortran' => self::FORTRAN,
        // GAS — text/x-asm shared with NASM; GAS wins
        'text/x-asm' => self::GAS,
        // GO
        'text/x-go' => self::GO,
        // GROFF
        'application/x-troff-man' => self::GROFF,
        'text/troff' => self::GROFF,
        // HASKELL
        'text/x-haskell' => self::HASKELL,
        // HTML
        'text/html' => self::HTML,
        'application/xhtml+xml' => self::HTML,
        // JADE
        'text/jade' => self::JADE,
        // JAVA
        'text/x-java' => self::JAVA,
        'text/x-java-source' => self::JAVA,
        // JSON
        'application/json' => self::JSON,
        'application/schema+json' => self::JSON,
        // JS
        'text/javascript' => self::JS,
        'application/javascript' => self::JS,
        'application/x-javascript' => self::JS,
        'text/jscript' => self::JS,
        'text/jsx' => self::JS,
        // LHS
        'text/x-literate-haskell' => self::LHS,
        // LUA
        'text/x-lua' => self::LUA,
        // MAKE
        'text/x-makefile' => self::MAKE,
        // MARKDOWN — application/x-genesis-rom skipped (binary Sega ROM)
        'text/markdown' => self::MARKDOWN,
        'text/x-markdown' => self::MARKDOWN,
        // MODELICA — application/x-gettext-translation skipped (binary compiled gettext)
        'text/x-modelica' => self::MODELICA,
        // MXML
        'application/xv+xml' => self::MXML,
        // OBJECTIVEC — text/x-matlab and text/x-octave skipped (no case for those languages)
        'text/x-objcsrc' => self::OBJECTIVEC,
        // OCAML
        'text/x-ocaml' => self::OCAML,
        // OOC
        'text/x-ooc' => self::OOC,
        // PERL — application/x-pagemaker skipped (binary PageMaker files)
        'application/x-perl' => self::PERL,
        'text/x-perl' => self::PERL,
        // PHP
        'text/x-php' => self::PHP,
        'application/x-php' => self::PHP,
        'application/x-httpd-php' => self::PHP,
        // POSTSCRIPT — image/x-eps skipped (image variant)
        'application/postscript' => self::POSTSCRIPT,
        // POT — application/mspowerpoint and variants skipped (binary PowerPoint)
        'text/x-gettext-translation-template' => self::POT,
        'text/x-pot' => self::POT,
        'application/x-gettext' => self::POT,
        'text/x-gettext-translation' => self::POT,
        'text/x-po' => self::POT,
        // PYTHON
        'text/x-python' => self::PYTHON,
        'text/x-python2' => self::PYTHON,
        'text/x-python3' => self::PYTHON,
        // RB
        'application/x-ruby' => self::RB,
        'text/x-ruby' => self::RB,
        // RST
        'text/x-rst' => self::RST,
        // SASS
        'text/x-sass' => self::SASS,
        // SCALA — text/x-scala also matches sc extension, but sc→PYTHON in TYPE_BY_EXTENSION
        'text/x-scala' => self::SCALA,
        // SCHEME — application/vnd.lotus-screencam skipped (binary)
        'text/x-scheme' => self::SCHEME,
        // SCSS
        'text/x-scss' => self::SCSS,
        // SQL
        'application/sql' => self::SQL,
        'application/x-sql' => self::SQL,
        'text/x-sql' => self::SQL,
        // TCL
        'application/x-tcl' => self::TCL,
        'text/tcl' => self::TCL,
        'text/x-tcl' => self::TCL,
        // TCSH
        'application/x-csh' => self::TCSH,
        // TEX
        'application/x-tex' => self::TEX,
        'text/x-tex' => self::TEX,
        // TEXT
        'text/plain' => self::TEXT,
        'text/calendar' => self::TEXT,
        'text/directory' => self::TEXT,
        // TYPESCRIPT — video/mp2t skipped (binary MPEG-2 stream), Qt Linguist types skipped
        'application/typescript' => self::TYPESCRIPT,
        // V (Verilog)
        'text/x-verilog' => self::V,
        'text/x-svsrc' => self::V,
        // VALA
        'text/x-vala' => self::VALA,
        // VBNET — application/x-virtual-boy-rom skipped (binary ROM)
        'text/x-vb' => self::VBNET,
        'text/x-basic' => self::VBNET,
        // XML
        'application/xml' => self::XML,
        'text/xml' => self::XML,
        'application/rss+xml' => self::XML,
        'text/rss' => self::XML,
        'application/wsdl+xml' => self::XML,
        // XSLT
        'application/xslt+xml' => self::XSLT,
        // YAML
        'application/yaml' => self::YAML,
        'application/x-yaml' => self::YAML,
        'text/x-yaml' => self::YAML,
        'text/yaml' => self::YAML,
        // MDX
        'text/mdx' => self::MDX,
        // RICHTEXT
        'text/richtext' => self::RICHTEXT,
        // RTF
        'text/rtf' => self::RTF,
        // RUST
        'text/rust' => self::RUST,
        // STYLUS
        'text/stylus' => self::STYLUS,
        // VBS / VBScript
        'text/vbs' => self::VBS,
        'text/vbscript' => self::VBS,
        'text/vbscript.encode' => self::VBS,
        // VCARD
        'text/vcard' => self::VCARD,
        'text/x-vcard' => self::VCARD,
        // DOCKERFILE
        'text/x-dockerfile' => self::DOCKERFILE,
        // VCALENDAR
        'text/x-vcalendar' => self::VCALENDAR,
        // SAGEMATH
        'text/x-sagemath' => self::SAGEMATH,
        // KAITAI STRUCT
        'text/x-kaitai-struct' => self::KAITAI,
        // KOTLIN
        'text/x-kotlin' => self::KOTLIN,
        // LESS
        'text/less' => self::LESS,
    ];

    /**
     * Tries to determine the PlainTextLanguageType from the given filename's extension.
     * Returns null if no matching type is found.
     *
     * @param string $filename
     * @return self|null
     */
    public static function tryFromFilename(string $filename): self|null
    {
        foreach (self::TYPE_BY_EXTENSION as $ext => $type) {
            if (str_ends_with($filename, '.' . $ext)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Tries to determine the PlainTextLanguageType from the given MIME type.
     * Returns null if no matching type is found.
     *
     * @param string $mimeType
     * @return self|null
     */
    public static function tryFromMimetype(string $mimeType): self|null
    {
        $base = trim(strtok($mimeType, ';'));
        return self::TYPE_BY_MIME[$base] ?? null;
    }

    /**
     * Returns all MIME types associated with the extensions of all PlainTextLanguageTypes.
     *
     * @return string[]
     */
    public static function getMimeTypes(): array
    {
        return array_keys(self::TYPE_BY_MIME);
    }
}
