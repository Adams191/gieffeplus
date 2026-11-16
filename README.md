# Reseller Map – plugin WordPress

> Store locator per WordPress: anagrafica dei rivenditori, ricerca per indirizzo e mappa Google con i punti vendita più vicini, calcolati lato server.

![WordPress](https://img.shields.io/badge/WordPress-6.4+-21759B?logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?logo=php&logoColor=white)
![Google Maps](https://img.shields.io/badge/Google_Maps-API-4285F4?logo=googlemaps&logoColor=white)
![REST API](https://img.shields.io/badge/WP-REST_API-555)

## Il progetto

Il sito di un'azienda del settore sportivo aveva bisogno di una pagina "trova il rivenditore più vicino" per centinaia di negozi in tutta Italia.

La versione 2.0 è una **riscrittura completa** del plugin precedente, che era basato su variabili globali. La nuova versione ha un'architettura a classi, i dati gestiti da WordPress, un'API REST dedicata e strumenti di importazione per lo staff.

## Funzionalità

- **Custom post type `rivenditore`** con meta box per indirizzo, coordinate e link Google Maps, gestibile dal pannello di WordPress
- **Shortcode `[rivenditori]`**: campo di ricerca con suggerimenti di Google Places, geolocalizzazione dell'indirizzo, mappa con marker e lista dei negozi ordinata per distanza
- **Endpoint REST** `GET /wp-json/gieffeplus/v1/reseller?lat=…&lng=…` che restituisce i rivenditori più vicini
- **Import CSV** dal back-office, con template scaricabile e intestazioni flessibili (`titolo`/`nome`/`ragione sociale`, `lat`/`latitudine`, …)
- **Comando WP-CLI** `wp gieffeplus import-resellers [--dry-run]` per gli import massivi
- **Elemento WPBakery Page Builder** per inserire la mappa senza scrivere shortcode
- Pagina di impostazioni per la chiave API di Google Maps, con avviso in bacheca se la chiave manca

## Scelte tecniche

| Ambito | Implementazione |
|---|---|
| **Architettura** | Bootstrap singleton e una classe per ogni responsabilità (post type, meta, REST, shortcode, asset, impostazioni, import), senza variabili globali |
| **Calcolo delle distanze** | Formula dell'haversine in PHP lato server: il client riceve solo i risultati più vicini, non l'intero dataset |
| **Prestazioni** | Elenco dei rivenditori in cache con i transient, invalidata a ogni salvataggio o eliminazione; script di Google Maps caricato in modalità asincrona e solo nelle pagine che contengono lo shortcode |
| **Sicurezza** | Nonce e controllo delle capability su meta box, impostazioni e import; parametri REST con `sanitize_callback` e `validate_callback` (range di latitudine e longitudine); output con `esc_html`/`esc_attr`/`esc_url` |
| **Dati** | Meta registrati con `register_meta` e sanificazione dedicata delle coordinate; chiave API salvata nelle opzioni, mai nel codice |
| **Front-end** | jQuery (già caricato da WordPress, dal tema e da WPBakery), escaping dell'HTML generato dinamicamente, distanze formattate in m/km |
| **Compatibilità** | Controllo della versione PHP con avviso in bacheca invece della pagina bianca; i18n tramite text domain; pulizia completa in `uninstall.php` |

## Codice originale vs. componenti di terze parti

**Scritto da me:** tutto il contenuto del repository, cioè `index.php`, `includes/`, `js/script.js`, `css/style.css`, `uninstall.php` e il formato di import in `data/`.

**Componenti esterni** (non inclusi nel repository):

| Componente | Uso | Come viene incluso |
|---|---|---|
| [WordPress](https://wordpress.org/) | Piattaforma (post type, REST API, Settings API, WP-CLI) | Ambiente di esecuzione |
| [Google Maps JavaScript API](https://developers.google.com/maps) (+ Places, Geocoding) | Mappa, autocompletamento degli indirizzi, geocoding | Caricata a runtime da Google con la chiave del cliente |
| jQuery | Manipolazione del DOM | Versione inclusa in WordPress |
| [WPBakery Page Builder](https://wpbakery.com/) | Integrazione opzionale (`vc_map`) | Plugin commerciale, se presente |

## Shortcode

```text
[rivenditori title="Trova il rivenditore più vicino a te" results_count="6" map_height="420" zoom="10"]
```

| Attributo | Default | Descrizione |
|---|---|---|
| `title` / `subtitle` | testi predefiniti | Intestazioni del box di ricerca |
| `placeholder` | `CAP, città o indirizzo` | Testo del campo di ricerca |
| `button_text` | `Cerca` | Etichetta del pulsante |
| `results_title` | `Gli store nelle tue vicinanze` | Titolo dei risultati |
| `results_count` | `6` | Numero di risultati (1–20) |
| `map_height` | `420` | Altezza della mappa in px (min 200) |
| `zoom` | `10` | Zoom iniziale |
| `center_lat` / `center_lng` | `41.8719` / `12.5674` | Centro iniziale della mappa (Italia) |

## Struttura

```
├── index.php                              bootstrap del plugin (singleton)
├── includes/
│   ├── class-gieffeplus-post-type.php     CPT "rivenditore"
│   ├── class-gieffeplus-custom-fields.php meta box e register_meta
│   ├── class-gieffeplus-rest-api.php      endpoint, haversine, cache
│   ├── class-gieffeplus-shortcode.php     [rivenditori]
│   ├── class-gieffeplus-assets.php        caricamento condizionale degli script
│   ├── class-gieffeplus-settings.php      chiave API Google Maps
│   ├── class-gieffeplus-csv-import.php    import CSV dal back-office
│   ├── class-gieffeplus-cli-import.php    comando WP-CLI
│   └── class-gieffeplus-vc-integration.php elemento WPBakery
├── js/script.js · css/style.css
├── data/resellers-import.php              sorgente dati per WP-CLI (vuota nel repository)
└── uninstall.php
```

## Installazione

1. Copia la cartella in `wp-content/plugins/` e attiva **Reseller Map** (WordPress ≥ 6.4, PHP ≥ 7.4).
2. In *Rivenditori → Impostazioni* inserisci una chiave Google Maps con Maps JavaScript, Places e Geocoding abilitate.
3. Importa i rivenditori da CSV o con WP-CLI, oppure inseriscili a mano.
4. Inserisci lo shortcode `[rivenditori]` in una pagina.

## Licenza e autore

GPL v2 o successiva · **Andrea D'Amico** – Full Stack Web Developer
