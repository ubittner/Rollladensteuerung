<?php

// Declare
declare(strict_types=1);

trait RS_blindControl
{
    /**
     * Sets the blind slider.
     *
     * @param float $Level
     */
    public function SetBlindSlider(float $Level): void
    {
        $check = $this->CheckLogic($Level);
        $this->SendDebug(__FUNCTION__, 'Resultat Logikprüfung: ' . $check, 0);
        if ($check) {
            $this->SetValue('BlindSlider', $Level);
            $this->SetBlindLevel($Level, false);
        } else {
            $this->UpdateBlindSlider();
        }
    }

    /**
     * Toggles the blind to position closed or opened.
     *
     * @param bool $State
     * false    = close blind
     * true     = open blind
     *
     * @return bool
     * Returns the result of the toggle mode.
     */
    public function ToggleBlind(bool $State): bool
    {
        $result = false;
        $name = 'BlindActuator';
        if ($this->ValidatePropertyVariable($name)) {
            switch ($State) {
                // Close blind
                case false:
                    $value = 0;
                    $stateText = 'geschlossen';
                    break;

                // Open blind
                case true:
                    $value = 1;
                    $stateText = 'geöffnet';
                    break;

                default:
                    $value = 0;
                    $stateText = 'geschlossen';

            }
            $this->SetValue('BlindSlider', $value);
            $id = $this->ReadPropertyInteger($name);
            $toggleBlind = @RequestAction($id, $value);
            if (!$toggleBlind) {
                $this->LogMessage(__FUNCTION__ . ' Der Rollladen konnte nicht ' . $stateText . ' werden.', KL_ERROR);
                $this->SendDebug(__FUNCTION__, 'Der Rollladen konnte nicht ' . $stateText . ' werden.', 0);
            } else {
                $result = true;
                $this->SendDebug(__FUNCTION__, 'Der Rollladen wird ' . $stateText . '.', 0);
            }
        }
        return $result;
    }

    /**
     * Sets the blind level.
     *
     * @param float $Level
     * @param bool $CheckLogic
     * false    = don't check logic, set blind level immediately
     * true     = check logic
     */
    public function SetBlindLevel(float $Level, bool $CheckLogic): void
    {
        if ($CheckLogic) {
            if (!$this->CheckLogic($Level)) {
                return;
            }
        }
        // Check process, if still running then stop blind
        $setBlind = true;
        $name = 'BlindLevelProcess';
        if ($this->ValidatePropertyVariable($name)) {
            $id = $this->ReadPropertyInteger($name);
            if (GetValue($id) == 1) {
                $setBlind = false;
            }
        }
        // Check property first
        if ($this->ReadPropertyInteger('BlindActuatorProperty') == 1) {
            // We have to change the value from 1 to 0 and vise versa
            $Level = (float) abs($Level - 1);
            $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $Level, 0);
        }
        $name = 'BlindActuator';
        if ($this->ValidatePropertyVariable($name)) {
            $id = $this->ReadPropertyInteger('BlindActuator');
            if ($setBlind) {
                $this->SetValue('BlindSlider', $Level);
                $setBlindLevel = @RequestAction($id, $Level);
                if (!$setBlindLevel) {
                    $this->LogMessage(__FUNCTION__ . ' Die Rollladenposition ' . $Level * 100 . '% konnte nicht angesteuert werden.', KL_ERROR);
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenposition ' . $Level * 100 . '% konnte nicht angesteuert werden.', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Die Rollladenposition ' . $Level * 100 . '% wird angesteuert.', 0);
                }
            } else {
                $parentID = IPS_GetParent($id);
                HM_WriteValueBoolean($parentID, 'STOP', true);
                $this->SendDebug(__FUNCTION__, 'Die Rollladenfahrt wurde gestoppt.', 0);
            }
        }
    }

    //#################### Private

    /**
     * Updates the blind slider.
     */
    private function UpdateBlindSlider(): void
    {
        $name = 'BlindLevelState';
        if ($this->ValidatePropertyVariable($name)) {
            $blindLevelState = $this->ReadPropertyInteger($name);
            $blindLevel = GetValue($blindLevelState);
            $property = $this->ReadPropertyInteger('BlindActuatorProperty');
            // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
            if ($property == 1) {
                $blindLevel = (float) abs($blindLevel - 1);
            }
            $this->SendDebug(__FUNCTION__, 'Aktualisierung wird durchgeführt, neuer Wert: ' . $blindLevel * 100 . '%.', 0);
            $this->SetValue('BlindSlider', $blindLevel);
        }
    }

    /**
     * Checks the logic for a new blind level.
     *
     * @param float $Level
     * @return bool
     * false    = new blind level is not ok
     * true     = new blind level is ok
     */
    private function CheckLogic(float $Level): bool
    {
        $Level = $Level * 100;
        $setBlindLevel = true;
        // Door and window sensors
        if ($this->GetValue('DoorWindowState') && ($Level < 50)) {
            $setBlindLevel = false;
            $this->SendDebug(__FUNCTION__, 'Abbruch, eine Tür oder ein Fenster ist offen.', 0);
        }
        // Check blind level position difference
        if ($this->ReadPropertyBoolean('UseCheckBlindPosition')) {
            $name = 'BlindLevelState';
            if ($this->ValidatePropertyVariable($name)) {
                $id = $this->ReadPropertyInteger($name);
                $blindLevel = GetValue($id) * 100;
                // Check if we have a different actuator logic (0% = opened, 100% = closed) comparing to the module logic (0% = closed, 100% = opened)
                if ($this->ReadPropertyInteger('BlindActuatorProperty') == 1) {
                    $blindLevel = (float) abs($blindLevel - 1) * 100;
                }
                $this->SendDebug(__FUNCTION__, 'Aktuelle Position: ' . $blindLevel . '%', 0);
                $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $Level . '%', 0);
                $difference = $this->ReadPropertyInteger('BlindPositionDifference');
                if ($difference > 0) {
                    $actualDifference = abs($blindLevel - $Level);
                    $this->SendDebug(__FUNCTION__, 'Positionsunterschied: ' . $actualDifference . '%', 0);
                    if ($actualDifference <= $difference) {
                        $this->SendDebug(__FUNCTION__, 'Der Positionsunterschied ist zu gering.', 0);
                        $setBlindLevel = false;
                    }
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'Resultat Logikprüfung: ' . $setBlindLevel, 0);
        return $setBlindLevel;
    }
}