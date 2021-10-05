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

trait RS_sunriseSunset
{
    public function ExecuteSunriseSunsetAction(int $VariableID, int $Mode): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $modeName = 'Sonnenaufgang';
        $variableName = 'Sunrise';
        $actionName = 'SunriseActions';
        if ($Mode == 1) { # sunrise
            $modeName = 'Sonnenuntergang';
            $variableName = 'Sunset';
            $actionName = 'SunsetActions';
        }
        $this->SendDebug(__FUNCTION__, 'Die Variable ' . $VariableID . ' (' . $modeName . ') hat sich geändert!', 0);
        $action = $this->CheckAction($variableName, $actionName);
        if (!$action) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Variable ' . $modeName . ' hat keine aktivierten Aktionen!', 0);
            return;
        }
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