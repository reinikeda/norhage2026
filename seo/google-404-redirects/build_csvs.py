#!/usr/bin/env python3
"""Build per-domain 404 → live-product CSVs for Google-indexed Norhage URLs.

Format: source URL,target URL
Only rows that actually 404 (including WooCommerce error404 HTML after a 301).
"""
from __future__ import annotations

import csv
import json
import re
import ssl
import urllib.error
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
)
CTX = ssl.create_default_context()
OUT = Path(__file__).resolve().parent
WAYBACK = Path("/tmp/nh-seo/wayback-product.json")

# Hand-checked Google SERP URLs (polycarbonate + greenhouse keywords, 7 shops).
GOOGLE_URLS = [
    # --- norhage.no ---
    "https://norhage.no/product/polykarbonatplater-10mm-arla-2wall-standard-storrelse/",
    "https://norhage.no/product/polykarbonatplater-10mm-polygal-bronse-standardmal/",
    "https://norhage.no/product/polykarbonatplater-16mm-arla-3w-klar-standardmal/",
    "https://norhage.no/product/polykarbonatplater-16mm-polygal-bronse-standardmal/",
    "https://norhage.no/product/16mm-standard-storrelse-arla-polykarbonatplater/",
    "https://norhage.no/product/10mm-hvit-kanalplast-plater/",
    "https://norhage.no/product/10mm-bronse-kanalplast-plater/",
    "https://norhage.no/product/4mm-standard-storrelse-arla-polykarbonatplater/",
    "https://norhage.no/product/6mm-standard-storrelse-arla-polykarbonatplater/",
    "https://norhage.no/product/kanalplast-40mm-polygal-klar-14w-uv1-bredde-123-m/",
    "https://norhage.no/product/veggdrivhus-vegg-300/",
    "https://norhage.no/product/drivhus-tre-500/",
    "https://norhage.no/product/forlengelse-til-drivhus-makan/",
    "https://norhage.no/product/drivhus-premium-tunnel-m/",
    "https://norhage.no/product/svart-drivhus-premium-house-lux-bredde-2m-4m/",
    "https://norhage.no/product/takvindu-til-drivhus-premium-house/",
    "https://norhage.no/product/plantebindingssett-til-premium-drivhus/",
    "https://norhage.no/product/fundament-til-drivhus-makan/",
    "https://norhage.no/product/sidevinduet-til-drivhus-makan/",
    "https://norhage.no/product/takvindu-til-drivhus-makan-70-x-90-cm/",
    "https://norhage.no/product/gratis-takvindu-for-drivhus-makan/",
    # --- norhage.se ---
    "https://norhage.se/product/8mm-arla-klar-kanalplast-skivor/",
    "https://norhage.se/product/kanalplast-6mm-arla-klar-2w-uv1-bredd-105-21-m/",
    "https://norhage.se/product/20mm-standardstorlek-polygal-polykarbonatskivor/",
    "https://norhage.se/product/kanalplast-20mm-arla-klar-7w-uv1-bredd-105-21-m/",
    "https://norhage.se/product/20mm-polygal-klar-kanalplast-skivor/",
    "https://norhage.se/product/kanalplast-16mm-arla-klar-6w-uv1-bredd-105-21-m/",
    "https://norhage.se/product/polykarbonat-10mm-polygal-brons-standardmatt/",
    "https://norhage.se/product/polykarbonat-16mm-polygal-brons-standardmatt/",
    "https://norhage.se/product/10mm-arla-multiclear-strong-6w-2uv-polykarbonatskivor/",
    "https://norhage.se/product/vaxthus-premium-tunnel-l/",
    "https://norhage.se/product/vaxthus-premium-tunnel-m/",
    "https://norhage.se/product/vaxthus-tunnelart/",
    "https://norhage.se/product/vaxthus-slim-200/",
    "https://norhage.se/product/vaggvaxthus-vagg-300/",
    "https://norhage.se/product/takfonster-for-vaxthus-premium-house-lux/",
    "https://norhage.se/product/takfonster-for-vaxthus-premium-tunnel/",
    "https://norhage.se/product/vaxthus-tra-700/",
    "https://norhage.se/product/fonster-for-vaxthus-tra/",
    # --- norhage.de ---
    "https://norhage.de/product/16mm-arla-klar-polycarbonat-doppelstegplatten/",
    "https://norhage.de/product/10mm-polypiu-klar-polycarbonat-doppelstegplatten/",
    "https://norhage.de/product/doppelstegplatten-10mm-arla-opal-2w-uv1-breite-105-21-m/",
    "https://norhage.de/product/25mm-standardgroesse-polypiu-hohlkammerplatten/",
    "https://norhage.de/product/40mm-standardgroesse-polypiu-hohlkammerplatten/",
    "https://norhage.de/product/gewaechshaus-premium-tunnel-s/",
    "https://norhage.de/product/rahmen-fuer-gewaechshaus-holz-700/",
    "https://norhage.de/product/schwarzes-gewaechshaus-premium-house-lux-breite-2m-4m/",
    "https://norhage.de/product/sturmsicheres-gewaechshaus-makan-16-160m%C2%B2/",
    "https://norhage.de/product/sturmsicheres-gewaechshaus-makan-800-64-160-m%C2%B2/",
    "https://norhage.de/product/wasser-und-strommodul-fuer-gewaechshaus-premium/",
    "https://norhage.de/product/verlaengerung-fuer-gewaechshaus-makan/",
    "https://norhage.de/product/fundament-fuer-gewaechshaus-makan/",
    "https://norhage.de/product/doppeltuer-fuer-gewaechshaus-makan/",
    # --- norhage.fi (Google currently ranks /tuote/; also probe old /product/) ---
    "https://norhage.fi/product/kennolevy-leikkaus-arla-5x-uv1-kirkas-16-mm/",
    "https://norhage.fi/product/kennolevy-arla-2w-uv1-kirkas-10-mm/",
    "https://norhage.fi/product/kennolevy-leikkaus-arla-2w-uv1-kirkas-10-mm/",
    "https://norhage.fi/product/10mm-polypiu-kirkas-polykarbonaattilevy/",
    "https://norhage.fi/product/10mm-arla-pronssinen-polykarbonaattilevy/",
    "https://norhage.fi/product/10mm-polygal-valkoinen-polykarbonaattilevy/",
    "https://norhage.fi/product/10mm-arla-6w-2uv-polykarbonaattilevyt-21m-leveys/",
    "https://norhage.fi/product/16mm-arla-kirkas-polykarbonaattilevy/",
    "https://norhage.fi/product/16mm-arla-5x-polykarbonaattilevyt/",
    "https://norhage.fi/product/16mm-arla-pronssinen-polykarbonaattilevy/",
    "https://norhage.fi/product/4mm-arla-kirkas-polykarbonaattilevy/",
    "https://norhage.fi/product/6mm-arla-kirkas-polykarbonaattilevy/",
    "https://norhage.fi/product/6mm-arla-pronssinen-polykarbonaattilevy/",
    "https://norhage.fi/product/8mm-arla-kirkas-polykarbonaattilevy/",
    # --- norhage.eu ---
    "https://norhage.eu/product/multiwall-polycarbonate-16-mm-clear-arla-uv-protected-2500-g-m%C2%B2-cut-to-size/",
    "https://norhage.eu/product/16mm-arla-klar-polycarbonat-doppelstegplatten/",
    "https://norhage.eu/product/polykarbonatplater-10mm-arla-2wall-standard-storrelse/",
]

