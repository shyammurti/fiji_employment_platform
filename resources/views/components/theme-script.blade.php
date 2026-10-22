{{-- Runs before first paint so a dark-mode page never flashes white. --}}
<script>
    try {
        const theme = localStorage.getItem('theme');
        if (theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    } catch (error) { /* storage blocked: follow the light default */ }
</script>
