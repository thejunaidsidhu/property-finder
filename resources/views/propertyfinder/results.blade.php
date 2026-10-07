@extends('layouts.app')

@section('content')
<div class="container">
  <h2>Search Results</h2>

  @foreach($results as $platform => $listings)
    <h4>{{ strtoupper($platform) }} Results:</h4>
    @forelse($listings as $listing)
      <p>
        <a href="{{ $listing['url'] }}" target="_blank">{{ $listing['title'] }}</a> — {{ $listing['price'] }} (Agent: {{ $listing['agent'] }})
      </p>
    @empty
      <p>No results found.</p>
    @endforelse
  @endforeach
</div>
@endsection
