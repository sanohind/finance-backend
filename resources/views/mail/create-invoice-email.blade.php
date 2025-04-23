{{-- <x-mail::header :url="$url" hidden/> --}}
<x-mail::message>
{{-- header --}}
<h2>Dear Respective In-Charge,</h2>

Notification New Invoice has been Create by {{ $data['partner_address'] }}. Please login to check the same.

{{-- Content --}}
<x-mail::panel>
    <p>Supplier : {{ $data['bp_code'] }} - {{ $data['partner_address'] }}</p>
    <p>Invoice Number : {{ $data['inv_no'] }}</p>
    <p>Status : {{ $data['status'] }}</p>
    <p>Invoice Plant Date : {{ $data['plan_date'] }}</p>
    <p>Invoice Total Amount : Rp {{ number_format($data['total_amount'], 0, ',', '.') }}</p>
</x-mail::panel>

{{-- <x-mail::button> --}}
{{-- <p>View Invoice Receipt</p> --}}
{{-- </x-mail::button> --}}

{{-- Footer --}}
Thanks,<br>
<p>{{ $data['partner_address'] }}</p>
{{-- {{ config('app.name') }} --}}
<br>
<p>Note : This is a system generated e-mail. We request that you do not reply to this mail ID.</p>
</x-mail::message>
