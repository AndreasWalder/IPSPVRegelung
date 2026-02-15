# PVRegelung (IP-Symcon Modul)

Dieses Modul regelt PV-Überschuss mit Prioritäten für Wärmepumpe, Heizstab und Wallbox. Die Logik läuft timerbasiert und speichert ihren Zustand pro Instanz.

## Installation (lokal per ZIP)
1. ZIP entpacken nach: `IP-Symcon/modules/PVRegelung/`
2. In IP-Symcon: **Module Control** → **Neu laden/Rescan**
3. Instanz anlegen: Objektbaum → **Instanz hinzufügen** → **PV Regelung**
4. In der Instanz-Konfiguration alle benötigten Variablen-IDs und Einheiten setzen
5. Loop-Timer startet gemäß Konfiguration automatisch

---

## Funktionsbeschreibung (Gesamtlogik)

Die Regelung arbeitet in jedem Loop-Zyklus in dieser Reihenfolge:

1. **Messwerte einlesen und normalisieren**
   - Netzleistung, PV-Leistung(en), Batterie-SOC/Batterieleistung, Temperatur, WP-/Wallbox-Rückmeldungen
   - kW/W Eingänge werden intern auf **Watt** vereinheitlicht
2. **Netzsignal filtern**
   - Das Netzsignal wird geglättet (`SurplusFilterAlpha`), um hektisches Schalten zu vermeiden
3. **Überschuss/Import berechnen**
   - Export (Überschuss) und Import werden aus dem gefilterten Netzsignal ermittelt
4. **Priorisierte Verbraucher planen**
   - Wärmepumpe (inkl. Mindestlaufzeit, Sperrzeit, Hysterese)
   - Heizstab stufig (1/2/3) mit Wochenlogik und Mindestzeiten
   - Wallbox mit Stromvorgabe, Rampenlogik, Soft-Off und 1P/3P Umschaltung
5. **Ausgänge schreiben**
   - Sollwerte/Schalter an Zielvariablen senden
   - UI-Variablen (Status, Werte, manuelle Sollwerte) aktualisieren
6. **State persistieren**
   - Interner Zustand (z. B. letzte Schaltzeit, aktive Stufe, Sperrzeiten) wird im Buffer gespeichert

Zusätzlich können Sie den Loop manuell starten oder den internen Zustand zurücksetzen.

---

## Komplette Parameter-Anleitung

> Hinweis: `...VarID` bedeutet immer die Objekt-ID einer bestehenden IP-Symcon Variable. `0` bedeutet i. d. R. „nicht verwendet“.

### 1) Allgemein

- **LoopSeconds** (int, s)
  - Zykluszeit der Regelung.
  - Praktisch: Kleinere Werte reagieren schneller, größere Werte sind ruhiger.
- **AutoRunOnStart** (bool)
  - Führt beim Start/Apply sofort einen Regelzyklus aus.

### 2) Messwerte / Eingänge

- **GridMeterID** (int)
  - Variable mit Netzleistung (Bezug/Einspeisung).
- **GridMeterUnit** (`"W"` oder `"kW"`)
  - Einheit des Netzleistungswertes.

- **PV1ID** (int), **PV1Unit** (`"W"`/`"kW"`)
  - Erste PV-Leistungsquelle.
- **PV2ID** (int), **PV2Unit** (`"W"`/`"kW"`)
  - Zweite PV-Leistungsquelle (z. B. separater WR).

- **BoilerTempVarID** (int)
  - Ist-Temperatur Boiler/Warmwasser.
- **HpTargetC** (float, °C)
  - Zieltemperatur für die Wärmepumpen-Logik.
- **RodMinStartTempC** (float, °C)
  - Mindesttemperatur, ab der Heizstab-Weekly/Startbedingungen greifen.

- **BatterySocVarID** (int)
  - Batterie-Ladezustand in % (Anzeige/Logik-Kontext).
- **BatteryChargePowerVarID** (int)
  - Batterieleistung (Laden/Entladen je nach Vorzeichen/Quelle).
- **BatteryChargePowerUnit** (`"W"`/`"kW"`)
  - Einheit der Batterieleistung.

### 3) Prioritäten / globale Regelstrategie

- **PriorityHeatpumpFirst** (bool)
  - `true`: Wärmepumpe hat Vorrang vor nachgelagerten Verbrauchern.
- **PriorityWeeklyRodBeforeWallbox** (bool)
  - `true`: Weekly-Heizstab wird vor Wallbox berücksichtigt.

### 4) Überschuss/Netz-Filter

- **SurplusExportDeadbandW** (int, W)
  - Totband um 0 W Export für WP/Heizstab, um Flattern zu reduzieren.
- **SurplusMaxGridImportW** (int, W)
  - Maximal erlaubter Netzbezug für die Regelung (Schutz gegen zu hohen Import).
