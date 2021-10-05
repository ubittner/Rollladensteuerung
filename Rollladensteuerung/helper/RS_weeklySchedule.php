<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Rollladensteuerung/tree/master/Rollladensteuerung
 */

declare(strict_types=1);

trait RS_weeklySchedule
{
    public function ShowActualWeeklyScheduleAction(): void
    {
        $warning = json_decode('"\u26a0\ufe0f"') . "\tFehler\n\n";
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            echo $warning . "Es ist kein Wochenplan vorhanden oder\nder zugewiesene Wochenplan existiert nicht mehr!";
            return;
        }
        if (@IPS_ObjectExists($id)) {
            $event = IPS_GetEvent($id);
            if ($event['EventActive'] != 1) {
                echo $warning . 'Der Wochenplan ist zur Zeit inaktiv!';
            } else {
                $actionID = $this->DetermineAction();
                $actionName = $warning . 'Es wurde keine Aktion gefunden!';
                $event = IPS_GetEvent($id);
                foreach ($event['ScheduleActions'] as $action) {
                    if ($action['ID'] === $actionID) {
                        $actionName = json_decode('"\u2705"') . "\tAktuelle Aktion\n\nID " . $actionID . ' = ' . $action['Name'];
                    }
                }
                echo $actionName;
            }
        }
    }

    public function ExecuteWeeklyScheduleAction(): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, 'Der Wochenplan hat ausgelöst.', 0);
        //Check event plan
        if (!$this->ValidateWeeklySchedule()) {
            return;
        }
        $actionID = $this->DetermineAction();
        switch ($actionID) {
            case 1: # Close
                $actionName = 'WeeklyScheduleActionOne';
                $action = $this->CheckAction('WeeklySchedule', $actionName);
                if (!$action) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Wochenplanaktion: 1 = Schließen hat keine aktivierten Aktionen!', 0);
                    return;
                }
                $this->SendDebug(__FUNCTION__, 'Wochenplanaktion: 1 = Schließen', 0);
                break;

            case 2: # Open
                $actionName = 'WeeklyScheduleActionTwo';
                $action = $this->CheckAction('WeeklySchedule', $actionName);
                if (!$action) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Wochenplanaktion: 2 = Öffnen hat keine aktivierten Aktionen!', 0);
                    return;
                }
                $this->SendDebug(__FUNCTION__, 'Wochenplanaktion: 2 = Öffnen', 0);
                break;

            case 3: # Shading
                $actionName = 'WeeklyScheduleActionThree';
                $action = $this->CheckAction('WeeklySchedule', $actionName);
                if (!$action) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, Wochenplanaktion: 3 = Beschatten hat keine aktivierten Aktionen!', 0);
                    return;
                }
                $this->SendDebug(__FUNCTION__, 'Wochenplanaktion: 3 = Beschatten', 0);
                break;
        }
        if (isset($actionName)) {
            foreach (json_decode($this->ReadPropertyString($actionName), true) as $setting) {
                if ($setting['UseSettings']) {
                    $selectPosition = $setting['SelectPosition'];
                    switch ($selectPosition) {
                        case 0: # None
                            $this->SendDebug(__FUNCTION__, 'Abbruch, keine Aktion ausgewählt!', 0);
                            continue 2;

                        case 1: # Defined position
                            $position = $setting['Position'];
                            break;

                        case 2: # Last position
                            $position = intval($this->GetValue('LastPosition'));
                            break;

                        case 3: # Setpoint position
                            $position = intval($this->GetValue('SetpointPosition'));
                            break;
                    }
                    if (isset($position)) {
                        //Check conditions
                        $checkConditions = $this->CheckAllConditions(json_encode($setting));
                        if (!$checkConditions) {
                            continue;
                        }
                        //Trigger action
                        if ($setting['UpdateSetpointPosition']) {
                            $this->SetValue('SetpointPosition', $position);
                        }
                        if ($setting['UpdateLastPosition']) {
                            $this->SetValue('LastPosition', $position);
                        }
                        $this->TriggerExecutionDelay(intval($setting['ExecutionDelay']));
                        $this->MoveBlind($position);
                    }
                }
            }
        }
    }

    #################### Private

    private function DetermineAction(): int
    {
        $actionID = 0;
        if ($this->ValidateWeeklySchedule()) {
            $timestamp = time();
            $searchTime = date('H', $timestamp) * 3600 + date('i', $timestamp) * 60 + date('s', $timestamp);
            $weekDay = date('N', $timestamp);
            $id = $this->ReadPropertyInteger('WeeklySchedule');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $event = IPS_GetEvent($id);
                foreach ($event['ScheduleGroups'] as $group) {
                    if (($group['Days'] & pow(2, $weekDay - 1)) > 0) {
                        $points = $group['Points'];
                        foreach ($points as $point) {
                            $startTime = $point['Start']['Hour'] * 3600 + $point['Start']['Minute'] * 60 + $point['Start']['Second'];
                            if ($startTime <= $searchTime) {
                                $actionID = $point['ActionID'];
                            }
                        }
                    }
                }
            }
        }
        return $actionID;
    }

    private function ValidateWeeklySchedule(): bool
    {
        $result = false;
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $event = IPS_GetEvent($id);
            if ($event['EventActive'] == 1) {
                $result = true;
            }
        }
        if (!$result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wochenplan ist nicht vorhanden oder deaktiviert!', 0);
        }
        return $result;
    }
}