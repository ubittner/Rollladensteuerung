<?php

// Declare
declare(strict_types=1);

trait RS_astroMode
{
    /**
     * Sets the postion by astro mode.
     *
     * @param int $Mode
     * 0    = sunrise
     * 1    = sunset
     * 2    = day
     * 3    = night
     */
    private function TriggerAstroMode(int $Mode): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt: ' . microtime(true), 0);
        $this->SendDebug(__FUNCTION__, 'Modus: ' . json_encode($Mode), 0);
        $executionDelay = $this->ReadPropertyInteger('ExecutionDelay');
        if ($executionDelay > 0) {
            // Delay
            $min = self::MINIMUM_DELAY_MILLISECONDS;
            $max = $executionDelay * 1000;
            $delay = rand($min, $max);
            IPS_Sleep($delay);
        }
        // Set blind if atomatic mode is enabled and sleep mode is disabled
        if ($this->GetValue('AutomaticMode') && !$this->GetValue('SleepMode')) {
            switch ($Mode) {
                // Sunrise
                // Day
                case 0:
                case 2:
                    $level = $this->ReadPropertyInteger('SunriseAction') / 100;
                    $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $level, 0);
                    $this->SetBlindLevel($level, false);
                    break;
                // Sunset
                // Night
                case 1:
                case 3:
                    $level = $this->ReadPropertyInteger('SunsetAction') / 100;
                    $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $level, 0);
                    $this->SetBlindLevel($level, false);
                    break;
            }
        }
    }
}