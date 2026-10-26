#!/bin/bash
# Deploy-script voor op de NAS (Taakplanner, draaien als root)
# Haalt de laatste versie binnen en synchroniseert naar de web-map.
set -e

REPO=/volume3/git/benoot-website
DEST=/volume3/web

if [ ! -d "$REPO" ]; then
  git clone https://github.com/dlife/benoot-website.git "$REPO"
else
  git -C "$REPO" pull --ff-only
fi

# Alles behalve repo-specifieke bestanden naar de web-map kopiëren;
# web_images/ (foto's op de NAS) blijft onaangeroerd.
rsync -a \
  --exclude '.git' \
  --exclude 'nas' \
  --exclude 'README.md' \
  "$REPO/" "$DEST/"

exit 0