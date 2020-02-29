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
        $stateName = 'Tag';
        if ($this->ReadPropertyBoolean('TwilightDefinedPosition')) {
            $level = $this->ReadPropertyInteger('TwilightPositionDay') / 100;
        } else {
            $level = $this->GetValue('SetpointPosition');
        }
        $direction = 1;
        if ($State) {
            $stateName = 'Nacht';
            $level = $this->ReadPropertyInteger('TwilightPositionNight') / 100;
            $direction = 0;
        }
        $this->SendDebug(__FUNCTION__, 'Parameter $State = ' . $State . ' = ' . $stateName, 0);
        // Set blind level if automatic mode is enabled and sleep mode is disabled
        if ($this->CheckModes(__FUNCTION__)) {
            $level = $this->CheckPosition($level, $direction);
            if ($level == -1) {
                // Abort, level is not valid
                return $result;
            }
            $result = $this->SetBlindLevel($level, true);
        }
        return $result;
    }
}