@extends('layouts.app', ['title' => 'NetRescue CIT · '.$device->display_name, 'pageTitle' => 'Checks'])

@section('content')
<div class="page-fit">
    <header class="page-head">
        <div>
            <a class="back-link" href="{{ route('devices.show', $device) }}">{{ $device->display_name }}</a>
            <h1>All checks</h1>
            <p>Stored measurements for this computer.</p>
        </div>
    </header>
    <section class="table-panel log-panel">
        <x-check-table :checks="$checks" :warning="$warningMs" />
    </section>
</div>
@endsection
