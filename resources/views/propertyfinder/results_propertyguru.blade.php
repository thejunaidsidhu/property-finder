@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2>PropertyGuru Scraping Results</h2>

    @foreach($results as $result)
        <div class="card my-3 p-3">
            <h4>{{ $result['client']['client_name'] }}</h4>
            <p><strong>Criteria:</strong>
                {{ $result['client']['house_type'] }},
                {{ $result['client']['district'] }},
                {{ $result['client']['price'] }}
            </p>

            @if(empty($result['listings']))
                <p class="text-muted">No listings found.</p>
            @else
                <div class="row">
                    @foreach($result['listings'] as $listing)
                        <div class="col-md-4 mb-3">
                            <div class="card shadow-sm">
                                @if($listing['image'])
                                    <img src="{{ $listing['image'] }}" class="card-img-top" alt="Property image">
                                @endif
                                <div class="card-body">
                                    <h6 class="card-title">{{ $listing['title'] }}</h6>
                                    <p>{{ $listing['price'] }}</p>
                                    <p class="text-muted">{{ $listing['address'] }}</p>
                                    <a href="{{ $listing['url'] }}" target="_blank" class="btn btn-primary btn-sm">View</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
