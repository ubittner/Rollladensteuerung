<?php

// Declare
declare(strict_types=1);

trait RS_sunriseSunset
{
    /**
     * Sets the blind position by sunrise and sunset.
     *
     * @param int $Mode
     * 0    = sunrise
     * 1    = sunset
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    private function TriggerSunriseSunset(int $Mode): bool
    {
        $this->SendDebug(__FUNCTION__, 'Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $modeName = 'Sonnenaufgang';
        $level = $this->ReadPropertyInteger('SunrisePosition') / 100;
        $direction = 1;
        if ($Mode == 1) {
            $modeName = 'Sonnenuntergang';
            $level = $this->ReadPropertyInteger('SunsetPosition') / 100;
            $direction = 0;
        }
        $this->SendDebug(__FUNCTION__, 'Parameter $Mode = ' . $Mode . ' = ' . $modeName, 0);
        $result = false;
        // Set blind if automatic mode is enabled and sleep mode is disabled
        if ($this->CheckModes(__FUNCTION__)) {
            if ($this->CheckLogic($level, $direction)) {
                $this->SetValue('SetpointPosition', $level * 100);
                $result = $this->SetBlindLevel($level, true);
            }
        }
        return $result;
    }
}