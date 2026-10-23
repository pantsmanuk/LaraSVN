<section class="properties" aria-labelledby="properties-heading">
    <h2 id="properties-heading">Subversion properties</h2>
    @if($properties === [])
        <p class="muted">No properties are set on this path.</p>
    @else
        <dl class="property-list">
            @foreach($properties as $name => $value)
                <div><dt>{{ $name }}</dt><dd><pre>{{ $value }}</pre></dd></div>
            @endforeach
        </dl>
    @endif
</section>