# Confirmed live replacements (Google title + sitemap). Never map PC → monolith/trapez/tape/gutter.
MANUAL_MAP: dict[str, str] = {
    # NO
    "https://norhage.no/product/polykarbonatplater-10mm-arla-2wall-standard-storrelse/":
        "https://norhage.no/produkt/kanalplast-10mm-klar-arla-2w-uv1-plater/",
    "https://norhage.no/product/polykarbonatplater-10mm-polygal-bronse-standardmal/":
        "https://norhage.no/produkt/kanalplast-10mm-bronse-polygal-2w-uv1-plater/",
    "https://norhage.no/product/polykarbonatplater-16mm-arla-3w-klar-standardmal/":
        "https://norhage.no/produkt/kanalplast-16mm-klar-arla-5x-uv1-plater/",
    "https://norhage.no/product/polykarbonatplater-16mm-polygal-bronse-standardmal/":
        "https://norhage.no/produkt/kanalplast-16mm-bronse-polygal-x-uv1-plater/",
    "https://norhage.no/product/16mm-standard-storrelse-arla-polykarbonatplater/":
        "https://norhage.no/produkt/kanalplast-16mm-bronse-arla-6w-uv1-plater/",
    "https://norhage.no/product/10mm-bronse-kanalplast-plater/":
        "https://norhage.no/produkt/kanalplast-10mm-bronse-arla-2w-uv1-plater/",
    "https://norhage.no/product/4mm-standard-storrelse-arla-polykarbonatplater/":
        "https://norhage.no/produkt/kanalplast-4mm-klar-arla-2w-uv1-plater/",
    "https://norhage.no/product/6mm-standard-storrelse-arla-polykarbonatplater/":
        "https://norhage.no/produkt/kanalplast-6mm-klar-arla-2w-uv1-plater/",
    "https://norhage.no/product/kanalplast-40mm-polygal-klar-14w-uv1-bredde-123-m/":
        "https://norhage.no/produkt/kanalplast-40mm-klar-polygal-14w-uv1-plater-bredde-123m/",
    "https://norhage.no/product/veggdrivhus-vegg-300/":
        "https://norhage.no/produkt/drivhus-vegg-300/",
    "https://norhage.no/product/drivhus-tre-500/":
        "https://norhage.no/produkt/drivhus-tre-500/",
    "https://norhage.no/product/forlengelse-til-drivhus-makan/":
        "https://norhage.no/produkt/forlengelse-for-drivhus-makan/",
    "https://norhage.no/product/drivhus-premium-tunnel-m/":
        "https://norhage.no/produkt/drivhus-premium-tunnel-m/",
    "https://norhage.no/product/svart-drivhus-premium-house-lux-bredde-2m-4m/":
        "https://norhage.no/produkt/drivhus-premium-house-lux/",
    "https://norhage.no/product/takvindu-til-drivhus-premium-house/":
        "https://norhage.no/produkt/drivhustakvindu-premium-house/",
    "https://norhage.no/product/plantebindingssett-til-premium-drivhus/":
        "https://norhage.no/produkt/plantebindingssett-til-drivhus-premium-house/",
    "https://norhage.no/product/fundament-til-drivhus-makan/":
        "https://norhage.no/produkt/fundament-til-drivhus-makan/",
    "https://norhage.no/product/sidevinduet-til-drivhus-makan/":
        "https://norhage.no/produkt/sidevindu-til-drivhus-makan/",
    "https://norhage.no/product/takvindu-til-drivhus-makan-70-x-90-cm/":
        "https://norhage.no/produkt/takvindu-til-drivhus-makan/",
    "https://norhage.no/product/gratis-takvindu-for-drivhus-makan/":
        "https://norhage.no/produkt/takvindu-til-drivhus-makan/",
    # SE — 10 mm 6W klar standard was discontinued; 2W klar 10 mm is the live hero SKU.
    "https://norhage.se/product/10mm-arla-multiclear-strong-6w-2uv-polykarbonatskivor/":
        "https://norhage.se/produkt/kanalplast-arla-2w-uv1-klar-10-mm/",
    "https://norhage.se/product/8mm-arla-klar-kanalplast-skivor/":
        "https://norhage.se/produkt/kanalplast-arla-2w-uv1-klar-8-mm/",
    "https://norhage.se/product/kanalplast-6mm-arla-klar-2w-uv1-bredd-105-21-m/":
        "https://norhage.se/produkt/kanalplast-arla-2w-uv1-klar-6-mm/",
    "https://norhage.se/product/20mm-standardstorlek-polygal-polykarbonatskivor/":
        "https://norhage.se/produkt/kanalplast-arla-7w-uv1-klar-20-mm/",
    "https://norhage.se/product/kanalplast-20mm-arla-klar-7w-uv1-bredd-105-21-m/":
        "https://norhage.se/produkt/kanalplast-arla-7w-uv1-klar-20-mm/",
    "https://norhage.se/product/20mm-polygal-klar-kanalplast-skivor/":
        "https://norhage.se/produkt/kanalplast-20mm-klar-arla-uv-skydd-2800-g-m%c2%b2-efter-matt/",
    "https://norhage.se/product/kanalplast-16mm-arla-klar-6w-uv1-bredd-105-21-m/":
        "https://norhage.se/produkt/kanalplast-arla-5x-uv1-klar-16-mm/",
    "https://norhage.se/product/polykarbonat-10mm-polygal-brons-standardmatt/":
        "https://norhage.se/produkt/kanalplast-polygal-2w-uv1-brons-10-mm/",
    "https://norhage.se/product/polykarbonat-16mm-polygal-brons-standardmatt/":
        "https://norhage.se/produkt/kanalplast-polygal-x-uv1-brons-16-mm/",
    "https://norhage.se/product/vaxthus-premium-tunnel-l/":
        "https://norhage.se/produkt/vaxthus-premium-tunnel-l-50-210m%c2%b2/",
    "https://norhage.se/product/vaxthus-premium-tunnel-m/":
        "https://norhage.se/produkt/vaxthus-premium-tunnel-m-12-64m%c2%b2/",
    "https://norhage.se/product/vaxthus-tunnelart/":
        "https://norhage.se/produkt-kategori/vaxthus/industriella-vaxthus/",
    "https://norhage.se/product/vaxthus-slim-200/":
        "https://norhage.se/produkt/vaxthus-slim-200-4-16m%c2%b2/",
    "https://norhage.se/product/vaggvaxthus-vagg-300/":
        "https://norhage.se/produkt/vaxthus-vagg-300-6-18m%c2%b2/",
    "https://norhage.se/product/takfonster-for-vaxthus-premium-house-lux/":
        "https://norhage.se/produkt/takfonster-for-vaxthus-premium-house-lux/",
    "https://norhage.se/product/takfonster-for-vaxthus-premium-tunnel/":
        "https://norhage.se/produkt/takfonster-for-vaxthus-premium-tunnel/",
    "https://norhage.se/product/vaxthus-tra-700/":
        "https://norhage.se/produkt/vaxthus-tra-700-70-280m%c2%b2/",
    "https://norhage.se/product/fonster-for-vaxthus-tra/":
        "https://norhage.se/produkt/fonster-for-vaxthus-tra/",
    # DE
    "https://norhage.de/product/16mm-arla-klar-polycarbonat-doppelstegplatten/":
        "https://norhage.de/produkt/stegplatten-16-mm-klar-arla-uv-schutz-2500-g-m%c2%b2-nach-mass/",
    "https://norhage.de/product/10mm-polypiu-klar-polycarbonat-doppelstegplatten/":
        "https://norhage.de/produkt/stegplatten-10-mm-klar-arla-uv-schutz-1700-g-m%c2%b2-nach-mass/",
    "https://norhage.de/product/doppelstegplatten-10mm-arla-opal-2w-uv1-breite-105-21-m/":
        "https://norhage.de/produkt/stegplatten-10-mm-opal-arla-uv-schutz-1700-g-m%c2%b2-nach-mass/",
    "https://norhage.de/product/25mm-standardgroesse-polypiu-hohlkammerplatten/":
        "https://norhage.de/produkt/doppelstegplatte-arla-11w-uv1-opal-25-mm/",
    "https://norhage.de/product/40mm-standardgroesse-polypiu-hohlkammerplatten/":
        "https://norhage.de/produkt/doppelstegplatte-polygal-14w-uv1-klar-40-mm-breite-123-m/",
    "https://norhage.de/product/gewaechshaus-premium-tunnel-s/":
        "https://norhage.de/produkt/gewaechshaus-premium-tunnel-s-6-30m%c2%b2/",
    "https://norhage.de/product/rahmen-fuer-gewaechshaus-holz-700/":
        "https://norhage.de/produkt/gewaechshausrahmen-holz-700-70-280m%c2%b2/",
    "https://norhage.de/product/schwarzes-gewaechshaus-premium-house-lux-breite-2m-4m/":
        "https://norhage.de/produkt/gewaechshaus-premium-house-lux-54-332m%c2%b2/",
    "https://norhage.de/product/sturmsicheres-gewaechshaus-makan-16-160m%c2%b2/":
        "https://norhage.de/produkt/sturmsicheres-gewaechshaus-makan-klassisk-16-120m%c2%b2/",
    "https://norhage.de/product/sturmsicheres-gewaechshaus-makan-800-64-160-m%c2%b2/":
        "https://norhage.de/produkt/sturmsicheres-gewaechshaus-makan-800-64-160-m%c2%b2/",
    "https://norhage.de/product/wasser-und-strommodul-fuer-gewaechshaus-premium/":
        "https://norhage.de/produkt/strom-und-wassermodul-fuer-gewaechshaus-premium/",
    "https://norhage.de/product/verlaengerung-fuer-gewaechshaus-makan/":
        "https://norhage.de/produkt/verlaengerung-fuer-gewaechshaus-makan/",
    "https://norhage.de/product/fundament-fuer-gewaechshaus-makan/":
        "https://norhage.de/produkt/fundament-fuer-gewaechshaus-makan/",
    "https://norhage.de/product/doppeltuer-fuer-gewaechshaus-makan/":
        "https://norhage.de/produkt/doppeltuer-zum-gewaechshaus-makan-klassisk/",
    # FI standard (non-remnant) Google/Wayback product pages
    "https://norhage.fi/product/10mm-polypiu-kirkas-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-kirkas-10-mm/",
    "https://norhage.fi/product/10mm-arla-pronssinen-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-pronssi-10-mm/",
    "https://norhage.fi/product/10mm-polygal-valkoinen-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-leikkaus-arla-2w-uv1-opaali-10-mm/",
    "https://norhage.fi/product/10mm-arla-6w-2uv-polykarbonaattilevyt-21m-leveys/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-kirkas-10-mm/",
    "https://norhage.fi/product/16mm-arla-kirkas-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-5x-uv1-kirkas-16-mm/",
    "https://norhage.fi/product/16mm-arla-5x-polykarbonaattilevyt/":
        "https://norhage.fi/tuote/kennolevy-arla-5x-uv1-kirkas-16-mm/",
    "https://norhage.fi/product/16mm-arla-pronssinen-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-6w-uv1-pronssi-16-mm/",
    "https://norhage.fi/product/16mm-arla-2w-hg-1uv-polykarbonaattilevyt-098m-leveys/":
        "https://norhage.fi/tuote/kennolevy-arla-5x-uv1-kirkas-16-mm/",
    "https://norhage.fi/product/16mm-vakiokokoiset-arla-polykarbonaattilevyt/":
        "https://norhage.fi/tuote/kennolevy-arla-5x-uv1-kirkas-16-mm/",
    "https://norhage.fi/product/4mm-arla-kirkas-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-kirkas-4-mm/",
    "https://norhage.fi/product/6mm-arla-kirkas-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-kirkas-6-mm/",
    "https://norhage.fi/product/6mm-arla-pronssinen-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-pronssi-6-mm/",
    "https://norhage.fi/product/8mm-arla-kirkas-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-kirkas-8-mm/",
    "https://norhage.fi/product/8mm-arla-pronssinen-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-pronssi-8-mm/",
    "https://norhage.fi/product/20mm-polygal-kirkas-polykarbonaattilevy/":
        "https://norhage.fi/tuote/kennolevy-arla-7w-uv1-kirkas-20-mm/",
    "https://norhage.fi/product/kennolevy-leikkaus-arla-5x-uv1-kirkas-16-mm/":
        "https://norhage.fi/tuote/kennolevy-leikkaus-arla-5x-uv1-kirkas-16-mm/",
    "https://norhage.fi/product/kennolevy-arla-2w-uv1-kirkas-10-mm/":
        "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-kirkas-10-mm/",
    "https://norhage.fi/product/kennolevy-leikkaus-arla-2w-uv1-kirkas-10-mm/":
        "https://norhage.fi/tuote/kennolevy-leikkaus-arla-2w-uv1-kirkas-10-mm/",
    # EU — English permalink already /product/; only old foreign slugs 404
    "https://norhage.eu/product/16mm-arla-klar-polycarbonat-doppelstegplatten/":
        "https://norhage.eu/product/multiwall-polycarbonate-16-mm-clear-arla-uv-protected-2500-g-m%c2%b2-cut-to-size/",
    "https://norhage.eu/product/polykarbonatplater-10mm-arla-2wall-standard-storrelse/":
        "https://norhage.eu/product/multiwall-polycarbonate-sheet-10-mm-clear-arla-uv-protected-1700-g-m%c2%b2/",
}

