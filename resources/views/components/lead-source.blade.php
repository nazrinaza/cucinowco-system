@props(['label' => 'Direct'])
<span {{ $attributes->class(['lead-source', 'direct' => $label === 'Direct']) }}>{{ $label }}</span>
