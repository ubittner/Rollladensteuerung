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
        switch ($Mode) {
            case 0:
                $modeName = 'Sonnenaufgang';
                break;

            case 1:
                $modeName = 'Sonnenuntergang';
                break;

            case 2:
                $modeName = 'Tag';
                break;

            case 3:
                $modeName = 'Nacht';
                break;

            default:
                $modeName = 'Unbekannt';

        }
        $this->SendDebug(__FUNCTION__, 'Die Methode wird mit dem Parameter $Mode = ' . $Mode . ' = ' . $modeName . ' ausgeführt. (' . microtime(true) . ')', 0);
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
                // Sunrise or day
                case 0:
                case 2:
                    $level = $this->ReadPropertyInteger('SunriseAction') / 100;
                    $this->SendDebug(__FUNCTION__, 'Der Rollladen wird auf ' . $level * 100 . '% gefahren.', 0);
                    $this->SetBlindLevel($level, false);
                    break;
                // Sunset or night
                case 1:
                case 3:
                    $level = $this->ReadPropertyInteger('SunsetAction') / 100;
                    $this->SendDebug(__FUNCTION__, 'Der Rollladen wird auf ' . $level * 100 . '% gefahren.', 0);
                    $this->SetBlindLevel($level, false);
                    break;
            }
        }
    }
}