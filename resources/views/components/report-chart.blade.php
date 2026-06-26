@props([
    'config',
    'height' => '18rem',
])

<div
    x-data="reportChart(@js($config))"
    x-init="init()"
    class="relative"
    style="height: {{ $height }};"
>
    <canvas x-ref="canvas" class="h-full w-full"></canvas>
</div>
