<?php

// Declare
declare(strict_types=1);

trait RS_twilightDetection
{
    /**
     * Sets the blind position by twilight detection.
     *
     * @param bool $State
     * false    = day
     * true     = night
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    private function TriggerTwilightDetection(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $result = false;
        $this->SetValue('TwilightState', $State);
        $id = $this->ReadPropertyInteger('IsDay');
        if ($id == 0 || !IPS_ObjectExists($id)) {
            $this->SendDebug(__FUNCTION__, "Abbruch, Die Variable 'Ist es Tag' wurde nicht ausgewählt!", 0);
            return $result;
        }
        if (GetValue($id)) {
            $stateName = 'Tag';
            if ($this->ReadPropertyBoolean('TwilightDefinedPosition')) {
                $level = $this->ReadPropertyInteger('TwilightPositionDay') / 100;
            } else {
                $level = $this->GetValue('SetpointPosition') / 100;
            }
            $direction = 1;
            if ($State) {
                $stateName = 'Nacht';
                $level = $this->ReadPropertyInteger('TwilightPositionNight') / 100;
                $direction = 0;
            }
            $this->SendDebug(__FUNCTION__, 'Parameter $State = ' . json_encode($State) . ' = ' . $stateName, 0);
            // Set blind level if automatic mode is enabled and sleep mode is disabled
            if ($this->CheckModes(__FUNCTION__)) {
                $level = $this->CheckPosition($level, $direction);
                if ($level == -1) {
                    // Abort, level is not valid
                    return $result;
                }
                $this->WriteAttributeBoolean('UpdateSetpointPosition', false);
                $result = $this->SetBlindLevel($level, true);
            }
        }
        return $result;
    }
}