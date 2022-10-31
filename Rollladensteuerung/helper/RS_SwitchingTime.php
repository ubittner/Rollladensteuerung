<?php

/**
 * @project       Rollladensteuerung/Rollladensteuerung
 * @file          RS_SwitchingTime.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait RS_SwitchingTime
{
    public function ExecuteSwitchingTime(int $SwitchingTime): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        switch ($SwitchingTime) {
            case 0: # Abort
                return;

            case 1: # Switching time one
                $switchingTimeName = 'Schaltzeit 1';
                $settings = json_decode($this->ReadPropertyString('SwitchingTimeOneActions'), true);
                break;

            case 2: # Switching time two
                $switchingTimeName = 'Schaltzeit 2';
                $settings = json_decode($this->ReadPropertyString('SwitchingTimeTwoActions'), true);
                break;

            case 3: # Switching time three
                $switchingTimeName = 'Schaltzeit 3';
                $settings = json_decode($this->ReadPropertyString('SwitchingTimeThreeActions'), true);
                break;

            case 4: # Switching time four
                $switchingTimeName = 'Schaltzeit 4';
                $settings = json_decode($this->ReadPropertyString('SwitchingTimeFourActions'), true);
                break;

        }
        if (isset($settings) && isset($switchingTimeName)) {
            $action = false;
            foreach ($settings as $setting) {
                if ($setting['UseSettings']) {
                    $action = true;
                }
            }
            if (!$action) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die ' . $switchingTimeName . ' hat keine aktivierte Aktion!', 0);
                return;
            }
            foreach ($settings as $setting) {
                if ($setting['UseSettings']) {
                    $this->SendDebug(__FUNCTION__, 'Die ' . $switchingTimeName . ' wird ausgeführt!', 0);
                    $selectPosition = $setting['SelectPosition'];
                    switch ($selectPosition) {
                        case 0: # None
                            $this->SendDebug(__FUNCTION__, 'Abbruch, keine Aktion ausgewählt!', 0);
                            continue 2;

                        case 1: # Defined position
                            $position = $setting['Position'];
                            break;

                        case 2: # Last position
                            $position = intval($this->GetValue('LastPosition'));
                            break;

                        case 3: # Setpoint position
                            $position = intval($this->GetValue('SetpointPosition'));
                            break;
                    }
                    if (isset($position)) {
                        //Check conditions
                        $checkConditions = $this->CheckAllConditions(json_encode($setting));
                        if (!$checkConditions) {
                            $this->SetSwitchingTimes();
                            continue;
                        }
                        //Trigger action
                        if ($setting['UpdateSetpointPosition']) {
                            $this->SetValue('SetpointPosition', $position);
                        }
                        if ($setting['UpdateLastPosition']) {
                            $this->SetValue('LastPosition', $position);
                        }
                        $this->TriggerExecutionDelay(intval($setting['ExecutionDelay']));
                        $this->MoveBlind($position);
                        $this->SetSwitchingTimes();
                    }
                }
            }
        }
    }

    #################### Private

    private function SetSwitchingTimes(): void
    {
        //Switching time one
        $interval = 0;
        $setTimer = false;
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeOneActions')) as $switchingTimeAction) {
            if ($switchingTimeAction->UseSettings) {
                $setTimer = true;
            }
        }
        if ($setTimer) {
            $interval = $this->GetSwitchingTimerInterval('SwitchingTimeOne');
        }
        $this->SetTimerInterval('SwitchingTimeOne', $interval);
        //Switching time two
        $interval = 0;
        $setTimer = false;
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeTwoActions')) as $switchingTimeAction) {
            if ($switchingTimeAction->UseSettings) {
                $setTimer = true;
            }
        }
        if ($setTimer) {
            $interval = $this->GetSwitchingTimerInterval('SwitchingTimeTwo');
        }
        $this->SetTimerInterval('SwitchingTimeTwo', $interval);
        //Switching time three
        $interval = 0;
        $setTimer = false;
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeThreeActions')) as $switchingTimeAction) {
            if ($switchingTimeAction->UseSettings) {
                $setTimer = true;
            }
        }
        if ($setTimer) {
            $interval = $this->GetSwitchingTimerInterval('SwitchingTimeThree');
        }
        $this->SetTimerInterval('SwitchingTimeThree', $interval);
        //Switching time four
        $interval = 0;
        $setTimer = false;
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeFourActions')) as $switchingTimeAction) {
            if ($switchingTimeAction->UseSettings) {
                $setTimer = true;
            }
        }
        if ($setTimer) {
            $interval = $this->GetSwitchingTimerInterval('SwitchingTimeFour');
        }
        $this->SetTimerInterval('SwitchingTimeFour', $interval);
        //Set info for next switching time
        $this->SetNextSwitchingTimeInfo();
    }

    private function GetSwitchingTimerInterval(string $TimerName): int
    {
        $timer = json_decode($this->ReadPropertyString($TimerName));
        $now = time();
        $hour = $timer->hour;
        $minute = $timer->minute;
        $second = $timer->second;
        $definedTime = $hour . ':' . $minute . ':' . $second;
        if (time() >= strtotime($definedTime)) {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        } else {
            $timestamp = mktime($hour, $minute, $second, (int) date('n'), (int) date('j'), (int) date('Y'));
        }
        return ($timestamp - $now) * 1000;
    }

    private function SetNextSwitchingTimeInfo(): void
    {
        $timer = [];
        //Switching time one
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeOneActions'), true) as $switchingTimeAction) {
            if ($switchingTimeAction['UseSettings']) {
                $timer[] = ['name' => 'SwitchingTimeOne', 'interval' => $this->GetSwitchingTimerInterval('SwitchingTimeOne')];
            }
        }
        //Switching time two
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeTwoActions'), true) as $switchingTimeAction) {
            if ($switchingTimeAction['UseSettings']) {
                $timer[] = ['name' => 'SwitchingTimeTwo', 'interval' => $this->GetSwitchingTimerInterval('SwitchingTimeTwo')];
            }
        }
        //Switching time three
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeThreeActions'), true) as $switchingTimeAction) {
            if ($switchingTimeAction['UseSettings']) {
                $timer[] = ['name' => 'SwitchingTimeThree', 'interval' => $this->GetSwitchingTimerInterval('SwitchingTimeThree')];
            }
        }
        //Switching time four
        foreach (json_decode($this->ReadPropertyString('SwitchingTimeFourActions'), true) as $switchingTimeAction) {
            if ($switchingTimeAction['UseSettings']) {
                $timer[] = ['name' => 'SwitchingTimeFour', 'interval' => $this->GetSwitchingTimerInterval('SwitchingTimeFour')];
            }
        }
        if (!empty($timer)) {
            foreach ($timer as $key => $row) {
                $interval[$key] = $row['interval'];
            }
            array_multisort($interval, SORT_ASC, $timer);
            $timestamp = time() + ($timer[0]['interval'] / 1000);
            $this->SetValue('NextSwitchingTime', $this->GetTimeStampString($timestamp));
        } else {
            $this->SetValue('NextSwitchingTime', '-');
        }
    }
}