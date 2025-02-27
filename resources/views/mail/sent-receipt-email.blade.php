{{-- <x-mail::header :url="$url" hidden/> --}}
<x-mail::message>
{{-- header --}}
<h2>Dear Respective In-Charge,</h2>

A new notification Invoice Receipt has been confirmed by PT Sanoh Indonesia. Please login to check the same.

{{-- Content --}}
<x-mail::panel>
    <p>Supplier : {{ $data['bp_code'] }} - {{ $data['partner_address'] }}</p>
    <p>Invoice Number : {{ $data['inv_no'] }}</p>
    <p>Status : {{ $data['status'] }}</p>
    <p>Invoice Payment Date : {{ $data['plan_date'] }}</p>
    <p>Invoice Total Amount : {{ $data['total_amount'] }}</p>
</x-mail::panel>

{{-- <x-mail::button> --}}
{{-- <p>View Invoice Receipt</p> --}}
{{-- </x-mail::button> --}}

{{-- Footer --}}
Thanks,<br>
<p>PT. SANOH INDONESIA</p>
{{-- {{ config('app.name') }} --}}
<br>
<p>Note : This is a system generated e-mail. We request that you do not reply to this mail ID.</p>
</x-mail::message>
