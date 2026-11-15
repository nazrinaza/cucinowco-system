<?php

namespace App\Support;

/**
 * Banks an agent can receive commission payouts into (IBG / DuitNow transfer).
 *
 * Keys are stored on the agent; labels are what agents and HQ see. Keep keys stable:
 * renaming a label is safe, changing a key orphans saved payout details.
 */
class MalaysianBanks
{
    public const LIST = [
        'affin' => 'Affin Bank',
        'affin_islamic' => 'Affin Islamic Bank',
        'agrobank' => 'Agrobank',
        'al_rajhi' => 'Al Rajhi Bank Malaysia',
        'alliance' => 'Alliance Bank',
        'alliance_islamic' => 'Alliance Islamic Bank',
        'ambank' => 'AmBank',
        'ambank_islamic' => 'AmBank Islamic',
        'aeon_bank' => 'AEON Bank',
        'bank_islam' => 'Bank Islam Malaysia',
        'bank_muamalat' => 'Bank Muamalat',
        'bank_of_china' => 'Bank of China (Malaysia)',
        'bank_rakyat' => 'Bank Rakyat',
        'bsn' => 'Bank Simpanan Nasional (BSN)',
        'boost_bank' => 'Boost Bank',
        'ccb' => 'China Construction Bank (Malaysia)',
        'cimb' => 'CIMB Bank',
        'cimb_islamic' => 'CIMB Islamic Bank',
        'citibank' => 'Citibank Malaysia',
        'gxbank' => 'GXBank',
        'hong_leong' => 'Hong Leong Bank',
        'hong_leong_islamic' => 'Hong Leong Islamic Bank',
        'hsbc' => 'HSBC Bank Malaysia',
        'hsbc_amanah' => 'HSBC Amanah Malaysia',
        'icbc' => 'ICBC (Malaysia)',
        'kaf_digital' => 'KAF Digital Bank',
        'kfh' => 'Kuwait Finance House (Malaysia)',
        'maybank' => 'Maybank',
        'maybank_islamic' => 'Maybank Islamic',
        'mbsb' => 'MBSB Bank',
        'ocbc' => 'OCBC Bank (Malaysia)',
        'ocbc_al_amin' => 'OCBC Al-Amin Bank',
        'public_bank' => 'Public Bank',
        'public_islamic' => 'Public Islamic Bank',
        'rhb' => 'RHB Bank',
        'rhb_islamic' => 'RHB Islamic Bank',
        'ryt_bank' => 'Ryt Bank',
        'standard_chartered' => 'Standard Chartered Malaysia',
        'standard_chartered_saadiq' => 'Standard Chartered Saadiq',
        'uob' => 'UOB Malaysia',
    ];

    public static function label(?string $key): ?string
    {
        return $key === null ? null : (self::LIST[$key] ?? $key);
    }
}
