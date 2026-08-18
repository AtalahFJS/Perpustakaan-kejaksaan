@props(['href', 'aktif' => false])

<a href="{{ $href }}"
   @class([
       'rounded-md px-3 py-1.5 font-medium whitespace-nowrap transition',
       'bg-white/15 text-white' => $aktif,
       'text-korps-100 hover:bg-white/10 hover:text-white' => ! $aktif,
   ])>
    {{ $slot }}
</a>
