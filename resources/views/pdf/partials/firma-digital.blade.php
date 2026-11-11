{{-- Sello visible de la firma digital del empleador (el PDF va firmado con su certificado). --}}
@if (! empty($firma))
<br><span style="display: inline-block; margin-top: 3px; padding: 2px 6px; border: 1px solid #15803d; border-radius: 3px; color: #15803d; font-size: 7.5px; line-height: 1.3;">Firmado digitalmente por {{ $firma['titular'] }}@if (! empty($firma['documento'])) · {{ $firma['documento'] }}@endif<br>{{ $firma['fecha'] }}</span>
@endif