- **SurplusFilterAlpha** (float, 0..1)
  - Glättungsfaktor für Netzleistung.
  - Klein = stärker geglättet, groß = schneller/empfindlicher.

### 5) Ausgabe „Netz (gefiltert, invertiert)"

- **OutputsGridFilteredInvertedVarID** (int)
  - Zielvariable für invertiertes, gefiltertes Netzsignal.
- **OutputsGridFilteredInvertedUnit** (`"W"`/`"kW"`)
  - Einheit der Ausgabe.

### 6) Wärmepumpe (WP)

- **HeatpumpEnabled** (bool)
  - Aktiviert/deaktiviert die WP-Überschusslogik.
- **HeatpumpForceRunVarID** (int)
  - Optionaler externer Force-Run Schalter.
- **HeatpumpRunningVarID** (int)
  - Rückmeldung, ob WP tatsächlich läuft.
- **HeatpumpPowerInVarID** (int), **HeatpumpPowerInUnit**
  - Gemessene WP-Leistungsaufnahme.
- **HeatpumpSurplusOutVarID** (int), **HeatpumpSurplusOutUnit**
  - Ausgangssignal für WP-Überschuss/Freigabe (je nach Anlagenlogik).
- **HeatpumpSurplusOutSigned** (bool)
  - Definiert, ob das Ausgangssignal vorzeichenbehaftet geschrieben wird.

- **HeatpumpTempHysteresisC** (float, °C)
  - Hysterese um Temperaturziel, verhindert Ein/Aus-Flattern.
- **HeatpumpMinSurplusW** (int, W)
  - Mindestüberschuss zum Start/Freigabe.
- **HeatpumpMinOnSeconds** (int, s)
  - Mindest-EIN-Zeit.
- **HeatpumpMinOffSeconds** (int, s)
  - Mindest-AUS-Zeit (Sperrzeit).
- **HeatpumpAssumedPowerW** (int, W)
  - Annahmewert für WP-Leistung, falls kein verlässlicher Istwert vorliegt.

### 7) Heizstab (stufig + weekly)

- **HeatingRodEnabled** (bool)
  - Aktiviert Heizstab-Logik.
- **HeatingRodSwitchVarID1 / 2 / 3** (int)
  - Bool-Ausgänge für Stufe 1/2/3.
- **HeatingRodPowerPerUnitW** (int, W)
  - Leistung je Stufe.
- **HeatingRodMinSurplusW** (int, W)
  - Startschwelle für Heizstab (Default typ. hoch, z. B. 9 kW).
- **HeatingRodMinOnSeconds** (int, s)
  - Mindest-EIN-Zeit.
- **HeatingRodMinOffSeconds** (int, s)
  - Mindest-AUS-Zeit.

- **HeatingRodWeeklyEnabled** (bool)
  - Aktiviert Wochenlogik (Hygiene-/Nachheizfenster).
- **HeatingRodDaysAfterTargetReached** (int, Tage)
  - Anzahl Tage seit letzter Zieltemperatur bis erneuter Weekly-Freigabe.
- **HeatingRodWeeklyStart** (`HH:MM`)
  - Startzeit Weekly-Fenster.
- **HeatingRodWeeklyEnd** (`HH:MM`)
  - Endzeit Weekly-Fenster.

### 8) Wallbox

- **WallboxEnabled** (bool)
  - Aktiviert Wallbox-Regelung.
- **WallboxEnableVarID** (int)
  - Schalt-/Freigabevariable Wallbox.
- **WallboxChargePowerVarID** (int), **WallboxChargePowerUnit**
  - Gemessene aktuelle Ladeleistung.
- **WallboxSetCurrentAVarID** (int)
  - Soll-Ladestrom in Ampere.

#### 8.1 Phasenumschaltung (1P/3P)
- **WallboxPhase1pVarID** (int)
  - Zielvariable für Phasenmodus.
- **WallboxPhaseTrueIs1p** (bool)
  - Mapping der Bool-Bedeutung (`true=1p` oder umgekehrt).
- **WallboxPhaseMinHoldSeconds** (int, s)
  - Mindesthaltezeit vor erneuter Phasenänderung.
- **WallboxPhaseSwitchTo3pMinW** (int, W)
  - Schwelle für Umschalten auf 3-phasig.
- **WallboxPhaseSwitchTo1pMaxW** (int, W)
  - Schwelle für Zurückschalten auf 1-phasig.

#### 8.2 Elektrische Basisparameter
- **WallboxPhases3p** (int)
  - Anzahl Phasen im 3P-Modus (normal 3).
- **WallboxPhases1p** (int)
  - Anzahl Phasen im 1P-Modus (normal 1).
- **WallboxVoltageV** (int, V)
  - Netzspannung für Leistungsabschätzung.

