<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'LaraSVN')</title>
    <script src="{{ asset('build/theme.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('build/app.css') }}">
    <script type="module" src="{{ asset('build/code.js') }}"></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="app-header">
    <a class="brand" href="{{ url('/') }}"><span class="brand-mark" aria-hidden="true">L</span>LaraSVN</a>
    <label class="appearance" hidden>Appearance
        <select id="appearance"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select>
    </label>
</header>
<div class="workspace">
    <aside class="repository-nav" aria-label="Repository navigation">
        <nav aria-label="Repositories">
            <a href="{{ route('repositories') }}">All repositories</a>
            @foreach($navigationRepositories as $name => $configuration)
                <a href="{{ route('listing', ['repname' => $name, 'path' => '/']) }}" @if(($repository ?? null) === $name) aria-current="true" @endif>{{ $name }}</a>
            @endforeach
        </nav>
        @isset($repository)
            @if(array_key_exists($repository, $navigationRepositories))
                @php($rootParams = ['repname' => $repository, 'path' => '/', 'rev' => $revision ?? 0, 'peg' => $revision ?? 0])
                <p class="nav-heading section-label">Repository tools</p>
                <nav aria-label="Repository tools">
                    <a href="{{ route('listing', $rootParams) }}">Browse root</a>
                    <a href="{{ route('log', $rootParams) }}">Repository history</a>
                    <a href="{{ route('search', $rootParams) }}">Find files</a>
                    <a href="{{ route('compare', ['repname' => $repository]) }}">Compare snapshots</a>
                </nav>
            @endif
        @endisset
    </aside>
    <main id="main" tabindex="-1">@yield('content')</main>
</div>
</body>
</html>
