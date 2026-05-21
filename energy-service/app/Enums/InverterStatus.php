<?php

declare(strict_types=1);

namespace App\Enums;

enum InverterStatus: string
{
    case WaitingForOperation    = '100';
    case SelfTest               = '101';
    case Normal                 = '102';
    case RecoverableFault       = '103';
    case PermanentFault         = '104';
    case FirmwareUpgrade        = '105';
    case EpsDetection           = '106';
    case OffGrid                = '107';
    case SelfTestItalian        = '108';
    case SleepMode              = '109';
    case StandbyMode            = '110';
    case PvWakeUpBatteryMode    = '111';
    case GeneratorDetection     = '112';
    case GeneratorMode          = '113';
    case FastShutdownStandby    = '114';
    case VppMode                = '130';
    case TouSelfUse             = '131';
    case TouCharging            = '132';
    case TouDischarging         = '133';
    case TouBatteryOff          = '134';
    case TouPeakShaving         = '135';
    case GeneratorNormal        = '136';
    case BatteryExpansion       = '137';
    case OnGridBatteryHeating   = '138';
    case EpsBatteryHeating      = '139';
    case NormalModeR1           = '141';
    case NormalModeR2           = '142';
    case NormalModeR3           = '143';
    case NormalModeR4           = '144';
    case NormalModeR5           = '145';
    case NormalModeR6           = '146';
    case NormalModeR7           = '147';
    case NormalModeSS           = '148';
    case SelfUse                = '150';
    case ForceTimeUse           = '151';
    case BackUpMode             = '152';
    case FeedinPriority         = '153';
    case DemandMode             = '154';
    case ConstantPowerMode      = '155';
    case OpenAdrMode            = '160';

    public function isOperational(): bool
    {
        return in_array($this, [
            self::Normal,
            self::SelfUse,
            self::ForceTimeUse,
            self::TouSelfUse,
            self::TouCharging,
            self::TouDischarging,
            self::FeedinPriority,
        ], strict: true);
    }

    public function isFault(): bool
    {
        return in_array($this, [
            self::RecoverableFault,
            self::PermanentFault,
        ], strict: true);
    }
}
