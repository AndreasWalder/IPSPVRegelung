<?php
declare(strict_types=1);

/**
 * ============================================================
 * PV REGELUNG — Überschuss + Vorrang (Wärmepumpe vor Akku)
 * IP-Symcon Modul (Timer-basiert)
 * ============================================================
 *
 * Änderungsverlauf (Changelog)
 * 2026-02-13: v1 — Initial: Modul-Port aus Script v1.8 (Logik 1:1, Timer/State/UI als Modul).
 * 2026-02-13: v1 — Initial: Überschuss-Regelung mit Prioritäten (WP → Heizpatrone weekly → Wallbox),
 *                 Hysterese, Mindestlaufzeiten, State-Variablen, Timer-Loop.
 * 2026-02-13: v1.1 — Einheiten/IDs angepasst:
 *                 • Grid/PV/WP/Wallbox teilweise in kW → intern auf W umgerechnet
 *                 • PV2 bereits in W
 *                 • Heizstab weekly nur wenn BoilerTemp >= 50°C
 * 2026-02-13: v1.2 — Anzeigevariablen/Debug:
 *                 • Automatische Struktur + Variablen für: PV Produktion, PV Überschuss,
 *                   Gebäude Verbrauch, Wallbox, Batterieladezustand
 *                 • Profile werden automatisch angelegt
 * 2026-02-13: v1.3 — Fix: Profilnamen ohne Sonderzeichen (PV_W / PV_kW / PV_PCT / PV_A)
 * 2026-02-13: v1.4 — Visualisierung: Hausverbrauch (ohne WB/WP/Batt) + Wallbox-Regelung nutzt Überschuss vor WB (Export + WB Ist)
 * 2026-02-13: v1.5 — Rest-Überschuss (Soll ~0) nach allen Verbrauchern + Wallbox Rampenregelung (sanft rauf/runter, Soft-Off bei Wolke)
 * 2026-02-13: v1.6 — Wallbox Phasen-Umschaltung 1P/3P per Bool-Var (true=1P, false=3P) mit Hysterese + Mindesthaltezeit
 * 2026-02-13: v1.7 — Zusatz-Output: Netz (gefiltert) invertiert (Vorzeichen umdrehen)
 * 2026-02-13: v1.8 — export_deadband_w wirkt nur noch auf WP/Heizstab (Wallbox inkl. Phase läuft immer, außer bei Import-Limit)
 * 2026-02-13: v1.9 — UI: zusätzliche selbst erstellte Variable „Netz (gefiltert, invertiert)“ ergänzt
 * 2026-02-13: v1.10 — Fix Wallbox-Abschaltung: Bei dauerhaft zu wenig Überschuss wird nach Soft-Off-Delay sicher ausgeschaltet
 * 2026-02-13: v1.11 — Hausverbrauch: Batterie-Leistung als signed Wert behandelt
 *                  (Entladung wird addiert, Ladung wird abgezogen)
 * 2026-02-13: v1.12 — Loop-Intervall Standard auf 15s gesetzt; Mindestgrenze auf 5s.
 * 2026-02-13: v1.13 — Hausverbrauch: WP-Leistung nur abziehen, wenn WP laut Laufstatus wirklich aktiv ist.
 * 2026-02-13: v1.14 — Hausverbrauch: Batterie nur bei Entladung addieren; Ladung nicht mehr abziehen.
 * 2026-02-13: v1.15 — Hausverbrauch: Batterie-Entladung aus positiven Leistungswerten berücksichtigen.
 * 2026-02-13: v1.16 — Wallbox: manueller Lademodus (Bool + Ladeleistung + Ziel-SOC) bis Auto-SOC erreicht ist, unabhängig von PV-Überschuss.
 * 2026-02-13: v1.17 — UI/Bedienung: „Freigabe" in IPS anklickbar; deaktivierte Freigabe schaltet Wallbox-Überschussregelung aus.
 *                  Manuelle Ladeleistung und Ziel-SOC als interaktive IPS-Variablen (Action auf Instanz).
 * 2026-02-13: v1.18 — Action-Skript für klickbare UI-Variablen erstellt/zugewiesen (IP-Symcon-konform).
 *                  Bei Freigabe EIN wird Sperrzeit zurückgesetzt und Regelung sofort neu ausgewertet.
 * 2026-02-13: v1.19 — UI-Fix manuelle Wallbox-Werte: Action direkt auf Instanz gelegt (RequestAction),
 *                  Profil "PV_WI" mit sinnvollem Wertebereich für editierbare Eingabe in der Visu.
 * 2026-02-13: v1.20 — Fix Action-Binding: CustomAction wieder auf erzeugtes Action-Skript gesetzt,
 *                  damit keine "Skript existiert nicht"-Warnungen auftreten.
 * 2026-02-13: v1.21 — Härtung Action-Skript: Script-ID wird auf Existenz/Typ geprüft und bei Bedarf
 *                  neu erstellt, damit kein ungültiger CustomAction-Verweis gesetzt wird.
 * 2026-02-13: v1.22 — Manuelle Wallbox-Ladeleistung in kW (2.0–11.0) statt W; inkl. Migration alter W-Werte.
 * 2026-02-13: v1.23 — Manuelle Ladeleistung ohne Nachkommastelle (2–11 kW) und sofortiges AUS bei Deaktivierung von "Manuell laden aktiv".
 * 2026-02-13: v1.24 — UI-Bereinigung: separate Wallbox-Freigabe-Variable entfernt; Wallbox-Regelung läuft wieder ohne zusätzlichen UI-Schalter.
 * 2026-02-13: v1.25 — Heizstab weekly auf 3x Bool-Ausgänge umgestellt (statt %), Auto-Modus + Boiler-Soll aus Visu,
 *                  Startschwelle Überschuss einstellbar (Default 9kW), Weekly-Autostart bleibt erhalten.
 * 2026-02-13: v1.26 — Boiler-Solltemperatur ausschließlich als editierbare Visu-Variable „Boiler Solltemperatur“
 *                  im UI; Konfigurationsfelder „Heizstab Zieltemperatur“ und externe Soll-Var-ID entfernt.
 * 2026-02-13: v1.27 — Heizstab stufig (1/2/3), manuelle Sofortsteuerung (EIN/AUS) und Wochenlogik
 *                  auf „Tage seit letzter Solltemperatur-Erreichung“ umgestellt.
 * 2026-02-15: v1.28 — Wallbox-Automatik startet nur noch bei anhaltendem PV-Überschuss
 *                  (Startschwelle + Mindestdauer konfigurierbar, Standard 2 kW / 15 min).
 * 2026-02-17: v1.29 — Hausverbrauch: Batterieladung (positiver Leistungswert) wird vom Hausverbrauch
 *                  abgezogen, Batterieentladung (negativer Leistungswert) addiert.
 * 2026-02-17: v1.30 — Korrektur Hausverbrauch: Rücknahme der Gebäudelast-Anpassung auf Batterie-Entladung.
 *                  Heizstab wird im Hausverbrauch wieder mitgezählt.
 * 2026-02-17: v1.31 — Hausverbrauch (ohne WB/WP/Batt): Batterie-Entladung wird nicht mehr doppelt addiert.
 * 2026-02-18: v1.32 — Heizstab-Abregelung entschärft: Bei sinkendem Überschuss wird stufenweise reduziert
 *                  (max. eine Stufe pro Zyklus) statt abrupt auf AUS zu fallen.
 * 2026-02-18: v1.33 — Heizstab-Stufung: Mindestlaufzeit-Timer wird nur bei Hochregeln neu gesetzt,
 *                  damit bei abfallendem Überschuss weiterhin zeitnah weiter heruntergeregelt werden kann.
 * 2026-02-27: v1.34 — Wallbox manuell: Aktivierung "Manuell laden aktiv" erzwingt den Start unabhängig
 *                  vom aktuellen SOC; das manuelle Laden wird nicht mehr sofort wegen Ziel-SOC blockiert.
 * 2026-03-01: v1.35 — Zwei kurze Live-Textausgaben ergänzt: "Aktuelle Entscheidung" und
 *                  "Nächste Tendenz" zur besseren Nachvollziehbarkeit der Regelung.
 * 2026-03-03: v1.36 — Wallbox optional mit „Fahrzeug angesteckt“-Signal:
 *                  Wenn kein Fahrzeug erkannt wird, bleibt Wallbox AUS und der Überschuss steht anderen
 *                  Verbrauchern (z. B. Heizstab) zur Verfügung.
 * 2026-03-03: v1.37 — Wallbox Fahrzeugerkennung erweitert: Neben Bool wird jetzt auch ein
 *                  go-eCharger-Status (Integer) unterstützt. Integer > 1 gilt als Fahrzeug erkannt.
 * 2026-03-03: v1.38 — Option "Bool true bedeutet Fahrzeug angesteckt" entfernt.
 *                  Bool wird nun immer direkt ausgewertet (true=angesteckt), Integer-Status bleibt >1.
 * 2026-03-03: v1.39 — Fahrzeugerkennung Wallbox nur noch über Status-Integer:
 *                  konfigurierter Wert muss Integer sein, Fahrzeug gilt bei Status > 1 als angesteckt.
 * 2026-03-21: v1.40 — Wallbox gezielt entschärft:
 *                  • Akku-Entladung kann die Wallbox jetzt aktiv ausbremsen/verhindern
 *                  • Batterieladung kann optional als freier PV-Überschuss für die Wallbox verwendet werden
 *                    (konservativ erst ab hohem SOC), damit vorhandener Überschuss nicht halbiert wirkt.
 * 2026-03-24: v1.41 — Wallbox-Sollwert nutzt im laufenden Betrieb wieder „Export + aktuelle WB-Istleistung“.
 *                  Dadurch regelt die Wallbox auf ~0 Einspeisung statt nur auf den reinen Exportwert
 *                  und verschenkt bei stabilem Überschuss keine Leistung.
 * 2026-03-25: v1.42 — Wallbox PV-Priorisierung verschärft:
 *                  • Kein Wallbox-Zuschlag mehr aus Batterieladung
 *                  • Batterie-Entladung wird bereits ab >0 W konsequent geblockt
 *                  • Bei eindeutig genügend PV-Leistung wird direkt auf Maximalstrom geregelt
 *                  • Entscheidungstexte nennen explizit Netz-/Akku-Status als Begründung
 * 2026-03-27: v1.43 — Wallbox/Heizstab-Kopplung verbessert:
 *                  • Wenn Fahrzeug angesteckt ist und die Wallbox laden kann, werden Heizstäbe unterdrückt
 *                    (Wallbox hat dann Vorrang).
 *                  • Wallbox-Leistung wird dabei weiterhin phasenrichtig berechnet (1P/3P bei gleichem Strom).
 * 2026-03-27: v1.44 — Wallbox-Phasenstabilität verbessert:
 *                  • Phasenentscheidung berücksichtigt bei laufender Wallbox die aktuelle WB-Istleistung
 *                    (Export + WB-Ist), um unnötiges 1P/3P-Pendeln zu vermeiden.
 * 2026-03-27: v1.45 — Rest-Überschuss/Tendenz korrigiert:
 *                  • Für die Rest-/Tendenz-Anzeige wird bei laufender Wallbox wieder die aktuelle
 *                    WB-Istleistung berücksichtigt, damit freie Rampenreserve sichtbar bleibt.
 * 2026-03-27: v1.46 — Wallbox-Regelung nutzt Rest-Überschuss als Zusatzreserve:
 *                  • Positiver Rest-Überschuss aus dem vorherigen Zyklus wird der
 *                    nächsten Wallbox-Sollwertplanung zugeschlagen, damit bei
 *                    vorhandener Rampenreserve nicht unnötig heruntergeregelt wird.
 */

class PVRegelung extends IPSModule
{
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('LoopSeconds', 15);
        $this->RegisterPropertyBoolean('AutoRunOnStart', false);

        $this->RegisterPropertyInteger('GridMeterID', 52080);
        $this->RegisterPropertyString('GridMeterUnit', 'kW');

        $this->RegisterPropertyInteger('PV1ID', 23960);
        $this->RegisterPropertyString('PV1Unit', 'kW');

        $this->RegisterPropertyInteger('PV2ID', 18333);
        $this->RegisterPropertyString('PV2Unit', 'W');

        $this->RegisterPropertyInteger('BoilerTempVarID', 31429);
        $this->RegisterPropertyFloat('HpTargetC', 50.0);
        $this->RegisterPropertyFloat('RodMinStartTempC', 50.0);

        $this->RegisterPropertyInteger('BatterySocVarID', 49601);
        $this->RegisterPropertyInteger('BatteryChargePowerVarID', 0);
        $this->RegisterPropertyString('BatteryChargePowerUnit', 'kW');

        $this->RegisterPropertyBoolean('PriorityHeatpumpFirst', true);
        $this->RegisterPropertyBoolean('PriorityWeeklyRodBeforeWallbox', true);

        $this->RegisterPropertyInteger('SurplusExportDeadbandW', 150);
        $this->RegisterPropertyInteger('SurplusMaxGridImportW', 300);
        $this->RegisterPropertyFloat('SurplusFilterAlpha', 0.35);

        $this->RegisterPropertyInteger('OutputsGridFilteredInvertedVarID', 25288);
        $this->RegisterPropertyString('OutputsGridFilteredInvertedUnit', 'kW');

        $this->RegisterPropertyBoolean('HeatpumpEnabled', true);
        $this->RegisterPropertyInteger('HeatpumpForceRunVarID', 0);
        $this->RegisterPropertyInteger('HeatpumpRunningVarID', 27471);
        $this->RegisterPropertyInteger('HeatpumpPowerInVarID', 12867);
        $this->RegisterPropertyString('HeatpumpPowerInUnit', 'kW');
        $this->RegisterPropertyInteger('HeatpumpSurplusOutVarID', 17999);
        $this->RegisterPropertyString('HeatpumpSurplusOutUnit', 'kW');
        $this->RegisterPropertyBoolean('HeatpumpSurplusOutSigned', false);
        $this->RegisterPropertyInteger('HeatpumpPvProductionOutVarID', 0);
        $this->RegisterPropertyString('HeatpumpPvProductionOutUnit', 'kW');

