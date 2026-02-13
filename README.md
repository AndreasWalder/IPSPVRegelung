# PVRegelung (IP-Symcon Modul)

Dieses Modul ist ein 1:1-Port des IP-Symcon Logik-Skripts „PV REGELUNG — Überschuss + Vorrang (Wärmepumpe vor Akku)“ (Stand Script v1.8).

## Installation (lokal per ZIP)
1. ZIP entpacken nach: IP-Symcon/modules/PVRegelung/
2. In IP-Symcon: „Module Control“ → „Neu laden“ / „Rescan“
3. Instanz anlegen: Objektbaum → „Instanz hinzufügen“ → „PV Regelung“
4. In der Instanz-Konfiguration die Variablen-IDs setzen (Grid/PV/Wallbox/WP etc.)
5. Timer läuft automatisch im eingestellten Loop.

## Hinweise
- UI-Struktur (Kategorien/Variablen) wird unter der Instanz automatisch angelegt.
- State wird pro Instanz im Buffer gespeichert.