# Same historical /product/ slugs cloned across shops (DK/LT barely indexed).
CROSS_SLUG_TARGETS: dict[str, dict[str, str]] = {
    "dk": {
        "polykarbonatplater-10mm-arla-2wall-standard-storrelse":
            "https://norhage.dk/produkt/kanalplast-10mm-klar-arla-2w-uv1-plater/",
        "polykarbonatplater-10mm-polygal-bronse-standardmal":
            "https://norhage.dk/produkt/kanalplast-10mm-bronse-polygal-2w-uv1-plater/",
        "polykarbonatplater-16mm-arla-3w-klar-standardmal":
            "https://norhage.dk/produkt/kanalplast-16mm-klar-arla-5x-uv1-plater/",
        "polykarbonatplater-16mm-polygal-bronse-standardmal":
            "https://norhage.dk/produkt/kanalplast-16mm-bronse-polygal-x-uv1-plater/",
        "16mm-standard-storrelse-arla-polykarbonatplater":
            "https://norhage.dk/produkt/kanalplast-16mm-bronse-arla-6w-uv1-plater/",
        "10mm-bronse-kanalplast-plater":
            "https://norhage.dk/produkt/kanalplast-10mm-bronse-arla-2w-uv1-plater/",
        "4mm-standard-storrelse-arla-polykarbonatplater":
            "https://norhage.dk/produkt/kanalplast-4mm-klar-arla-2w-uv1-plater/",
        "6mm-standard-storrelse-arla-polykarbonatplater":
            "https://norhage.dk/produkt/kanalplast-6mm-klar-arla-2w-uv1-plater/",
        "kanalplast-40mm-polygal-klar-14w-uv1-bredde-123-m":
            "https://norhage.dk/produkt/kanalplast-40mm-klar-polygal-14w-uv1-plater-bredde-123m/",
        "veggdrivhus-vegg-300":
            "https://norhage.dk/produkt/drivhus-vegg-300/",
        "drivhus-tre-500":
            "https://norhage.dk/produkt/drivhus-tre-500/",
        "forlengelse-til-drivhus-makan":
            "https://norhage.dk/produkt/forlengelse-for-drivhus-makan/",
        "drivhus-premium-tunnel-m":
            "https://norhage.dk/produkt/drivhus-premium-tunnel-m/",
        "svart-drivhus-premium-house-lux-bredde-2m-4m":
            "https://norhage.dk/produkt/drivhus-premium-house-lux/",
        "takvindu-til-drivhus-premium-house":
            "https://norhage.dk/produkt/drivhustakvindu-premium-house/",
        "plantebindingssett-til-premium-drivhus":
            "https://norhage.dk/produkt/plantebindingssett-til-drivhus-premium-house/",
        "16mm-arla-klar-polycarbonat-doppelstegplatten":
            "https://norhage.dk/produkt/kanalplast-16mm-klar-arla-uv-beskyttet-2500-g-m%c2%b2-pa-mal/",
        "10mm-polypiu-klar-polycarbonat-doppelstegplatten":
            "https://norhage.dk/produkt/kanalplast-10mm-klar-arla-uv-beskyttet-1700-g-m%c2%b2-pa-mal/",
    },
    "lt": {
        "polykarbonatplater-10mm-arla-2wall-standard-storrelse":
            "https://norhage.lt/produktas/skaidraus-kanalinio-polikarbonato-plokste-arla-10-mm/",
        "polykarbonatplater-16mm-arla-3w-klar-standardmal":
            "https://norhage.lt/produktas/skaidraus-kanalinio-polikarbonato-plokste-arla-16-mm/",
        "polykarbonatplater-10mm-polygal-bronse-standardmal":
            "https://norhage.lt/produktas/bronzinio-kanalinio-polikarbonato-plokste-2w-polygal-10-mm/",
        "polykarbonatplater-16mm-polygal-bronse-standardmal":
            "https://norhage.lt/produktas/bronzinio-kanalinio-polikarbonato-plokste-x-polygal-16-mm/",
        "16mm-standard-storrelse-arla-polykarbonatplater":
            "https://norhage.lt/produktas/bronzinio-kanalinio-polikarbonato-plokste-6w-arla-16-mm/",
        "10mm-bronse-kanalplast-plater":
            "https://norhage.lt/produktas/bronzinio-kanalinio-polikarbonato-plokste-2w-arla-10-mm/",
        "4mm-standard-storrelse-arla-polykarbonatplater":
            "https://norhage.lt/produktas/skaidraus-kanalinio-polikarbonato-plokste-arla-4-mm/",
        "6mm-standard-storrelse-arla-polykarbonatplater":
            "https://norhage.lt/produktas/skaidraus-kanalinio-polikarbonato-plokste-arla-6-mm/",
        "drivhus-tre-500":
            "https://norhage.lt/produktas/medinis-siltnamis-tree-500/",
        "veggdrivhus-vegg-300":
            "https://norhage.lt/produktu-kategorija/siltnamiai/",
        "16mm-arla-klar-polycarbonat-doppelstegplatten":
            "https://norhage.lt/produktas/skaidraus-kanalinio-polikarbonato-plokste-arla-16-mm/",
        "10mm-polypiu-klar-polycarbonat-doppelstegplatten":
            "https://norhage.lt/produktas/skaidraus-kanalinio-polikarbonato-plokste-arla-10-mm/",
        "kanalplast-40mm-polygal-klar-14w-uv1-bredde-123-m":
            "https://norhage.lt/produktas/skaidraus-kanalinio-polikarbonato-plokste-14w-polygal-40-mm/",
    },
}

