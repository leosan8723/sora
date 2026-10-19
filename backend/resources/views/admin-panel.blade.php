{{-- The private admin panel shell. Rendered only by AdminPanelController --}}
{{-- at the configured ADMIN_PATH. Tags the document as noindex and injects --}}
{{-- the private base path into the SPA bootstrap. --}}
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <meta name="theme-color" content="#ffffff" />
    <title>سورا — مدیر سامانه</title>
    <script>
      // Private navigation base injected by the backend. This value is never
      // bundled into public frontend assets (see AdminPanelController).
      window.__ADMIN_BASE__ = {!! json_encode($adminBase, JSON_UNESCAPED_SLASHES) !!};
    </script>
    @if (isset($dev))
      {{-- Vite dev server asset loading with the standard React refresh preamble. --}}
      <script type="module">
        import RefreshRuntime from '{{ $dev['reactRefresh'] }}'
        RefreshRuntime.injectIntoGlobalHook(window)
        window.$RefreshReg$ = () => {}
        window.$RefreshSig$ = () => (type) => type
        window.__vite_plugin_react_preamble_installed__ = true
      </script>
      <script type="module" src="{{ $dev['client'] }}"></script>
    @endif
    @foreach ($cssUrls as $cssUrl)
      <link rel="stylesheet" href="{{ $cssUrl }}" />
    @endforeach
</head>
<body>
    <div id="root"></div>
    <script type="module" src="{{ $scriptUrl }}"></script>
</body>
</html>
