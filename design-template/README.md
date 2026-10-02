# Care Lab Design-Template (WordPress)

Das komplette Design-System der teppichlabor.de-Seite, als eigenständiges,
wiederverwendbares Template gesichert. Stand: Export direkt von der Live-Seite,
siehe Datum des letzten Commits dieses Ordners.

## Was hier drinsteckt

- `site.css` — das komplette Stylesheet (Design-Tokens, Layout, Komponenten:
  Hero-Slider, Showcase-Kacheln, Wizard-Formulare, Nav-Dropdown, Chat-Widget, etc.)
- `header.html` / `footer.html` — die beiden WordPress-Template-Parts (Nav, Footer-Spalten,
  eingebettetes `site.css`, globale Scroll-Reveal-/Hero-Dots-/Vanta-JS)
- `pages/*.html` — der Content-Block jeder einzelnen Seite, 1:1 wie aktuell live
- `media-manifest.json` — Referenzliste aller Bild-/Video-URLs, die das Design aktuell nutzt
  (gehört zu teppichlabor.de, bei Wiederverwendung auf einer anderen Seite durch eigene
  Assets ersetzen)

## Funktionsprinzip

Kein klassisches WP-Theme (kein `style.css`-Theme-Header, keine PHP-Templates). Stattdessen:
twentytwentyfive als Basis-Theme, der komplette visuelle Auftritt lebt in einem einzigen
`wp:html`-Block pro Template-Part/Seite, gefüllt über die WordPress-REST-API
(`/wp-json/wp/v2/template-parts/...` und `/wp-json/wp/v2/pages/...`).

Vorteil: kein Theme-Deployment nötig, alles geht per REST-Call. Nachteil: kein "echtes"
installierbares `.zip`-Theme, das sich über Appearance → Themes aktivieren lässt.

## Wiederverwendung auf einem neuen WordPress-Projekt

1. twentytwentyfive (oder ein anderes leeres Block-Theme) aktivieren.
2. `header.html` und `footer.html` per `POST /wp-json/wp/v2/template-parts/{theme}//header`
   bzw. `.../footer` einspielen, mit `__CSS__` durch den Inhalt von `site.css` ersetzt
   (siehe `scripts/push_parts.py`).
3. Eigene Platzhalter (`__LOGO_WORDMARK__`, `__HERO_*__`, Bottle-/Tile-Bilder etc. — alle
   `__GROSSBUCHSTABEN__`-Tokens in den HTML-Dateien) durch eigene Media-URLs ersetzen.
4. Seiteninhalte aus `pages/*.html` per `POST /wp-json/wp/v2/pages` bzw. `/pages/{id}`
   übertragen, Platzhalter ebenso ersetzen (siehe `scripts/push_page.py`).
5. Navigation/Footer-Links, Telefonnummer, Adresse, rechtliche Angaben auf das neue
   Unternehmen anpassen, nicht einfach Care-Lab-Texte übernehmen.

## Design-Sprache (falls als Referenz für ein anderes Projekt genutzt)

- Typografie: Inter, schwarz auf weiß, hohe Kontraste, keine Farben außer Schwarz/Weiß/Grau
  (bewusst kein Markenfarbakzent, Zurückhaltung statt Buntheit)
- Editorial, reduziert, viel Weißraum, dünne Trennlinien statt Karten mit Schatten
- Hero: Multi-Slide-Karussell, Scrim-Overlay für garantierten Textkontrast unabhängig
  vom Hintergrundbild (siehe `.hero-slide::after` in site.css)
- Honest-Boxes: hellgraue Kästen für ehrliche Einschränkungen/offene Punkte, durchgängiges
  Stilmittel statt Marketing-Übertreibung
- Showcase-Kacheln mit Video-Loops, Scroll-Reveal-Animationen via IntersectionObserver
- Mehrstufige Wizard-Formulare (Progress-Bar, Step-Validation) für Anfragen

## Bekannte technische Grenzen (wichtig, bevor man das Muster woanders wiederholt)

- Formular-Plugins (Contact Form 7, MailPoet) lassen sich NICHT per REST-API anlegen/editieren,
  nur lesen. Workaround: Standard-Formular nutzen + WordPress-Admin-E-Mail-Einstellung ändern,
  oder einmalig manuell im wp-admin einrichten.
- Kein Zugriff auf Theme-Dateisystem/FTP über REST, nur Plugin-Install per Slug aus dem
  WordPress.org-Verzeichnis und Core-Settings.