PERMALINK = {
    "no": "/produkt/",
    "se": "/produkt/",
    "dk": "/produkt/",
    "fi": "/tuote/",
    "de": "/produkt/",
    "lt": "/produktas/",
    "eu": "/product/",
}

CAT = {
    "no": {
        "pc": "https://norhage.no/produkt-kategori/polykarbonat/kanalplast-polykarbonat/",
        "gh": "https://norhage.no/produkt-kategori/drivhus/",
    },
    "se": {
        "pc": "https://norhage.se/produkt-kategori/polykarbonat/kanalplast-polykarbonat/",
        "gh": "https://norhage.se/produkt-kategori/vaxthus/",
    },
    "dk": {
        "pc": "https://norhage.dk/produktkategori/polycarbonat/kanalplast-polycarbonat/",
        "gh": "https://norhage.dk/produktkategori/drivhuse/",
    },
    "fi": {
        "pc": "https://norhage.fi/tuotekategoria/polykarbonaatti/polykarbonaatti-kennolevyt/",
        "gh": "https://norhage.fi/tuotekategoria/kasvihuoneet/",
    },
    "de": {
        "pc": "https://norhage.de/produkt-kategorie/polycarbonat/polycarbonat-stegplatten/",
        "gh": "https://norhage.de/produkt-kategorie/gewaechshaeuser/",
    },
    "lt": {
        "pc": "https://norhage.lt/produktu-kategorija/polikarbonatas/kanalinis-polikarbonatas/",
        "gh": "https://norhage.lt/produktu-kategorija/siltnamiai/",
    },
    "eu": {
        "pc": "https://norhage.eu/product-category/polycarbonate/polycarbonate-multiwall-sheets/",
        "gh": "https://norhage.eu/product-category/greenhouses/",
    },
}

