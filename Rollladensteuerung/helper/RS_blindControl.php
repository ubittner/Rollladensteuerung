<?php

// Declare
declare(strict_types=1);

trait RS_blindControl
{
    /**
     * Sets the control blind timer.
     */
    public function SetControlBlindTimer()
    {
        $milliseconds = $this->ReadPropertyInteger('ActionDelay') * 1000;
        $this->SetTimerInterval('ControlBlind', $milliseconds);
    }

    /**
     * Controls the blind, used by timer for automatik mode.
     */
    public function ControlBlind()
    {
        // Stop timer
        $this->SetTimerInterval('ControlBlind', 0);
        // Get action
        $nextAction = $this->ReadAttributeInteger('NextAction');
        switch ($nextAction) {
            // 1 = close blind
            case 1:
                $close = true;
                if ($this->ReadPropertyBoolean('UseLockoutProtection')) {
                    $sensor = $this->ReadPropertyInteger('LockoutProtectionSensor');
                    if ($sensor != 0 && IPS_ObjectExists($sensor)) {
                        if (boolval(GetValue($sensor))) {
                            $close = false;
                        }
                    }
                }
                if ($close) {
                    $closedLevel = $this->ReadPropertyInteger('BlindPositionClosed') / 100;
                    $this->SetBlindLevel($closedLevel);
                }
                break;
            // 2 = open blind
            case 2:
                $openedLevel = $this->ReadPropertyInteger('BlindPositionOpened') / 100;
                $this->SetBlindLevel($openedLevel);
                break;
            default:
                break;
        }
        // Reset attribute
        $this->WriteAttributeInteger('NextAction', 0);
        // Set next action
        $weeklyEventPlan = $this->ReadPropertyInteger('WeeklyEventPlan');
        if ($weeklyEventPlan != 0 && IPS_ObjectExists($weeklyEventPlan)) {
            $events = IPS_GetEvent($weeklyEventPlan);
            $eventActive = $events['EventActive'];
            if ($eventActive) {
                $nextRunTimestamp = $events['NextRun'];
                $nextAction = $this->GetNextAction();
                if ($nextAction != 0) {
                    $stateText = 'heruntergefahren';
                    if ($nextAction == 2) {
                        $stateText = 'hochgefahren';
                    }
                    $day = date("l", $nextRunTimestamp);
                    switch ($day) {
                        case 'Monday':
                            $day = 'Montag';
                            break;
                        case 'Tuesday':
                            $day = 'Dienstag';
                            break;
                        case 'Wednesday':
                            $day = 'Mittwoch';
                            break;
                        case 'Thursday':
                            $day = 'Donnerstag';
                            break;
                        case 'Friday':
                            $day = 'Freitag';
                            break;
                        case 'Saturday':
                            $day = 'Samstag';
                            break;
                        case 'Sunday':
                            $day = 'Sonntag';
                            break;
                    }
                    $nextRunDate = date('d.m.Y, H:i:s,', $nextRunTimestamp);
                    $text = $day . ', ' . $nextRunDate . ' Der Rollladen wird ' . $stateText . '.';
                    $this->SetValue('NextAction', $text);
                }
            }
        }
        $this->UpdateBlindLevel();
    }

    /**
     * Checks the action.
     */
    public function CheckAction()
    {
        if ($this->GetValue('AutomaticMode')) {
            $action = $this->GetAction(time());
            switch ($action) {
                // 0 = no actual action found
                case 0:
                    $this->SendDebug('CheckAction', 'No actual action found!', 0);
                    break;
                // 1 = close blind
                case 1:
                    $this->WriteAttributeInteger('NextAction', 1);
                    if ($this->ReadPropertyInteger('ActionDelay') > 0) {
                        $this->SetControlBlindTimer();
                    } else {
                        $this->ControlBlind();
                    }
                    break;
                // 2 = open blind
                case 2:
                    $this->WriteAttributeInteger('NextAction', 2);
                    if ($this->ReadPropertyInteger('ActionDelay') > 0) {
                        $this->SetControlBlindTimer();
                    } else {
                        $this->ControlBlind();
                    }
                    break;
            }
        } else {
            $this->SetValue('NextAction', 'Automatik ist inaktiv.');
        }
    }

    /**
     * Updates the blind level slider.
     */
    public function UpdateBlindLevel()
    {
        $blindLevelState = $this->ReadPropertyInteger('BlindLevelState');
        if ($blindLevelState != 0 && IPS_ObjectExists($blindLevelState)) {
            $blindLevel = GetValue($blindLevelState);
            $property = $this->ReadPropertyInteger('BlindActuatorProperty');
            // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
            if ($property == 1) {
                $blindLevel = (float)abs($blindLevel - 1);
            }
            $this->SetValue('BlindSlider', $blindLevel);
        }
    }

    /**
     * Sets the blind level.
     *
     * @param float $Level
     */
    public function SetBlindLevel(float $Level)
    {
        $setBlind = true;
        $processID = $this->ReadPropertyInteger('BlindLevelProcess');
        if ($processID != 0 && IPS_ObjectExists($processID)) {
            $process = GetValue($processID);
            if ($process == 1) {
                $setBlind = false;
            }
        }
        // Check property first
        $property = $this->ReadPropertyInteger('BlindActuatorProperty');
        if ($property == 1) {
            // We have to change the value from 1 to 0 and vise versa
            $Level = (float)abs($Level - 1);
            $this->SendDebug('SetBlindLevel', 'Level: ' . $Level, 0);
        }
        $blindActuator = $this->ReadPropertyInteger('BlindActuator');
        if ($blindActuator != 0 && IPS_ObjectExists($blindActuator)) {
            if ($setBlind) {
                $this->SetValue('BlindSlider', $Level);
                RequestAction($blindActuator, $Level);
            } else {
                $instanceID = IPS_GetParent($blindActuator);
                HM_WriteValueBoolean($instanceID, 'STOP', true);
                $this->SendDebug('SetBlindLevel', 'STOP', 0);
            }
        }
    }
}