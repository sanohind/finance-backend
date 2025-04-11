<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvHeaderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'inv_no'            => $this->inv_no,
            'bp_code'           => $this->bp_code,
            'plan_date'         => $this->plan_date,
            'actual_date'       => $this->actual_date,
            'inv_faktur'        => $this->inv_faktur,
            'inv_faktur_date'   => $this->inv_faktur_date,
            'ppn_id'            => $this->ppn_id,
            'tax_base_amount'   => $this->tax_base_amount,
            'tax_amount'        => $this->tax_amount,
            'pph_id'            => $this->pph,
            'pph_base_amount'   => $this->pph_base_amount,
            'pph_amount'        => $this->pph_amount,
            'total_dpp'         => $this->total_dpp,
            'total_amount'      => $this->total_amount,
            'status'            => $this->status,
            'reason'            => $this->reason,
            'receipt_number'    => $this->receipt_number,
            'receipt_path'      => $this->receipt_path,
            'created_by'        => $this->created_by,
            'updated_by'        => $this->updated_by,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
