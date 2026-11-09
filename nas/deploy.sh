#!/bin/bash
# Deploy-script voor op de NAS (Taakplanner, draaien als root)
# Haalt de laatste versie binnen en synchroniseert naar de web-map.
set -e

REPO=/volume3/git/benoot-website
DEST=/volume3/web

# Zorg dat bestanden leesbaar worden voor de webserver-gebruiker (http).
# Git checkout als root met umask 077 geeft anders rechten 600 en PHP
# krijgt dan "Access denied" (gebeurd 9 nov 2026).
umask 022

if [ ! -d "$REPO" ]; then
  git clone https://github.com/dlife/benoot-website.git "$REPO"
else
  git -C "$REPO" pull --ff-only
fi

# Alles behalve repo-specifieke bestanden naar de web-map kopiëren.
# Belangrijk: captions.json en prints.json NIET overschrijven —
# dat zijn databestanden die op de NAS onderhouden worden.
# Forceer leesrechten (u+rwX, go+rX) zodat PHP ze kan lezen.
rsync -a   --chmod=Du+rwX,go+rX,Fu+rw,go+r   --exclude '.git'   --exclude 'nas'   --exclude 'README.md'   --exclude 'captions.json'   --exclude 'prints.json'   "$REPO/" "$DEST/"

exit 0
