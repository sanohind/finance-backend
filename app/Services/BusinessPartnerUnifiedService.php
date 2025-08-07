<?php

namespace App\Services;

use App\Models\Local\Partner;

class BusinessPartnerUnifiedService
{
    /**
     * Get all related bp_codes (parent & child) for unified search.
     */
    public function getUnifiedBpCodes($bpCode)
    {
        $base = preg_replace('/-\d+$/', '', $bpCode);
        return Partner::where('bp_code', 'like', $base . '%')->pluck('bp_code');
    }

    /**
     * Get all related Partner models (parent & child).
     */
    public function getUnifiedPartners($bpCode)
    {
        $base = preg_replace('/-\d+$/', '', $bpCode);
        return Partner::where('bp_code', 'like', $base . '%')->get();
    }

    /**
     * Update parent-child relationship for existing data (migration helper).
     */
    public function updateParentChildRelation()
    {
        $partners = Partner::all();
        foreach ($partners as $partner) {
            if (preg_match('/-\d+$/', $partner->bp_code)) {
                $base = preg_replace('/-\d+$/', '', $partner->bp_code);
                $partner->parent_bp_code = $base;
                $partner->save();
            }
        }
    }
}