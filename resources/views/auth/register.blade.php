<x-layouts.app>
    <h1>회원가입</h1>
    <form method="POST" action="{{ route('register') }}">
        @csrf
        <input name="name" placeholder="이름" value="{{ old('name') }}">
        <input name="email" placeholder="이메일" value="{{ old('email') }}">
        <input name="password" type="password" placeholder="비밀번호">
        <input name="password_confirmation" type="password" placeholder="비밀번호 확인">
        <button>가입</button>
    </form>
</x-layouts.app>
