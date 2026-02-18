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
        $this->RegisterPropertyInteger('HeatingRodMinOnSeconds', 180);
        $this->RegisterPropertyInteger('HeatingRodMinOffSeconds', 180);

        $this->RegisterPropertyBoolean('HeatingRodWeeklyEnabled', true);
        $this->RegisterPropertyInteger('HeatingRodDaysAfterTargetReached', 7);
        $this->RegisterPropertyString('HeatingRodWeeklyStart', '10:00');
        $this->RegisterPropertyString('HeatingRodWeeklyEnd', '16:00');

        $this->RegisterPropertyBoolean('WallboxEnabled', true);
        $this->RegisterPropertyInteger('WallboxEnableVarID', 47100);
        $this->RegisterPropertyInteger('WallboxChargePowerVarID', 23401);
        $this->RegisterPropertyString('WallboxChargePowerUnit', 'kW');
        $this->RegisterPropertyInteger('WallboxSetCurrentAVarID', 56376);

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
        $this->RegisterPropertyInteger('WallboxAutoStartMinSurplusW', 2000);
        $this->RegisterPropertyInteger('WallboxAutoStartMinDurationSeconds', 900);

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
                'min_on_seconds' => (int)$this->ReadPropertyInteger('HeatingRodMinOnSeconds'),
                'min_off_seconds' => (int)$this->ReadPropertyInteger('HeatingRodMinOffSeconds'),
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
                'auto_start_min_surplus_w' => (int)$this->ReadPropertyInteger('WallboxAutoStartMinSurplusW'),
                'auto_start_min_duration_seconds' => (int)$this->ReadPropertyInteger('WallboxAutoStartMinDurationSeconds'),
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
            $manualDone = $carSoc >= $manualTargetSoc;
            if ($manualDone) {
                $this->setManualVarByIdent('pv_manual_wb_enable', false);
                $manualActive = false;
            }
        }

        if ($manualActive) {
            [$wbOn, $wbA, $state] = $this->planWallboxManualPower($CFG, $state, $manualPowerW);
            $this->applyHeatpump($CFG, $state, false);
            $this->applyHeatingRodStage($CFG, $state, 0);
            $this->applyWallbox($CFG, $state, $wbOn, $wbA);

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
            ]);

            $this->saveState($state);
            return;
        }

        if ($importW > $maxImport && !$rodManualOn) {
            $state = $this->wallboxSoftOff($CFG, $state);
            $this->applyHeatpump($CFG, $state, false);
            $this->applyHeatingRodStage($CFG, $state, 0);
            $this->applyWallbox($CFG, $state, (bool)($state['wb_soft_on'] ?? false), (int)($state['wb_soft_a'] ?? 0));
            $this->updateUiVars($CFG, [
                'restSurplusW' => 0.0,
                'rodOn' => 0,
                'rodStage' => 0,
                'weeklyRodActive' => 0,
            ]);
            $this->saveState($state);
            return;
        }

        $deadband  = (float)$CFG['surplus']['export_deadband_w'];

        $availableBeforeWBW = $exportW + $wallboxChargeW;
        $remainingW = $availableBeforeWBW;

        $hpOn = false;
        $rodOn = false;
        $wbOn = false;
        $wbA = 0;
        $rodDaysSinceTargetActual = $this->daysSinceLastTargetReached($state);

        if ($exportW < $deadband && !$rodManualOn) {
            [$wbOn, $wbA, $state] = $this->planWallboxRamped($CFG, $state, $remainingW);

            $this->applyHeatpump($CFG, $state, false);
            $this->applyHeatingRodStage($CFG, $state, 0);
            $this->applyWallbox($CFG, $state, $wbOn, $wbA);

            $wbTargetW = $this->wallboxPowerFromA($CFG, $state, $wbA);
            $reserveW = (float)($CFG['wallbox']['reserve_w'] ?? 0.0);
            $restSurplusW = max(0.0, $availableBeforeWBW - $wbTargetW - $reserveW);

            $this->updateUiVars($CFG, [
                'hpOn' => 0,
                'rodOn' => 0,
                'rodStage' => 0,
                'wbOn' => $wbOn ? 1 : 0,
                'wbA' => $wbA,
                'remainingW' => $remainingW,
                'weeklyRodActive' => 0,
                'rodDaysSinceTargetActual' => $rodDaysSinceTargetActual,
                'restSurplusW' => $restSurplusW,
                'manualActive' => 0,
            ]);

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

        [$wbOn, $wbA, $state] = $this->planWallboxRamped($CFG, $state, $remainingW);

        $this->applyHeatpump($CFG, $state, $hpOn);
        $this->applyHeatingRodStage($CFG, $state, $rodStage);
        $this->applyWallbox($CFG, $state, $wbOn, $wbA);

        $hpUsedW = $hpOn ? $this->heatpumpPowerW($CFG) : 0.0;
        $rodUsedW = $this->heatingRodPowerForStageW($CFG, $rodStage);
        $wbTargetW = $this->wallboxPowerFromA($CFG, $state, $wbA);
        $reserveW = (float)($CFG['wallbox']['reserve_w'] ?? 0.0);

        $restSurplusW = max(0.0, $availableBeforeWBW - $hpUsedW - $rodUsedW - $wbTargetW - $reserveW);

        $this->updateUiVars($CFG, [
            'hpOn' => $hpOn ? 1 : 0,
            'rodOn' => $rodOn ? 1 : 0,
            'rodStage' => $rodStage,
            'wbOn' => $wbOn ? 1 : 0,
            'wbA' => $wbA,
            'remainingW' => $remainingW,
            'weeklyRodActive' => $needWeeklyRod ? 1 : 0,
            'rodDaysSinceTargetActual' => $rodDaysSinceTargetActual,
            'restSurplusW' => $restSurplusW,
            'manualActive' => 0,
            'manualRodOn' => $rodManualOn ? 1 : 0,
        ]);

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
            if ($availableW < $minSurplus) return [0, $availableW];
            if (!$canTurnOn) return [0, $availableW];
        }

        if ($isOn && !$canTurnOff) {
            $usedW = $this->heatingRodPowerForStageW($CFG, $currentStage);
            return [$currentStage, max(0.0, $availableW - $usedW)];
        }

        $desiredStage = min($maxStage, (int)floor($availableW / $powerPerStage));

        $targetStage = $desiredStage;
        if ($isOn && $availableW < ($minSurplus * 0.8)) {
            $targetStage = max(0, $currentStage - 1);
        }

        if ($isOn && $targetStage < $currentStage) {
            $targetStage = max($targetStage, $currentStage - 1);
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

        $enableVar = (int)($CFG['wallbox']['enable_var'] ?? 0);
        if ($enableVar <= 0) return [false, 0, $state];

        $setA = (int)($CFG['wallbox']['set_current_a_var'] ?? 0);
        if ($setA <= 0) return [false, 0, $state];

        $reserve = (float)($CFG['wallbox']['reserve_w'] ?? 0.0);

        $availableForPhaseDecisionW = max(0.0, $availableW);
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

        $rawA = (int)floor($availableW / ($voltage * $phases));
        $targetA = (int)(floor($rawA / $step) * $step);
        $targetA = max(0, min($maxA, $targetA));

        $now = time();
        $isOn = (bool)($state['wb_is_on'] ?? false);
        $lastOn  = (int)($state['wb_last_on_ts'] ?? 0);
        $lastOff = (int)($state['wb_last_off_ts'] ?? 0);

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

        if ($targetA >= $minA) {
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
            $newA = $this->moveTowardsInt($curA, $desiredA, $rampUp, $rampDown);

            $state['wb_deficit_since_ts'] = 0;
            return [true, $newA, $state];
        }

        if ($isOn) {
            $defSince = (int)($state['wb_deficit_since_ts'] ?? 0);
            if ($defSince <= 0) {
                $state['wb_deficit_since_ts'] = $now;
                $defSince = $now;
            }

            $newA = $this->moveTowardsInt($curA, 0, $rampUp, $rampDown);

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

        if ($isNowOn && $isOn && $currentStage !== $targetStage) {
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

        $this->ensureVariableByIdent($cBat, 'pv_soc', 'SOC', 2, 'PV_PCT');

        $this->moveObjectByIdent($root, $cHeat, 'pv_rod_auto_mode');
        $this->moveObjectByIdent($root, $cHeat, 'pv_manual_rod_on');
        $this->moveObjectByIdent($root, $cHeat, 'pv_rod_days_since_target');
        $this->moveObjectByIdent($root, $cHeat, 'pv_dbg_rod_days_since_target_actual');
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
        $this->ensureVariableByIdent($cHeat, 'pv_dbg_rod_days_since_target_actual', 'Vergangene Tage seit letzter Solltemperatur', 2, '~Float');
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

        if (isset($v['wallboxChargeW'])) $this->setVarByIdent($cWb, 'pv_wb_power_kw', $this->wToKw((float)$v['wallboxChargeW']));
        if (isset($v['wbA'])) $this->setVarByIdent($cWb, 'pv_wb_target_a', (int)$v['wbA']);
        if (isset($v['manualActive'])) $this->setVarByIdent($cWb, 'pv_manual_wb_enable', ((int)$v['manualActive']) === 1);
        if (isset($v['manualPowerW'])) $this->setVarByIdent($cWb, 'pv_manual_wb_power_w', round(((float)$v['manualPowerW']) / 1000.0));
        if (isset($v['manualTargetSoc'])) $this->setVarByIdent($cWb, 'pv_manual_wb_target_soc', (float)$v['manualTargetSoc']);
        if (isset($v['manualCarSoc'])) $this->setVarByIdent($cWb, 'pv_manual_wb_car_soc', (float)$v['manualCarSoc']);

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
