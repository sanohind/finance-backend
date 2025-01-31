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
            'inv_no' => $this->inv_no,
            'inv_date' => $this->inv_date,
            'inv_faktur' => $this->inv_faktur,
            'inv_supplier' => $this->inv_supplier,
            'total_dpp' => $this->total_dpp,
            'tax' => $this->tax,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'reason' => $this->reason,
            'bp_code' => $this->bp_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
