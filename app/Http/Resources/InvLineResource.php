<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'inv_line_id'           => $this->inv_line_id,
            'po_no'                 => $this->po_no,
            'supplier_id'           => $this->supplier_id,
            'supplier'              => $this->supplier,
            'po_date'               => $this->po_date,
            'po_qty'                => $this->po_qty,
            'po_price'              => $this->po_price,
            'currency'              => $this->currency,
            'rate'                  => $this->rate,
            'receipt_no'            => $this->receipt_no,
            'receipt_date'          => $this->receipt_date,
            'receipt_line'          => $this->receipt_line,
            'item'                  => $this->item,
            'item_desc'             => $this->item_desc,
            'old_partno'            => $this->old_partno,
            'receipt_qty'           => $this->receipt_qty,
            'receipt_unit'          => $this->receipt_unit,
            'packing_slip'          => $this->packing_slip,
            'receipt_status'        => $this->receipt_status,
            'warehouse'             => $this->warehouse,
            'extend_price'          => $this->extend_price,
            'extend_price_idr'      => $this->extend_price_idr,
            'inv_doc'               => $this->inv_doc,
            'inv_date'              => $this->inv_date, // Tenggat Tanggal Pembayaran
            'supplier_invoice'      => $this->supplier_invoice,
            'supplier_invoice_date' => $this->supplier_invoice_date,
            'doc_code'              => $this->doc_code,
            'doc_no'                => $this->doc_no,
            'doc_date'              => $this->doc_date,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }
}
