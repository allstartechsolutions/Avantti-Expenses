@props(['size' => 44])

{{--
    The mark at the top of an outgoing e-mail: the company's own icon if one
    was uploaded, the product's otherwise. It sits on a white pad because the
    header bar behind it is dark, and the company name stays underneath in
    text — most clients block remote images until the reader allows them, so
    the e-mail must still say who it is from with the picture missing.
--}}
<div style="margin: 0 0 12px; line-height: 0;">
    <img src="{{ App\Services\Branding::iconUrl() }}"
         alt="{{ App\Services\Branding::name() }}"
         width="{{ $size }}"
         height="{{ $size }}"
         style="display: inline-block; width: {{ $size }}px; height: {{ $size }}px; object-fit: contain; background-color: #ffffff; border-radius: 8px; padding: 5px; border: 0;">
</div>
