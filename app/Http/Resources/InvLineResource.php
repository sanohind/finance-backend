<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvLineResource extends JsonResource
{
    /**
     * Format decimal value by removing trailing zeros
     * Example: 0.1000 → 0.1, 0.1230 → 0.123
     *
     * @param mixed $value
     * @return mixed
     */
    private function formatDecimal($value)
    {
        if ($value === null) {
            return null;
        }

        // Convert to string first to preserve precision
        $stringValue = (string) $value;

        // Remove trailing zeros and decimal point if all decimals are zeros
        $formatted = rtrim(rtrim($stringValue, '0'), '.');

        // If the result is empty or just a minus sign, return 0
        if ($formatted === '' || $formatted === '-') {
            return 0;
        }

        // Convert to float for JSON encoding (JSON will automatically format without trailing zeros)
        $floatValue = (float) $formatted;

        // If it's a whole number, return as integer for cleaner output
        if ($floatValue == (int) $floatValue) {
            return (int) $floatValue;
        }

        return $floatValue;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'inv_line_id'          => $this->inv_line_id,
            'po_no'                => $this->po_no,
            'bp_id'                => $this->bp_id,
            'bp_name'              => $this->bp_name,
            'currency'             => $this->currency,
            'po_type'              => $this->po_type,
            'po_reference'         => $this->po_reference,
            'po_line'              => $this->po_line,
            'po_sequence'          => $this->po_sequence,
            'po_receipt_sequence'  => $this->po_receipt_sequence,
            'actual_receipt_date'  => $this->actual_receipt_date,
            'actual_receipt_year'  => $this->actual_receipt_year,
            'actual_receipt_period'=> $this->actual_receipt_period,
            'receipt_no'           => $this->receipt_no,
            'receipt_line'         => $this->receipt_line,
            'gr_no'                => $this->gr_no,
            'packing_slip'         => $this->packing_slip,
            'item_no'              => $this->item_no,
            'ics_code'             => $this->ics_code,
            'ics_part'             => $this->ics_part,
            'part_no'              => $this->part_no,
            'item_desc'            => $this->item_desc,
            'item_group'           => $this->item_group,
            'item_type'            => $this->item_type,
            'item_type_desc'       => $this->item_type_desc,
            'request_qty'          => $this->formatDecimal($this->request_qty),
            'actual_receipt_qty'   => $this->formatDecimal($this->actual_receipt_qty),
            'approve_qty'          => $this->formatDecimal($this->approve_qty),
            'unit'                 => $this->unit,
            'receipt_amount'       => $this->receipt_amount,
            'receipt_unit_price'   => $this->receipt_unit_price,
            'is_final_receipt'     => $this->is_final_receipt,
            'is_confirmed'         => $this->is_confirmed,
            'inv_doc_no'           => $this->inv_doc_no,
            'inv_doc_date'         => $this->inv_doc_date,
            'inv_qty'              => $this->inv_qty,
            'inv_amount'           => $this->inv_amount,
            'inv_supplier_no'      => $this->inv_supplier_no,
            'inv_due_date'         => $this->inv_due_date,
            'payment_doc'          => $this->payment_doc,
            'payment_doc_date'     => $this->payment_doc_date,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
        ];
    }
}