SKIP_RE = re.compile(
    r"akryyli|pmma|alumiini|profiili|klebeband|massivplatte|monoliitt|"
    r"monolit-|trapez|trapes",
    re.I,
)


def domain_of(url: str) -> str:
    host = urllib.parse.urlparse(url).netloc.replace("www.", "")
    return host.split(".")[-1]


def slug_of(url: str) -> str:
    path = urllib.parse.unquote(urllib.parse.urlparse(url).path).strip("/")
    parts = path.split("/")
    return parts[-1] if parts else ""


def key_url(url: str) -> str:
    p = urllib.parse.urlparse(url.strip())
    path = urllib.parse.unquote(p.path).rstrip("/") + "/"
    return f"{p.scheme}://{p.netloc}{path}"


def http_status(url: str) -> tuple[int, str]:
    req = urllib.request.Request(url, method="GET", headers={"User-Agent": UA, "Accept": "text/html"})
    try:
        with urllib.request.urlopen(req, timeout=25, context=CTX) as r:
            final = r.geturl()
            code = r.status
            body = r.read(12000).decode("utf-8", "replace")
            if "error404" in body or 'class="error404' in body:
                return 404, final
            return code, final
    except urllib.error.HTTPError as e:
        return e.code, url
    except Exception:
        return 0, url