#### 8.3 Stromgrenzen / Dynamik
- **WallboxMinA** (int, A)
  - Mindestladestrom.
- **WallboxMaxA** (int, A)
  - Maximaler Ladestrom.
- **WallboxStepA** (int, A)
  - Schrittweite der Sollwertänderung.
- **WallboxMinOnSeconds** (int, s)
  - Mindest-EIN-Zeit.
- **WallboxMinOffSeconds** (int, s)
  - Mindest-AUS-Zeit.
- **WallboxReserveW** (int, W)
  - Reserveleistung, die nicht vollständig verplant wird.
- **WallboxAutoStartMinSurplusW** (int, W)
  - Mindest-Überschuss für den automatischen Start der Wallbox (Standard: 2000 W).
- **WallboxAutoStartMinDurationSeconds** (int, s)
  - Mindestdauer, die der Start-Überschuss anliegen muss, bevor Auto-Start freigegeben wird (Standard: 900 s).
- **WallboxRampUpA** (int, A/Loop)
  - Wie schnell der Strom nach oben gefahren wird.
- **WallboxRampDownA** (int, A/Loop)
  - Wie schnell der Strom reduziert wird.
- **WallboxSoftOffGraceSeconds** (int, s)
  - Verzögerung für Soft-Off bei kurzzeitigem Einbruch (z. B. Wolken).

#### 8.4 Manueller Lademodus
- **WallboxManualCarSocVarID** (int)
  - Fahrzeug-SOC-Istwert für automatisches Beenden des manuellen Ladens.
- **WallboxManualDefaultTargetSoc** (float, %)
  - Default-Ziel-SOC im manuellen Modus.
- **WallboxManualDefaultPowerW** (int; intern historisch, UI in kW)
  - Default-Ladeleistung für manuellen Modus (im UI als kW geführt).

### 9) UI

- **UIRootName** (string)
  - Name der automatisch angelegten Root-Kategorie unter der Instanz.

---

## Bedienfunktionen / Aktionen

- **Jetzt ausführen**
  - Startet sofort einen Regelzyklus (`PVREG_RunNow`).
- **State zurücksetzen**
  - Löscht internen Zustand (`PVREG_ResetState`).

Zusätzlich werden im UI editierbare Variablen angelegt (z. B. manueller Wallbox-Modus, Boiler-Solltemperatur). Änderungen werden über `RequestAction` verarbeitet.

---

## Technische Funktionsbeschreibung der wichtigsten Modul-Funktionen

- **Create()**
  - Registriert alle Properties (Parameter), Timer und Basiskonfiguration.
- **ApplyChanges()**
  - Baut/aktualisiert UI-Struktur und Profile, setzt Timerintervall, optional Sofortlauf.
- **RequestAction($Ident, $Value)**
  - Zentrale Verarbeitung von UI-Aktionen und interaktiven Variablen.
- **RunNow()**
  - Öffentlicher Trigger für einen kompletten Regelzyklus.
- **ResetState()**
  - Setzt internen Laufzeit-Zustand zurück.
- **runLoop()**
  - Interner Loop-Entry, lädt Konfiguration/State und startet die Hauptlogik.
- **buildCfg()**
  - Baut eine normalisierte Konfigurationsstruktur aus den Properties.
- **main(array $CFG)**
  - Kernalgorithmus: Messen → Planen → Schreiben.

Wesentliche Planungs-/Ausgabefunktionen:
- **planHeatpump(...)**
  - Entscheidungslogik für WP inkl. Hysterese und Mindestzeiten.
- **planHeatingRod(...)**
  - Stufige Heizstab-Steuerung inkl. Weekly-Mechanik.
- **planWallbox(...)**
  - Stromregelung, Rampen, Soft-Off, 1P/3P-Schaltung.
- **writeHeatpumpSurplusSignal(...)**
  - Schreibt WP-Ausgangssignal einheiten-/vorzeichenrichtig.
- **writeGridFilteredInverted(...)**
  - Schreibt das gefilterte invertierte Netzsignal als Output.

---

## Hinweise zur Parametrierung (Praxis)

1. **Einheiten zuerst korrekt setzen** (`W`/`kW`), sonst entstehen falsche Sollwerte.
2. **Mit konservativen Grenzen starten** (höhere Mindestzeiten, kleine Rampen).
3. **Totband + Filter abstimmen**:
   - Bei Flattern: `SurplusExportDeadbandW` erhöhen, `SurplusFilterAlpha` reduzieren.
4. **Wallbox sicher einregeln**:
   - `WallboxMinA/MaxA`, Phase-Schwellen und Soft-Off gemeinsam testen.
5. **Weekly-Heizstab nur nutzen, wenn hydraulisch/elektrisch gewünscht**.
