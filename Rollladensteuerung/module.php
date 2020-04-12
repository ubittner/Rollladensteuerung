<?php

/*
 * @module      Rollladensteuerung
 *
 * @prefix      RS
 *
 * @file        module.php
 *
 * @developer   Ulrich Bittner
 * @copyright   (c) 2019, 2020
 * @license     CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     1.01-90
 * @date        2020-04-12, 18:00, 1586710800
 * @review      2020-04-12, 18:00
 *
 * @see         https://github.com/ubittner/Rollladensteuerung
 *
 * @guids       Library
 *              {BC55481C-37C5-4232-979F-6494F9F6893C}
 *
 *              Rollladensteuerung
 *             	{CEAE98E6-EFB4-4D0E-A7DE-3A9764F12DB6}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Rollladensteuerung extends IPSModule
{
    // Helper
    use RS_backupRestore;
    use RS_blindActuator;
    use RS_dayDetection;
    use RS_doorWindowSensors;
    use RS_emergencySensors;
    use RS_sunriseSunset;
    use RS_twilightDetection;
    use RS_weeklySchedule;

    // Constants
    private const MINIMUM_DELAY_MILLISECONDS = 100;
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterProperties();

        // Create profiles
        $this->CreateProfiles();

        // Register variables
        $this->RegisterVariables();

        // Register timers
        $this->RegisterTimers();

        // Register Attributes
        $this->RegisterAttributes();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Validate configuration
        $this->ValidateConfiguration();

        // Disable timers
        $this->DisableTimers();

        // Create links
        $this->CreateLinks();

        // Set options
        $this->SetOptions();

        // Position presets
        $this->UpdatePositionPresets();

        // Register messages
        $this->RegisterMessages();

        // Check condition
        $this->CheckActualCondition();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'));
        // Registered messages
        $registeredVariables = $this->GetMessageList();
        foreach ($registeredVariables as $senderID => $messageID) {
            if (!IPS_ObjectExists($senderID)) {
                foreach ($messageID as $messageType) {
                    $this->UnregisterMessage($senderID, $messageType);
                }
                continue;
            } else {
                $senderName = IPS_GetName($senderID);
                $description = $senderName;
                $parentID = IPS_GetParent($senderID);
                if (is_int($parentID) && $parentID != 0 && @IPS_ObjectExists($parentID)) {
                    $description = IPS_GetName($parentID);
                }
            }
            switch ($messageID) {
                case [10001]:
                    $messageDescription = 'IPS_KERNELSTARTED';
                    break;

                case [10603]:
                    $messageDescription = 'VM_UPDATE';
                    break;

                case [10803]:
                    $messageDescription = 'EM_UPDATE';
                    break;

                default:
                    $messageDescription = 'keine Bezeichnung';
            }
            $formData->actions[1]->items[0]->values[] = [
                'Description'        => $description,
                'SenderID'           => $senderID,
                'SenderName'         => $senderName,
                'MessageID'          => $messageID,
                'MessageDescription' => $messageDescription];
        }
        return json_encode($formData);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, 'SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        $timeStamp = date('d.m.Y, H:i:s');
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            // $Data[0] = actual value
            // $Data[1] = difference to last value
            // $Data[2] = last value
            case VM_UPDATE:
                // Sunrise
                $id = $this->ReadPropertyInteger('Sunrise');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'Die Variable Sonnenaufgang hat sich geändert! ' . $timeStamp, 0);
                            $this->TriggerSunriseSunset(0);
                        }
                    }
                }
                // Sunset
                $id = $this->ReadPropertyInteger('Sunset');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'Die Variable Sonnenuntergang hat sich geändert! ' . $timeStamp, 0);
                            $this->TriggerSunriseSunset(1);
                        }
                    }
                }
                // Is day
                $id = $this->ReadPropertyInteger('IsDay');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, "'Ist es Tag' - Erkennung: " . json_encode($Data[0]), 0);
                            // Night
                            if (!$Data[0]) {
                                $this->SendDebug(__FUNCTION__, 'Es ist Nacht (' . $timeStamp . ')', 0);
                            }
                            // Day
                            if ($Data[0]) {
                                $this->SendDebug(__FUNCTION__, 'Es ist Tag (' . $timeStamp . ')', 0);
                            }
                            $this->TriggerIsDayDetection($Data[0]);
                        }
                    }
                }
                // Twilight state
                $id = $this->ReadPropertyInteger('TwilightState');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'Dämmerungsstatus: ' . json_encode($Data[0]), 0);
                            $this->SetValue('TwilightState', $Data[0]);
                            // Day
                            if (!$Data[0]) {
                                $this->SendDebug(__FUNCTION__, 'Es ist Tag (' . $timeStamp . ')', 0);
                            }
                            // Night
                            if ($Data[0]) {
                                $this->SendDebug(__FUNCTION__, 'Es ist Nacht (' . $timeStamp . ')', 0);
                            }
                            $this->TriggerTwilightDetection($Data[0]);
                        }
                    }
                }
                // Actuator state level
                $id = $this->ReadPropertyInteger('ActuatorStateLevel');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'Die Rollladenposition hat sich geändert', 0);
                            $this->UpdateBlindSlider();
                        }
                    }
                }
                // Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (array_search($SenderID, array_column($doorWindowSensors, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->CheckDoorWindowSensors();
                            $state = (bool) $Data[0];
                            $this->TriggerActionByDoorWindowSensor($state);
                        }
                    }
                }
                // Emergency sensors
                $id = json_decode($this->ReadPropertyString('EmergencySensors'), true);
                if (!empty($id)) {
                    if (array_search($SenderID, array_column($id, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->TriggerEmergencySensor($SenderID);
                        }
                    }
                }
                break;

            // $Data[0] = last run
            // $Data[1] = next run
            case EM_UPDATE:
                // Weekly schedule
                $this->TriggerActionByWeeklySchedule();
                break;

        }
    }

    //#################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AutomaticMode':
                $this->ToggleAutomaticMode($Value);
                break;

            case 'BlindSlider':
                $this->SetBlindSlider($Value);
                break;

            case 'PositionPresets':
                $this->SetValue($Ident, $Value);
                $this->SetBlindLevel(floatval($Value / 100), false);
                break;

            case 'SleepMode':
                $this->ToggleSleepMode($Value);
                break;

        }
    }

    /**
     * Toggles the automatic mode.
     *
     * @param bool $State
     */
    public function ToggleAutomaticMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $State = ' . json_encode($State), 0);
        $this->SetValue('AutomaticMode', $State);
        $this->CheckActualCondition();
        // Weekly schedule visibility
        $id = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableWeeklySchedule') && $State) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }
        // Sunset visibility
        $id = @IPS_GetLinkIDByName('Sonnenuntergang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableSunset') && $State) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }
        // Sunrise visibility
        $id = @IPS_GetLinkIDByName('Sonnenaufgang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableSunrise') && $State) {
                $hide = false;
            }
            IPS_SetHidden($id, $hide);
        }
    }

    /**
     * Sets the blind slider.
     *
     * @param float $Level
     */
    public function SetBlindSlider(float $Level): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $Level = ' . $Level, 0);
        $minimumDifference = $this->CheckMinimumPositionDifference($Level);
        if ($minimumDifference == -1) {
            $this->SetValue('BlindSlider', $this->GetActualLevel());
            return;
        }
        $this->SetValue('BlindSlider', $Level);
        $updateSetpointPosition = false;
        if ($this->ReadPropertyBoolean('ManualControlUpdateSetpointPosition')) {
            $this->SetValue('SetpointPosition', $Level * 100);
            $updateSetpointPosition = true;
        }
        $this->WriteAttributeBoolean('UpdateSetpointPosition', $updateSetpointPosition);
        $this->SetBlindLevel($Level, false);
    }

    /**
     * Toggles the sleep mode.
     *
     * @param bool $State
     * false    = sleep mode off
     * true     = sleep mode on
     */
    public function ToggleSleepMode(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        $this->SendDebug(__FUNCTION__, 'Parameter $State = ' . json_encode($State), 0);
        if ($State) {
            if ($this->GetValue('AutomaticMode')) {
                $this->SetValue('SleepMode', $State);
                // Duration from hours to seconds
                $duration = $this->ReadPropertyInteger('SleepDuration') * 60 * 60;
                // Set timer interval
                $this->SetTimerInterval('DeactivateSleepMode', $duration * 1000);
                $timestamp = time() + $duration;
                $this->SetValue('SleepModeTimer', date('d.m.Y, H:i:s', ($timestamp)));
            }
        } else {
            $this->SetValue('SleepMode', $State);
            $this->DisableTimers();
            $this->CheckActualCondition();
        }
    }

    protected function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    //##################### Private

    private function RegisterProperties(): void
    {
        // Visibility
        $this->RegisterPropertyBoolean('EnableAutomaticMode', true);
        $this->RegisterPropertyBoolean('EnableSunrise', true);
        $this->RegisterPropertyBoolean('EnableSunset', true);
        $this->RegisterPropertyBoolean('EnableWeeklySchedule', true);
        $this->RegisterPropertyBoolean('EnableSetpointPosition', true);
        $this->RegisterPropertyBoolean('EnableBlindSlider', true);
        $this->RegisterPropertyBoolean('EnablePositionPresets', true);
        $this->RegisterPropertyString('PositionPresets', '[{"Value":0,"Text":"0 %"},{"Value":25,"Text":"25 %"}, {"Value":50,"Text":"50 %"},{"Value":75,"Text":"75 %"},{"Value":100,"Text":"100 %"}]');
        $this->RegisterPropertyBoolean('EnableSleepMode', true);
        $this->RegisterPropertyBoolean('EnableDoorWindowState', true);
        $this->RegisterPropertyBoolean('EnableTwilightState', true);
        $this->RegisterPropertyBoolean('EnableSleepModeTimer', true);

        // Blind actuator
        $this->RegisterPropertyInteger('ActuatorInstance', 0);
        $this->RegisterPropertyInteger('DeviceType', 0);
        $this->RegisterPropertyInteger('ActuatorProperty', 0);
        $this->RegisterPropertyInteger('ExecutionDelay', 3);
        $this->RegisterPropertyInteger('ActuatorStateLevel', 0);
        $this->RegisterPropertyInteger('ActuatorStateProcess', 0);
        $this->RegisterPropertyInteger('ActuatorControlLevel', 0);
        $this->RegisterPropertyBoolean('CheckMinimumBlindPositionDifference', false);
        $this->RegisterPropertyInteger('BlindPositionDifference', 5);

        // Manual control
        $this->RegisterPropertyBoolean('ManualControlUpdateSetpointPosition', true);

        // Automatic
        $this->RegisterPropertyBoolean('AdjustBlindLevel', false);

        // Sunrise and sunset
        $this->RegisterPropertyInteger('Sunrise', 0);
        $this->RegisterPropertyInteger('SunrisePosition', 50);
        $this->RegisterPropertyInteger('Sunset', 0);
        $this->RegisterPropertyInteger('SunsetPosition', 50);
        $this->RegisterPropertyBoolean('SunriseSunsetUpdateSetpointPosition', true);

        // Weekly schedule
        $this->RegisterPropertyInteger('WeeklySchedule', 0);
        $this->RegisterPropertyInteger('BlindPositionClosed', 0);
        $this->RegisterPropertyInteger('BlindPositionOpened', 100);
        $this->RegisterPropertyInteger('BlindPositionShading', 50);
        $this->RegisterPropertyBoolean('WeeklyScheduleUpdateSetpointPosition', true);

        // Is day
        $this->RegisterPropertyInteger('IsDay', 0);
        $this->RegisterPropertyBoolean('IsDayAdjustPosition', false);
        $this->RegisterPropertyInteger('IsDayPosition', 100);
        $this->RegisterPropertyBoolean('IsNightAdjustPosition', false);
        $this->RegisterPropertyInteger('IsNightPosition', 0);
        $this->RegisterPropertyBoolean('IsDayUpdateSetpointPosition', false);

        // Twilight state
        $this->RegisterPropertyInteger('TwilightState', 0);
        $this->RegisterPropertyInteger('TwilightPositionDay', 100);
        $this->RegisterPropertyBoolean('TwilightSetpointPositionDay', true);
        $this->RegisterPropertyInteger('TwilightPositionNight', 40);
        $this->RegisterPropertyBoolean('TwilightUpdateSetpointPosition', false);

        // Sleep duration
        $this->RegisterPropertyInteger('SleepDuration', 12);

        // Door and window sensors
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyBoolean('LockoutProtection', false);
        $this->RegisterPropertyInteger('LockoutPosition', 60);
        $this->RegisterPropertyBoolean('OpenBlind', false);
        $this->RegisterPropertyInteger('OpenBlindPosition', 50);
        $this->RegisterPropertyBoolean('CloseBlind', false);
        $this->RegisterPropertyInteger('CloseBlindPosition', 100);
        $this->RegisterPropertyBoolean('CloseBlindSetpointPosition', false);

        // Emergency sensors
        $this->RegisterPropertyString('EmergencySensors', '[]');
    }

    private function CreateProfiles(): void
    {
        // Automatic mode
        $profile = 'RS.' . $this->InstanceID . '.AutomaticMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Execute', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Clock', 0x00FF00);

        // Setpoint position
        $profile = 'RS.' . $this->InstanceID . '.SetpointPosition';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Jalousie');
        IPS_SetVariableProfileText($profile, '', ' %');
        IPS_SetVariableProfileValues($profile, 0, 100, 0);

        // Blind slider
        $profile = 'RS.' . $this->InstanceID . '.BlindSlider.Reversed';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 2);
        }
        IPS_SetVariableProfileIcon($profile, 'Intensity');
        IPS_SetVariableProfileText($profile, '', '%');
        IPS_SetVariableProfileDigits($profile, 1);
        IPS_SetVariableProfileValues($profile, 0, 1, 0.05);

        // Position presets
        $profile = 'RS.' . $this->InstanceID . '.PositionPresets';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Intensity');

        // Sleep mode
        $profile = 'RS.' . $this->InstanceID . '.SleepMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Sleep', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Sleep', 0x0000FF);

        // Door and window state
        $profile = 'RS.' . $this->InstanceID . '.DoorWindowState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', 'Window', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', 'Window', 0x0000FF);

        // Twilight state
        $profile = 'RS.' . $this->InstanceID . '.TwilightState';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Es ist Tag', 'Sun', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Es ist Nacht', 'Moon', 0x0000FF);
    }

    private function UpdatePositionPresets(): void
    {
        // Position presets
        $profile = 'RS.' . $this->InstanceID . '.PositionPresets';
        $associations = IPS_GetVariableProfile($profile)['Associations'];
        if (!empty($associations)) {
            foreach ($associations as $association) {
                // Delete
                IPS_SetVariableProfileAssociation($profile, $association['Value'], '', '', -1);
            }
        }
        $positionPresets = json_decode($this->ReadPropertyString('PositionPresets'));
        if (!empty($positionPresets)) {
            foreach ($positionPresets as $preset) {
                // Create
                IPS_SetVariableProfileAssociation($profile, $preset->Value, $preset->Text, '', -1);
            }
        }
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['AutomaticMode', 'SetpointPosition', 'BlindSlider.Reversed', 'PositionPresets', 'SleepMode', 'DoorWindowState', 'TwilightState'];
        foreach ($profiles as $profile) {
            $profileName = 'RS.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Automatic mode
        $profile = 'RS.' . $this->InstanceID . '.AutomaticMode';
        $this->RegisterVariableBoolean('AutomaticMode', 'Automatik', $profile, 0);
        $this->EnableAction('AutomaticMode');

        // Setpoint position
        $profile = 'RS.' . $this->InstanceID . '.SetpointPosition';
        $this->RegisterVariableInteger('SetpointPosition', 'Soll-Position', $profile, 4);

        // Blind slider
        $profile = 'RS.' . $this->InstanceID . '.BlindSlider.Reversed';
        $this->RegisterVariableFloat('BlindSlider', 'Rollladen', $profile, 5);
        $this->EnableAction('BlindSlider');
        IPS_SetIcon($this->GetIDForIdent('BlindSlider'), 'Jalousie');

        // Position presets
        $profile = 'RS.' . $this->InstanceID . '.PositionPresets';
        $this->RegisterVariableInteger('PositionPresets', 'Position Voreinstellungen', $profile, 6);
        $this->EnableAction('PositionPresets');

        // Sleep mode
        $profile = 'RS.' . $this->InstanceID . '.SleepMode';
        $this->RegisterVariableBoolean('SleepMode', 'Ruhe-Modus', $profile, 7);
        $this->EnableAction('SleepMode');

        // Door and window state
        $profile = 'RS.' . $this->InstanceID . '.DoorWindowState';
        $this->RegisterVariableBoolean('DoorWindowState', 'Tür- / Fensterstatus', $profile, 8);

        // Twilight state
        $profile = 'RS.' . $this->InstanceID . '.TwilightState';
        $this->RegisterVariableBoolean('TwilightState', 'Dämmerungsstatus', $profile, 9);

        // Sleep mode timer info
        $this->RegisterVariableString('SleepModeTimer', 'Ruhe-Modus Timer', '', 10);
        $id = $this->GetIDForIdent('SleepModeTimer');
        IPS_SetIcon($id, 'Clock');
    }

    private function CreateLinks(): void
    {
        // Sunrise
        $targetID = $this->ReadPropertyInteger('Sunrise');
        $linkID = @IPS_GetLinkIDByName('Sonnenaufgang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 1);
            IPS_SetName($linkID, 'Sonnenaufgang');
            IPS_SetIcon($linkID, 'Sun');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }

        // Sunset
        $targetID = $this->ReadPropertyInteger('Sunset');
        $linkID = @IPS_GetLinkIDByName('Sonnenuntergang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 1);
            IPS_SetName($linkID, 'Sonnenuntergang');
            IPS_SetIcon($linkID, 'Moon');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
        // Weekly schedule
        $targetID = $this->ReadPropertyInteger('WeeklySchedule');
        $linkID = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            // Check for existing link
            if ($linkID === false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 1);
            IPS_SetName($linkID, 'Wochenplan');
            IPS_SetIcon($linkID, 'Calendar');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if ($linkID !== false) {
                IPS_SetHidden($linkID, true);
            }
        }
    }

    private function SetOptions(): void
    {
        // Automatic mode
        IPS_SetHidden($this->GetIDForIdent('AutomaticMode'), !$this->ReadPropertyBoolean('EnableAutomaticMode'));

        // Setpoint position
        IPS_SetHidden($this->GetIDForIdent('SetpointPosition'), !$this->ReadPropertyBoolean('EnableSetpointPosition'));

        // Sunrise
        $id = @IPS_GetLinkIDByName('Sonnenaufgang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('Sunrise');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($this->ReadPropertyBoolean('EnableSunrise') && $this->GetValue('AutomaticMode')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }

        // Sunset
        $id = @IPS_GetLinkIDByName('Sonnenuntergang', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('Sunset');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($this->ReadPropertyBoolean('EnableSunset') && $this->GetValue('AutomaticMode')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }

        // Weekly schedule
        $id = @IPS_GetLinkIDByName('Wochenplan', $this->InstanceID);
        if ($id !== false) {
            $hide = true;
            if ($this->ValidateEventPlan()) {
                if ($this->ReadPropertyBoolean('EnableWeeklySchedule') && $this->GetValue('AutomaticMode')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }

        // Blind slider
        IPS_SetHidden($this->GetIDForIdent('BlindSlider'), !$this->ReadPropertyBoolean('EnableBlindSlider'));

        // Position presets
        IPS_SetHidden($this->GetIDForIdent('PositionPresets'), !$this->ReadPropertyBoolean('EnablePositionPresets'));

        // Sleep Mode
        IPS_SetHidden($this->GetIDForIdent('SleepMode'), !$this->ReadPropertyBoolean('EnableSleepMode'));

        // Door and window state
        IPS_SetHidden($this->GetIDForIdent('DoorWindowState'), !$this->ReadPropertyBoolean('EnableDoorWindowState'));

        // Twilight state
        $id = $this->GetIDForIdent('TwilightState');
        $twilightState = $this->ReadPropertyInteger('TwilightState');
        $use = false;
        if ($twilightState != 0 && IPS_ObjectExists($twilightState)) {
            $use = $this->ReadPropertyBoolean('EnableTwilightState');
        }
        IPS_SetHidden($id, !$use);

        // Sleep mode timer info
        IPS_SetHidden($this->GetIDForIdent('SleepModeTimer'), !$this->ReadPropertyBoolean('EnableSleepModeTimer'));
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('DeactivateSleepMode', 0, 'RS_ToggleSleepMode(' . $this->InstanceID . ', false);');
    }

    private function DisableTimers(): void
    {
        $this->SetTimerInterval('DeactivateSleepMode', 0);
        $this->SetValue('SleepModeTimer', '-');
    }

    private function RegisterAttributes(): void
    {
        $this->RegisterAttributeBoolean('UpdateSetpointPosition', true);
    }

    private function ResetAttributes(): void
    {
        $this->WriteAttributeBoolean('UpdateSetpointPosition', true);
    }

    private function UnregisterMessages(): void
    {
        foreach ($this->GetMessageList() as $id => $registeredMessage) {
            foreach ($registeredMessage as $messageType) {
                if ($messageType == VM_UPDATE) {
                    $this->UnregisterMessage($id, VM_UPDATE);
                }
                if ($messageType == EM_UPDATE) {
                    $this->UnregisterMessage($id, EM_UPDATE);
                }
            }
        }
    }

    private function RegisterMessages(): void
    {
        // Unregister first
        $this->UnregisterMessages();

        // Sunrise
        $id = $this->ReadPropertyInteger('Sunrise');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }

        // Sunset
        $id = $this->ReadPropertyInteger('Sunset');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }

        // Weekly schedule
        $id = $this->ReadPropertyInteger('WeeklySchedule');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, EM_UPDATE);
        }

        // Is day
        if ($this->ReadPropertyBoolean('IsDayAdjustPosition') || $this->ReadPropertyBoolean('IsNightAdjustPosition')) {
            $id = $this->ReadPropertyInteger('IsDay');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
            }
        }

        // Twilight state
        $id = $this->ReadPropertyInteger('TwilightState');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }

        // Actuator state level
        $id = $this->ReadPropertyInteger('ActuatorStateLevel');
        if ($id != 0 && IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Actuator state process
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0 && IPS_ObjectExists($id)) {
            $this->RegisterMessage($id, VM_UPDATE);
        }
        // Door and window sensors
        $doorWindowSensors = $this->GetDoorWindowSensors();
        if (!empty($doorWindowSensors)) {
            foreach ($doorWindowSensors as $id) {
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $this->RegisterMessage($id, VM_UPDATE);
                }
            }
        }
    }

    private function ValidateConfiguration(): void
    {
        $state = 102;
        $deviceType = $this->ReadPropertyInteger('DeviceType');
        // Blind actuator
        $id = $this->ReadPropertyInteger('ActuatorInstance');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Instanz Rollladenaktor ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                    $this->LogMessage('Konfiguration: Instanz Rollladenaktor GUID ungültig!', KL_ERROR);
                    $state = 200;
                } else {
                    // Check channel
                    $config = json_decode(IPS_GetConfiguration($id));
                    $address = strstr($config->Address, ':', false);
                    switch ($deviceType) {
                        // HM
                        case 1:
                            if ($address != ':1') {
                                $this->LogMessage('Konfiguration: Instanz Rollladenaktor Kanal ungültig!', KL_ERROR);
                                $state = 200;
                            }
                            break;

                        // HmIP
                        case 2:
                        case 3:
                            if ($address != ':3') {
                                $this->LogMessage('Konfiguration: Instanz Rollladenaktor Kanal ungültig!', KL_ERROR);
                                $state = 200;
                            }
                            break;

                    }
                }
            }
        }
        // Actuator state level
        $id = $this->ReadPropertyInteger('ActuatorStateLevel');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Variable Rollladenposition ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $parent = IPS_GetParent($id);
                if ($parent == 0) {
                    $this->LogMessage('Konfiguration: Variable Rollladenposition, keine übergeordnete ID gefunden!', KL_ERROR);
                    $state = 200;
                } else {
                    $instance = IPS_GetInstance($parent);
                    $moduleID = $instance['ModuleInfo']['ModuleID'];
                    if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                        $this->LogMessage('Konfiguration: Variable Rollladenposition GUID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        // Check channel
                        $config = json_decode(IPS_GetConfiguration($parent));
                        $address = strstr($config->Address, ':', false);
                        switch ($deviceType) {
                            // HM
                            case 1:
                                if ($address != ':1') {
                                    $this->LogMessage('Konfiguration: Variable Rollladenposition Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                            // HmIP
                            case 2:
                            case 3:
                                if ($address != ':3') {
                                    $this->LogMessage('Konfiguration: Variable Rollladenposition Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                        }
                    }
                }
                $ident = IPS_GetObject($id)['ObjectIdent'];
                if ($ident != 'LEVEL') {
                    $this->LogMessage('Konfiguration: Variable Rollladenposition IDENT ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Actuator state process
        $id = $this->ReadPropertyInteger('ActuatorStateProcess');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Variable Aktivitätszustand ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $parent = IPS_GetParent($id);
                if ($parent == 0) {
                    $this->LogMessage('Konfiguration: Variable Aktivitätszustand, keine übergeordnete ID gefunden!', KL_ERROR);
                    $state = 200;
                } else {
                    $instance = IPS_GetInstance($parent);
                    $moduleID = $instance['ModuleInfo']['ModuleID'];
                    if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                        $this->LogMessage('Konfiguration: Variable Aktivitätszustand GUID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        // Check channel
                        $config = json_decode(IPS_GetConfiguration($parent));
                        $address = strstr($config->Address, ':', false);
                        switch ($deviceType) {
                            // HM
                            case 1:
                                if ($address != ':1') {
                                    $this->LogMessage('Konfiguration: Variable Aktivitätszustand Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                            // HmIP
                            case 2:
                            case 3:
                                if ($address != ':3') {
                                    $this->LogMessage('Konfiguration: Variable Aktivitätszustand Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                        }
                    }
                }
                $ident = IPS_GetObject($id)['ObjectIdent'];
                switch ($deviceType) {
                    // HM
                    case 1:
                        if ($ident != 'WORKING') {
                            $this->LogMessage('Konfiguration: Variable Aktivitätszustand IDENT ungültig!', KL_ERROR);
                            $state = 200;
                        }
                        break;

                    // HmIP
                    case 2:
                    case 3:
                        if ($ident != 'PROCESS') {
                            $this->LogMessage('Konfiguration: Variable Aktivitätszustand IDENT ungültig!', KL_ERROR);
                            $state = 200;
                        }
                        break;
                }
            }
        }
        // Actuator control level
        $id = $this->ReadPropertyInteger('ActuatorControlLevel');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Konfiguration: Variable Rollladensteuerung ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $parent = IPS_GetParent($id);
                if ($parent == 0) {
                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung, keine übergeordnete ID gefunden!', KL_ERROR);
                    $state = 200;
                } else {
                    $instance = IPS_GetInstance($parent);
                    $moduleID = $instance['ModuleInfo']['ModuleID'];
                    if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
                        $this->LogMessage('Konfiguration: Variable Rollladensteuerung GUID ungültig!', KL_ERROR);
                        $state = 200;
                    } else {
                        // Check channel
                        $config = json_decode(IPS_GetConfiguration($parent));
                        $address = strstr($config->Address, ':', false);
                        switch ($deviceType) {
                            // HM
                            case 1:
                                if ($address != ':1') {
                                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                            // HmIP
                            case 2:
                            case 3:
                                if ($address != ':4') {
                                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung Kanal ungültig!', KL_ERROR);
                                    $state = 200;
                                }
                                break;

                        }
                    }
                }
                $ident = IPS_GetObject($id)['ObjectIdent'];
                if ($ident != 'LEVEL') {
                    $this->LogMessage('Konfiguration: Variable Rollladensteuerung IDENT ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }
        // Set state
        $this->SetStatus($state);
    }

    private function CheckActualCondition(): void
    {
        /*
         * 1. Twilight detection
         *      If we have twilight detection, the blind level is set based on the twilight position.
         *
         * 2. Weekly schedule
         *      If we don't use twilight detection, the weekly schedule is used and the blind level is set based on the action position.
         *
         * 3. Sunrise and sunset
         *      If we don't use twilight detection and weekly schedule, the blind level is set based on the sunrise or sunset position.
         */
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt (' . microtime(true) . ')', 0);
        // Update and setpoint position
        $level = $this->GetActualLevel();
        $this->SetValue('SetpointPosition', $level * 100);
        // Update blind slider
        $this->SetValue('BlindSlider', $level);
        // Check door and window sensors
        $this->CheckDoorWindowSensors();
        // Update twilight state
        $id = $this->ReadPropertyInteger('TwilightState');
        if ($id != 0 && IPS_ObjectExists($id)) {
            $this->SetValue('TwilightState', GetValueBoolean($id));
        }
        if (!$this->CheckModes(__FUNCTION__)) {
            return;
        }
        if ($this->ReadPropertyBoolean('AdjustBlindLevel')) {
            // Twilight state
            $id = $this->ReadPropertyInteger('TwilightState');
            if ($id != 0 && IPS_ObjectExists($id)) {
                $this->TriggerTwilightDetection(GetValueBoolean($id));
                return;
            }
            // Weekly schedule
            $action = $this->DetermineAction();
            if ($action != 0) {
                $this->TriggerActionByWeeklySchedule();
                return;
            }
            // Sunrise and sunset
            $sunrise = $this->ReadPropertyInteger('Sunrise');
            if ($sunrise != 0 && IPS_ObjectExists($sunrise)) {
                $sunriseTimestamp = GetValue($sunrise);
            }
            $sunset = $this->ReadPropertyInteger('Sunset');
            if ($sunset != 0 && IPS_ObjectExists($sunset)) {
                $sunsetTimestamp = GetValue($sunset);
            }
            if (isset($sunriseTimestamp) && isset($sunsetTimestamp)) {
                $timestamp = time();
                $sunriseDiff = abs($sunriseTimestamp - $timestamp);
                $sunsetDiff = abs($sunsetTimestamp - $timestamp);
                $mode = 1;
                if ($sunriseDiff < $sunsetDiff) {
                    $mode = 0;
                }
                $this->TriggerSunriseSunset($mode);
            }
        }
    }

    /**
     * Checks the automatic and sleep mode.
     *
     * @param string $MethodName
     *
     * @return bool
     * false    = automatic mode is disabled or sleep mode is enabled
     * true     = automatic mode is enabled and sleep mode is disbaled
     */
    private function CheckModes(string $MethodName): bool
    {
        $result = true;
        // Check automatic mode
        if (!$this->GetValue('AutomaticMode')) {
            $this->SendDebug($MethodName, 'Abbruch, Die Automatik ist ausgeschaltet!', 0);
            $result = false;
        }
        // Check sleep mode
        if ($this->GetValue('SleepMode')) {
            $this->SendDebug($MethodName, 'Abbruch, Der Ruhe-Modus ist eingeschaltet!', 0);
            $result = false;
        }
        return $result;
    }
}