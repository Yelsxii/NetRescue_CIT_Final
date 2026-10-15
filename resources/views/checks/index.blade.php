@extends('layouts.app', ['title' => 'NetRescue CIT · Checks'])

@section('content')
<div class="page-fit">
    <header class="page-head">
        <div>
            <h1>Checks</h1>
            <p>Saved network measurements from this computer.</p>
        </div>
    </header>
    <section class="table-panel log-panel">
        <x-check-table :checks="$checks" :warning="$warningMs" />
        @if($checks->hasPages())
            <div class="pagination-wrap">{{ $checks->links('partials.pagination') }}</div>
        @endif
    </section>
</div>
@endsection
