@php use App\Services\Translation\LocaleService; @endphp

@switch(app(LocaleService::class)->getCurrentLocale()->lang)
    @case('de_DE')
    <ul>
    <li><strong>Datenschutz & Sicherheit:</strong> End-to-End-Verschlüsselung und lokale Speicherung auf Hochschulservern gewährleisten höchsten Datenschutz, um die Sicherheit sensibler Daten im akademischen Kontext zu garantieren.<br><br></li>
    
    <li><strong>Geräteübergreifende Nutzung & Zusammenarbeit:</strong> Verschlüsselte Chats und Gruppenräume ermöglichen interaktive Zusammenarbeit und die nahtlose Nutzung auf verschiedenen Geräten.<br><br></li>

    <li><strong>Überarbeitete Benutzeroberfläche & neue Quality-of-Life-Features:</strong> Verbesserungen im UI-Design bieten eine intuitive Bedienung, schnelleren Zugriff auf Funktionen und zahlreiche kleine Komfort-Updates, die den Workflow effizienter und angenehmer machen.</li>
    </ul>
    @break
    
    @case('en_US')
        <h1 class=\"headerLine\">HAWKI 2.0</h1>
        <h3>We have further improved HAWKI for you!</h3>
        <p>
            <b>Functionality:</b>
        </p>
        <p>Multi-Language with translated texts for English, Italian, French, and Spanish.</p>
        <p>Display of mathematical formulas, LaTex, and syntax highlighting improvement.</p>
        <p>
            <b>Quality of Life:</b>
        </p>
        <p>Dark Mode for our night owls.</p>
        <p>System prompts are now transparently visible.</p>
        <p>
            <b>Security Updates:</b>
        </p>
        <p>We have made HAWKI more secure in some areas and updated the code structure.
            </p>
        <p>We thank Thorger Jansen (Discovery, Analysis, Coordination) from the 
            <a class=\"accentText\" target=\"_blank\" href=\"https://www.sec-consult.com\"><b>SEC Consult Vulnerability Lab</b></a> 
            for responsibly reporting the identified issues and collaborating with us to address them.
        </p>
    @break

@endswitch
