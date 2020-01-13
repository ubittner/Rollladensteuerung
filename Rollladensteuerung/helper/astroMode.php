<?php

// Declare
declare(strict_types=1);

trait RS_astroMode
{
    /**
     * Triggers the sunrise.
     */
    private function TriggerSunrise(): void
    {
        $executionDelay = $this->ReadPropertyInteger('ExecutionDelay');
        if ($executionDelay > 0) {
            // Delay
            $min = self::MINIMUM_DELAY_MILLISECONDS;
            $max = $executionDelay * 1000;
            $delay = rand($min, $max);
            IPS_Sleep($delay);
        }
        // Open blind if atomatic mode is enabled and sleep mode is disabled
        if ($this->GetValue('AutomaticMode') && !$this->GetValue('SleepMode')) {
            $this->SetBlindLevel(1, true);
        }
    }

    /**
     * Triggers the sunset.
     */
    private function TriggerSunset(): void
    {
        $executionDelay = $this->ReadPropertyInteger('ExecutionDelay');
        if ($executionDelay > 0) {
            // Delay
            $min = self::MINIMUM_DELAY_MILLISECONDS;
            $max = $executionDelay * 1000;
            $delay = rand($min, $max);
            IPS_Sleep($delay);
        }
        // Close blind if automatic mode is enabled and sleep mode is disabled
        if ($this->GetValue('AutomaticMode') && !$this->GetValue('SleepMode')) {
            $this->SetBlindLevel(0, true);
        }
    }
}