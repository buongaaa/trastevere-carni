// LISTINO DI TRASTEVERE CARNI
// Unico posto dove cambiare prezzi, pacchi, consegna e codici sconto: lo leggono sia il sito sia il pagamento (api/).
// Regole: prezzi in euro con il punto per i decimali (16.9 = 16,90 €), nomi tra virgolette, nessuna virgola dopo l'ultima voce.
window.PREZZI = {
  "kg": {
    "Guancia": 16, "Collo": 12, "Spalla": 16.9, "Punta di petto": 13, "Costata": 29.9, "Lombata e Filetto": 31.9,
    "Pancia e Bavetta": 19, "Scamone": 26, "Coscia": 14.9, "Coda": 12, "Stinco e Ossobuco": 16,
    "Salsicce di maiale": 9, "Costolette di maiale": 7.9, "Bistecca di manzo": 24, "Abbacchio a scottadito": 26,
    "Spiedini di pollo": 14, "Tagliata di scamone": 14.9, "Hamburger della casa": 16, "Salsicce": 9, "Costine": 7.9,
    "Capocollo a fette": 14, "Coratella / spiedini": 15,
    "Tacos di carne": 16.9, "Polpettone allo speck": 14.9, "Rotolo farcito": 12.9, "Bistecche di lombo": 8.9,
    "Polpette": 14.9, "Cosce di pollo panate": 10.9
  },
  "pacchi": {
    "Pacco Famiglia da €40": 40, "Pacco Famiglia da €65": 65, "Pacco Famiglia da €95": 95,
    "Pacco Scorta da €80": 80, "Pacco Scorta da €130": 130,
    "Pacco Brace da €60": 60, "Pacco Brace da €70": 70
  },
  "consegna": {
    "costo": 5,
    "gratisSopra": null,
    "minimo": 25,
    "zona": "dentro il Grande Raccordo Anulare",
    "cap": ["00135", "00136", "00137", "00138", "00139", "00141", "00142", "00143", "00144", "00145", "00146", "00147", "00148", "00149", "00151", "00152", "00153", "00154", "00155", "00156", "00157", "00158", "00159", "00161", "00162", "00163", "00164", "00165", "00166", "00167", "00168", "00169", "00171", "00172", "00173", "00174", "00175", "00176", "00177", "00178", "00179", "00181", "00182", "00183", "00184", "00185", "00186", "00187", "00189", "00191", "00192", "00193", "00195", "00196", "00197", "00198", "00199"]
  },
  "coupon": { "BENVENUTO10": 0.10 },
  "marginePeso": 0.10
};
