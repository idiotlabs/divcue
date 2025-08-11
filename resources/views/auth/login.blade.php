<x-layouts.app>
    <h1>로그인</h1>
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <input name="email"    placeholder="이메일" value="{{ old('email') }}">
        <input name="password" type="password" placeholder="비밀번호">
        <label><input type="checkbox" name="remember"> 기억하기</label>
        <button>로그인</button>
    </form>
</x-layouts.app>
