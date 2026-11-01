@extends($layout)
@section('title', 'Change Password')

@section('content')
  <div class="abar">
    <a class="back" href="{{ $back }}">‹</a>
    <div><div class="t">Change Password</div><div class="sub">Use a strong password you don't use elsewhere</div></div>
  </div>

  @if (session('ok'))<div class="alert">✅ {{ session('ok') }}</div>@endif
  @if ($errors->any() && ! $errors->has('current_password') && ! $errors->has('password'))<div class="alert err">⚠️ {{ $errors->first() }}</div>@endif

  <div class="wrap">
    <form method="POST" action="{{ route('customer.password.update') }}">
      @csrf
      @method('PUT')
      <div class="card">
        <h3>Change password</h3>
        @include('account.partials.password-fields', ['inputClass' => 'inp', 'labelClass' => 'lbl'])
      </div>
      <div style="padding-bottom:20px"><button class="btn">Update Password</button></div>
    </form>
  </div>
@endsection