def remnant_target(url: str) -> str | None:
    """Map leftover 10/16 mm sheet SKUs to the matching live standard/cut sheet."""
    d = domain_of(url)
    slug = slug_of(url).lower()
    if SKIP_RE.search(slug):
        return None
    is_remnant = any(x in slug for x in ("jaannokset", "tahteet", "reste", "jamapala"))
    if d == "fi":
        if not is_remnant:
            return None
        if not any(x in slug for x in ("10-mm", "10mm", "16-mm", "16mm")):
            return None
        th16 = "16-mm" in slug or "16mm" in slug
        polygal = "polygal" in slug
        opaali = "opaali" in slug or "opal" in slug
        pronssi = "pronssi" in slug or "bronze" in slug
        kirkas = "kirkas" in slug or "klar" in slug or "clear" in slug
        if th16:
            if opaali:
                return "https://norhage.fi/tuote/kennolevy-arla-5x-uv1-opaali-16-mm/"
            if pronssi:
                return "https://norhage.fi/tuote/kennolevy-arla-6w-uv1-pronssi-16-mm/"
            return "https://norhage.fi/tuote/kennolevy-arla-5x-uv1-kirkas-16-mm/"
        if opaali:
            return "https://norhage.fi/tuote/kennolevy-leikkaus-arla-2w-uv1-opaali-10-mm/"
        if polygal and pronssi:
            return "https://norhage.fi/tuote/kennolevy-polygal-2w-uv1-pronssi-10-mm/"
        if pronssi:
            return "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-pronssi-10-mm/"
        if kirkas or "polypiu" in slug:
            return "https://norhage.fi/tuote/kennolevy-arla-2w-uv1-kirkas-10-mm/"
        return CAT["fi"]["pc"]
    if d == "de":
        if not is_remnant:
            return None
        if "10-mm" not in slug and "10mm" not in slug:
            return None
        polygal = "polygal" in slug
        opal = "opal" in slug
        bronze = "bronze" in slug
        if polygal and bronze:
            return "https://norhage.de/produkt/doppelstegplatte-polygal-2w-uv1-bronze-10-mm/"
        if bronze:
            return "https://norhage.de/produkt/doppelstegplatte-arla-2w-uv1-bronze-10-mm/"
        if opal:
            return "https://norhage.de/produkt/stegplatten-10-mm-opal-arla-uv-schutz-1700-g-m%c2%b2-nach-mass/"
        return "https://norhage.de/produkt/doppelstegplatte-arla-2w-uv1-klar-10-mm/"
    return None


