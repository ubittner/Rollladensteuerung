<?php

// Declare
declare(strict_types=1);

trait RS_blindControl
{
    /**
     * Sets the control blind timer for action delay.
     */
    public function SetControlBlindTimer()
    {
        $milliseconds = $this->ReadPropertyInteger('ActionDelay') * 1000;
        $this->SetTimerInterval('ControlBlind', $milliseconds);
    }

    /**
     * Controls the blind, used by timer for action delay and used by the assigned weekplan.
     */
    public function ControlBlind()
    {
        // Stop timer for action delay
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
        $this->SetNextActionInfo();
        $this->UpdateBlindLevel();
    }

    /**
     * Sets the next action imformation.
     */
    public function SetNextActionInfo()
    {
        $text = 'Es ist kein Wochenplan ausgewählt!';
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
                    //$this->SetValue('NextAction', $text);
                }
            }
        }
        if (!$this->GetValue('AutomaticMode')) {
            $text = 'Automatik ist inaktiv.';
        }
        $this->SetValue('NextAction', $text);

    }

    /**
     * Checks the action.
     */
    public function CheckAction()
    {
        if ($this->GetValue('AutomaticMode')) {
            $action = $this->GetAction(time());
            $this->SendDebug('CheckActionResult', $action, 0);
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
        }
        // Set next action
        $this->SetNextActionInfo();
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
     * Checks the actual blind level in comparison to the actual event.
     */
    private function CheckBlindLevel()
    {
        $useSetBlindLevel = $this->ReadPropertyBoolean('UseSetBlindLevel');
        if ($this->GetValue('AutomaticMode') && $useSetBlindLevel) {
            $this->CheckAction();
        } else {
            $this->SetNextActionInfo();
        }
    }

    /**
     * Sets the blind level.
     *
     * @param float $Level
     */
    public function SetBlindLevel(float $Level)
    {
        // Check process, if still running then stop blind
        $setBlind = true;
        $processID = $this->ReadPropertyInteger('BlindLevelProcess');
        if ($processID != 0 && IPS_ObjectExists($processID)) {
            $process = GetValue($processID);
            if ($process == 1) {
                $setBlind = false;
            }
        }
        // Check blind level position difference
        $useCheckBlindPosition = $this->ReadPropertyBoolean('UseCheckBlindPosition');
        if ($useCheckBlindPosition) {
            $blindLevelState = $this->ReadPropertyInteger('BlindLevelState');
            if ($blindLevelState != 0 && IPS_ObjectExists($blindLevelState)) {
                $actualBlindLevel = GetValue($blindLevelState) * 100;
                $property = $this->ReadPropertyInteger('BlindActuatorProperty');
                // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
                if ($property == 1) {
                    $actualBlindLevel = (float)abs($actualBlindLevel - 1) * 100;
                }
                $this->SendDebug('SetBlindLevel', 'Actual blind level: ' . $actualBlindLevel . '%', 0);
                $blindPositionDifference = $this->ReadPropertyInteger('BlindPositionDifference');
                if ($blindPositionDifference > 0) {
                    $actualDifference = abs($actualBlindLevel - ($Level * 100));
                    $this->SendDebug('SetBlindLevel', 'Actual postion difference: ' . $actualDifference . '%', 0);
                    if ($actualDifference <= $blindPositionDifference) {
                        $setBlind = false;
                    }
                }
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
                $setBlindLevel = @RequestAction($blindActuator, $Level);
                if (!$setBlindLevel) {
                    IPS_Sleep(250);
                    @RequestAction($blindActuator, $Level);
                }
            } else {
                $instanceID = IPS_GetParent($blindActuator);
                HM_WriteValueBoolean($instanceID, 'STOP', true);
                $this->SendDebug('SetBlindLevel', 'STOP', 0);
            }
        }
    }

    /**
     * Closes the blind (0%).
     */
    public function CloseBlind()
    {
        $blindActuator = $this->ReadPropertyInteger('BlindActuator');
        if ($blindActuator != 0 && IPS_ObjectExists($blindActuator)) {
            $this->SetValue('BlindSlider', 0);
            $close = @RequestAction($blindActuator, 0);
            if (!$close) {
                IPS_Sleep(250);
                @RequestAction($blindActuator, 0);
            }
        }
    }

    /**
     * Opens the blind (100%).
     */
    public function OpenBlind()
    {
        $blindActuator = $this->ReadPropertyInteger('BlindActuator');
        if ($blindActuator != 0 && IPS_ObjectExists($blindActuator)) {
            $this->SetValue('BlindSlider', 1);
            $open = @RequestAction($blindActuator, 1);
            if (!$open) {
                IPS_Sleep(250);
                @RequestAction($blindActuator, 1);
            }
        }
    }
}