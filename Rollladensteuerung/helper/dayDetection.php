<?php

// Declare
declare(strict_types=1);

trait RS_dayDetection
{
    /**
     * Sets the blind position by is day detection.
     *
     * @param bool $State
     * false    = night
     * true     = day
     *
     * @return bool
     * false    = an error occurred
     * true     = ok
     */
    private function TriggerIsDayDetection(bool $State): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $result = false;
        // Day
        if ($State) {
            if (!$this->ReadPropertyBoolean('IsDayAdjustPosition')) {
                return $result;
            }
            $stateName = 'Es ist Tag';
            $level = $this->ReadPropertyInteger('IsDayPosition') / 100;
            $direction = 1;
        }
        // Night
        if (!$State) {
            if (!$this->ReadPropertyBoolean('IsNightAdjustPosition')) {
                return $result;
            }
            $stateName = 'Es ist Nacht';
            $level = $this->ReadPropertyInteger('IsNightPosition') / 100;
            $direction = 0;

        }
        if (isset($stateName)) {
            $this->SendDebug(__FUNCTION__, 'Parameter $State = ' . $State . ' = ' . $stateName, 0);
        }
        // Set blind level if automatic mode is enabled and sleep mode is disabled
        if (isset($level) && isset($direction)) {
            if ($this->CheckModes(__FUNCTION__)) {
                $level = $this->CheckPosition($level, $direction);
                if ($level == -1) {
                    // Abort, level is not valid
                    return $result;
                }
                $result = $this->SetBlindLevel($level, true);
            }
        }
        return $result;
    }
}