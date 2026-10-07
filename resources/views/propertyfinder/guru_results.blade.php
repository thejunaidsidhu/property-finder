@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">Scraping Results From PropertyGuru</h2>
    <div class="card my-3 p-3">
        <h4>
        </h4>
        @foreach($results as $data)
            @if(isset($data['cached']))
                <span style="width:100px;font-size:20px;" class="badge bg-success">Cached</span>
            @endif
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                @foreach($data['found'] as $item)
                    <div class="col">
                        <div class="card h-100 shadow-sm">
                            <img src="{{ $item['thumbnail'] }}" class="card-img-top" alt="Property Image" style="height:200px; object-fit:cover;">
                            <div class="card-body">
                                <h5 class="card-title" style="color:#ff214f">{{ $item['title'] }}</h5>
                                <!-- <h5 class="card-title">{{ $item['address'] ?? 'No Address' }}</h5> -->
                                <p class="card-text">
                                    <strong>Price:</strong> {{ $item['price'] }}<br>
                                    <strong>Bedrooms:</strong> {{ $item['beds'] }}<br>
                                    <strong>Bathrooms:</strong> {{ $item['baths'] }}<br>
                                    <strong>Land Size:</strong> {{ $item['area'] }} sqft <br>
                                    <strong>Address:</strong> {{ $item['address'] }}<br>
                                </p>
                                <a href="{{ $item['url'] }}" class="btn btn-primary btn-sm" target="_blank">View Details</a>
                            </div>
                            <div class="card-footer text-muted">
                                Updated: {{ isset($item['updated_at']) ? \Carbon\Carbon::parse($item['updated_at'])->format('d M Y') : 'N/A' }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
@endsection

