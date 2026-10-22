{{--
    Inline help tooltip: a small (i) button that explains a field or figure.
    Opens on hover, keyboard focus or tap; Esc, scrolling or tapping elsewhere closes it.
    The bubble is positioned against the window (not its container), so tables
    and scrolling cards cannot clip it. It sits above the icon, or below when
    there is no room.
    Usage: <label>Closing date <x-tip>Applications stop after this date.</x-tip></label>
--}}
@props(['label' => 'More information', 'align' => null])
@php($id = 'tip-'.\Illuminate\Support\Str::random(8))
<span class="tip relative inline-flex align-middle"
      x-data="{
          open: false,
          show() { this.open = true; this.$nextTick(() => this.place()); },
          place() {
              const icon = this.$refs.icon.getBoundingClientRect(), bubble = this.$refs.bubble;
              const width = bubble.offsetWidth, height = bubble.offsetHeight, gap = 8;
              const left = Math.min(Math.max(icon.left + icon.width / 2 - width / 2, gap), window.innerWidth - width - gap);
              const above = icon.top - height - gap;
              bubble.style.left = left + 'px';
              bubble.style.top = (above >= gap ? above : icon.bottom + gap) + 'px';
          },
      }"
      @mouseenter="show()" @mouseleave="open = false" @keydown.escape.stop="open = false" @click.outside="open = false"
      @scroll.window.passive="open = false" @resize.window="open = false">
    <button type="button" x-ref="icon" class="tip-trigger" aria-label="{{ $label }}" aria-describedby="{{ $id }}" :aria-expanded="open.toString()"
            @focus="show()" @blur="open = false" @click.prevent="show()">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10" cy="10" r="7.5"/><path stroke-linecap="round" d="M10 9v4.5M10 6.6h.01"/></svg>
    </button>
    <span id="{{ $id }}" role="tooltip" x-ref="bubble" x-show="open" x-cloak x-transition.opacity.duration.120ms class="tip-bubble">{{ $slot }}</span>
</span>
