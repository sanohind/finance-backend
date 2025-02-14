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
            'inv_date'          => $this->inv_date,
            'inv_faktur'        => $this->inv_faktur,
            'inv_supplier'      => $this->inv_supplier,
            'ppn_id'            => $this->ppn_id,
            'tax_description'   => $this->tax_description,
            'tax_base_amount'   => $this->tax_base_amount,
            'tax_amount'        => $this->tax_amount,
            'pph_id'            => $this->pph,
            'pph_description'   => $this->pph_description,
            'pph_base_amount'   => $this->pph_base_amount,
            'pph_amount'        => $this->pph_amount,
            'total_dpp'         => $this->total_dpp,
            'total_amount'      => $this->total_amount,
            'status'            => $this->status,
            'reason'            => $this->reason,
            'created_by'        => $this->created_by,
            'updated_by'        => $this->updated_by,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }
}
