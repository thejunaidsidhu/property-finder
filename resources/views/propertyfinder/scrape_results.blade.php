@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">Scraping Results From HLD</h2>

    <div class="mb-3">
        <label>Hide:</label>
        <select id="hide_status" class="form-select" style="width:200px;display:inline-block;">
            <option value="">Show All</option>
            <option value="recommended">Recommended</option>
            <option value="not_suitable">Not Suitable</option>
        </select>
    </div>

    @foreach($results as $result)
        <div class="card my-3 p-3">
            <h4>
                {{ $result['client'] }}
                @if(isset($result['cached']) && $result['cached'])
                    <span class="badge bg-success">Cached</span>
                @endif
            </h4>
            <p><strong>Criteria:</strong> {{ implode(', ', array_filter([$result['criteria']['house_type'], $result['criteria']['district'], $result['criteria']['price']])) }}</p>

            @if(isset($result['error']))
                <p class="text-danger">Error: {{ $result['error'] }}</p>
            @elseif(empty($result['found']))
                <p>No listings found.</p>
            @else
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                    @foreach($result['found'] as $listing)
                        <div class="col listing-card" data-status="{{ $listing['status'] ?? '' }}">
                            <div class="card h-100 shadow-sm">
                                @if(isset($listing['photos'][0]['photo_url']))
                                    <img src="https://www.singaporelanded.com.sg/storage/{{ $listing['photos'][0]['photo_url'] }}" class="card-img-top" alt="Property Image" style="height:200px; object-fit:cover;">
                                @else
                                    <img src="https://via.placeholder.com/300x200?text=No+Image" class="card-img-top" alt="No Image">
                                @endif
                                <div class="card-body">
                                    <h5 class="card-title" style="color:#ff214f">District {{ $listing['district_id'] ?? ''}} {{ $listing['district_area'] ?? 'No Area' }} {{ $listing['house_types_name'] ?? '' }}</h5>
                                    <!-- <h5 class="card-title">{{ $listing['address'] ?? 'No Address' }}</h5> -->
                                    <p class="card-text">
                                        <strong>Price:</strong> ${{ number_format($listing['asking_price'] ?? 0) }}<br>
                                        <strong>House Type:</strong> {{ $listing['house_types_name'] ?? 'N/A' }}<br>
                                        <strong>Bedrooms:</strong> {{ $listing['bedroom_number'] ?? 'N/A' }}<br>
                                        <strong>Bathrooms:</strong> {{ $listing['bathroom_number'] ?? 'N/A' }}<br>
                                        <strong>Parking:</strong> {{ $listing['parking_number'] ?? 'N/A' }}<br>
                                        <strong>Land Size:</strong> {{ isset($listing['land_size']) ? $listing['land_size'] . ' sqft' : 'N/A' }} <br>
                                        <strong>Address:</strong> {{ $listing['address'] ?? 'No Address' }}<br>
                                    </p>
                                    <a href="https://www.singaporelanded.com.sg/listing_details/{{ $listing['id'] }}" class="btn btn-primary btn-sm" target="_blank">View Details</a>
                                    @if(isset($listing['url']))
                                        <a href="{{ $listing['url'] }}" class="btn btn-primary btn-sm" target="_blank">View Listing</a>
                                    @endif
                                </div>
                                <div class="mt-2">
                                    <button class="btn btn-success btn-sm label-btn"
                                            data-client="{{ $result['client'] }}"
                                            data-id="{{ $listing['id'] }}"
                                            data-platform="hld"
                                            data-status="recommended">
                                        Recommended
                                    </button>

                                    <button class="btn btn-danger btn-sm label-btn"
                                            data-client="{{ $result['client'] }}"
                                            data-id="{{ $listing['id'] }}"
                                            data-platform="hld"
                                            data-status="not_suitable">
                                        Not Suitable
                                    </button>

                                    @if(!empty($listing['status']))
                                        <span class="badge bg-info text-dark">{{ ucfirst($listing['status']) }}</span>
                                    @endif
                                </div>
                                
                                <div class="card-footer text-muted">
                                    Updated: {{ isset($listing['updated_at']) ? \Carbon\Carbon::parse($listing['updated_at'])->format('d M Y') : 'N/A' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
    @if($filteredGU != [])
    <h2 class="mb-4">Scraping Results From PropertyGuru</h2>
    <div class="card my-3 p-3">       
        @foreach($filteredGU as $data)
            <div class="mb-4">
                <h4>{{ $data['client'] }}</h4>
                @if(isset($data['cached']))
                    <span style="width:100px;font-size:20px;" class="badge bg-success">Cached</span>
                @endif
                
                @if(isset($data['status']) && $data['status'] === 'processing')
                    <div class="alert alert-info">
                        <strong>⏳ Processing...</strong> {{ $data['message'] ?? 'Scraping in progress. Please refresh in a moment to see results.' }}
                    </div>
                @elseif(isset($data['status']) && $data['status'] === 'queued')
                    <div class="alert alert-info">
                        <strong>📋 Queued</strong> {{ $data['message'] ?? 'Job has been queued. Please refresh to see results when ready.' }}
                    </div>
                @elseif(isset($data['error']))
                    <div class="alert alert-danger">
                        <strong>Error:</strong> {{ $data['error'] }}
                    </div>
                @elseif(empty($data['found']))
                    <div class="alert alert-warning">
                        <strong>No listings found.</strong> The scraping completed but found 0 properties matching your criteria. 
                        This could mean:
                        <ul class="mb-0 mt-2">
                            <li>No properties match all your filters (price, land size, bedrooms, district)</li>
                            <li>Try relaxing some filters or check if the criteria are too restrictive</li>
                        </ul>
                    </div>
                @else
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                        @foreach($data['found'] as $item)
                    <div class="col listing-card" data-status="{{ $item['status'] ?? '' }}">
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
                            <div class="mt-2">
                                <button class="btn btn-success btn-sm label-btn"
                                        data-client="{{ $data['client'] }}"
                                        data-id="{{ $item['id'] }}"
                                        data-platform="propertyguru"
                                        data-status="recommended">
                                    Recommended
                                </button>

                                <button class="btn btn-danger btn-sm label-btn"
                                        data-client="{{ $data['client'] }}"
                                        data-id="{{ $item['id'] }}"
                                        data-platform="propertyguru"
                                        data-status="not_suitable">
                                    Not Suitable
                                </button>

                                @if(!empty($item['status']))
                                    <span class="badge bg-info text-dark">{{ ucfirst($item['status']) }}</span>
                                @endif
                            </div>
                            <div class="card-footer text-muted">
                                Updated: {{ isset($item['updated_at']) ? \Carbon\Carbon::parse($item['updated_at'])->format('d M Y') : 'N/A' }}
                            </div>
                        </div>
                    </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('hide_status');
    if (!select) return;

    function applyFilter() {
        const hide = select.value; // '' | 'recommended' | 'not_suitable'
        // show all if empty
        document.querySelectorAll('.listing-card').forEach(card => {
            const status = (card.dataset.status || '').toString();
            if (!hide) {
                card.style.display = ''; // default (bootstrap col)
            } else {
                // hide cards whose status matches the selected hide value
                if (status === hide) card.style.display = 'none';
                else card.style.display = '';
            }
        });
    }

    // apply on change
    select.addEventListener('change', applyFilter);

    // optional: remember last selection in sessionStorage (persist while tab open)
    const saved = sessionStorage.getItem('hide_status');
    if (saved) {
        select.value = saved;
        applyFilter();
    }

    select.addEventListener('change', function () {
        sessionStorage.setItem('hide_status', this.value);
    });
});
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.label-btn').forEach(btn => {
        btn.addEventListener('click', function () {

            let formData = {
                listing_id: this.dataset.id,
                platform: this.dataset.platform,
                status: this.dataset.status,
                client_name: this.dataset.client,
                _token: '{{ csrf_token() }}'
            };

            fetch("{{ route('listing.label') }}", {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(formData)
            })
            .then(res => res.json())
            .then(() => location.reload());
        });
    });
});
</script>
@endsection