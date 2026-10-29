<?php
// CONFIGURAZIONE PAGAMENTI — da compilare su Hostinger (Gestione file → public_html/api/config.php)
//
// 1. Chiave segreta Stripe: Dashboard Stripe → Sviluppatori → Chiavi API → "Chiave segreta".
//    Usa prima quella di TEST (inizia con sk_test_), poi quella reale (sk_live_) quando tutto funziona.
// 2. Segreto webhook: Dashboard Stripe → Sviluppatori → Webhook → Aggiungi endpoint
//    URL: https://IL-TUO-DOMINIO/api/webhook.php — eventi: checkout.session.completed, checkout.session.expired
//    Poi copia il "Segreto di firma" (inizia con whsec_).
// Non condividere mai questo file e non incollare le chiavi in chat o email.

const STRIPE_SECRET_KEY     = 'sk_test_INSERISCI_QUI';
const STRIPE_WEBHOOK_SECRET = 'whsec_INSERISCI_QUI';

const SITE_URL   = 'https://trasteverecarni.it';    // senza barra finale
const SHOP_NAME  = 'Trastevere Carni';
const SHOP_EMAIL = 'trasteverecarni@gmail.com';     // qui arrivano gli ordini
const FROM_EMAIL = 'ordini@trasteverecarni.it';     // casella creata su Hostinger (Email) con il dominio
