@extends('layouts.customer')
@section('title', 'New Ticket')

@section('content')
  <div class="abar">
    <a class="back" href="{{ route('customer.tickets.index') }}">‹</a>
    <div><div class="t">New Ticket</div><div class="sub">We're here to help</div></div>
  </div>

  @if ($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

  <form method="POST" action="{{ route('customer.tickets.store') }}" class="wrap">
    @csrf
    <div class="card">
      <label class="lbl">Subject</label>
      <input type="text" name="subject" class="inp" placeholder="Brief summary" required>
      <div class="row2">
        <div><label class="lbl">Category</label><select name="category" class="inp">@foreach (\App\Models\Ticket::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
        <div><label class="lbl">Priority</label><select name="priority" class="inp"><option value="low">Low</option><option value="normal" selected>Normal</option><option value="high">High</option><option value="urgent">Urgent</option></select></div>
      </div>
      <label class="lbl">Message</label>
      <textarea name="message" rows="5" class="inp" placeholder="Describe your issue…" required></textarea>
    </div>
    <button class="btn" style="margin-bottom:20px">Submit Ticket</button>
  </form>
@endsection
