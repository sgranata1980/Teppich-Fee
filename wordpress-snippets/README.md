# Aktive Code Snippets auf teppichlabor.de

Alle hier als Backup abgelegten Snippets laufen live über das Plugin
**Code Snippets** (wp-admin → Snippets → All Snippets). Reihenfolge
entspricht der Snippet-ID auf der Live-Seite.

| ID | Name in WordPress | Datei hier | Zweck |
|----|---|---|---|
| 5 | Care Lab Chat Assistent | `../chat-worker/wordpress-snippet.php` | REST-Endpoint `/wp-json/care-lab/v1/chat` für das Chat-Widget, siehe `chat-worker/README.md` |
| 6 | Care Lab Zweit-Domain (care-lab.app) | `domain-care-lab-app.php` | Verhindert den WordPress-Redirect von care-lab.app auf teppichlabor.de |
| 7 | Care Lab Login-Design (Apple-Look) | `login-design.php` | Stylt wp-login.php: weiß, blaue Buttons, eigenes Logo statt WordPress-Logo |

## Warum als Backup im Repo

Code Snippets speichert den Code in der WordPress-Datenbank, nicht im
Git-Repo. Diese Kopien sind reine Referenz/Backup, falls mal ein
Snippet in wp-admin aus Versehen verändert oder gelöscht wird — dann
einfach den Inhalt der passenden Datei hier zurück ins Snippet kopieren
(wp-admin → Snippets → betroffenes Snippet → Edit → Code ersetzen →
Update).

## Ändert sich live etwas an einem Snippet

Bitte kurz Bescheid geben oder selbst hier im Repo nachziehen, damit
Backup und Live-Stand nicht auseinanderlaufen.
