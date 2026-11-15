@php $def = $map[$value] ?? null; @endphp
<span class="badge {{ $def['badge'] ?? 'text-bg-light' }}">{{ $def ? __($def['title']) : $value }}</span>
