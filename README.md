# benoot-website

Broncode van **www.benoot.be** — de statische 3D-printwebsite van Dieter Benoot.

## Publiceren

De NAS haalt deze repository automatisch binnen (Taakplanner) en kopieert de bestanden naar de web-map die Web Station serveert. Een push naar `main` is binnen enkele minuten live.

## Structuur

- `index.html` — de volledige site (één zelfstandig bestand, geen build-stap)
- `nas/deploy.sh` — pull- en sync-script dat op de NAS draait

## Afbeeldingen

Foto's staan in `web_images/` in de web-map op de NAS (buiten deze repo) en worden als `/web_images/...` in de pagina gelinkt.