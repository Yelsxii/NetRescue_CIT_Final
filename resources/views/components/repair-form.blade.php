@props(['incident', 'returnTo' => null, 'confirmed' => false])
@php($guidance = \App\Support\RepairGuidance::for($incident))
<form method="POST" action="{{ route('incidents.recover', $incident) }}"
    {{ $attributes->class(['js-busy-form', 'js-repair-form']) }}
    data-repair-confirmation="{{ $confirmed ? 'reviewed' : ($guidance['is_follow_up'] ? 'required' : 'initial') }}"
    data-repair-title="{{ $guidance['confirmation_title'] }}"
    data-repair-previous="{{ $guidance['previous_summary'] }}"
    data-repair-status="{{ $guidance['previous_status'] }}"
    data-repair-action-title="{{ $guidance['action_title'] }}"
    data-repair-purpose="{{ $guidance['purpose'] }}"
    data-repair-limitation="{{ $guidance['limitation'] }}"
    data-repair-consequence="{{ $guidance['consequence'] }}"
    data-repair-attention="{{ $guidance['attention'] }}"
    data-repair-button="{{ $guidance['button_label'] }}"
    data-busy-text="{{ $guidance['busy_text'] }}">
    @csrf
    @if($returnTo === 'dashboard')<input type="hidden" name="return_to" value="dashboard">@endif
    <input type="hidden" name="repair_action" value="{{ $incident->recovery_action_code }}">
    <input type="hidden" name="previous_request_id" value="{{ $guidance['previous_request_id'] }}">
    <input type="hidden" name="repair_confirmed" value="{{ $confirmed ? '1' : '0' }}">
    <button class="button button-primary" type="submit">{{ $guidance['button_label'] }}</button>
</form>
