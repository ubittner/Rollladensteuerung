<?php

/** @noinspection PhpUnused */

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Rollladensteuerung/tree/master/Rollladensteuerung
 */

declare(strict_types=1);

trait RS_actuator
{
    public function DetermineBlindActuatorVariables(): void
    {
        $id = $this->ReadPropertyInteger('Actuator');
        if ($id == 0 || !@IPS_ObjectExists($id)) {
            return;
        }
        $moduleID = IPS_GetInstance($id)['ModuleInfo']['ModuleID'];
        if ($moduleID !== self::HOMEMATIC_DEVICE_GUID) {
            return;
        }
        $result = true;
        $deviceType = $this->ReadPropertyInteger('DeviceType');
        $children = IPS_GetChildrenIDs($id);
        if (!empty($children)) {
            foreach ($children as $child) {
                $ident = IPS_GetObject($child)['ObjectIdent'];
                switch ($deviceType) {
                    case 1: # HM-LC-Bl1-FM, Channel 1
                        switch ($ident) {
                            case 'LEVEL':
                                IPS_SetProperty($this->InstanceID, 'ActuatorBlindPosition', $child);
                                IPS_SetProperty($this->InstanceID, 'ActuatorControl', $child);
                                break;

                            case 'WORKING':
                                IPS_SetProperty($this->InstanceID, 'ActuatorActivityStatus', $child);
                                break;

                        }
                        break;

                    case 2: # HmIP-BROLL, Channel 4
                    case 3: # HmIP-FROLL, Channel 4
                        switch ($ident) {
                            case 'LEVEL':
                                IPS_SetProperty($this->InstanceID, 'ActuatorBlindPosition', $child);
                                IPS_SetProperty($this->InstanceID, 'ActuatorControl', $child);
                                break;

                            case 'PROCESS':
                                IPS_SetProperty($this->InstanceID, 'ActuatorActivityStatus', $child);
                                break;

                        }
                        break;

                    default:
                        $result = false;

                }
            }
        }
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
        if ($result) {
            echo 'Die Variablen wurden erfolgreich ermittelt!';
        } else {
            echo "Es ist ein Fehler aufgetreten,\nbitte Konfiguration prüfen!";
        }
    }
}