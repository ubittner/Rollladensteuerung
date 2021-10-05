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
        $deviceType = $this->ReadPropertyInteger('DeviceType');
        $children = IPS_GetChildrenIDs($id);
        if (!empty($children)) {
            foreach ($children as $child) {
                $ident = IPS_GetObject($child)['ObjectIdent'];
                switch ($deviceType) {
                    case 1: # HM-LC-Bl1-FM
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

                    case 2: # HmIP-BROLL
                    case 3: # HmIP-FROLL
                        switch ($ident) {
                            case 'LEVEL':
                                IPS_SetProperty($this->InstanceID, 'ActuatorBlindPosition', $child);
                                break;

                            case 'PROCESS':
                                IPS_SetProperty($this->InstanceID, 'ActuatorActivityStatus', $child);
                                break;

                        }
                        break;

                }
            }
        }
        //Homematic IP uses also Channel 4
        if ($deviceType == 2 || $deviceType == 3) {
            //Actuator control level is on channel 4
            $config = json_decode(IPS_GetConfiguration($id));
            $address = strstr($config->Address, ':', true) . ':4';
            $instances = IPS_GetInstanceListByModuleID(self::HOMEMATIC_DEVICE_GUID);
            if (!empty($instances)) {
                foreach ($instances as $instance) {
                    $config = json_decode(IPS_GetConfiguration($instance));
                    if ($config->Address == $address) {
                        $children = IPS_GetChildrenIDs($instance);
                        if (!empty($children)) {
                            foreach ($children as $child) {
                                $ident = IPS_GetObject($child)['ObjectIdent'];
                                if ($ident == 'LEVEL') {
                                    IPS_SetProperty($this->InstanceID, 'ActuatorControl', $child);
                                }
                            }
                        }
                    }
                }
            }
        }
        if (IPS_HasChanges($this->InstanceID)) {
            IPS_ApplyChanges($this->InstanceID);
        }
        echo 'Die Variablen wurden erfolgreich ermittelt!';
    }
}