        $this->RegisterPropertyFloat('HeatpumpTempHysteresisC', 0.5);
        $this->RegisterPropertyInteger('HeatpumpMinSurplusW', 800);
        $this->RegisterPropertyInteger('HeatpumpMinOnSeconds', 300);
        $this->RegisterPropertyInteger('HeatpumpMinOffSeconds', 300);
        $this->RegisterPropertyInteger('HeatpumpAssumedPowerW', 2000);

        $this->RegisterPropertyBoolean('HeatingRodEnabled', true);
        $this->RegisterPropertyInteger('HeatingRodSwitchVarID1', 0);
        $this->RegisterPropertyInteger('HeatingRodSwitchVarID2', 0);
        $this->RegisterPropertyInteger('HeatingRodSwitchVarID3', 0);
        $this->RegisterPropertyInteger('HeatingRodPowerPerUnitW', 3000);
        $this->RegisterPropertyInteger('HeatingRodMinSurplusW', 9000);
        $this->RegisterPropertyInteger('HeatingRodSurplusHysteresisW', 4000);
        $this->RegisterPropertyInteger('HeatingRodMinOnSeconds', 180);
        $this->RegisterPropertyInteger('HeatingRodMinOffSeconds', 180);
        $this->RegisterPropertyInteger('HeatingRodStartDelaySeconds', 90);

        $this->RegisterPropertyBoolean('HeatingRodWeeklyEnabled', true);
        $this->RegisterPropertyInteger('HeatingRodDaysAfterTargetReached', 7);
        $this->RegisterPropertyString('HeatingRodWeeklyStart', '10:00');
        $this->RegisterPropertyString('HeatingRodWeeklyEnd', '16:00');

        $this->RegisterPropertyBoolean('WallboxEnabled', true);
        $this->RegisterPropertyInteger('WallboxEnableVarID', 47100);
        $this->RegisterPropertyInteger('WallboxChargePowerVarID', 23401);
        $this->RegisterPropertyString('WallboxChargePowerUnit', 'kW');
        $this->RegisterPropertyInteger('WallboxSetCurrentAVarID', 56376);
        $this->RegisterPropertyInteger('WallboxCarConnectedVarID', 0);

        $this->RegisterPropertyInteger('WallboxPhase1pVarID', 19401);
        $this->RegisterPropertyBoolean('WallboxPhaseTrueIs1p', true);
        $this->RegisterPropertyInteger('WallboxPhaseMinHoldSeconds', 5);
        $this->RegisterPropertyInteger('WallboxPhaseSwitchTo3pMinW', 5000);
        $this->RegisterPropertyInteger('WallboxPhaseSwitchTo1pMaxW', 4500);

        $this->RegisterPropertyInteger('WallboxPhases3p', 3);
        $this->RegisterPropertyInteger('WallboxPhases1p', 1);
        $this->RegisterPropertyInteger('WallboxVoltageV', 230);

        $this->RegisterPropertyInteger('WallboxMinA', 6);
        $this->RegisterPropertyInteger('WallboxMaxA', 16);
        $this->RegisterPropertyInteger('WallboxStepA', 1);
        $this->RegisterPropertyInteger('WallboxMinOnSeconds', 180);
        $this->RegisterPropertyInteger('WallboxMinOffSeconds', 120);
        $this->RegisterPropertyInteger('WallboxReserveW', 200);
        $this->RegisterPropertyInteger('WallboxSurplusHysteresisW', 500);
        $this->RegisterPropertyInteger('WallboxBlockBatteryDischargeW', 300);
        $this->RegisterPropertyBoolean('WallboxUseBatteryChargeSurplus', true);
        $this->RegisterPropertyFloat('WallboxUseBatteryChargeSurplusAboveSoc', 95.0);
        $this->RegisterPropertyInteger('WallboxAutoStartMinSurplusW', 2000);
        $this->RegisterPropertyInteger('WallboxAutoStartMinDurationSeconds', 900);
        $this->RegisterPropertyInteger('WallboxControlMinHoldSeconds', 45);

        $this->RegisterPropertyInteger('WallboxRampUpA', 1);
        $this->RegisterPropertyInteger('WallboxRampDownA', 1);
        $this->RegisterPropertyInteger('WallboxSoftOffGraceSeconds', 120);
        $this->RegisterPropertyInteger('WallboxManualCarSocVarID', 0);
        $this->RegisterPropertyFloat('WallboxManualDefaultTargetSoc', 80.0);
        $this->RegisterPropertyInteger('WallboxManualDefaultPowerW', 4200);

        $this->RegisterPropertyString('UIRootName', 'PV Regelung');

        $this->RegisterTimer('Loop', 0, 'IPS_RequestAction($_IPS["TARGET"], "Loop", 0);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $loop = $this->ReadPropertyInteger('LoopSeconds');
        $loop = max(5, min(300, $loop));
        $this->SetTimerInterval('Loop', $loop * 1000);

        $CFG = $this->buildCfg();
        $this->ensureUiStructure($CFG);
        $this->ensureManualWallboxDefaults($CFG);

        if ($this->ReadPropertyBoolean('AutoRunOnStart')) {
            $this->RunNow();
        }
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ((string)$Ident) {
            case 'Loop':
                $this->runLoop();
                break;
            case 'pv_manual_wb_enable':
                $manualEnabled = (bool)$Value;
                $this->setManualVarByIdent('pv_manual_wb_enable', $manualEnabled);
                if (!$manualEnabled) {
                    $CFG = $this->buildCfg();
                    $state = $this->loadState();
                    $state['wb_soft_on'] = false;
                    $state['wb_soft_a'] = 0;
                    $state['wb_deficit_since_ts'] = 0;
                    $this->applyWallbox($CFG, $state, false, 0);
                    $this->saveState($state);
                }
                $this->runLoop();
                break;
            case 'pv_manual_wb_power_w':
                $this->setManualVarByIdent('pv_manual_wb_power_w', $this->normalizeManualPowerToKw((float)$Value));
                $this->runLoop();
                break;
            case 'pv_manual_wb_target_soc':
                $this->setManualVarByIdent('pv_manual_wb_target_soc', max(0.0, min(100.0, (float)$Value)));
                $this->runLoop();
                break;
            case 'pv_rod_auto_mode':
                $this->setHeatingVarByIdent('pv_rod_auto_mode', (bool)$Value);
                $this->runLoop();
                break;
            case 'pv_manual_rod_on':
                $this->setHeatingVarByIdent('pv_manual_rod_on', (bool)$Value);
                $this->runLoop();
                break;
            case 'pv_rod_days_since_target':
                $this->setHeatingVarByIdent('pv_rod_days_since_target', max(1, min(20, (int)$Value)));
                $this->runLoop();
                break;
            case 'pv_boiler_target_c':
                $this->setHeatingVarByIdent('pv_boiler_target_c', max(0.0, min(90.0, (float)$Value)));
                $this->runLoop();
                break;
            default:
                throw new Exception('Invalid Ident: ' . (string)$Ident);
        }
    }

    public function RunNow(): void
    {
        $this->runLoop();
    }

    public function ResetState(): void
    {
        $this->SetBuffer('PV_STATE_JSON', '');
    }

