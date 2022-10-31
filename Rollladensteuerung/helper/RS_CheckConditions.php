<?php

/**
 * @project       Rollladensteuerung/Rollladensteuerung
 * @file          RS_CheckConditions.php
 * @author        Ulrich Bittner
 * @copyright     2022 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnusedPrivateMethodInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

trait RS_CheckConditions
{
    private function CheckAllConditions(string $Settings): bool
    {
        $setting = json_decode($Settings, true);
        $conditions = [
            ['type' => 0, 'condition' => ['Position' => $setting['Position'], 'CheckPositionDifference' => $setting['CheckPositionDifference']]],
            ['type' => 1, 'condition' => ['Position' => $setting['Position'], 'CheckLockoutProtection' => $setting['CheckLockoutProtection']]],
            ['type' => 2, 'condition' => $setting['CheckAutomaticMode']],
            ['type' => 3, 'condition' => $setting['CheckSleepMode']],
            ['type' => 4, 'condition' => $setting['CheckBlindMode']],
            ['type' => 5, 'condition' => $setting['CheckIsDay']],
            ['type' => 6, 'condition' => $setting['CheckTwilight']],
            ['type' => 7, 'condition' => $setting['CheckPresence']],
            ['type' => 8, 'condition' => $setting['CheckDoorWindowStatus']]];
        return $this->CheckConditions(json_encode($conditions));
    }

    /**
     * Checks the conditions.
     *
     * @param string $Conditions
     * 0    = position difference
     * 1    = lockout protection
     * 2    = automatic mode
     * 3    = sleep mode
     * 4    = blind mode
     * 5    = is day
     * 6    = twilight
     * 7    = presence
     * 8    = door and windows
     *
     * @return bool
     * false    = mismatch
     * true     = condition is valid
     */
    private function CheckConditions(string $Conditions): bool
    {
        $result = true;
        $Conditions = json_decode($Conditions, true);
        if (!empty($Conditions)) {
            $results = [];
            foreach ($Conditions as $condition) {
                switch ($condition['type']) {
                    case 0: # Position difference
                        $checkPositionDifference = $this->CheckPositionDifferenceCondition($condition['condition']);
                        $results[$condition['type']] = $checkPositionDifference;
                        break;

                    case 1: # Lockout protection
                        $checkLockoutProtection = $this->CheckLockoutProtectionCondition($condition['condition']);
                        $results[$condition['type']] = $checkLockoutProtection;
                        break;

                    case 2: # Automatic mode
                        $checkAutomaticMode = $this->CheckAutomaticModeCondition($condition['condition']);
                        $results[$condition['type']] = $checkAutomaticMode;
                        break;

                    case 3: # Sleep mode
                        $checkSleepMode = $this->CheckSleepModeCondition($condition['condition']);
                        $results[$condition['type']] = $checkSleepMode;
                        break;

                    case 4: # Blind mode
                        $checkBlindMode = $this->CheckBlindModeCondition($condition['condition']);
                        $results[$condition['type']] = $checkBlindMode;
                        break;

                    case 5: # Is day
                        $checkIsDay = $this->CheckIsDayCondition($condition['condition']);
                        $results[$condition['type']] = $checkIsDay;
                        break;

                    case 6: # Twilight
                        $checkTwilight = $this->CheckTwilightCondition($condition['condition']);
                        $results[$condition['type']] = $checkTwilight;
                        break;

                    case 7: # Presence
                        $checkPresence = $this->CheckPresenceCondition($condition['condition']);
                        $results[$condition['type']] = $checkPresence;
                        break;

                    case 8: # Door and window status
                        $checkDoorWindows = $this->CheckDoorWindowCondition($condition['condition']);
                        $results[$condition['type']] = $checkDoorWindows;
                        break;

                }
            }
            if (in_array(false, $results)) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die Bedingungen wurden nicht erfüllt!', 0);
                $result = false;
            }
        }
        return $result;
    }

    private function CheckPositionDifferenceCondition(array $Conditions): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $checkPositionDifference = $Conditions['CheckPositionDifference'];
        $this->SendDebug(__FUNCTION__, 'Positionsunterschied: ' . $checkPositionDifference . '%.', 0);
        $newBlindPosition = $Conditions['Position'];
        $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $newBlindPosition . '%.', 0);
        if ($checkPositionDifference > 0) { # 0 = don't check for position difference, > 0 check position difference
            $actualBlindPosition = $this->GetActualBlindPosition();
            $this->SendDebug(__FUNCTION__, 'Aktuelle Position: ' . $actualBlindPosition . '%.', 0);
            $range = ($actualBlindPosition * $checkPositionDifference) / 100;
            $minimalPosition = $actualBlindPosition - $range;
            $this->SendDebug(__FUNCTION__, 'Minimale Position: ' . $minimalPosition . '%.', 0);
            $maximalPosition = $actualBlindPosition + $range;
            $this->SendDebug(__FUNCTION__, 'Maximale Position: ' . $maximalPosition . '%.', 0);
            if ($actualBlindPosition > 0) {
                if ($newBlindPosition > $minimalPosition && $newBlindPosition < $maximalPosition) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Positionsunterschied ist zu gering!', 0);
                    $result = false;
                } else {
                    $this->SendDebug(__FUNCTION__, 'Neue Position: ' . $newBlindPosition . '% entspricht der Bedingung.', 0);
                }
            }
        }
        return $result;
    }

    private function CheckLockoutProtectionCondition(array $Conditions): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $checkLockoutPosition = $Conditions['CheckLockoutProtection'];
        //Check moving direction
        $direction = $this->CheckBlindMovingDirection(intval($Conditions['Position']));
        $doorWindowStatus = boolval($this->GetValue('DoorWindowStatus'));
        if ($checkLockoutPosition == 100) { # don't move blind down always
            if ($direction == 0 && $doorWindowStatus) { # down
                $this->SendDebug(__FUNCTION__, 'Abbruch, der Aussperrschutz ist aktiv!', 0);
                $result = false;
            }
        }
        if ($checkLockoutPosition > 0 && $checkLockoutPosition < 100) { # check position
            if ($direction == 0 && $doorWindowStatus) { // down
                $actualBlindPosition = $this->GetActualBlindPosition();
                $newBlindPosition = $Conditions['Position'];
                if ($newBlindPosition <= $actualBlindPosition) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Aussperrschutz ist bei ' . $checkLockoutPosition . '% aktiv!', 0);
                    $result = false;
                }
            }
        }
        return $result;
    }

    private function CheckAutomaticModeCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $automaticMode = boolval($this->GetValue('AutomaticMode')); # false = automatic mode is off, true = automatic mode is on
        switch ($Condition) {
            case 1: # Automatic mode must be off
                if ($automaticMode) { # Automatic mode is on
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Aus', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Automatik ist eingeschaltet!', 0);
                    $result = false;
                }
                break;

            case 2: # Automatic mode must be on
                if (!$automaticMode) { # Automatic mode is off
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = An', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Automatik ist ausgeschaltet!', 0);
                    $result = false;
                }
                break;

        }
        return $result;
    }

    private function CheckSleepModeCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $sleepMode = boolval($this->GetValue('SleepMode')); # false = sleep mode is off, true = sleep mode is on
        switch ($Condition) {
            case 1: # Sleep mode must be off
                if ($sleepMode) { # Sleep mode is on
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Aus', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Ruhe-Modus ist eingeschaltet!', 0);
                    $result = false;
                }
                break;

            case 2: # Sleep mode must be on
                if (!$sleepMode) { # Sleep mode is off
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = An', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Ruhe-Modus ist ausgeschaltet!', 0);
                    $result = false;
                }
                break;

        }
        return $result;
    }

    private function CheckBlindModeCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $blindMode = intval($this->GetValue('BlindMode')); # 0 = closed, 1 = stop, 2 = timer, 3 = opened
        switch ($Condition) {
            case 1: # Blind must be closed
                if ($blindMode == 2 || $blindMode == 3) { # Timer is on or blind is opened
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Geschlossen', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Rollladen ist nicht geschlossen!', 0);
                    $result = false;
                }
                break;

            case 2: # Timer must be on
                if ($blindMode == 0 || $blindMode == 3) { # Blind is closed or opened
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Timer', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Timer ist nicht aktiv!', 0);
                    $result = false;
                }
                break;

            case 3: # Timer must be on or blind must be opened
                if ($blindMode == 0) { # Blind is closed
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 3 = Timer - Geöffnet', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Rollladen ist geschlossen!', 0);
                    $result = false;
                }
                break;

            case 4: # Blind must be opened
                if ($blindMode == 0 || $blindMode == 2) { # Blind is closed or timer is on
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 4 =  Geöffnet', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Rollladen ist nicht geöffnet!', 0);
                    $result = false;
                }
                break;

        }
        return $result;
    }

    private function CheckIsDayCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        switch ($Condition) {
            case 1: # Must be night
                $id = $this->ReadPropertyInteger('IsDay');
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Ist es Tag - Prüfung ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $isDayStatus = boolval(GetValue($id));
                    if ($isDayStatus) { # Day
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Es ist Nacht', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Tag!', 0);
                        $result = false;
                    }
                }
                break;

            case 2: #  Must be day
                $id = $this->ReadPropertyInteger('IsDay');
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, die Ist es Tag - Prüfung ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $isDayStatus = boolval(GetValue($id));
                    if (!$isDayStatus) { # Night
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Es ist Tag', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Nacht!', 0);
                        $result = false;
                    }
                }
                break;
        }
        return $result;
    }

    private function CheckTwilightCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $id = $this->ReadPropertyInteger('TwilightStatus');
        switch ($Condition) {
            case 1: # Must be day
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Dämmerungsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $twilightStatus = boolval(GetValue($id));
                    if ($twilightStatus) { # Night
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Es ist Tag', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Nacht!', 0);
                        $result = false;
                    }
                }
                break;

            case 2: # Must be night
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Dämmerungsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $twilightStatus = boolval(GetValue($id));
                    if (!$twilightStatus) { # Day
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Es ist Nacht', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Es ist Tag!', 0);
                        $result = false;
                    }
                }
                break;
        }
        return $result;
    }

    private function CheckPresenceCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $id = $this->ReadPropertyInteger('PresenceStatus');
        switch ($Condition) {
            case 1: # Must be absence
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Anwesenheitsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $presenceStatus = boolval(GetValue($id));
                    if ($presenceStatus) { # Presence
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Abwesenheit', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Anwesenheit!', 0);
                        $result = false;
                    }
                }
                break;

            case 2: # Must be presence
                if ($id == 0 || !@IPS_ObjectExists($id)) {
                    $this->SendDebug(__FUNCTION__, 'Abbruch, der Anwesenheitsstatus ist nicht konfiguriert oder vorhanden!', 0);
                    $result = false;
                }
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $presenceStatus = boolval(GetValue($id));
                    if (!$presenceStatus) { # Absence
                        $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Anwesenheit', 0);
                        $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Status: Abwesenheit!', 0);
                        $result = false;
                    }
                }
                break;

        }
        return $result;
    }

    private function CheckDoorWindowCondition(int $Condition): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        $doorWindowStatus = boolval($this->GetValue('DoorWindowStatus'));
        switch ($Condition) {
            case 1: # Must be closed
                if ($doorWindowStatus) { # Opened
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 1 = Tür- / Fensterstatus: Geschlossen', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Tür- / Fensterstatus: Geöffnet!', 0);
                    $result = false;
                }
                break;

            case 2: # Must be opened
                if (!$doorWindowStatus) { # Closed
                    $this->SendDebug(__FUNCTION__, 'Bedingung: 2 = Tür- / Fensterstatus: Geöffnet', 0);
                    $this->SendDebug(__FUNCTION__, 'Abbruch, aktueller Tür- / Fensterstatus: Geschlossen!', 0);
                    $result = false;
                }
                break;

        }
        return $result;
    }

    private function CheckTimeCondition(string $ExecutionTimeAfter, string $ExecutionTimeBefore): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        $result = true;
        //Actual time
        $actualTime = time();
        $this->SendDebug(__FUNCTION__, 'Aktuelle Uhrzeit: ' . date('H:i:s', $actualTime) . ', ' . $actualTime . ', ' . date('d.m.Y', $actualTime), 0);
        //Time after
        $timeAfter = json_decode($ExecutionTimeAfter);
        $timeAfterHour = $timeAfter->hour;
        $timeAfterMinute = $timeAfter->minute;
        $timeAfterSecond = $timeAfter->second;
        $timestampAfter = mktime($timeAfterHour, $timeAfterMinute, $timeAfterSecond, (int) date('n'), (int) date('j'), (int) date('Y'));
        //Time before
        $timeBefore = json_decode($ExecutionTimeBefore);
        $timeBeforeHour = $timeBefore->hour;
        $timeBeforeMinute = $timeBefore->minute;
        $timeBeforeSecond = $timeBefore->second;
        $timestampBefore = mktime($timeBeforeHour, $timeBeforeMinute, $timeBeforeSecond, (int) date('n'), (int) date('j'), (int) date('Y'));
        if ($timestampAfter != $timestampBefore) {
            $this->SendDebug(__FUNCTION__, 'Bedingung Uhrzeit nach: ' . date('H:i:s', $timestampAfter) . ', ' . $timestampAfter . ', ' . date('d.m.Y', $timestampAfter), 0);
            //Same day
            if ($timestampAfter <= $timestampBefore) {
                $this->SendDebug(__FUNCTION__, 'Bedingung Uhrzeit vor: ' . date('H:i:s', $timestampBefore) . ', ' . $timestampBefore . ', ' . date('d.m.Y', $timestampBefore), 0);
                $this->SendDebug(__FUNCTION__, 'Zeitraum ist am gleichen Tag', 0);
                if ($actualTime >= $timestampAfter && $actualTime <= $timestampBefore) {
                    $this->SendDebug(__FUNCTION__, 'Aktuelle Zeit liegt im definierten Zeitraum.', 0);
                } else {
                    $result = false;
                    $this->SendDebug(__FUNCTION__, 'Aktuelle Zeit liegt außerhalb des definierten Zeitraums.', 0);
                }
            } else { # Overnight
                if ($actualTime > $timestampBefore) {
                    $this->SendDebug(__FUNCTION__, 'Zeitraum erstreckt sich über zwei Tage.', 0);
                    $timestampBefore = mktime($timeBeforeHour, $timeBeforeMinute, $timeBeforeSecond, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
                }
                $this->SendDebug(__FUNCTION__, 'Bedingung Uhrzeit vor: ' . date('H:i:s', $timestampBefore) . ', ' . $timestampBefore . ', ' . date('d.m.Y', $timestampBefore), 0);
                if ($actualTime >= $timestampAfter && $actualTime <= $timestampBefore) {
                    $this->SendDebug(__FUNCTION__, 'Aktuelle Zeit liegt im definierten Zeitraum.', 0);
                } else {
                    $this->SendDebug(__FUNCTION__, 'Aktuelle Zeit liegt außerhalb des definierten Zeitraum.', 0);
                    $result = false;
                }
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'Aktuelle Zeit liegt im definierten Zeitraum.', 0);
        }
        return $result;
    }
}