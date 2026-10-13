<svg class="icone" aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
@switch($n)
  @case('casa')<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/>@break
  @case('lupa')<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>@break
  @case('pessoa')<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>@break
  @case('ajuda')<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 115 0c0 1.5-2.5 2-2.5 4"/><path d="M12 17h.01"/>@break
  @case('play')<path d="M7 4.5v15l13-7.5z" fill="currentColor" stroke="none"/>@break
  @case('check')<path d="M5 12.5l4.5 4.5L19 7.5"/>@break
  @case('cadeado')<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>@break
  @case('coracao')<path d="M12 20s-7-4.5-7-10a4 4 0 017-2.5A4 4 0 0119 10c0 5.5-7 10-7 10z"/>@break
  @case('arquivo')<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5"/>@break
  @case('seta')<path d="M15 5l-7 7 7 7"/>@break
@endswitch
</svg>
