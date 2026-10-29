# Trastevere Carni — sito web

Sito della macelleria Trastevere Carni (Via Guglielmo Mengarini 67/69, Roma).

## Struttura
- `index.html` — il sito
- `prezzi.js` — listino unico: prezzi al kg, pacchi, consegna, codici sconto
- `img/` — logo, foto del negozio, carne della rastrelliera
- `api/` — pagamento online con Stripe ed email degli ordini (PHP, serve Hostinger)

## Pubblicazione su Hostinger
1. Carica tutto in `public_html` (Gestione file).
2. Copia `api/config.example.php` in `api/config.php` e inserisci le chiavi Stripe **direttamente sul server**.
3. Su Stripe aggiungi il webhook `https://<dominio>/api/webhook.php` con gli eventi `checkout.session.completed` e `checkout.session.expired`.

`api/config.php` non è nel repository di proposito: contiene le chiavi segrete.

## Anteprima
Su GitHub Pages il sito funziona in modalità anteprima: i pagamenti online sono disattivati.
