@extends('layouts.app')

@section('content')
<div class="container">
  <h2>Upload Client Requirements</h2>

  <form id="uploadForm" action="{{ route('propertyfinder.upload') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="mb-3">
      <label class="form-label">Select Platform:</label><br>
      <label class="me-2"><input type="radio" name="platform" value="pg"> PropertyGuru</label>
      <label class="me-2"><input type="radio" name="platform" value="hld"> HLD</label>
      <label class="me-2"><input type="radio" name="platform" value="both" checked> Both</label>
    </div>

    <div class="mb-3">
      <input type="file" name="file" required class="form-control">
    </div>

    <button id="submitBtn" type="submit" class="btn btn-primary">Run Search</button>
  </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById('uploadForm');
    const btn  = document.getElementById('submitBtn');

    form.addEventListener('submit', function () {
        btn.disabled = true;
        btn.innerText = "Scraping...";
    });
});
</script>
@endsection
