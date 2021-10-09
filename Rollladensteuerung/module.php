<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Rollladensteuerung/tree/master/Rollladensteuerung
 */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Rollladensteuerung extends IPSModule
{
    //Helper
    use RS_actuator;
    use RS_backupRestore;
    use RS_checkConditions;
    use RS_doorWindowSensors;
    use RS_emergencyTriggers;
    use RS_isDayDetection;
    use RS_moveBlind;
    use RS_presenceDetection;
    use RS_sunriseSunset;
    use RS_switchingTime;
    use RS_trigger;
    use RS_twilightDetection;
    use RS_weeklySchedule;

    //Constants
    private const LIBRARY_GUID = '{5A853C5C-2A05-BBCB-1E8A-26E91974C977}';
    private const MODULE_NAME = 'Rollladensteuerung';
    private const MODULE_PREFIX = 'UBRS';
    private const HOMEMATIC_DEVICE_GUID = '{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}';
    private const DEVICE_DELAY_MILLISECONDS = 250;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        #################### Properties

        //Functions
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        $this->RegisterPropertyBoolean('EnableAutomaticMode', true);
        $this->RegisterPropertyBoolean('EnableSleepMode', true);
        $this->RegisterPropertyInteger('SleepDuration', 12);
        $this->RegisterPropertyBoolean('EnableBlindMode', true);
        $this->RegisterPropertyString('CloseBlind', '[{"LabelCloseBlind":"","UseSettings":true,"Position":0,"UpdateSetpointPosition":false,"UpdateLastPosition":false,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0,"CheckDoorWindowStatus":0}]');
        $this->RegisterPropertyBoolean('EnableStopFunction', true);
        $this->RegisterPropertyString('Timer', '[{"LabelTimer":"","UseSettings":true,"Position":50,"UpdateSetpointPosition":false,"UpdateLastPosition":false,"Duration":30,"DurationUnit":1,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0,"CheckDoorWindowStatus":0,"LabelOperationalAction":"","OperationalAction":1,"DefinedPosition":0}]');
        $this->RegisterPropertyString('OpenBlind', '[{"LabelOpenBlind":"","UseSettings":true,"Position":100,"UpdateSetpointPosition":false,"UpdateLastPosition":false,"LabelSwitchingConditions":"","CheckPositionDifference":0,"CheckLockoutProtection":0,"CheckAutomaticMode":0,"CheckSleepMode":0,"CheckBlindMode":0,"CheckIsDay":0,"CheckTwilight":0,"CheckPresence":0,"CheckDoorWindowStatus":0}]');
        $this->RegisterPropertyBoolean('EnableBlindSlider', true);
        $this->RegisterPropertyBoolean('EnableBlindSliderManualChange', true);
        $this->RegisterPropertyBoolean('BlindSliderUpdateSetpointPosition', false);
        $this->RegisterPropertyBoolean('BlindSliderUpdateLastPosition', false);
        $this->RegisterPropertyBoolean('EnablePositionPresets', true);
        $this->RegisterPropertyString('PositionPresets', '[{"Value":0,"Text":"Geschlossen"}, {"Value":60,"Text":"Beschattet"},{"Value":80,"Text":"Belüfttet"},{"Value":100,"Text":"Geöffnet"}]');
        $this->RegisterPropertyBoolean('PositionPresetsUpdateButton', false);
        $this->RegisterPropertyBoolean('PositionPresetsUpdateSetpointPosition', false);
        $this->RegisterPropertyBoolean('PositionPresetsUpdateLastPosition', false);
        $this->RegisterPropertyBoolean('EnableSetpointPosition', true);
        $this->RegisterPropertyBoolean('EnableSetpointPositionManualChange', false);
        $this->RegisterPropertyBoolean('EnableLastPosition', true);
        $this->RegisterPropertyBoolean('EnableLastPositionManualChange', false);
        $this->RegisterPropertyBoolean('EnableDoorWindowStatus', true);
        $this->RegisterPropertyBoolean('EnableBlindModeTimer', true);
        $this->RegisterPropertyBoolean('EnableSleepModeTimer', true);
        $this->RegisterPropertyBoolean('EnableNextSwitchingTime', true);
        $this->RegisterPropertyBoolean('EnableSunrise', true);
        $this->RegisterPropertyBoolean('EnableSunset', true);
        $this->RegisterPropertyBoolean('EnableWeeklySchedule', true);
        $this->RegisterPropertyBoolean('EnableIsDay', true);
        $this->RegisterPropertyBoolean('EnableTwilight', true);
        $this->RegisterPropertyBoolean('EnablePresence', true);
        //Actuator
        $this->RegisterPropertyInteger('Actuator', 0);
        $this->RegisterPropertyInteger('DeviceType', 2);
        $this->RegisterPropertyInteger('ActuatorProperty', 1);
        $this->RegisterPropertyInteger('ActuatorBlindPosition', 0);
        $this->RegisterPropertyBoolean('ActuatorUpdateBlindPosition', false);
        $this->RegisterPropertyBoolean('ActuatorUpdateSetpointPosition', false);
        $this->RegisterPropertyBoolean('ActuatorUpdateLastPosition', false);
        $this->RegisterPropertyInteger('ActuatorActivityStatus', 0);
        $this->RegisterPropertyInteger('ActuatorControl', 0);
        //Door and window status
        $this->RegisterPropertyString('DoorWindowSensors', '[]');
        $this->RegisterPropertyString('DoorWindowOpenAction', '[]');
        $this->RegisterPropertyString('DoorWindowCloseAction', '[]');
        //Switching times
        $this->RegisterPropertyString('SwitchingTimeOne', '{"hour":0,"minute":0,"second":0}');
        $this->RegisterPropertyString('SwitchingTimeOneActions', '[]');
        $this->RegisterPropertyString('SwitchingTimeTwo', '{"hour":0,"minute":0,"second":0}');
        $this->RegisterPropertyString('SwitchingTimeTwoActions', '[]');
        $this->RegisterPropertyString('SwitchingTimeThree', '{"hour":0,"minute":0,"second":0}');
        $this->RegisterPropertyString('SwitchingTimeThreeActions', '[]');
        $this->RegisterPropertyString('SwitchingTimeFour', '{"hour":0,"minute":0,"second":0}');
        $this->RegisterPropertyString('SwitchingTimeFourActions', '[]');
        //Sunrise and sunset
        $this->RegisterPropertyInteger('Sunrise', 0);
        $this->RegisterPropertyString('SunriseActions', '[]');
        $this->RegisterPropertyInteger('Sunset', 0);
        $this->RegisterPropertyString('SunsetActions', '[]');
        //Weekly schedule
        $this->RegisterPropertyInteger('WeeklySchedule', 0);
        $this->RegisterPropertyString('WeeklyScheduleActionOne', '[]');
        $this->RegisterPropertyString('WeeklyScheduleActionTwo', '[]');
        $this->RegisterPropertyString('WeeklyScheduleActionThree', '[]');
        //Is day
        $this->RegisterPropertyInteger('IsDay', 0);
        $this->RegisterPropertyString('NightAction', '[]');
        $this->RegisterPropertyString('DayAction', '[]');
        //Twilight
        $this->RegisterPropertyInteger('TwilightStatus', 0);
        $this->RegisterPropertyString('TwilightDayAction', '[]');
        $this->RegisterPropertyString('TwilightNightAction', '[]');
        //Presence and absence
        $this->RegisterPropertyInteger('PresenceStatus', 0);
        $this->RegisterPropertyString('AbsenceAction', '[]');
        $this->RegisterPropertyString('PresenceAction', '[]');
        //Triggers
        $this->RegisterPropertyString('Triggers', '[]');
        //Emergency triggers
        $this->RegisterPropertyString('EmergencyTriggers', '[]');

        #################### Variables

        //Automatic mode
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AutomaticMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Execute', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Clock', 0x00FF00);
        $this->RegisterVariableBoolean('AutomaticMode', 'Automatik', $profile, 10);
        $this->EnableAction('AutomaticMode');
        //Sleep mode
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.SleepMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Sleep', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'An', 'Sleep', 0x00FF00);
        $this->RegisterVariableBoolean('SleepMode', 'Ruhe-Modus', $profile, 20);
        $this->EnableAction('SleepMode');
        //Blind mode
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.BlindMode';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Shutter');
        IPS_SetVariableProfileAssociation($profile, 0, 'Schließen', '', 0x0000FF);
        IPS_SetVariableProfileAssociation($profile, 1, 'Stop', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 2, 'Timer', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 3, 'Öffnen', '', 0x00FF00);
        $this->RegisterVariableInteger('BlindMode', 'Rollladen', $profile, 30);
        $this->EnableAction('BlindMode');
        //Blind slider
        $id = @$this->GetIDForIdent('BlindSlider');
        $profile = '~Intensity.100';
        $this->RegisterVariableInteger('BlindSlider', 'Rollladenposition', $profile, 40);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('BlindSlider'), 'Jalousie');
        }
        $this->EnableAction('BlindSlider');
        //Position presets
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.PositionPresets';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileIcon($profile, 'Menu');
        $this->RegisterVariableInteger('PositionPresets', 'Position Voreinstellungen', $profile, 50);
        $this->EnableAction('PositionPresets');
        //Setpoint position
        $id = @$this->GetIDForIdent('SetpointPosition');
        $profile = '~Intensity.100';
        $this->RegisterVariableInteger('SetpointPosition', 'Soll-Position', $profile, 60);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('SetpointPosition'), 'Information');
        }
        //Last position
        $id = @$this->GetIDForIdent('LastPosition');
        $profile = '~Intensity.100';
        $this->RegisterVariableInteger('LastPosition', 'Letzte Position', $profile, 70);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('LastPosition'), 'Information');
        }
        //Door and window status
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.DoorWindowStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Geschlossen', 'Window', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Geöffnet', 'Window', 0x0000FF);
        $this->RegisterVariableBoolean('DoorWindowStatus', 'Tür- / Fensterstatus', $profile, 80);
        //Blind mode timer
        $id = @$this->GetIDForIdent('BlindModeTimer');
        $this->RegisterVariableString('BlindModeTimer', 'Rollladenposition bis', '', 90);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('BlindModeTimer'), 'Clock');
        }
        //Sleep mode timer
        $id = @$this->GetIDForIdent('SleepModeTimer');
        $this->RegisterVariableString('SleepModeTimer', 'Ruhe-Modus Timer', '', 100);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('SleepModeTimer'), 'Clock');
        }
        //Next switching time
        $id = @$this->GetIDForIdent('NextSwitchingTime');
        $this->RegisterVariableString('NextSwitchingTime', 'Nächste Schaltzeit', '', 110);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('NextSwitchingTime'), 'Information');
        }

        #################### Timers

        //Sleep mode timer
        $this->RegisterTimer('SleepMode', 0, self::MODULE_PREFIX . '_DeactivateSleepModeTimer(' . $this->InstanceID . ');');
        //Blind timer
        $this->RegisterTimer('StopBlindTimer', 0, self::MODULE_PREFIX . '_StopBlindTimer(' . $this->InstanceID . ');');
        //Switching timers
        $this->RegisterTimer('SwitchingTimeOne', 0, self::MODULE_PREFIX . '_ExecuteSwitchingTime(' . $this->InstanceID . ', 1);');
        $this->RegisterTimer('SwitchingTimeTwo', 0, self::MODULE_PREFIX . '_ExecuteSwitchingTime(' . $this->InstanceID . ', 2);');
        $this->RegisterTimer('SwitchingTimeThree', 0, self::MODULE_PREFIX . '_ExecuteSwitchingTime(' . $this->InstanceID . ', 3);');
        $this->RegisterTimer('SwitchingTimeFour', 0, self::MODULE_PREFIX . '_ExecuteSwitchingTime(' . $this->InstanceID . ', 4);');
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        #################### Options

        //Automatic mode
        IPS_SetHidden($this->GetIDForIdent('AutomaticMode'), !$this->ReadPropertyBoolean('EnableAutomaticMode'));
        //Sleep mode
        IPS_SetHidden($this->GetIDForIdent('SleepMode'), !$this->ReadPropertyBoolean('EnableSleepMode'));
        //Blind Mode
        IPS_SetHidden($this->GetIDForIdent('BlindMode'), !$this->ReadPropertyBoolean('EnableBlindMode'));
        //Blind mode timer
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.BlindMode';
        $associations = IPS_GetVariableProfile($profile)['Associations'];
        if (!$this->ReadPropertyBoolean('EnableStopFunction')) {
            //Delete
            $key = array_search(1, array_column($associations, 'Value'));
            if (is_int($key)) {
                IPS_SetVariableProfileAssociation($profile, 1, '', '', -1);
            }
        } else {
            IPS_SetVariableProfileAssociation($profile, 1, 'Stop', '', 0xFF0000);
        }
        if (!json_decode($this->ReadPropertyString('Timer'), true)[0]['UseSettings']) {
            //Delete
            $key = array_search(2, array_column($associations, 'Value'));
            if (is_int($key)) {
                IPS_SetVariableProfileAssociation($profile, 2, '', '', -1);
            }
        } else {
            IPS_SetVariableProfileAssociation($profile, 2, 'Timer', '', 0xFFFF00);
        }
        //Blind slider
        IPS_SetHidden($this->GetIDForIdent('BlindSlider'), !$this->ReadPropertyBoolean('EnableBlindSlider'));
        if (!$this->ReadPropertyBoolean('EnableBlindSliderManualChange')) {
            $this->DisableAction('BlindSlider');
        } else {
            $this->EnableAction('BlindSlider');
        }
        //Position presets
        IPS_SetHidden($this->GetIDForIdent('PositionPresets'), !$this->ReadPropertyBoolean('EnablePositionPresets'));
        //Setpoint position
        IPS_SetHidden($this->GetIDForIdent('SetpointPosition'), !$this->ReadPropertyBoolean('EnableSetpointPosition'));
        if (!$this->ReadPropertyBoolean('EnableSetpointPositionManualChange')) {
            $this->DisableAction('SetpointPosition');
        } else {
            $this->EnableAction('SetpointPosition');
        }
        //Last position
        IPS_SetHidden($this->GetIDForIdent('LastPosition'), !$this->ReadPropertyBoolean('EnableLastPosition'));
        if (!$this->ReadPropertyBoolean('EnableLastPositionManualChange')) {
            $this->DisableAction('LastPosition');
        } else {
            $this->EnableAction('LastPosition');
        }
        //Door and window status
        IPS_SetHidden($this->GetIDForIdent('DoorWindowStatus'), !$this->ReadPropertyBoolean('EnableDoorWindowStatus'));
        //Blind mode timer
        IPS_SetHidden($this->GetIDForIdent('BlindModeTimer'), !$this->ReadPropertyBoolean('EnableBlindModeTimer'));
        //Sleep mode timer
        IPS_SetHidden($this->GetIDForIdent('SleepModeTimer'), !$this->ReadPropertyBoolean('EnableSleepModeTimer'));
        //Next switching time
        $hide = !$this->ReadPropertyBoolean('EnableNextSwitchingTime');
        if (!$hide) {
            $properties = ['SwitchingTimeOneActions', 'SwitchingTimeTwoActions', 'SwitchingTimeThreeActions', 'SwitchingTimeFourActions'];
            $hide = true;
            foreach ($properties as $property) {
                $actions = json_decode($this->ReadPropertyString($property), true);
                foreach ($actions as $action) {
                    if ($action['UseSettings']) {
                        $hide = false;
                    }
                }
            }
        }
        IPS_SetHidden($this->GetIDForIdent('NextSwitchingTime'), $hide);
        //Sunrise
        $id = @IPS_GetLinkIDByName('Nächster Sonnenaufgang', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            $sunrise = false;
            foreach (json_decode($this->ReadPropertyString('SunriseActions'), true) as $sunriseAction) {
                if ($sunriseAction['UseSettings']) {
                    $sunrise = true;
                }
            }
            if ($sunrise) {
                $sunriseID = $this->ReadPropertyInteger('Sunrise');
                if ($sunriseID != 0 && @IPS_ObjectExists($sunriseID)) {
                    $hide = !$this->ReadPropertyBoolean('EnableSunrise');
                }
            }
            IPS_SetHidden($id, $hide);
        }
        //Sunset
        $id = @IPS_GetLinkIDByName('Nächster Sonnenuntergang', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            $sunset = false;
            foreach (json_decode($this->ReadPropertyString('SunriseActions'), true) as $sunsetAction) {
                if ($sunsetAction['UseSettings']) {
                    $sunset = true;
                }
            }
            if ($sunset) {
                $sunsetID = $this->ReadPropertyInteger('Sunrise');
                if ($sunsetID != 0 && @IPS_ObjectExists($sunsetID)) {
                    $hide = !$this->ReadPropertyBoolean('EnableSunset');
                }
            }
            IPS_SetHidden($id, $hide);
        }
        //Weekly schedule
        $id = @IPS_GetLinkIDByName('Nächstes Wochenplanereignis', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            if ($this->ReadPropertyBoolean('EnableWeeklySchedule')) {
                if ($this->ValidateWeeklySchedule()) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        //Is day
        $id = @IPS_GetLinkIDByName('Ist es Tag', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('IsDay');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                $profile = 'Location.' . $targetID . '.IsDay';
                if (!IPS_VariableProfileExists($profile)) {
                    IPS_CreateVariableProfile($profile, 0);
                    IPS_SetVariableProfileAssociation($profile, 0, 'Es ist Nacht', 'Moon', 0x0000FF);
                    IPS_SetVariableProfileAssociation($profile, 1, 'Es ist Tag', 'Sun', 0xFFFF00);
                    IPS_SetVariableCustomProfile($targetID, $profile);
                }
                if ($this->ReadPropertyBoolean('EnableIsDay')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        //Twilight
        $id = @IPS_GetLinkIDByName('Dämmerungsstatus', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('TwilightStatus');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($this->ReadPropertyBoolean('EnableTwilight')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }
        //Presence
        $id = @IPS_GetLinkIDByName('Anwesenheitsstatus', $this->InstanceID);
        if (is_int($id)) {
            $hide = true;
            $targetID = $this->ReadPropertyInteger('PresenceStatus');
            if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
                if ($this->ReadPropertyBoolean('EnablePresence')) {
                    $hide = false;
                }
            }
            IPS_SetHidden($id, $hide);
        }

        #################### References & message registrations

        //Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all message registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == EM_UPDATE) {
                    $this->UnregisterMessage($id, EM_UPDATE);
                }
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        //Register
        if (!$this->CheckMaintenanceMode()) {
            $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
            //Actuator blind position
            if ($this->ReadPropertyBoolean('ActuatorUpdateBlindPosition')) {
                $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
                if ($id != 0 && IPS_ObjectExists($id)) {
                    $this->RegisterReference($id);
                    $this->RegisterMessage($id, VM_UPDATE);
                }
            }
            //Door and window sensors
            foreach (json_decode($this->ReadPropertyString('DoorWindowSensors')) as $sensor) {
                if ($sensor->UseSettings) {
                    $id = $sensor->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $this->RegisterReference($id);
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
            //Sunrise
            $id = $this->ReadPropertyInteger('Sunrise');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            //Sunset
            $id = $this->ReadPropertyInteger('Sunset');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            //Weekly schedule
            $id = $this->ReadPropertyInteger('WeeklySchedule');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, EM_UPDATE);
            }
            //Is day
            $id = $this->ReadPropertyInteger('IsDay');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            //Twilight status
            $id = $this->ReadPropertyInteger('TwilightStatus');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            //Presence status
            $id = $this->ReadPropertyInteger('PresenceStatus');
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
            }
            //Triggers
            foreach (json_decode($this->ReadPropertyString('Triggers')) as $variable) {
                if ($variable->UseSettings) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $this->RegisterReference($id);
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
            //Emergency triggers
            foreach (json_decode($this->ReadPropertyString('EmergencyTriggers')) as $variable) {
                if ($variable->UseSettings) {
                    $id = $variable->ID;
                    if ($id != 0 && @IPS_ObjectExists($id)) {
                        $this->RegisterReference($id);
                        $this->RegisterMessage($id, VM_UPDATE);
                    }
                }
            }
        }

        #################### Position presets

        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.PositionPresets';
        // Delete
        foreach (IPS_GetVariableProfile($profile)['Associations'] as $association) {
            IPS_SetVariableProfileAssociation($profile, $association['Value'], '', '', -1);
        }
        foreach (json_decode($this->ReadPropertyString('PositionPresets')) as $preset) {
            // Create
            IPS_SetVariableProfileAssociation($profile, $preset->Value, $preset->Text, '', -1);
        }

        #################### Links

        //Sunrise
        $targetID = 0;
        $sunrise = $this->ReadPropertyInteger('Sunrise');
        if ($sunrise != 0 && @IPS_ObjectExists($sunrise)) {
            $targetID = $sunrise;
        }
        $linkID = @IPS_GetLinkIDByName('Nächster Sonnenaufgang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && $linkID == false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 120);
            IPS_SetName($linkID, 'Nächster Sonnenaufgang');
            IPS_SetIcon($linkID, 'Sun');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }
        //Sunset
        $targetID = 0;
        $sunset = $this->ReadPropertyInteger('Sunset');
        if ($sunset != 0 && @IPS_ObjectExists($sunset)) {
            $targetID = $sunset;
        }
        $linkID = @IPS_GetLinkIDByName('Nächster Sonnenuntergang', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && $linkID == false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 130);
            IPS_SetName($linkID, 'Nächster Sonnenuntergang');
            IPS_SetIcon($linkID, 'Moon');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }
        //Weekly schedule
        $targetID = $this->ReadPropertyInteger('WeeklySchedule');
        $linkID = @IPS_GetLinkIDByName('Nächstes Wochenplanereignis', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && $linkID == false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 140);
            IPS_SetName($linkID, 'Nächstes Wochenplanereignis');
            IPS_SetIcon($linkID, 'Calendar');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }
        //Is day
        $targetID = $this->ReadPropertyInteger('IsDay');
        $linkID = @IPS_GetLinkIDByName('Ist es Tag', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && $linkID == false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 150);
            IPS_SetName($linkID, 'Ist es Tag');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }
        //Twilight
        $targetID = $this->ReadPropertyInteger('TwilightStatus');
        $linkID = @IPS_GetLinkIDByName('Dämmerungsstatus', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && $linkID == false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 160);
            IPS_SetName($linkID, 'Dämmerungsstatus');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }
        //Presence
        $targetID = $this->ReadPropertyInteger('PresenceStatus');
        $linkID = @IPS_GetLinkIDByName('Anwesenheitsstatus', $this->InstanceID);
        if ($targetID != 0 && @IPS_ObjectExists($targetID)) {
            //Check for existing link
            if (!is_int($linkID) && $linkID == false) {
                $linkID = IPS_CreateLink();
            }
            IPS_SetParent($linkID, $this->InstanceID);
            IPS_SetPosition($linkID, 170);
            IPS_SetName($linkID, 'Anwesenheitsstatus');
            IPS_SetLinkTargetID($linkID, $targetID);
        } else {
            if (is_int($linkID)) {
                IPS_SetHidden($linkID, true);
            }
        }

        #################### Misc

        //Deactivate sleep mode
        $this->DeactivateSleepModeTimer();

        //Deactivate blind mode
        $this->DeactivateBlindModeTimer();

        //Set switching timers
        $this->SetSwitchingTimes();

        //Check door and windows
        $this->CheckDoorWindowSensors();

        //Update blind slider
        $this->UpdateBlindPosition();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        $profiles = ['AutomaticMode', 'SleepMode', 'BlindMode', 'PositionPresets', 'DoorWindowStatus'];
        foreach ($profiles as $profile) {
            $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                if ($this->CheckMaintenanceMode()) {
                    return;
                }

                //Actuator blind position
                $id = $this->ReadPropertyInteger('ActuatorBlindPosition');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $this->SendDebug(__FUNCTION__, 'Die Rollladenposition hat sich geändert.', 0);
                            $scriptText = self::MODULE_PREFIX . '_UpdateBlindPosition(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                //Door and window sensors
                $doorWindowSensors = json_decode($this->ReadPropertyString('DoorWindowSensors'), true);
                if (!empty($doorWindowSensors)) {
                    if (array_search($SenderID, array_column($doorWindowSensors, 'ID')) !== false) {
                        if ($Data[1]) {
                            $this->CheckDoorWindowSensors();
                        }
                    }
                }
                //Sunrise
                $sunrise = $this->ReadPropertyInteger('Sunrise');
                if ($sunrise != 0 && @IPS_ObjectExists($sunrise)) {
                    if ($SenderID == $sunrise) {
                        if ($Data[1]) {
                            $scriptText = self::MODULE_PREFIX . '_ExecuteSunriseSunsetAction(' . $this->InstanceID . ', ' . $SenderID . ', 0);';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                //Sunset
                $sunset = $this->ReadPropertyInteger('Sunset');
                if ($sunset != 0 && @IPS_ObjectExists($sunset)) {
                    if ($SenderID == $sunset) {
                        if ($Data[1]) {
                            $scriptText = self::MODULE_PREFIX . '_ExecuteSunriseSunsetAction(' . $this->InstanceID . ', ' . $SenderID . ', 1);';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                //Is day
                $id = $this->ReadPropertyInteger('IsDay');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $scriptText = self::MODULE_PREFIX . '_ExecuteIsDayDetection(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                //Twilight
                $id = $this->ReadPropertyInteger('TwilightStatus');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $scriptText = self::MODULE_PREFIX . '_ExecuteTwilightDetection(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                //Presence
                $id = $this->ReadPropertyInteger('PresenceStatus');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($SenderID == $id) {
                        if ($Data[1]) {
                            $scriptText = self::MODULE_PREFIX . '_ExecutePresenceDetection(' . $this->InstanceID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                //Triggers
                $triggers = json_decode($this->ReadPropertyString('Triggers'), true);
                if (!empty($triggers)) {
                    if (array_search($SenderID, array_column($triggers, 'ID')) !== false) {
                        $scriptText = self::MODULE_PREFIX . '_CheckTrigger(' . $this->InstanceID . ', ' . $SenderID . ');';
                        IPS_RunScriptText($scriptText);
                    }
                }
                //Emergency triggers
                $emergencyTriggers = json_decode($this->ReadPropertyString('EmergencyTriggers'), true);
                if (!empty($emergencyTriggers)) {
                    if (array_search($SenderID, array_column($emergencyTriggers, 'ID')) !== false) {
                        if ($Data[1]) {
                            $scriptText = self::MODULE_PREFIX . '_ExecuteEmergencyTrigger(' . $this->InstanceID . ', ' . $SenderID . ');';
                            IPS_RunScriptText($scriptText);
                        }
                    }
                }
                break;

            case EM_UPDATE:

                //$Data[0] = last run
                //$Data[1] = next run

                if ($this->CheckMaintenanceMode()) {
                    return;
                }

                // Weekly schedule
                $scriptText = self::MODULE_PREFIX . '_ExecuteWeeklyScheduleAction(' . $this->InstanceID . ');';
                IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        //Door and window sensors
        foreach (json_decode($this->ReadPropertyString('DoorWindowSensors')) as $variable) {
            $rowColor = '#FFC0C0'; # red
            $id = $variable->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                //Deactivated
                if (!$variable->UseSettings) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $form['elements'][2]['items'][1]['values'][] = ['rowColor' => $rowColor];
        }
        //Triggers
        foreach (json_decode($this->ReadPropertyString('Triggers')) as $variable) {
            $rowColor = '#FFC0C0'; # red
            $id = $variable->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                //Deactivated
                if (!$variable->UseSettings) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $form['elements'][9]['items'][1]['values'][] = ['rowColor' => $rowColor];
        }
        //EmergencyTriggers
        foreach (json_decode($this->ReadPropertyString('EmergencyTriggers')) as $variable) {
            $rowColor = '#FFC0C0'; # red
            $id = $variable->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                $rowColor = '#C0FFC0'; # light green
                //Deactivated
                if (!$variable->UseSettings) {
                    $rowColor = '#DFDFDF'; # grey
                }
            }
            $form['elements'][10]['items'][1]['values'][] = ['rowColor' => $rowColor];
        }
        //Status
        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $version = '[Version ' . $library['Version'] . '-' . $library['Build'] . ' vom ' . date('d.m.Y', $library['Date']) . ']';
        $form['status'] = [
            [
                'code'    => 101,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird erstellt',
            ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' ist aktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 103,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird gelöscht (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => self::MODULE_NAME . ' ist inaktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 200,
                'icon'    => 'inactive',
                'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ') ' . $version
            ]
        ];
        return json_encode($form);
    }

    public function ReloadConfiguration(): void
    {
        $this->ReloadForm();
    }

    public function ShowAllFunctions(bool $State): void
    {
        for ($i = 1; $i <= 17; $i++) {
            $this->UpdateFormField('Panel' . $i, 'expanded', $State);
        }
    }

    public function DeactivateSleepModeTimer(): void
    {
        $this->SetValue('SleepMode', false);
        $this->SetTimerInterval('SleepMode', 0);
        $this->SetValue('SleepModeTimer', '-');
    }

    public function CreateScriptExample(): void
    {
        $scriptID = IPS_CreateScript(0);
        IPS_SetName($scriptID, 'Beispielskript (Komfort-Rollladensteuerung #' . $this->InstanceID . ')');
        $scriptContent = "<?php\n\n// Methode:\n// " . self::MODULE_PREFIX . "_MoveBlind(integer \$InstanceID, integer \$Position, integer \$Duration, integer \$DurationUnit);\n\n### Beispiele:\n\n// Rollladen auf 0% schließen:\n" . self::MODULE_PREFIX . '_MoveBlind(' . $this->InstanceID . ", 0, 0, 0);\n\n// Rollladen für 180 Sekunden öffnen:\n" . self::MODULE_PREFIX . '_MoveBlind(' . $this->InstanceID . ", 100, 180, 0);\n\n// Rollladen für 5 Minuten öffnen:\n" . self::MODULE_PREFIX . '_MoveBlind(' . $this->InstanceID . ", 100, 5, 1);\n\n// Rollladen auf 70% öffnen:\n" . self::MODULE_PREFIX . '_MoveBlind(' . $this->InstanceID . ', 70, 0, 0);';
        IPS_SetScriptContent($scriptID, $scriptContent);
        IPS_SetParent($scriptID, $this->InstanceID);
        IPS_SetPosition($scriptID, 200);
        IPS_SetHidden($scriptID, true);
        if ($scriptID != 0) {
            echo 'Beispielskript wurde erfolgreich erstellt!';
        }
    }

    #################### Request action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'AutomaticMode':
                $this->ToggleAutomaticMode($Value);
                break;

            case 'SleepMode':
                $this->ToggleSleepMode($Value);
                break;

            case 'BlindMode':
                $this->ExecuteBlindMode($Value);
                break;

            case 'BlindSlider':
                $this->SetBlindSlider($Value);
                break;

            case 'PositionPresets':
                $this->ExecutePositionPreset($Value);
                break;

        }
    }

    public function ToggleAutomaticMode(bool $State): void
    {
        $this->SetValue('AutomaticMode', $State);
    }

    public function ToggleSleepMode(bool $State): void
    {
        $this->SetValue('SleepMode', $State);
        if ($State) {
            $this->SetSleepModeTimer();
        } else {
            $this->DeactivateSleepModeTimer();
        }
    }

    public function ExecuteBlindMode(int $Mode): void
    {
        switch ($Mode) {
            case 0: # Close
                $settings = json_decode($this->ReadPropertyString('CloseBlind'), true);
                $action = true;
                $mode = 0;
                break;

            case 1: # Stop
                $action = false;
                $this->SetValue('BlindMode', 1);
                $this->DeactivateBlindModeTimer();
                $this->StopBlindMoving();
                break;

            case 2: # Timer
                $settings = json_decode($this->ReadPropertyString('Timer'), true);
                $action = true;
                $mode = 2;
                break;

            case 3: # Open
                $settings = json_decode($this->ReadPropertyString('OpenBlind'), true);
                $action = true;
                $mode = 3;
                break;

        }
        //Trigger action
        if (isset($action) && isset($mode) && $action) {
            if (!empty($settings)) {
                foreach ($settings as $setting) {
                    if ($setting['UseSettings']) {
                        $position = intval($setting['Position']);
                        //Check conditions
                        $checkConditions = $this->CheckAllConditions(json_encode($setting));
                        if (!$checkConditions) {
                            return;
                        }
                        $this->SetValue('BlindMode', $mode);
                        if ($setting['UpdateSetpointPosition']) {
                            $this->SetValue('SetpointPosition', $position);
                        }
                        if ($setting['UpdateLastPosition']) {
                            $this->SetValue('LastPosition', $position);
                        }
                        $duration = 0;
                        $durationUnit = 0;
                        if ($mode == 2) { # Timer
                            $duration = $setting['Duration'];
                            $durationUnit = $setting['DurationUnit'];
                        }
                        $this->MoveBlind($position, $duration, $durationUnit);
                    }
                }
            }
        }
    }

    public function SetBlindSlider(int $Position): void
    {
        if ($this->ReadPropertyBoolean('BlindSliderUpdateSetpointPosition')) {
            $this->SetValue('SetpointPosition', $Position);
        }
        if ($this->ReadPropertyBoolean('BlindSliderUpdateLastPosition')) {
            $this->SetValue('LastPosition', $Position);
        }
        $this->MoveBlind(intval($Position));
    }

    public function ExecutePositionPreset(int $Position): void
    {
        $this->SetValue('PositionPresets', $Position);
        if ($this->ReadPropertyBoolean('PositionPresetsUpdateSetpointPosition')) {
            $this->SetValue('SetpointPosition', $Position);
        }
        if ($this->ReadPropertyBoolean('PositionPresetsUpdateLastPosition')) {
            $this->SetValue('LastPosition', $Position);
        }
        $this->MoveBlind($Position);
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function SetSleepModeTimer(): void
    {
        $this->SetValue('SleepMode', true);
        //Duration from hours to seconds
        $duration = $this->ReadPropertyInteger('SleepDuration') * 60 * 60;
        //Set timer interval
        $this->SetTimerInterval('SleepMode', $duration * 1000);
        $timestamp = time() + $duration;
        $this->SetValue('SleepModeTimer', date('d.m.Y, H:i:s', ($timestamp)));
    }

    private function SetClosestPositionPreset(int $Position): void
    {
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.PositionPresets';
        $associations = IPS_GetVariableProfile($profile)['Associations'];
        if (!empty($associations)) {
            $closestPreset = null;
            foreach ($associations as $association) {
                if ($closestPreset === null || abs($Position - $closestPreset) > abs($association['Value'] - $Position)) {
                    $closestPreset = $association['Value'];
                }
            }
        }
        if (isset($closestPreset)) {
            $this->SetValue('PositionPresets', $closestPreset);
        }
    }

    private function CheckAction(string $PropertyVariableName, string $PropertyActionName): bool
    {
        $result = false;
        foreach (json_decode($this->ReadPropertyString($PropertyActionName), true) as $action) {
            if ($action['UseSettings']) {
                $result = true;
            }
        }
        if ($result) {
            $id = $this->ReadPropertyInteger($PropertyVariableName);
            if ($id == 0 || !@IPS_ObjectExists($id)) {
                $result = false;
            }
        }
        return $result;
    }

    private function GetTimeStampString(int $Timestamp): string
    {
        $day = date('j', ($Timestamp));
        $month = date('F', ($Timestamp));
        switch ($month) {
            case 'January':
                $month = 'Januar';
                break;

            case 'February':
                $month = 'Februar';
                break;

            case 'March':
                $month = 'März';
                break;

            case 'April':
                $month = 'April';
                break;

            case 'May':
                $month = 'Mai';
                break;

            case 'June':
                $month = 'Juni';
                break;

            case 'July':
                $month = 'Juli';
                break;

            case 'August':
                $month = 'August';
                break;

            case 'September':
                $month = 'September';
                break;

            case 'October':
                $month = 'Oktober';
                break;

            case 'November':
                $month = 'November';
                break;

            case 'December':
                $month = 'Dezember';
                break;

        }
        $year = date('Y', ($Timestamp));
        $time = date('H:i:s', ($Timestamp));
        return $day . '. ' . $month . ' ' . $year . ' ' . $time;
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $text = 'Abbruch, der Wartungsmodus ist aktiv!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . $text, KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }
}