def extra_candidates() -> list[str]:
    extra: list[str] = []
    if WAYBACK.exists():
        data = json.loads(WAYBACK.read_text())
        for urls in data.values():
            for u in urls:
                lu = u.lower()
                if SKIP_RE.search(lu):
                    continue
                if remnant_target(u) or any(
                    k in lu
                    for k in (
                        "10mm-arla", "16mm-arla", "10mm-polygal", "16mm-polygal",
                        "10mm-polypiu", "kennolevy-arla", "kasvihuone",
                    )
                ):
                    extra.append(u)
    # Cross-shop historical English /product/ slugs on DK + LT
    for tld, mapping in CROSS_SLUG_TARGETS.items():
        for slug in mapping:
            extra.append(f"https://norhage.{tld}/product/{slug}/")
    return extra


def lookup_target(src: str) -> str | None:
    k = key_url(src)
    if k in MANUAL_MAP:
        return MANUAL_MAP[k]
    # percent-encoding variants
    for mk, mv in MANUAL_MAP.items():
        if key_url(urllib.parse.unquote(mk)) == key_url(urllib.parse.unquote(src)):
            return mv
    d = domain_of(src)
    slug = slug_of(src)
    if d in CROSS_SLUG_TARGETS and slug in CROSS_SLUG_TARGETS[d]:
        return CROSS_SLUG_TARGETS[d][slug]
    return remnant_target(src)