    public function ExportSettingsJson(): void
    {
        $CFG = $this->buildCfg();
        $this->ensureUiStructure($CFG);

        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));
        $cWb = $this->ensureCategoryByIdent($root, 'pv_ui_wb', 'Wallbox');
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');

        $configuration = json_decode((string)IPS_GetConfiguration($this->InstanceID), true);
        if (!is_array($configuration)) {
            $configuration = [];
        }

        $export = [
            'timestamp' => date('c'),
            'instance_id' => $this->InstanceID,
            'configuration' => $configuration,
            'cfg' => $CFG,
            'state' => $this->loadState(),
            'ui' => [
                'wallbox' => [
                    'manual_enable' => (bool)$this->readVarByIdent($cWb, 'pv_manual_wb_enable', false),
                    'manual_power_kw' => (float)$this->readVarByIdent($cWb, 'pv_manual_wb_power_w', 0.0),
                    'manual_target_soc' => (float)$this->readVarByIdent($cWb, 'pv_manual_wb_target_soc', 0.0),
                    'car_soc' => (float)$this->readVarByIdent($cWb, 'pv_manual_wb_car_soc', 0.0),
                    'car_connected' => (bool)$this->readVarByIdent($cWb, 'pv_wb_car_connected', false),
                    'target_a' => (int)$this->readVarByIdent($cWb, 'pv_wb_target_a', 0),
                    'power_kw' => (float)$this->readVarByIdent($cWb, 'pv_wb_power_kw', 0.0),
                ],
                'heating' => [
                    'auto_mode' => (bool)$this->readVarByIdent($cHeat, 'pv_rod_auto_mode', true),
                    'manual_on' => (bool)$this->readVarByIdent($cHeat, 'pv_manual_rod_on', false),
                    'boiler_target_c' => (float)$this->readVarByIdent($cHeat, 'pv_boiler_target_c', 0.0),
                ],
            ],
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"error":"json_encode fehlgeschlagen"}';
        }

        $this->setVarByIdent($root, 'pv_debug_json_export', $json);
        IPS_LogMessage('PVRegelung JSON Export', $json);
    }

    private function runLoop(): void
    {
        $CFG = $this->buildCfg();
        $this->main($CFG);
    }

    private function buildCfg(): array
    {
        return [
            'loop_seconds' => (int)$this->ReadPropertyInteger('LoopSeconds'),
            'power' => [
                'grid_meter' => ['id' => (int)$this->ReadPropertyInteger('GridMeterID'), 'unit' => (string)$this->ReadPropertyString('GridMeterUnit')],
                'pv1' => ['id' => (int)$this->ReadPropertyInteger('PV1ID'), 'unit' => (string)$this->ReadPropertyString('PV1Unit')],
                'pv2' => ['id' => (int)$this->ReadPropertyInteger('PV2ID'), 'unit' => (string)$this->ReadPropertyString('PV2Unit')],
            ],
            'boiler' => [
                'temp_c' => (int)$this->ReadPropertyInteger('BoilerTempVarID'),
                'hp_target_c' => (float)$this->ReadPropertyFloat('HpTargetC'),
                'rod_min_start_temp_c' => (float)$this->ReadPropertyFloat('RodMinStartTempC'),
            ],
            'battery' => [
                'soc' => ['id' => (int)$this->ReadPropertyInteger('BatterySocVarID'), 'unit' => 'PCT'],
                'charge_power' => ['id' => (int)$this->ReadPropertyInteger('BatteryChargePowerVarID'), 'unit' => (string)$this->ReadPropertyString('BatteryChargePowerUnit')],
            ],
            'priority' => [
                'heatpump_first' => (bool)$this->ReadPropertyBoolean('PriorityHeatpumpFirst'),
                'weekly_rod_before_wallbox' => (bool)$this->ReadPropertyBoolean('PriorityWeeklyRodBeforeWallbox'),
            ],
            'surplus' => [
                'export_deadband_w' => (int)$this->ReadPropertyInteger('SurplusExportDeadbandW'),
                'max_grid_import_w' => (int)$this->ReadPropertyInteger('SurplusMaxGridImportW'),
                'filter_alpha' => (float)$this->ReadPropertyFloat('SurplusFilterAlpha'),
            ],
            'outputs' => [
                'grid_filtered_inverted' => ['id' => (int)$this->ReadPropertyInteger('OutputsGridFilteredInvertedVarID'), 'unit' => (string)$this->ReadPropertyString('OutputsGridFilteredInvertedUnit')],
            ],
            'heatpump' => [
                'enabled' => (bool)$this->ReadPropertyBoolean('HeatpumpEnabled'),
                'force_run_var' => (int)$this->ReadPropertyInteger('HeatpumpForceRunVarID'),
                'running_var' => (int)$this->ReadPropertyInteger('HeatpumpRunningVarID'),
                'power_in' => ['id' => (int)$this->ReadPropertyInteger('HeatpumpPowerInVarID'), 'unit' => (string)$this->ReadPropertyString('HeatpumpPowerInUnit')],
                'surplus_out_var' => (int)$this->ReadPropertyInteger('HeatpumpSurplusOutVarID'),
                'surplus_out_unit' => (string)$this->ReadPropertyString('HeatpumpSurplusOutUnit'),
                'surplus_out_signed' => (bool)$this->ReadPropertyBoolean('HeatpumpSurplusOutSigned'),
                'pv_production_out_var' => (int)$this->ReadPropertyInteger('HeatpumpPvProductionOutVarID'),
                'pv_production_out_unit' => (string)$this->ReadPropertyString('HeatpumpPvProductionOutUnit'),
                'temp_hysteresis_c' => (float)$this->ReadPropertyFloat('HeatpumpTempHysteresisC'),
                'min_surplus_w' => (int)$this->ReadPropertyInteger('HeatpumpMinSurplusW'),
                'min_on_seconds' => (int)$this->ReadPropertyInteger('HeatpumpMinOnSeconds'),
                'min_off_seconds' => (int)$this->ReadPropertyInteger('HeatpumpMinOffSeconds'),
                'assumed_power_w' => (int)$this->ReadPropertyInteger('HeatpumpAssumedPowerW'),
            ],
            'heating_rod' => [
                'enabled' => (bool)$this->ReadPropertyBoolean('HeatingRodEnabled'),
                'switch_vars' => [
                    (int)$this->ReadPropertyInteger('HeatingRodSwitchVarID1'),
                    (int)$this->ReadPropertyInteger('HeatingRodSwitchVarID2'),
                    (int)$this->ReadPropertyInteger('HeatingRodSwitchVarID3'),
                ],
                'power_per_unit_w' => (int)$this->ReadPropertyInteger('HeatingRodPowerPerUnitW'),
                'min_surplus_w' => (int)$this->ReadPropertyInteger('HeatingRodMinSurplusW'),
                'surplus_hysteresis_w' => (int)$this->ReadPropertyInteger('HeatingRodSurplusHysteresisW'),
                'min_on_seconds' => (int)$this->ReadPropertyInteger('HeatingRodMinOnSeconds'),
                'min_off_seconds' => (int)$this->ReadPropertyInteger('HeatingRodMinOffSeconds'),
                'start_delay_seconds' => (int)$this->ReadPropertyInteger('HeatingRodStartDelaySeconds'),
                'weekly' => [
                    'enabled' => (bool)$this->ReadPropertyBoolean('HeatingRodWeeklyEnabled'),
                    'days_after_target_reached' => (int)$this->ReadPropertyInteger('HeatingRodDaysAfterTargetReached'),
                    'start_hhmm' => (string)$this->ReadPropertyString('HeatingRodWeeklyStart'),
                    'end_hhmm' => (string)$this->ReadPropertyString('HeatingRodWeeklyEnd'),
                ],
            ],
            'wallbox' => [
                'enabled' => (bool)$this->ReadPropertyBoolean('WallboxEnabled'),
                'enable_var' => (int)$this->ReadPropertyInteger('WallboxEnableVarID'),
                'charge_power' => ['id' => (int)$this->ReadPropertyInteger('WallboxChargePowerVarID'), 'unit' => (string)$this->ReadPropertyString('WallboxChargePowerUnit')],
                'set_current_a_var' => (int)$this->ReadPropertyInteger('WallboxSetCurrentAVarID'),
                'car_connected_var' => (int)$this->ReadPropertyInteger('WallboxCarConnectedVarID'),
                'phase_1p_var' => (int)$this->ReadPropertyInteger('WallboxPhase1pVarID'),
                'phase_true_is_1p' => (bool)$this->ReadPropertyBoolean('WallboxPhaseTrueIs1p'),
                'phase_min_hold_seconds' => (int)$this->ReadPropertyInteger('WallboxPhaseMinHoldSeconds'),
                'phase_switch_to_3p_min_w' => (int)$this->ReadPropertyInteger('WallboxPhaseSwitchTo3pMinW'),
                'phase_switch_to_1p_max_w' => (int)$this->ReadPropertyInteger('WallboxPhaseSwitchTo1pMaxW'),
                'phases_3p' => (int)$this->ReadPropertyInteger('WallboxPhases3p'),
                'phases_1p' => (int)$this->ReadPropertyInteger('WallboxPhases1p'),
                'voltage_v' => (int)$this->ReadPropertyInteger('WallboxVoltageV'),
                'min_a' => (int)$this->ReadPropertyInteger('WallboxMinA'),
                'max_a' => (int)$this->ReadPropertyInteger('WallboxMaxA'),
                'step_a' => (int)$this->ReadPropertyInteger('WallboxStepA'),
                'min_on_seconds' => (int)$this->ReadPropertyInteger('WallboxMinOnSeconds'),
                'min_off_seconds' => (int)$this->ReadPropertyInteger('WallboxMinOffSeconds'),
                'reserve_w' => (int)$this->ReadPropertyInteger('WallboxReserveW'),
                'surplus_hysteresis_w' => (int)$this->ReadPropertyInteger('WallboxSurplusHysteresisW'),
                'block_battery_discharge_w' => (int)$this->ReadPropertyInteger('WallboxBlockBatteryDischargeW'),
                'use_battery_charge_surplus' => (bool)$this->ReadPropertyBoolean('WallboxUseBatteryChargeSurplus'),
                'use_battery_charge_surplus_above_soc' => (float)$this->ReadPropertyFloat('WallboxUseBatteryChargeSurplusAboveSoc'),
                'auto_start_min_surplus_w' => (int)$this->ReadPropertyInteger('WallboxAutoStartMinSurplusW'),
                'auto_start_min_duration_seconds' => (int)$this->ReadPropertyInteger('WallboxAutoStartMinDurationSeconds'),
                'control_min_hold_seconds' => (int)$this->ReadPropertyInteger('WallboxControlMinHoldSeconds'),
                'ramp_up_a_per_loop' => (int)$this->ReadPropertyInteger('WallboxRampUpA'),
                'ramp_down_a_per_loop' => (int)$this->ReadPropertyInteger('WallboxRampDownA'),
                'soft_off_grace_seconds' => (int)$this->ReadPropertyInteger('WallboxSoftOffGraceSeconds'),
                'manual' => [
                    'car_soc_var' => (int)$this->ReadPropertyInteger('WallboxManualCarSocVarID'),
                    'default_target_soc' => (float)$this->ReadPropertyFloat('WallboxManualDefaultTargetSoc'),
                    'default_power_w' => (int)round($this->normalizeManualPowerToKw((float)$this->ReadPropertyInteger('WallboxManualDefaultPowerW')) * 1000.0),
                ],
            ],
            'ui' => [
                'root_name' => (string)$this->ReadPropertyString('UIRootName'),
            ],
        ];
    }

    private function main(array $CFG): void
    {
        $this->ensureTimer((int)$CFG['loop_seconds']);
        $this->ensureUiStructure($CFG);

        $state = $this->loadState();

        $gridW_raw = $this->readPowerToW($CFG['power']['grid_meter']);
        $gridW = $this->lowpass($gridW_raw, (float)$CFG['surplus']['filter_alpha'], (float)($state['gridW_filtered'] ?? $gridW_raw));
        $state['gridW_filtered'] = $gridW;

        $this->writeGridFilteredInverted($CFG, $gridW);

        $exportW = max(0.0, -$gridW);
        $importW = max(0.0,  $gridW);

        $pv1W = $this->readPowerToW($CFG['power']['pv1']);
        $pv2W = $this->readPowerToW($CFG['power']['pv2']);
        $pvTotalW = max(0.0, $pv1W + $pv2W);

        $battPowerW = 0.0;
        if (isset($CFG['battery']['charge_power']) && is_array($CFG['battery']['charge_power'])) {
            $battPowerW = $this->readPowerToW($CFG['battery']['charge_power']);
        }

        $battDischargeForHouseW = max(0.0, -$battPowerW);
        $buildingLoadW = max(0.0, $pvTotalW + $gridW + $battDischargeForHouseW);

        $wallboxChargeW = $this->readPowerToW($CFG['wallbox']['charge_power']);

        $boilerTemp = (float)$this->readVar((int)$CFG['boiler']['temp_c'], 0.0);

        $soc = $this->readPercent($CFG['battery']['soc']);
        $hpRunning = (bool)$this->readVar((int)$CFG['heatpump']['running_var'], false);
        $hpPowerW  = $this->readPowerToW($CFG['heatpump']['power_in']);

        $rodPowerW = $this->heatingRodPowerForStageW($CFG, (int)($state['rod_stage'] ?? 0));

        $hpPowerForHouseW = $hpRunning ? $hpPowerW : 0.0;
        $battChargeForHouseW = max(0.0, $battPowerW);
        $battDischargeForHouseW = max(0.0, -$battPowerW);
        $houseLoadW = max(0.0, $buildingLoadW - $wallboxChargeW - $hpPowerForHouseW - $battChargeForHouseW);

        $buildingLoadRawW = max(0.0, $pvTotalW + $gridW_raw + $battDischargeForHouseW);
        $houseLoadNoWbWpBattW = max(0.0, $buildingLoadRawW - $wallboxChargeW - $hpPowerForHouseW - $battChargeForHouseW);

        $this->writeHeatpumpSurplusSignal($CFG, $gridW, $pvTotalW, $houseLoadNoWbWpBattW, $rodPowerW);
        $this->writeHeatpumpPvProductionSignal($CFG, $pvTotalW);

        $weeklyDaysSinceTarget = $this->readHeatingRodDaysSinceTargetReached($CFG);
        $carConnected = $this->isWallboxCarConnected($CFG);
        $this->updateUiVars($CFG, [
            'pv1W' => $pv1W,
            'pv2W' => $pv2W,
            'pvTotalW' => $pvTotalW,
            'gridW_raw' => $gridW_raw,
            'gridW' => $gridW,
            'importW' => $importW,
            'exportW' => $exportW,
            'buildingLoadW' => $buildingLoadW,
            'wallboxChargeW' => $wallboxChargeW,
            'carConnected' => $carConnected ? 1 : 0,
            'boilerTemp' => $boilerTemp,
            'soc' => $soc,
            'hpRunning' => $hpRunning ? 1 : 0,
            'hpPowerW'  => $hpPowerW,
            'houseLoadW' => $houseLoadW,
            'boilerTargetC' => $this->readBoilerRodTargetC($CFG),
            'rodDaysSinceTarget' => $weeklyDaysSinceTarget,
            'rodAutoMode' => $this->readHeatingRodAutoMode($CFG) ? 1 : 0,
            'manualRodOn' => $this->readHeatingRodManualOn($CFG) ? 1 : 0,
            'rodStage' => (int)($state['rod_stage'] ?? 0),
        ]);

        $maxImport = (float)$CFG['surplus']['max_grid_import_w'];
        [$manualActive, $manualPowerW, $manualTargetSoc, $carSoc] = $this->readManualWallboxConfig($CFG);
        $rodManualOn = $this->readHeatingRodManualOn($CFG);

        if ($manualActive) {
            [$wbOn, $wbA, $state] = $this->planWallboxManualPower($CFG, $state, $manualPowerW);
            $this->applyHeatpump($CFG, $state, false);
            $this->applyHeatingRodStage($CFG, $state, 0);
            $this->applyWallbox($CFG, $state, $wbOn, $wbA);
            [$decisionText, $forecastText, $detailsText] = $this->buildDecisionTexts([
                'mode' => 'manual_wb',
                'manualPowerW' => $manualPowerW,
                'manualTargetSoc' => $manualTargetSoc,
                'carSoc' => $carSoc,
                'carConnected' => $carConnected,
                'hpOn' => false,
                'rodStage' => 0,
                'wbOn' => $wbOn,
                'wbA' => $wbA,
            ]);

            $this->updateUiVars($CFG, [
                'hpOn' => 0,
                'rodOn' => 0,
                'rodStage' => 0,
                'wbOn' => $wbOn ? 1 : 0,
                'wbA' => $wbA,
                'remainingW' => 0,
                'weeklyRodActive' => 0,
                'restSurplusW' => 0.0,
                'manualActive' => 1,
                'manualPowerW' => $manualPowerW,
                'manualTargetSoc' => $manualTargetSoc,
                'manualCarSoc' => $carSoc,
                'decisionText' => $decisionText,
                'forecastText' => $forecastText,
                'detailsText' => $detailsText,
            ]);

            $state['wb_rest_surplus_w'] = 0.0;
            $this->saveState($state);
            return;
        }

        if ($importW > $maxImport && !$rodManualOn) {
            $state = $this->wallboxSoftOff($CFG, $state);
            $this->applyHeatpump($CFG, $state, false);
            $this->applyHeatingRodStage($CFG, $state, 0);
            $this->applyWallbox($CFG, $state, (bool)($state['wb_soft_on'] ?? false), (int)($state['wb_soft_a'] ?? 0));
            $softWbOn = (bool)($state['wb_soft_on'] ?? false);
            $softWbA = (int)($state['wb_soft_a'] ?? 0);
            [$decisionText, $forecastText, $detailsText] = $this->buildDecisionTexts([
                'mode' => 'import_limit',
                'importW' => $importW,
                'maxImportW' => $maxImport,
                'carConnected' => $carConnected,
                'hpOn' => false,
                'rodStage' => 0,
                'wbOn' => $softWbOn,
                'wbA' => $softWbA,
                'remainingW' => 0.0,
            ]);
            $this->updateUiVars($CFG, [
                'restSurplusW' => 0.0,
                'rodOn' => 0,
                'rodStage' => 0,
                'weeklyRodActive' => 0,
                'decisionText' => $decisionText,
                'forecastText' => $forecastText,
                'detailsText' => $detailsText,
            ]);
            $state['wb_rest_surplus_w'] = 0.0;
            $this->saveState($state);
            return;
        }

        $deadband  = (float)$CFG['surplus']['export_deadband_w'];

        // Für die Sollwertplanung ausschließlich echten PV-Überschuss verwenden.
        // Die bisherige Addition der aktuellen Wallbox-Leistung führte dazu,
        // dass sich der Regler bei wenig/keinem Überschuss selbst "am Leben"
        // gehalten hat und unnötig Netzbezug bestehen blieb.
        $availableBeforeWBW = $exportW;
        $batteryWallboxAssistW = $this->batteryChargeAssistForWallboxW($CFG, $soc, $battPowerW);
        $batteryWallboxPenaltyW = $this->batteryDischargePenaltyForWallboxW($CFG, $battPowerW);
        $restSurplusCarryW = max(0.0, (float)($state['wb_rest_surplus_w'] ?? 0.0));
        $wallboxAvailableBeforeWBW = max(0.0, $availableBeforeWBW + $restSurplusCarryW + $batteryWallboxAssistW - $batteryWallboxPenaltyW);
        $remainingW = $availableBeforeWBW;

        $hpOn = false;
        $rodOn = false;
        $wbOn = false;
        $wbA = 0;
        [$rodDaysDisplay, $rodLastTargetStatus] = $this->buildRodLastTargetUiState($state);

        if ($exportW < $deadband && !$rodManualOn) {
            [$wbOn, $wbA, $state] = $this->planWallboxRamped($CFG, $state, $wallboxAvailableBeforeWBW);

            $this->applyHeatpump($CFG, $state, false);
            $this->applyHeatingRodStage($CFG, $state, 0);
            $this->applyWallbox($CFG, $state, $wbOn, $wbA);

            $wbTargetW = $this->wallboxPowerFromA($CFG, $state, $wbA);
            $reserveW = (float)($CFG['wallbox']['reserve_w'] ?? 0.0);
            $wbCurrentPowerW = ($wbOn || (bool)($state['wb_is_on'] ?? false))
                ? max(0.0, $this->readPowerToW($CFG['wallbox']['charge_power']))
                : 0.0;
            $restSurplusW = max(0.0, $wallboxAvailableBeforeWBW + $wbCurrentPowerW - $wbTargetW - $reserveW);
            [$decisionText, $forecastText, $detailsText] = $this->buildDecisionTexts([
                'mode' => 'low_surplus',
                'exportW' => $exportW,
                'deadbandW' => $deadband,
                'wbA' => $wbA,
                'carConnected' => $carConnected,
                'hpOn' => false,
                'rodStage' => 0,
                'wbOn' => $wbOn,
                'importW' => $importW,
                'batteryPowerW' => $battPowerW,
            ]);

            $this->updateUiVars($CFG, [
                'hpOn' => 0,
                'rodOn' => 0,
                'rodStage' => 0,
                'wbOn' => $wbOn ? 1 : 0,
                'wbA' => $wbA,
                'remainingW' => $remainingW,
                'weeklyRodActive' => 0,
                'rodDaysSinceTargetActual' => $rodDaysDisplay,
                'rodLastTargetStatus' => $rodLastTargetStatus,
                'restSurplusW' => $restSurplusW,
                'manualActive' => 0,
                'decisionText' => $decisionText,
                'forecastText' => $forecastText,
                'detailsText' => $detailsText,
            ]);

            $state['wb_rest_surplus_w'] = $restSurplusW;
            $this->saveState($state);
            return;
        }

        $CFG['heating_rod']['weekly']['days_after_target_reached'] = $weeklyDaysSinceTarget;
        $weeklyWindow = $this->isHeatingRodWindow($CFG['heating_rod']['weekly'], $state);
        $rodAllowedByTemp = $boilerTemp >= (float)$CFG['boiler']['rod_min_start_temp_c'];
        $rodAutoMode = $this->readHeatingRodAutoMode($CFG);
        $rodTargetC = $this->readBoilerRodTargetC($CFG);
        if ($boilerTemp >= $rodTargetC) {
            $state['rod_last_target_reached_ts'] = time();
        }
        [$rodDaysDisplay, $rodLastTargetStatus] = $this->buildRodLastTargetUiState($state);
        $needWeeklyRod = $rodAutoMode
            && $weeklyWindow
            && $rodAllowedByTemp
            && ($boilerTemp < ($rodTargetC - 0.2));

        $rodStage = 0;

        if ($rodManualOn) {
            $rodStage = $this->maxHeatingRodStage($CFG);
        } elseif (!($CFG['priority']['heatpump_first'] ?? true)) {
            [$rodStage, $remainingW] = $this->planHeatingRodStage($CFG, $state, $needWeeklyRod, $boilerTemp, $remainingW);
            [$hpOn, $remainingW] = $this->planHeatpump($CFG, $state, $boilerTemp, $remainingW);
        } else {
            [$hpOn, $remainingW] = $this->planHeatpump($CFG, $state, $boilerTemp, $remainingW);
            [$rodStage, $remainingW] = $this->planHeatingRodStage($CFG, $state, $needWeeklyRod, $boilerTemp, $remainingW);
        }

        $rodOn = $rodStage > 0;
        $wallboxAvailableAfterPriorityW = max(0.0, $remainingW + $restSurplusCarryW + $batteryWallboxAssistW - $batteryWallboxPenaltyW);
        [$wbPreviewOn] = $this->planWallboxRamped($CFG, $state, $wallboxAvailableAfterPriorityW);
        $wallboxHasPriority = $carConnected && $wbPreviewOn;

        if ($wallboxHasPriority) {
            $remainingW = max(0.0, $remainingW + $this->heatingRodPowerForStageW($CFG, $rodStage));
            $wallboxAvailableAfterPriorityW = max(0.0, $remainingW + $restSurplusCarryW + $batteryWallboxAssistW - $batteryWallboxPenaltyW);
            [$wbOn, $wbA, $state] = $this->planWallboxRamped($CFG, $state, $wallboxAvailableAfterPriorityW);
            $rodStage = 0;
            $rodOn = false;
        } else {
            $rodOn = $rodStage > 0;
            [$wbOn, $wbA, $state] = $this->planWallboxRamped($CFG, $state, $wallboxAvailableAfterPriorityW);
        }

        if ($carConnected && $wbOn && $rodStage > 0) {
            $remainingW = max(0.0, $remainingW + $this->heatingRodPowerForStageW($CFG, $rodStage));
            $wallboxAvailableAfterPriorityW = max(0.0, $remainingW + $restSurplusCarryW + $batteryWallboxAssistW - $batteryWallboxPenaltyW);
            [$wbOn, $wbA, $state] = $this->planWallboxRamped($CFG, $state, $wallboxAvailableAfterPriorityW);
            $rodStage = 0;
            $rodOn = false;
        }

        $this->applyHeatpump($CFG, $state, $hpOn);
        $this->applyHeatingRodStage($CFG, $state, $rodStage);
        $this->applyWallbox($CFG, $state, $wbOn, $wbA);

        $hpUsedW = $hpOn ? $this->heatpumpPowerW($CFG) : 0.0;
        $rodUsedW = $this->heatingRodPowerForStageW($CFG, $rodStage);
        $wbTargetW = $this->wallboxPowerFromA($CFG, $state, $wbA);
        $reserveW = (float)($CFG['wallbox']['reserve_w'] ?? 0.0);

        $wbCurrentPowerW = ($wbOn || (bool)($state['wb_is_on'] ?? false))
            ? max(0.0, $this->readPowerToW($CFG['wallbox']['charge_power']))
            : 0.0;
        $restSurplusW = max(0.0, $wallboxAvailableAfterPriorityW + $wbCurrentPowerW - $wbTargetW - $reserveW);
        [$decisionText, $forecastText, $detailsText] = $this->buildDecisionTexts([
            'mode' => 'auto',
            'hpOn' => $hpOn,
            'rodStage' => $rodStage,
            'wbOn' => $wbOn,
            'wbA' => $wbA,
            'remainingW' => $remainingW,
            'restSurplusW' => $restSurplusW,
            'carConnected' => $carConnected,
            'importW' => $importW,
            'batteryPowerW' => $battPowerW,
        ]);

        $this->updateUiVars($CFG, [
            'hpOn' => $hpOn ? 1 : 0,
            'rodOn' => $rodOn ? 1 : 0,
            'rodStage' => $rodStage,
            'wbOn' => $wbOn ? 1 : 0,
            'wbA' => $wbA,
            'remainingW' => $remainingW,
            'weeklyRodActive' => $needWeeklyRod ? 1 : 0,
            'rodDaysSinceTargetActual' => $rodDaysDisplay,
            'rodLastTargetStatus' => $rodLastTargetStatus,
            'restSurplusW' => $restSurplusW,
            'manualActive' => 0,
            'manualRodOn' => $rodManualOn ? 1 : 0,
            'decisionText' => $decisionText,
            'forecastText' => $forecastText,
            'detailsText' => $detailsText,
        ]);

        $state['wb_rest_surplus_w'] = $restSurplusW;
        $this->saveState($state);
    }

    private function planHeatpump(array $CFG, array &$state, float $boilerTemp, float $availableW): array
    {
        if (!($CFG['heatpump']['enabled'] ?? false)) return [false, $availableW];

        $varId = (int)($CFG['heatpump']['force_run_var'] ?? 0);
        if ($varId <= 0) return [false, $availableW];

        $target = (float)($CFG['boiler']['hp_target_c'] ?? 50.0);
        $hyst   = (float)($CFG['heatpump']['temp_hysteresis_c'] ?? 0.5);
        $minSurplus = (float)($CFG['heatpump']['min_surplus_w'] ?? 800.0);

        $now = time();
        $isOn = (bool)($state['hp_is_on'] ?? false);
        $lastOn  = (int)($state['hp_last_on_ts'] ?? 0);
        $lastOff = (int)($state['hp_last_off_ts'] ?? 0);

        $minOff = (int)($CFG['heatpump']['min_off_seconds'] ?? 300);
        $minOn  = (int)($CFG['heatpump']['min_on_seconds'] ?? 300);

        $canTurnOn  = (!$isOn) && (($now - $lastOff) >= $minOff);
        $canTurnOff = ($isOn)  && (($now - $lastOn)  >= $minOn);

        $wantOnByTemp  = $boilerTemp < ($target - $hyst);
        $wantOffByTemp = $boilerTemp >= $target;
        $wantOnBySurplus = $availableW >= $minSurplus;

        $desiredOn = $isOn;
        if (!$isOn && $canTurnOn && $wantOnByTemp && $wantOnBySurplus) $desiredOn = true;
        if ($isOn && $canTurnOff && $wantOffByTemp) $desiredOn = false;
        if ($isOn && $canTurnOff && ($availableW < ($minSurplus * 0.6))) $desiredOn = false;

        $hpPower = $this->heatpumpPowerW($CFG);
        if ($desiredOn) $availableW = max(0.0, $availableW - $hpPower);

        return [$desiredOn, $availableW];
    }

    private function planHeatingRodStage(array $CFG, array &$state, bool $weekly, float $boilerTemp, float $availableW): array
    {
        if (!($CFG['heating_rod']['enabled'] ?? false)) return [0, $availableW];
        if (!$weekly) return [0, $availableW];

        $target = $this->readBoilerRodTargetC($CFG);
        if ($boilerTemp >= $target) return [0, $availableW];

        $minSurplus = (float)$CFG['heating_rod']['min_surplus_w'];
        $surplusHyst = max(0.0, (float)($CFG['heating_rod']['surplus_hysteresis_w'] ?? 0.0));
        $startDelayS = max(0, (int)($CFG['heating_rod']['start_delay_seconds'] ?? 0));

        $maxStage = $this->maxHeatingRodStage($CFG);
        if ($maxStage <= 0) return [0, $availableW];

        $powerPerStage = max(0.0, (float)($CFG['heating_rod']['power_per_unit_w'] ?? 0.0));
        if ($powerPerStage <= 0.0) return [0, $availableW];

        $now = time();
        $currentStage = max(0, min($maxStage, (int)($state['rod_stage'] ?? 0)));
        $isOn = $currentStage > 0;
        $lastOn  = (int)($state['rod_last_on_ts'] ?? 0);
        $lastOff = (int)($state['rod_last_off_ts'] ?? 0);

        $canTurnOn  = (!$isOn) && (($now - $lastOff) >= (int)$CFG['heating_rod']['min_off_seconds']);
        $canTurnOff = ($isOn)  && (($now - $lastOn)  >= (int)$CFG['heating_rod']['min_on_seconds']);

        if (!$isOn) {
            $rodStartThresholdW = $minSurplus + $surplusHyst;
            if ($availableW >= $rodStartThresholdW) {
                if (((int)($state['rod_start_surplus_since_ts'] ?? 0)) <= 0) {
                    $state['rod_start_surplus_since_ts'] = $now;
                }
            } else {
                $state['rod_start_surplus_since_ts'] = 0;
                return [0, $availableW];
            }

            $surplusSince = (int)($state['rod_start_surplus_since_ts'] ?? 0);
            $startDelayReached = $startDelayS <= 0
                || ($surplusSince > 0 && (($now - $surplusSince) >= $startDelayS));

            if (!$startDelayReached) return [0, $availableW];
            if (!$canTurnOn) return [0, $availableW];
        } else {
            $state['rod_start_surplus_since_ts'] = 0;
        }

        if ($isOn && !$canTurnOff) {
            $usedW = $this->heatingRodPowerForStageW($CFG, $currentStage);
            return [$currentStage, max(0.0, $availableW - $usedW)];
        }

        $targetStage = min($maxStage, (int)floor($availableW / $powerPerStage));

        if ($isOn) {
            $offThresholdW = max(0.0, $minSurplus - $surplusHyst);
            if ($availableW < $offThresholdW) {
                $targetStage = max(0, $currentStage - 1);
            }
        }

        if ($targetStage <= 0) {
            return [0, $availableW];
        }

        $usedW = $this->heatingRodPowerForStageW($CFG, $targetStage);
        $availableW = max(0.0, $availableW - $usedW);

        return [$targetStage, $availableW];
    }

    private function planWallboxRamped(array $CFG, array $state, float $availableW): array
    {
        if (!($CFG['wallbox']['enabled'] ?? false)) return [false, 0, $state];

        if (!$this->isWallboxCarConnected($CFG)) {
            $state['wb_start_surplus_since_ts'] = 0;
            $state['wb_deficit_since_ts'] = 0;
            return [false, 0, $state];
        }

        $enableVar = (int)($CFG['wallbox']['enable_var'] ?? 0);
        if ($enableVar <= 0) return [false, 0, $state];

        $setA = (int)($CFG['wallbox']['set_current_a_var'] ?? 0);
        if ($setA <= 0) return [false, 0, $state];

        $reserve = (float)($CFG['wallbox']['reserve_w'] ?? 0.0);
        $surplusHystW = max(0.0, (float)($CFG['wallbox']['surplus_hysteresis_w'] ?? 0.0));
        $controlHoldS = max(0, (int)($CFG['wallbox']['control_min_hold_seconds'] ?? 0));

        $isOn = (bool)($state['wb_is_on'] ?? false);
        $wbCurrentPowerW = $isOn ? max(0.0, $this->readPowerToW($CFG['wallbox']['charge_power'])) : 0.0;

        $availableForPhaseDecisionW = max(0.0, $availableW + $wbCurrentPowerW);
        $availableW = max(0.0, $availableW - $reserve);

        $state = $this->ensureWallboxPhaseState($CFG, $state);
        $state = $this->decideAndApplyWallboxPhases($CFG, $state, $availableForPhaseDecisionW);

        $phases = (int)($state['wb_phases'] ?? (int)($CFG['wallbox']['phases_3p'] ?? 3));
        $voltage = (float)($CFG['wallbox']['voltage_v'] ?? 230.0);

        $minA = (int)($CFG['wallbox']['min_a'] ?? 6);
        $maxA = (int)($CFG['wallbox']['max_a'] ?? 16);
        $step = max(1, (int)($CFG['wallbox']['step_a'] ?? 1));

        $rampUp = max(1, (int)($CFG['wallbox']['ramp_up_a_per_loop'] ?? 1));
        $rampDown = max(1, (int)($CFG['wallbox']['ramp_down_a_per_loop'] ?? 2));
        $grace = max(0, (int)($CFG['wallbox']['soft_off_grace_seconds'] ?? 120));

        $lastOn  = (int)($state['wb_last_on_ts'] ?? 0);
        $lastOff = (int)($state['wb_last_off_ts'] ?? 0);
        $now = time();

        $availableControlW = $availableW;
        if ($isOn) {
            $availableControlW += $wbCurrentPowerW;
        }

        $rawA = (int)floor($availableControlW / ($voltage * $phases));
        $targetA = (int)(floor($rawA / $step) * $step);
        $targetA = max(0, min($maxA, $targetA));

        $canTurnOn  = (!$isOn) && (($now - $lastOff) >= (int)($CFG['wallbox']['min_off_seconds'] ?? 120));
        $canTurnOff = ($isOn)  && (($now - $lastOn)  >= (int)($CFG['wallbox']['min_on_seconds'] ?? 180));

        $autoStartMinSurplusW = max(0.0, (float)($CFG['wallbox']['auto_start_min_surplus_w'] ?? 2000.0));
        $autoStartMinDurationS = max(0, (int)($CFG['wallbox']['auto_start_min_duration_seconds'] ?? 900));

        if (!$isOn) {
            if ($availableForPhaseDecisionW >= $autoStartMinSurplusW) {
                if (((int)($state['wb_start_surplus_since_ts'] ?? 0)) <= 0) {
                    $state['wb_start_surplus_since_ts'] = $now;
                }
            } else {
                $state['wb_start_surplus_since_ts'] = 0;
            }
        } else {
            $state['wb_start_surplus_since_ts'] = 0;
        }

        $curA = (int)$this->readVar($setA, 0);
        if (!$isOn) $curA = 0;

        $lastControlChangeTs = (int)($state['wb_last_control_change_ts'] ?? 0);
        $canChangeControl = ($lastControlChangeTs <= 0) ? true : (($now - $lastControlChangeTs) >= $controlHoldS);

        $minPowerW = max(1.0, $voltage * $phases * $minA);
        $availableStartW = $availableW;
        if ($isOn && $availableW > 0.0) {
            $availableStartW += $surplusHystW;
        }

        $curPowerW = $this->wallboxPowerFromA($CFG, $state, $curA);
        $targetPowerW = $this->wallboxPowerFromA($CFG, $state, $targetA);
        if ($isOn && abs($targetPowerW - $curPowerW) < $surplusHystW) {
            $targetA = $curA;
        }

        if ($targetA >= $minA && $availableStartW >= ($minPowerW + $surplusHystW)) {
            if (!$isOn) {
                $surplusSince = (int)($state['wb_start_surplus_since_ts'] ?? 0);
                $surplusDurationReached = $autoStartMinDurationS <= 0
                    || ($surplusSince > 0 && (($now - $surplusSince) >= $autoStartMinDurationS));

                if (!$surplusDurationReached) {
                    return [false, 0, $state];
                }
            }

            if (!$isOn && !$canTurnOn) return [false, 0, $state];

            $desiredA = max($minA, $targetA);
            $newA = $canChangeControl
                ? $this->moveTowardsInt($curA, $desiredA, $rampUp, $rampDown)
                : $curA;
            if ($canChangeControl && $desiredA >= $maxA) {
                $newA = $maxA;
            }

            if ($newA !== $curA) {
                $state['wb_last_control_change_ts'] = $now;
            }

            $state['wb_deficit_since_ts'] = 0;
            return [true, $newA, $state];
        }

        if ($isOn) {
            $defSince = (int)($state['wb_deficit_since_ts'] ?? 0);
            if ($defSince <= 0) {
                $state['wb_deficit_since_ts'] = $now;
                $defSince = $now;
            }

            $newA = $canChangeControl
                ? $this->moveTowardsInt($curA, 0, $rampUp, $rampDown)
                : $curA;

            if ($newA !== $curA) {
                $state['wb_last_control_change_ts'] = $now;
            }

            if ($newA > 0) {
                return [true, $newA, $state];
            }

            if ((($now - $defSince) >= $grace)) {
                return [false, 0, $state];
            }

            return [true, 0, $state];
        }

        $state['wb_deficit_since_ts'] = 0;
        return [false, 0, $state];
    }

    private function ensureWallboxPhaseState(array $CFG, array $state): array
    {
        if (isset($state['wb_phases'])) return $state;

        $varId = (int)($CFG['wallbox']['phase_1p_var'] ?? 0);
        $trueIs1p = (bool)($CFG['wallbox']['phase_true_is_1p'] ?? true);
        $ph1 = (int)($CFG['wallbox']['phases_1p'] ?? 1);
        $ph3 = (int)($CFG['wallbox']['phases_3p'] ?? 3);

        $is1p = false;
        if ($varId > 0 && @IPS_VariableExists($varId)) {
            $cur = (bool)GetValue($varId);
            $is1p = $trueIs1p ? $cur : !$cur;
        }

        $state['wb_phases'] = $is1p ? $ph1 : $ph3;
        $state['wb_phase_last_switch_ts'] = (int)($state['wb_phase_last_switch_ts'] ?? 0);
        return $state;
    }

    private function decideAndApplyWallboxPhases(array $CFG, array $state, float $availableW): array
    {
        $varId = (int)($CFG['wallbox']['phase_1p_var'] ?? 0);
        if ($varId <= 0 || !@IPS_VariableExists($varId)) return $state;

        $trueIs1p = (bool)($CFG['wallbox']['phase_true_is_1p'] ?? true);
        $ph1 = (int)($CFG['wallbox']['phases_1p'] ?? 1);
        $ph3 = (int)($CFG['wallbox']['phases_3p'] ?? 3);

        $minHold = max(0, (int)($CFG['wallbox']['phase_min_hold_seconds'] ?? 300));
        $to3pMinW = max(0.0, (float)($CFG['wallbox']['phase_switch_to_3p_min_w'] ?? 4500.0));
        $to1pMaxW = max(0.0, (float)($CFG['wallbox']['phase_switch_to_1p_max_w'] ?? 3500.0));

        $now = time();
        $last = (int)($state['wb_phase_last_switch_ts'] ?? 0);
        $canSwitch = ($last <= 0) ? true : (($now - $last) >= $minHold);

        $curBool = (bool)GetValue($varId);
        $curIs1p = $trueIs1p ? $curBool : !$curBool;
        $curPh = $curIs1p ? $ph1 : $ph3;

        $desiredPh = $curPh;

        if ($curPh === $ph3) {
            if ($availableW <= $to1pMaxW) $desiredPh = $ph1;
        } else {
            if ($availableW >= $to3pMinW) $desiredPh = $ph3;
        }

        if ($desiredPh !== $curPh && $canSwitch) {
            $desiredIs1p = ($desiredPh === $ph1);
            $desiredBool = $trueIs1p ? $desiredIs1p : !$desiredIs1p;
            $this->safeSetBool($varId, $desiredBool);
            $state['wb_phase_last_switch_ts'] = $now;
            $state['wb_phases'] = $desiredPh;
            return $state;
        }

        $state['wb_phases'] = $curPh;
        return $state;
    }

    private function wallboxSoftOff(array $CFG, array $state): array
    {
        $enableVar = (int)($CFG['wallbox']['enable_var'] ?? 0);
        $setA = (int)($CFG['wallbox']['set_current_a_var'] ?? 0);
        if ($enableVar <= 0 || $setA <= 0) return $state;

        $rampDown = max(1, (int)($CFG['wallbox']['ramp_down_a_per_loop'] ?? 2));
        $grace = max(0, (int)($CFG['wallbox']['soft_off_grace_seconds'] ?? 120));

        $now = time();
        $isOn = (bool)($state['wb_is_on'] ?? false);
        if (!$isOn) {
            $state['wb_soft_on'] = false;
            $state['wb_soft_a'] = 0;
            $state['wb_deficit_since_ts'] = 0;
            return $state;
        }

        $defSince = (int)($state['wb_deficit_since_ts'] ?? 0);
        if ($defSince <= 0) {
            $state['wb_deficit_since_ts'] = $now;
            $defSince = $now;
        }

        $curA = (int)$this->readVar($setA, 0);
        $newA = max(0, $curA - $rampDown);

        if ($newA > 0) {
            $state['wb_soft_on'] = true;
            $state['wb_soft_a'] = $newA;
            return $state;
        }

        if ((($now - $defSince) >= $grace)) {
            $state['wb_soft_on'] = false;
            $state['wb_soft_a'] = 0;
            return $state;
        }

        $state['wb_soft_on'] = true;
        $state['wb_soft_a'] = 0;
        return $state;
    }

    private function moveTowardsInt(int $current, int $target, int $upStep, int $downStep): int
    {
        if ($target > $current) return min($target, $current + $upStep);
        if ($target < $current) return max($target, $current - $downStep);
        return $current;
    }

    private function batteryChargeAssistForWallboxW(array $CFG, float $soc, float $battPowerW): float
    {
        return 0.0;
    }

    private function batteryDischargePenaltyForWallboxW(array $CFG, float $battPowerW): float
    {
        $dischargeW = max(0.0, -$battPowerW);
        return $dischargeW;
    }

    private function wallboxPowerFromA(array $CFG, array $state, int $amps): float
    {
        $phases = (int)($state['wb_phases'] ?? (int)($CFG['wallbox']['phases_3p'] ?? 3));
        $voltage = (float)($CFG['wallbox']['voltage_v'] ?? 230.0);
        return max(0.0, (float)$amps) * $voltage * $phases;
    }

    private function readManualWallboxConfig(array $CFG): array
    {
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));
        $cWb = $this->ensureCategoryByIdent($root, 'pv_ui_wb', 'Wallbox');

        $active = (bool)$this->readVarByIdent($cWb, 'pv_manual_wb_enable', false);
        $defaultPowerKw = $this->normalizeManualPowerToKw(((float)($CFG['wallbox']['manual']['default_power_w'] ?? 4200.0)) / 1000.0);
        $powerKw = $this->normalizeManualPowerToKw((float)$this->readVarByIdent($cWb, 'pv_manual_wb_power_w', $defaultPowerKw));
        $targetSoc = (float)$this->readVarByIdent($cWb, 'pv_manual_wb_target_soc', (float)($CFG['wallbox']['manual']['default_target_soc'] ?? 80.0));

        $carSocVar = (int)($CFG['wallbox']['manual']['car_soc_var'] ?? 0);
        $carSoc = ($carSocVar > 0) ? (float)$this->readVar($carSocVar, 0.0) : 0.0;

        return [
            $active,
            (int)round($powerKw * 1000.0),
            max(0.0, min(100.0, $targetSoc)),
            max(0.0, min(100.0, $carSoc)),
        ];
    }

    private function planWallboxManualPower(array $CFG, array $state, int $manualPowerW): array
    {
        if (!($CFG['wallbox']['enabled'] ?? false)) return [false, 0, $state];

        if (!$this->isWallboxCarConnected($CFG)) {
            return [false, 0, $state];
        }

        $enableVar = (int)($CFG['wallbox']['enable_var'] ?? 0);
        $setA = (int)($CFG['wallbox']['set_current_a_var'] ?? 0);
        if ($enableVar <= 0 || $setA <= 0) return [false, 0, $state];

        $state = $this->ensureWallboxPhaseState($CFG, $state);
        $state = $this->decideAndApplyWallboxPhases($CFG, $state, (float)$manualPowerW);

        $phases = (int)($state['wb_phases'] ?? (int)($CFG['wallbox']['phases_3p'] ?? 3));
        $voltage = (float)($CFG['wallbox']['voltage_v'] ?? 230.0);

        $minA = (int)($CFG['wallbox']['min_a'] ?? 6);
        $maxA = (int)($CFG['wallbox']['max_a'] ?? 16);
        $step = max(1, (int)($CFG['wallbox']['step_a'] ?? 1));
        $rampUp = max(1, (int)($CFG['wallbox']['ramp_up_a_per_loop'] ?? 1));
        $rampDown = max(1, (int)($CFG['wallbox']['ramp_down_a_per_loop'] ?? 1));

        $rawA = (int)floor(((float)$manualPowerW) / max(1.0, $voltage * $phases));
        $targetA = (int)(floor($rawA / $step) * $step);
        $targetA = max($minA, min($maxA, $targetA));

        $now = time();
        $isOn = (bool)($state['wb_is_on'] ?? false);
        $lastOn = (int)($state['wb_last_on_ts'] ?? 0);
        $lastOff = (int)($state['wb_last_off_ts'] ?? 0);

        $canTurnOn = (!$isOn) && (($now - $lastOff) >= (int)($CFG['wallbox']['min_off_seconds'] ?? 120));
        $canTurnOff = ($isOn) && (($now - $lastOn) >= (int)($CFG['wallbox']['min_on_seconds'] ?? 180));

        if ($manualPowerW <= 0) {
            if (!$isOn || $canTurnOff) return [false, 0, $state];
            return [true, 0, $state];
        }

        if (!$isOn && !$canTurnOn) return [false, 0, $state];

        $curA = (int)$this->readVar($setA, 0);
        if (!$isOn) $curA = 0;

        $newA = $this->moveTowardsInt($curA, $targetA, $rampUp, $rampDown);
        return [true, max($minA, $newA), $state];
    }

    private function applyHeatpump(array $CFG, array &$state, bool $on): void
    {
        $varId = (int)($CFG['heatpump']['force_run_var'] ?? 0);
        if ($varId <= 0) return;

        $now = time();
        $isOn = (bool)($state['hp_is_on'] ?? false);

        if ($on && !$isOn) {
            $this->safeSetBool($varId, true);
            $state['hp_is_on'] = true;
            $state['hp_last_on_ts'] = $now;
        } elseif (!$on && $isOn) {
            $this->safeSetBool($varId, false);
            $state['hp_is_on'] = false;
            $state['hp_last_off_ts'] = $now;
        }
    }

    private function isWallboxCarConnected(array $CFG): bool
    {
        $varId = (int)($CFG['wallbox']['car_connected_var'] ?? 0);
        if ($varId <= 0 || !@IPS_VariableExists($varId)) {
            return true;
        }

        $var = @IPS_GetVariable($varId);
        $varType = (int)($var['VariableType'] ?? 0);
        if ($varType !== VARIABLETYPE_INTEGER) {
            $this->SendDebug('WallboxCarConnected', 'Variable ist nicht vom Typ Integer (Status erwartet).', 0);
            return false;
        }

        $status = (int)$this->readVar($varId, 0);
        return $status > 1;
    }

    private function applyHeatingRodStage(array $CFG, array &$state, int $stage): void
    {
        $vars = array_values(array_filter((array)($CFG['heating_rod']['switch_vars'] ?? []), function ($id) {
            return (int)$id > 0;
        }));
        if (count($vars) === 0) return;

        $targetStage = max(0, min(count($vars), $stage));
        $now = time();
        $isOn = (bool)($state['rod_is_on'] ?? false);
        $currentStage = max(0, min(count($vars), (int)($state['rod_stage'] ?? 0)));

        foreach ($vars as $idx => $varId) {
            $this->safeSetBool((int)$varId, $idx < $targetStage);
        }

        $state['rod_stage'] = $targetStage;
        $isNowOn = $targetStage > 0;

        if ($isNowOn && !$isOn) {
            $state['rod_is_on'] = true;
            $state['rod_last_on_ts'] = $now;
            return;
        }

        if (!$isNowOn && $isOn) {
            $state['rod_is_on'] = false;
            $state['rod_last_off_ts'] = $now;
            return;
        }

        if ($isNowOn && $isOn && $targetStage > $currentStage) {
            $state['rod_last_on_ts'] = $now;
        }
    }

    private function heatingRodTotalPowerW(array $CFG): float
    {
        return $this->heatingRodPowerForStageW($CFG, $this->maxHeatingRodStage($CFG));
    }

    private function readHeatingRodAutoMode(array $CFG): bool
    {
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');
        return (bool)$this->readVarByIdent($cHeat, 'pv_rod_auto_mode', true);
    }

    private function readHeatingRodManualOn(array $CFG): bool
    {
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');
        return (bool)$this->readVarByIdent($cHeat, 'pv_manual_rod_on', false);
    }

    private function readHeatingRodDaysSinceTargetReached(array $CFG): int
    {
        $default = max(1, min(20, (int)($CFG['heating_rod']['weekly']['days_after_target_reached'] ?? 7)));
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');
        return max(1, min(20, (int)$this->readVarByIdent($cHeat, 'pv_rod_days_since_target', $default)));
    }

    private function readBoilerRodTargetC(array $CFG): float
    {
        $default = 60.0;
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');
        return max(0.0, min(90.0, (float)$this->readVarByIdent($cHeat, 'pv_boiler_target_c', $default)));
    }

    private function applyWallbox(array $CFG, array &$state, bool $on, int $amps): void
    {
        $enVar = (int)($CFG['wallbox']['enable_var'] ?? 0);
        $aVar  = (int)($CFG['wallbox']['set_current_a_var'] ?? 0);

        if ($enVar <= 0 || !@IPS_VariableExists($enVar)) return;

        $now = time();
        $isOn = (bool)($state['wb_is_on'] ?? false);

        $amps = max(0, (int)$amps);

        if ($on && !$isOn) {
            if ($aVar > 0) $this->safeSetInt($aVar, $amps);
            $this->safeSetBool($enVar, true);
            $state['wb_is_on'] = true;
            $state['wb_last_on_ts'] = $now;
            return;
        }

        if (!$on && $isOn) {
            $this->safeSetBool($enVar, false);
            if ($aVar > 0) $this->safeSetInt($aVar, 0);
            $state['wb_is_on'] = false;
            $state['wb_last_off_ts'] = $now;
            return;
        }

        if ($on && $isOn) {
            $this->safeSetBool($enVar, true);
            if ($aVar > 0) $this->safeSetInt($aVar, $amps);
        }

        if (!$on && !$isOn) {
            $this->safeSetBool($enVar, false);
            if ($aVar > 0) $this->safeSetInt($aVar, 0);
        }
    }

    private function ensureUiStructure(array $CFG): void
    {
        $this->ensureProfileFloat('PV_W', ' W', 0, 0.0, 0.0, 1.0);
        $this->ensureProfileFloat('PV_kW', ' kW', 2, 0.0, 0.0, 0.01);
        $this->ensureProfileFloat('PV_kWI', ' kW', 0, 2.0, 11.0, 1.0);
        $this->ensureProfileFloat('PV_PCT', ' %', 0, 0.0, 100.0, 1.0);
        $this->ensureProfileInt('PV_A', ' A', 0, 0, 0, 1);
        $this->ensureProfileInt('PV_WI', ' W', 0, 0, 22000, 100);
        $this->ensureProfileInt('PV_DAYS_1_20', ' Tage', 0, 1, 20, 1);
        $this->ensureProfileFloat('PV_DAYS_F', ' Tage', 2, 0.0, 0.0, 0.01);
        $this->ensureProfileInt('PV_STAGE', '', 0, 0, 3, 1);

        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));

        $cProd = $this->ensureCategoryByIdent($root, 'pv_ui_prod', 'PV Produktion');
        $cSurp = $this->ensureCategoryByIdent($root, 'pv_ui_surp', 'PV Überschuss');
        $cLoad = $this->ensureCategoryByIdent($root, 'pv_ui_load', 'Gebäude Verbrauch');
        $cWb   = $this->ensureCategoryByIdent($root, 'pv_ui_wb', 'Wallbox');
        $cBat  = $this->ensureCategoryByIdent($root, 'pv_ui_bat', 'Batterieladezustand');
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');

        $this->ensureVariableByIdent($cProd, 'pv_prod_total_kw', 'Produktion gesamt', 2, 'PV_kW');
        $this->ensureVariableByIdent($cProd, 'pv_prod_pv1_kw', 'PV1', 2, 'PV_kW');
        $this->ensureVariableByIdent($cProd, 'pv_prod_pv2_kw', 'PV2', 2, 'PV_kW');

        $this->ensureVariableByIdent($cSurp, 'pv_grid_kw_raw', 'Netz (raw)', 2, 'PV_kW');
        $this->ensureVariableByIdent($cSurp, 'pv_grid_kw_filt', 'Netz (gefiltert)', 2, 'PV_kW');
        $this->ensureVariableByIdent($cSurp, 'pv_grid_kw_filt_inv', 'Netz (gefiltert, invertiert)', 2, 'PV_kW');
        $this->ensureVariableByIdent($cSurp, 'pv_import_kw', 'Bezug', 2, 'PV_kW');
        $this->ensureVariableByIdent($cSurp, 'pv_export_kw', 'Einspeisung', 2, 'PV_kW');
        $this->ensureVariableByIdent($cSurp, 'pv_rest_kw', 'Rest-Überschuss (Soll ~0)', 2, 'PV_kW');

        $this->ensureVariableByIdent($cLoad, 'pv_load_kw', 'Gebäudelast', 2, 'PV_kW');
        $this->ensureVariableByIdent($cLoad, 'pv_house_load_kw', 'Hausverbrauch (ohne WB/WP/Batt)', 2, 'PV_kW');
        $this->ensureVariableByIdent($cLoad, 'pv_boiler_temp', 'Boiler Temperatur', 2, '~Temperature');
        $this->ensureActionVariableByIdent($cHeat, 'pv_boiler_target_c', 'Boiler Solltemperatur', 2, '~Temperature');

        $this->ensureVariableByIdent($cWb, 'pv_wb_power_kw', 'Leistung Ist', 2, 'PV_kW');
        $this->removeVariableByIdent($cWb, 'pv_wb_enabled');
        $this->ensureVariableByIdent($cWb, 'pv_wb_target_a', 'Sollstrom', 1, 'PV_A');
        $this->ensureActionVariableByIdent($cWb, 'pv_manual_wb_enable', 'Manuell laden aktiv', 0, '~Switch');
        $this->migrateManualPowerVarToKw($cWb);
        $this->ensureActionVariableByIdent($cWb, 'pv_manual_wb_power_w', 'Manuelle Ladeleistung', 2, 'PV_kWI');
        $this->ensureActionVariableByIdent($cWb, 'pv_manual_wb_target_soc', 'Manuelles Ziel-SOC', 2, 'PV_PCT');
        $this->ensureVariableByIdent($cWb, 'pv_manual_wb_car_soc', 'Auto SOC (Ist)', 2, 'PV_PCT');
        $this->ensureVariableByIdent($cWb, 'pv_wb_car_connected', 'Fahrzeug angesteckt', 0, '~Switch');

        $this->ensureVariableByIdent($cBat, 'pv_soc', 'SOC', 2, 'PV_PCT');

        $this->moveObjectByIdent($root, $cHeat, 'pv_rod_auto_mode');
        $this->moveObjectByIdent($root, $cHeat, 'pv_manual_rod_on');
        $this->moveObjectByIdent($root, $cHeat, 'pv_rod_days_since_target');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod_days_since_target_actual');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod_last_target_status');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_weekly_active');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_hp_on');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod_on');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod_stage');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod1_on');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod2_on');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod3_on');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_hp_running');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_hp_power_kw');

        $this->ensureActionVariableByIdent($cHeat, 'pv_rod_auto_mode', 'Heizstab Automatik', 0, '~Switch');
        $this->ensureActionVariableByIdent($cHeat, 'pv_manual_rod_on', 'Heizstab Manuell EIN', 0, '~Switch');
        $daysId = @IPS_GetObjectIDByIdent('pv_rod_days_since_target', $cHeat);
        $hadDaysVar = ($daysId !== false);
        $daysId = $this->ensureActionVariableByIdent($cHeat, 'pv_rod_days_since_target', 'Tage seit letzter Solltemperatur', 1, 'PV_DAYS_1_20');
        if (!$hadDaysVar) {
            SetValue((int)$daysId, max(1, min(20, (int)$CFG['heating_rod']['weekly']['days_after_target_reached'])));
        }
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod_days_since_target_actual', 'Vergangene Tage seit letzter Solltemperatur', 2, 'PV_DAYS_F');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod_last_target_status', 'Status letzte Solltemperatur', 3, '');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_weekly_active', 'Weekly Heizstab aktiv', 0, '~Switch');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_hp_on', 'Wärmepumpe an', 0, '~Switch');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod_on', 'Heizstab aktiv', 0, '~Switch');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod_stage', 'Heizstab Stufe', 1, 'PV_STAGE');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod1_on', 'Heizstab 1 aktiv', 0, '~Switch');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod2_on', 'Heizstab 2 aktiv', 0, '~Switch');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod3_on', 'Heizstab 3 aktiv', 0, '~Switch');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_hp_running', 'WP läuft (Ist)', 0, '~Switch');
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_hp_power_kw', 'WP Leistung (Ist)', 2, 'PV_kW');

        $this->ensureVariableByIdent($root, 'pv_dbg_wb_on', 'Wallbox aktiv', 0, '~Switch');
        $this->ensureVariableByIdent($root, 'pv_dbg_remaining_kw', 'Rest-Überschuss vor WB', 2, 'PV_kW');
        $this->ensureVariableByIdent($root, 'pv_decision_text', 'Aktuelle Entscheidung', 3, '');
        $this->ensureVariableByIdent($root, 'pv_forecast_text', 'Nächste Tendenz', 3, '');
        $this->ensureVariableByIdent($root, 'pv_decision_details_text', 'Regelungsdetails', 3, '');
        $this->ensureVariableByIdent($root, 'pv_debug_json_export', 'Debug JSON Export', 3, '');
    }

    private function updateUiVars(array $CFG, array $v): void
    {
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));

        $cProd = $this->ensureCategoryByIdent($root, 'pv_ui_prod', 'PV Produktion');
        $cSurp = $this->ensureCategoryByIdent($root, 'pv_ui_surp', 'PV Überschuss');
        $cLoad = $this->ensureCategoryByIdent($root, 'pv_ui_load', 'Gebäude Verbrauch');
        $cWb   = $this->ensureCategoryByIdent($root, 'pv_ui_wb', 'Wallbox');
        $cBat  = $this->ensureCategoryByIdent($root, 'pv_ui_bat', 'Batterieladezustand');
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');

        if (isset($v['pvTotalW'])) $this->setVarByIdent($cProd, 'pv_prod_total_kw', $this->wToKw((float)$v['pvTotalW']));
        if (isset($v['pv1W']))     $this->setVarByIdent($cProd, 'pv_prod_pv1_kw', $this->wToKw((float)$v['pv1W']));
        if (isset($v['pv2W']))     $this->setVarByIdent($cProd, 'pv_prod_pv2_kw', $this->wToKw((float)$v['pv2W']));

        if (isset($v['gridW_raw'])) $this->setVarByIdent($cSurp, 'pv_grid_kw_raw', $this->wToKw((float)$v['gridW_raw']));
        if (isset($v['gridW']))     $this->setVarByIdent($cSurp, 'pv_grid_kw_filt', $this->wToKw((float)$v['gridW']));
        if (isset($v['gridW']))     $this->setVarByIdent($cSurp, 'pv_grid_kw_filt_inv', $this->wToKw(-(float)$v['gridW']));
        if (isset($v['importW']))   $this->setVarByIdent($cSurp, 'pv_import_kw', $this->wToKw((float)$v['importW']));
        if (isset($v['exportW']))   $this->setVarByIdent($cSurp, 'pv_export_kw', $this->wToKw((float)$v['exportW']));
        if (isset($v['restSurplusW'])) $this->setVarByIdent($cSurp, 'pv_rest_kw', $this->wToKw((float)$v['restSurplusW']));

        if (isset($v['buildingLoadW'])) $this->setVarByIdent($cLoad, 'pv_load_kw', $this->wToKw((float)$v['buildingLoadW']));
        if (isset($v['houseLoadW']))    $this->setVarByIdent($cLoad, 'pv_house_load_kw', $this->wToKw((float)$v['houseLoadW']));
        if (isset($v['boilerTemp']))    $this->setVarByIdent($cLoad, 'pv_boiler_temp', (float)$v['boilerTemp']);
        if (isset($v['boilerTargetC'])) $this->setVarByIdent($cHeat, 'pv_boiler_target_c', (float)$v['boilerTargetC']);
        if (isset($v['rodDaysSinceTarget'])) $this->setVarByIdent($cHeat, 'pv_rod_days_since_target', max(1, min(20, (int)$v['rodDaysSinceTarget'])));
        if (isset($v['rodDaysSinceTargetActual'])) $this->setVarByIdent($cHeat, 'pv_dbg_rod_days_since_target_actual', max(0.0, (float)$v['rodDaysSinceTargetActual']));
        if (isset($v['rodLastTargetStatus'])) $this->setVarByIdent($cHeat, 'pv_dbg_rod_last_target_status', (string)$v['rodLastTargetStatus']);

        if (isset($v['wallboxChargeW'])) $this->setVarByIdent($cWb, 'pv_wb_power_kw', $this->wToKw((float)$v['wallboxChargeW']));
        if (isset($v['wbA'])) $this->setVarByIdent($cWb, 'pv_wb_target_a', (int)$v['wbA']);
        if (isset($v['manualActive'])) $this->setVarByIdent($cWb, 'pv_manual_wb_enable', ((int)$v['manualActive']) === 1);
        if (isset($v['manualPowerW'])) $this->setVarByIdent($cWb, 'pv_manual_wb_power_w', round(((float)$v['manualPowerW']) / 1000.0));
        if (isset($v['manualTargetSoc'])) $this->setVarByIdent($cWb, 'pv_manual_wb_target_soc', (float)$v['manualTargetSoc']);
        if (isset($v['manualCarSoc'])) $this->setVarByIdent($cWb, 'pv_manual_wb_car_soc', (float)$v['manualCarSoc']);
        if (isset($v['carConnected'])) $this->setVarByIdent($cWb, 'pv_wb_car_connected', ((int)$v['carConnected']) === 1);

        if (isset($v['soc'])) $this->setVarByIdent($cBat, 'pv_soc', (float)$v['soc']);

        if (isset($v['rodAutoMode'])) $this->setVarByIdent($cHeat, 'pv_rod_auto_mode', ((int)$v['rodAutoMode']) === 1);
        if (isset($v['manualRodOn'])) $this->setVarByIdent($cHeat, 'pv_manual_rod_on', ((int)$v['manualRodOn']) === 1);
        if (isset($v['weeklyRodActive'])) $this->setVarByIdent($cHeat, 'pv_dbg_weekly_active', ((int)$v['weeklyRodActive']) === 1);
        if (isset($v['hpOn']))            $this->setVarByIdent($cHeat, 'pv_dbg_hp_on', ((int)$v['hpOn']) === 1);
        if (isset($v['rodOn']))           $this->setVarByIdent($cHeat, 'pv_dbg_rod_on', ((int)$v['rodOn']) === 1);
        if (isset($v['rodStage']))        $this->setVarByIdent($cHeat, 'pv_dbg_rod_stage', max(0, min(3, (int)$v['rodStage'])));
        if (isset($v['rodStage']))        $this->setVarByIdent($cHeat, 'pv_dbg_rod1_on', ((int)$v['rodStage']) >= 1);
        if (isset($v['rodStage']))        $this->setVarByIdent($cHeat, 'pv_dbg_rod2_on', ((int)$v['rodStage']) >= 2);
        if (isset($v['rodStage']))        $this->setVarByIdent($cHeat, 'pv_dbg_rod3_on', ((int)$v['rodStage']) >= 3);
        if (isset($v['wbOn']))            $this->setVarByIdent($root, 'pv_dbg_wb_on', ((int)$v['wbOn']) === 1);
        if (isset($v['remainingW']))      $this->setVarByIdent($root, 'pv_dbg_remaining_kw', $this->wToKw((float)$v['remainingW']));

        if (isset($v['hpRunning'])) $this->setVarByIdent($cHeat, 'pv_dbg_hp_running', ((int)$v['hpRunning']) === 1);
        if (isset($v['hpPowerW']))  $this->setVarByIdent($cHeat, 'pv_dbg_hp_power_kw', $this->wToKw((float)$v['hpPowerW']));
        if (isset($v['decisionText'])) $this->setVarByIdent($root, 'pv_decision_text', (string)$v['decisionText']);
        if (isset($v['forecastText'])) $this->setVarByIdent($root, 'pv_forecast_text', (string)$v['forecastText']);
        if (isset($v['detailsText'])) $this->setVarByIdent($root, 'pv_decision_details_text', (string)$v['detailsText']);
    }

    private function buildDecisionTexts(array $ctx): array
    {
        $mode = (string)($ctx['mode'] ?? 'auto');
        $carConnected = (bool)($ctx['carConnected'] ?? true);
        $importW = max(0.0, (float)($ctx['importW'] ?? 0.0));
        $batteryPowerW = (float)($ctx['batteryPowerW'] ?? 0.0);
        $batteryDischargeW = max(0.0, -$batteryPowerW);
        $gridBatteryHint = sprintf(
            '• Netz/Akku-Schutz: Netzbezug %.2f kW, Akku-Entladung %.2f kW. Wallbox nutzt nur echten PV-Überschuss.',
            $importW / 1000.0,
            $batteryDischargeW / 1000.0
        );

        $hpOn = (bool)($ctx['hpOn'] ?? false);
        $rodStage = max(0, (int)($ctx['rodStage'] ?? 0));
        $wbOn = (bool)($ctx['wbOn'] ?? false);
        $wbA = max(0, (int)($ctx['wbA'] ?? 0));
        $remainingKw = round(((float)($ctx['remainingW'] ?? 0.0)) / 1000.0, 2);
        $restSurplusKw = round(((float)($ctx['restSurplusW'] ?? $ctx['remainingW'] ?? 0.0)) / 1000.0, 2);

        if ($mode === 'manual_wb') {
            if (!$carConnected) {
                return [
                    'Manuell aktiv, aber kein Fahrzeug angesteckt: Wallbox bleibt AUS.',
                    'Wenn ein Fahrzeug angesteckt wird, kann die manuelle Ladung starten.',
                ];
            }

            $powerKw = round(max(0.0, ((float)($ctx['manualPowerW'] ?? 0.0)) / 1000.0), 1);
            $targetSoc = (float)($ctx['manualTargetSoc'] ?? 0.0);
            $carSoc = (float)($ctx['carSoc'] ?? 0.0);

            if (!$carConnected) {
                $decision = 'Manuell aktiv, aber kein Fahrzeug angesteckt: Wallbox bleibt AUS.';
                $forecast = 'Wenn ein Fahrzeug angesteckt wird, kann die manuelle Ladung starten.';
            } else {
                $decision = sprintf('Manuell: Wallbox lädt mit %.1f kW (SOC %.0f%%/%.0f%%).', $powerKw, $carSoc, $targetSoc);
                $forecast = $carSoc >= $targetSoc
                    ? 'Wenn manuell aktiv bleibt, stoppt Laden bei Ziel-SOC.'
                    : 'Wenn Werte stabil bleiben, lädt die Wallbox bis zum Ziel-SOC weiter.';
            }

            $details = implode("\n", [
                'Regelstatus (manuell):',
                '• Wärmepumpe: AUS (manuell WB hat Vorrang).',
                '• Heizstab: AUS (manuell WB hat Vorrang).',
                $carConnected
                    ? sprintf('• Wallbox: %s mit %d A (Soll %.1f kW).', $wbOn ? 'EIN' : 'AUS', $wbA, $powerKw)
                    : '• Wallbox: AUS (kein Fahrzeug angesteckt).',
            ]);

            return [$decision, $forecast, $details];
        }

        if ($mode === 'import_limit') {
            $importKw = round(((float)($ctx['importW'] ?? 0.0)) / 1000.0, 2);
            $limitKw = round(((float)($ctx['maxImportW'] ?? 0.0)) / 1000.0, 2);
            $decision = sprintf('Netzbezug zu hoch (%.2f kW > %.2f kW): Verbraucher werden reduziert.', $importKw, $limitKw);
            $forecast = $carConnected
                ? 'Wenn Bezug sinkt, schaltet die Automatik Wallbox/WP/Heizstab wieder stufenweise zu.'
                : 'Wenn Bezug sinkt, schaltet die Automatik WP/Heizstab wieder stufenweise zu (Wallbox bleibt ohne Fahrzeug außen vor).';

            $details = implode("\n", [
                'Regelstatus (Importbegrenzung):',
                sprintf('• Wärmepumpe: %s.', $hpOn ? 'EIN' : 'AUS'),
                sprintf('• Heizstab: %s.', $rodStage > 0 ? ('Stufe ' . $rodStage) : 'AUS'),
                $carConnected
                    ? sprintf('• Wallbox: %s%s.', $wbOn ? 'Soft-Modus aktiv' : 'AUS', $wbOn ? (' (' . $wbA . ' A)') : '')
                    : '• Wallbox: AUS (kein Fahrzeug angesteckt).',
            ]);

            return [$decision, $forecast, $details];
        }

        if ($mode === 'low_surplus') {
            $exportKw = round(((float)($ctx['exportW'] ?? 0.0)) / 1000.0, 2);
            $deadbandKw = round(((float)($ctx['deadbandW'] ?? 0.0)) / 1000.0, 2);
            $decision = $carConnected
                ? sprintf('Zu wenig Überschuss (%.2f kW < %.2f kW): WP/Heizstab aus, Wallbox nur sanft.', $exportKw, $deadbandKw)
                : sprintf('Zu wenig Überschuss (%.2f kW < %.2f kW): WP/Heizstab aus, Wallbox ohne Fahrzeug deaktiviert.', $exportKw, $deadbandKw);

            $forecast = !$carConnected
                ? 'Kein Fahrzeug angesteckt: Bei steigendem Überschuss bleiben Wallbox-Entscheidungen deaktiviert.'
                : ($wbA > 0
                    ? 'Wenn Überschuss weiter fällt, regelt die Wallbox weiter herunter bis AUS.'
                    : 'Wenn Überschuss steigt, startet zuerst die Wallbox wieder sanft.');

            $details = implode("\n", [
                'Regelstatus (niedriger Überschuss):',
                '• Wärmepumpe: AUS.',
                '• Heizstab: AUS.',
                $carConnected
                    ? sprintf('• Wallbox: %s%s.', $wbOn ? 'EIN' : 'AUS', $wbOn ? (' (' . $wbA . ' A, sanfte Regelung)') : '')
                    : '• Wallbox: AUS (kein Fahrzeug angesteckt).',
                $gridBatteryHint,
            ]);

            return [$decision, $forecast, $details];
        }

        $parts = [];
        $parts[] = $hpOn ? 'WP EIN' : 'WP AUS';
        $parts[] = $rodStage > 0 ? ('Heizstab Stufe ' . $rodStage) : 'Heizstab AUS';
        $parts[] = $wbOn ? 'Wallbox EIN' : 'Wallbox AUS';

        $decision = 'Auto: ' . implode(', ', $parts) . '.';
        $forecast = $restSurplusKw > 0.5
            ? ($carConnected
                ? sprintf('Bei stabilen Werten ist als Nächstes mehr Wallbox-Leistung möglich (Rest %.2f kW).', $restSurplusKw)
                : sprintf('Bei stabilen Werten bleibt die Wallbox ohne Fahrzeug außen vor (Rest %.2f kW).', $restSurplusKw))
            : sprintf('Bei stabilen Werten bleibt die Regelung etwa so (Rest %.2f kW).', $restSurplusKw);

        $details = implode("\n", [
            'Regelstatus (automatisch):',
            sprintf('• Wärmepumpe: %s.', $hpOn ? 'EIN' : 'AUS'),
            sprintf('• Heizstab: %s.', $rodStage > 0 ? ('Stufe ' . $rodStage) : 'AUS'),
            $carConnected
                ? sprintf('• Wallbox: %s%s.', $wbOn ? 'EIN' : 'AUS', $wbOn ? (' (' . $wbA . ' A)') : '')
                : '• Wallbox: AUS (kein Fahrzeug angesteckt).',
            sprintf('• Rest-Überschuss nach Planung: %.2f kW.', $restSurplusKw),
            $gridBatteryHint,
        ]);

        return [$decision, $forecast, $details];
    }

    private function readPowerToW(array $src): float
    {
        $id = (int)($src['id'] ?? 0);
        $unit = (string)($src['unit'] ?? 'W');
        $v = (float)$this->readVar($id, 0.0);
        if ($unit === 'kW' || $unit === 'kw') return $v * 1000.0;
        return $v;
    }

    private function readPercent(array $src): float
    {
        $id = (int)($src['id'] ?? 0);
        if ($id <= 0) return 0.0;
        $v = (float)$this->readVar($id, 0.0);
        return max(0.0, min(100.0, $v));
    }

    private function heatpumpPowerW(array $CFG): float
    {
        if (isset($CFG['heatpump']['power_in']) && is_array($CFG['heatpump']['power_in'])) {
            $w = $this->readPowerToW($CFG['heatpump']['power_in']);
            if ($w > 50.0) return $w;
        }
        return (float)($CFG['heatpump']['assumed_power_w'] ?? 0.0);
    }

    private function wToKw(float $w): float
    {
        return $w / 1000.0;
    }

    private function isHeatingRodWindow(array $weeklyCfg, array $state): bool
    {
        if (!($weeklyCfg['enabled'] ?? false)) return false;

        $days = max(1, min(20, (int)($weeklyCfg['days_after_target_reached'] ?? 7)));
        $start = (string)($weeklyCfg['start_hhmm'] ?? '10:00');
        $end   = (string)($weeklyCfg['end_hhmm'] ?? '16:00');

        $now = time();
        $elapsedDays = $this->daysSinceLastTargetReached($state);
        if ($elapsedDays < $days) return false;

        $mins = ((int)date('H', $now) * 60) + (int)date('i', $now);

        [$sh, $sm] = $this->parseHHMM($start);
        [$eh, $em] = $this->parseHHMM($end);

        $startM = $sh * 60 + $sm;
        $endM   = $eh * 60 + $em;

        if ($endM <= $startM) return ($mins >= $startM) || ($mins <= $endM);
        return ($mins >= $startM) && ($mins <= $endM);
    }

    private function daysSinceLastTargetReached(array $state): float
    {
        $lastReached = (int)($state['rod_last_target_reached_ts'] ?? 0);
        if ($lastReached <= 0) {
            return 9999.0;
        }

        return max(0.0, (time() - $lastReached) / 86400.0);
    }

    private function buildRodLastTargetUiState(array $state): array
    {
        $lastReached = (int)($state['rod_last_target_reached_ts'] ?? 0);
        if ($lastReached <= 0) {
            return [0.0, 'Noch nie erreicht'];
        }

        $days = max(0.0, (time() - $lastReached) / 86400.0);
        return [$days, 'Zuletzt erreicht: ' . date('d.m.Y H:i', $lastReached)];
    }

    private function maxHeatingRodStage(array $CFG): int
    {
        $vars = array_values(array_filter((array)($CFG['heating_rod']['switch_vars'] ?? []), function ($id) {
            return (int)$id > 0;
        }));
        return max(0, min(3, count($vars)));
    }

    private function heatingRodPowerForStageW(array $CFG, int $stage): float
    {
        $stage = max(0, min($this->maxHeatingRodStage($CFG), $stage));
        return max(0.0, (float)($CFG['heating_rod']['power_per_unit_w'] ?? 0.0)) * $stage;
    }

    private function parseHHMM(string $hhmm): array
    {
        $p = explode(':', $hhmm, 2);
        $h = isset($p[0]) ? (int)$p[0] : 0;
        $m = isset($p[1]) ? (int)$p[1] : 0;
        $h = max(0, min(23, $h));
        $m = max(0, min(59, $m));
        return [$h, $m];
    }

    private function lowpass(float $x, float $alpha, float $prev): float
    {
        $alpha = max(0.0, min(1.0, $alpha));
        return ($alpha * $x) + ((1.0 - $alpha) * $prev);
    }

    private function ensureTimer(int $seconds): void
    {
        $seconds = max(5, min(300, $seconds));
        $this->SetTimerInterval('Loop', $seconds * 1000);
    }

    private function loadState(): array
    {
        $raw = (string)$this->GetBuffer('PV_STATE_JSON');
        if ($raw === '') return [];
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }

    private function saveState(array $state): void
    {
        $this->SetBuffer('PV_STATE_JSON', json_encode($state, JSON_UNESCAPED_UNICODE));
    }
    private function readVar(int $varId, $default)
    {
        if ($varId <= 0) return $default;
        if (!@IPS_VariableExists($varId)) return $default;
        return GetValue($varId);
    }

    private function safeSetBool(int $varId, bool $v): void
    {
        if ($varId <= 0) return;
        if (!@IPS_VariableExists($varId)) return;
        if ((bool)GetValue($varId) !== $v) RequestAction($varId, $v);
    }

    private function safeSetInt(int $varId, int $v): void
    {
        if ($varId <= 0) return;
        if (!@IPS_VariableExists($varId)) return;
        if ((int)GetValue($varId) !== $v) RequestAction($varId, $v);
    }

    private function ensureCategoryByIdent(int $parentId, string $ident, string $name): int
    {
        $id = @IPS_GetObjectIDByIdent($ident, $parentId);
        if ($id === false) {
            $id = IPS_CreateCategory();
            IPS_SetParent($id, $parentId);
            IPS_SetIdent($id, $ident);
            IPS_SetName($id, $name);
        } else {
            IPS_SetName($id, $name);
        }
        return (int)$id;
    }

    private function ensureVariableByIdent(int $parentId, string $ident, string $name, int $type, string $profile): int
    {
        $id = @IPS_GetObjectIDByIdent($ident, $parentId);
        if ($id === false) {
            $id = IPS_CreateVariable($type);
            IPS_SetParent($id, $parentId);
            IPS_SetIdent($id, $ident);
            IPS_SetName($id, $name);
        } else {
            IPS_SetName($id, $name);
        }
        if ($profile !== '') @IPS_SetVariableCustomProfile($id, $profile);
        return (int)$id;
    }

    private function ensureActionVariableByIdent(int $parentId, string $ident, string $name, int $type, string $profile): int
    {
        $id = $this->ensureVariableByIdent($parentId, $ident, $name, $type, $profile);
        $actionScriptId = $this->ensureActionScriptId();
        if ($actionScriptId > 0) {
            IPS_SetVariableCustomAction($id, $actionScriptId);
        }
        return $id;
    }

    private function ensureActionScriptId(): int
    {
        $script = @IPS_GetObjectIDByIdent('pv_action_script', $this->InstanceID);

        $isValidScript = false;
        if ($script !== false && @IPS_ObjectExists((int)$script)) {
            $obj = IPS_GetObject((int)$script);
            $isValidScript = ((int)($obj['ObjectType'] ?? -1) === 3);
        }

        if (!$isValidScript) {
            $script = IPS_CreateScript(0);
            IPS_SetParent((int)$script, $this->InstanceID);
            IPS_SetIdent((int)$script, 'pv_action_script');
            IPS_SetName((int)$script, 'PVRegelung Action');
            IPS_SetHidden((int)$script, true);
        }

        $content = "<?php
"
            . "\$variableId = (int)\$_IPS['VARIABLE'];
"
            . "\$value = \$_IPS['VALUE'];
"
            . "\$object = IPS_GetObject(\$variableId);
"
            . "\$ident = (string)(\$object['ObjectIdent'] ?? '');
"
            . "if (\$ident === '') {
"
            . "    return;
"
            . "}
"
            . 'IPS_RequestAction(' . $this->InstanceID . ", \$ident, \$value);
";

        IPS_SetScriptContent((int)$script, $content);
        return (int)$script;
    }

    private function normalizeManualPowerToKw(float $power): float
    {
        if ($power > 50.0) {
            $power /= 1000.0;
        }
        return (float)round(max(2.0, min(11.0, $power)));
    }

    private function ensureManualWallboxDefaults(array $CFG): void
    {
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)($CFG['ui']['root_name'] ?? 'PV Regelung'));
        $cWb = $this->ensureCategoryByIdent($root, 'pv_ui_wb', 'Wallbox');

        $powerId = @IPS_GetObjectIDByIdent('pv_manual_wb_power_w', $cWb);
        $defaultPowerKw = $this->normalizeManualPowerToKw(((float)($CFG['wallbox']['manual']['default_power_w'] ?? 4200.0)) / 1000.0);
        if ($powerId !== false && (float)GetValue((int)$powerId) <= 0.0) {
            SetValue((int)$powerId, $defaultPowerKw);
        }

        $targetId = @IPS_GetObjectIDByIdent('pv_manual_wb_target_soc', $cWb);
        if ($targetId !== false && (float)GetValue((int)$targetId) <= 0.0) {
            SetValue((int)$targetId, (float)($CFG['wallbox']['manual']['default_target_soc'] ?? 80.0));
        }
    }

    private function migrateManualPowerVarToKw(int $parentId): void
    {
        $id = @IPS_GetObjectIDByIdent('pv_manual_wb_power_w', $parentId);
        if ($id === false) {
            return;
        }

        $var = IPS_GetVariable((int)$id);
        if ((int)($var['VariableType'] ?? -1) === 2) {
            return;
        }

        $oldValue = (float)GetValue((int)$id);
        $kwValue = $this->normalizeManualPowerToKw($oldValue);

        IPS_DeleteVariable((int)$id);
        $newId = IPS_CreateVariable(2);
        IPS_SetParent($newId, $parentId);
        IPS_SetIdent($newId, 'pv_manual_wb_power_w');
        IPS_SetName($newId, 'Manuelle Ladeleistung');
        IPS_SetVariableCustomProfile($newId, 'PV_kWI');
        SetValue($newId, $kwValue);
    }

    private function setManualVarByIdent(string $ident, $value): void
    {
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)$this->ReadPropertyString('UIRootName'));
        $cWb = $this->ensureCategoryByIdent($root, 'pv_ui_wb', 'Wallbox');
        $this->setVarByIdent($cWb, $ident, $value);
    }

    private function setHeatingVarByIdent(string $ident, $value): void
    {
        $root = $this->ensureCategoryByIdent($this->InstanceID, 'pv_ui_root', (string)$this->ReadPropertyString('UIRootName'));
        $cHeat = $this->ensureCategoryByIdent($root, 'pv_ui_heat', 'Heizung');
        $this->setVarByIdent($cHeat, $ident, $value);
    }

    private function moveObjectByIdent(int $fromParentId, int $toParentId, string $ident): void
    {
        if ($fromParentId === $toParentId) return;

        $id = @IPS_GetObjectIDByIdent($ident, $fromParentId);
        if ($id === false) return;
        if (!@IPS_ObjectExists((int)$id)) return;

        $existingAtTarget = @IPS_GetObjectIDByIdent($ident, $toParentId);
        if ($existingAtTarget !== false) {
            return;
        }

        $obj = IPS_GetObject((int)$id);
        if ((int)($obj['ParentID'] ?? 0) === $toParentId) return;

        IPS_SetParent((int)$id, $toParentId);
    }

    private function readVarByIdent(int $parentId, string $ident, $default)
    {
        $id = @IPS_GetObjectIDByIdent($ident, $parentId);
        if ($id === false) return $default;
        return GetValue((int)$id);
    }

    private function setVarByIdent(int $parentId, string $ident, $value): void
    {
        $id = @IPS_GetObjectIDByIdent($ident, $parentId);
        if ($id === false) return;
        SetValue($id, $value);
    }

    private function removeVariableByIdent(int $parentId, string $ident): void
    {
        $id = @IPS_GetObjectIDByIdent($ident, $parentId);
        if ($id === false) return;
        if (!@IPS_VariableExists((int)$id)) return;
        IPS_DeleteVariable((int)$id);
    }

    private function ensureProfileFloat(string $name, string $suffix, int $digits, float $min, float $max, float $step): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, 2);
        }
        IPS_SetVariableProfileIcon($name, '');
        IPS_SetVariableProfileText($name, '', $suffix);
        IPS_SetVariableProfileValues($name, $min, $max, $step);
        IPS_SetVariableProfileDigits($name, $digits);
    }

    private function ensureProfileInt(string $name, string $suffix, int $digits, int $min, int $max, int $step): void
    {
        if (!IPS_VariableProfileExists($name)) {
            IPS_CreateVariableProfile($name, 1);
        }
        IPS_SetVariableProfileIcon($name, '');
        IPS_SetVariableProfileText($name, '', $suffix);
        IPS_SetVariableProfileValues($name, $min, $max, $step);
    }

    private function writeHeatpumpSurplusSignal(array $CFG, float $gridW, float $pvTotalW, float $houseLoadNoWbWpBattW, float $rodPowerW): void
    {
        $outId = (int)($CFG['heatpump']['surplus_out_var'] ?? 0);
        if ($outId <= 0) return;

        $deadbandW = (float)($CFG['surplus']['export_deadband_w'] ?? 0);

        $signed = (bool)($CFG['heatpump']['surplus_out_signed'] ?? false);
        $unit   = (string)($CFG['heatpump']['surplus_out_unit'] ?? 'kW');

        if ($signed) {
            $valueW = $gridW;
        } else {
            $valueW = max(0.0, $pvTotalW - $houseLoadNoWbWpBattW - $rodPowerW);
            $valueW = ($valueW >= $deadbandW) ? $valueW : 0.0;
        }

        if ($unit === 'kW' || $unit === 'kw') {
            $this->safeSetFloat($outId, $valueW / 1000.0);
        } else {
            $this->safeSetFloat($outId, $valueW);
        }
    }

    private function writeHeatpumpPvProductionSignal(array $CFG, float $pvTotalW): void
    {
        $outId = (int)($CFG['heatpump']['pv_production_out_var'] ?? 0);
        if ($outId <= 0) return;

        $unit = (string)($CFG['heatpump']['pv_production_out_unit'] ?? 'kW');

        if ($unit === 'kW' || $unit === 'kw') {
            $this->safeSetFloat($outId, $pvTotalW / 1000.0);
        } else {
            $this->safeSetFloat($outId, $pvTotalW);
        }
    }

    private function writeGridFilteredInverted(array $CFG, float $gridW_filtered): void
    {
        $out = $CFG['outputs']['grid_filtered_inverted'] ?? null;
        if (!is_array($out)) return;

        $outId = (int)($out['id'] ?? 0);
        if ($outId <= 0) return;

        $unit = (string)($out['unit'] ?? 'W');

        $valueW = -$gridW_filtered;

        if ($unit === 'kW' || $unit === 'kw') {
            $this->safeSetFloat($outId, $valueW / 1000.0);
        } else {
            $this->safeSetFloat($outId, $valueW);
        }
    }

    private function safeSetFloat(int $varId, float $v): void
    {
        if ($varId <= 0) return;
        if (!@IPS_VariableExists($varId)) return;

        $cur = (float)GetValue($varId);
        if (abs($cur - $v) < 0.0001) return;

        @RequestAction($varId, $v);
        @SetValue($varId, $v);
    }
}
