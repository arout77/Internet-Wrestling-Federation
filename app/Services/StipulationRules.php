<?php
namespace App\Services;

class StipulationRules
{
    public const NONE              = null;
    public const CAGE              = 'cage';
    public const LADDER            = 'ladder';
    public const I_QUIT            = 'i_quit'; // renamed from 'submission'
    public const LAST_MAN_STANDING = 'last_man_standing';
    public const NO_DQ             = 'no_dq'; // new

    public static function getAllowedVictoryMethods(?string $stipulation): array
    {
        return match ($stipulation) {
            self::I_QUIT            => ['submission'], // I Quit match ends only by submission
            self::LAST_MAN_STANDING => ['knockout'],
            self::CAGE              => ['pinfall', 'submission', 'escape'],
            self::LADDER            => ['retrieve'],
            self::NO_DQ             => ['pinfall', 'submission', 'weapon_strike'], // weapon strike as win
            default                 => ['pinfall', 'submission'],
        };
    }

    public static function getLabel(?string $stipulation): string
    {
        return match ($stipulation) {
            self::CAGE              => 'Steel Cage Match',
            self::LADDER            => 'Ladder Match',
            self::I_QUIT            => 'I Quit Match',
            self::LAST_MAN_STANDING => 'Last Man Standing',
            self::NO_DQ             => 'No Disqualification Match',
            default                 => '',
        };
    }

    public static function isNoDQ(?string $stipulation): bool
    {
        return $stipulation === self::NO_DQ;
    }
}