def norm(url: str) -> str:
    url = url.strip()
    if not url.endswith("/") and "?" not in url:
        url += "/"
    return url


def main() -> None:
    candidates = [norm(u) for u in GOOGLE_URLS + extra_candidates()]
    seen: set[str] = set()
    urls: list[str] = []
    for u in candidates:
        key = key_url(urllib.parse.unquote(u))
        if key not in seen and "norhage." in key:
            seen.add(key)
            urls.append(u)

    print(f"checking {len(urls)} URLs", flush=True)
    results: dict[str, list[tuple[str, str]]] = {d: [] for d in PERMALINK}

    with ThreadPoolExecutor(max_workers=12) as ex:
        futs = {ex.submit(http_status, u): u for u in urls}
        for fut in as_completed(futs):
            src = futs[fut]
            code, final = fut.result()
            d = domain_of(src)
            if code != 404:
                print(f"LIVE {code} {src} -> {final}", flush=True)
                continue
            target = lookup_target(src)
            if not target:
                print(f"SKIP no-target 404 {src}", flush=True)
                continue
            results[d].append((norm(src), norm(target)))
            print(f"404 {src} -> {target}", flush=True)

    for d, rows in results.items():
        uniq: dict[str, str] = {}
        for s, t in sorted(rows):
            uniq[key_url(s)] = (s, t)
        path = OUT / f"norhage.{d}.csv"
        with path.open("w", newline="", encoding="utf-8") as f:
            w = csv.writer(f)
            w.writerow(["source URL", "target URL"])
            for s, t in (uniq[k] for k in sorted(uniq)):
                w.writerow([s, t])
        print(f"wrote {path.name} ({len(uniq)} rows)")


if __name__ == "__main__":
    main()
