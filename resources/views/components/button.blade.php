{{-- resources/views/livewire/alert-preferences.blade.php --}}
<div class="mx-auto max-w-4xl px-6 py-8">

    {{-- 헤더 ───────────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold tracking-tight text-primary">알림 설정</h1>

        {{-- 다크모드 토글 (선택) --}}
        <button
            x-data
            @click="document.documentElement.classList.toggle('dark');
                    localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'"
            class="rounded-full p-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 transition">
            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor"
                 viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 3v1m0 16v1m8.66-11.66l-.7.7M4.05 19.95l-.7.7M21 12h-1M4 12H3m16.66 4.66l-.7-.7M4.05 4.05l-.7-.7"/>
            </svg>
        </button>
    </div>

    {{-- 검색 & 회사 목록 ─────────────────────────────────────────────── --}}
    <section class="space-y-4 bg-white dark:bg-gray-800 rounded-panel shadow-card p-6">
        <h2 class="text-lg font-semibold mb-2">종목 검색</h2>

        <div class="flex gap-3">
            <input
                type="text"
                wire:model.debounce.300ms="search"
                placeholder="회사명 또는 티커를 입력하세요"
                class="flex-1 rounded-card border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900
                       p-3 text-sm focus:ring-primary focus:border-primary"/>

            @if (!empty($search))
                <x-button variant="secondary" size="sm" wire:click="$set('search', '')">
                    초기화
                </x-button>
            @endif
        </div>

        {{-- 검색 결과 --}}
        <div class="divide-y divide-gray-200 dark:divide-gray-700 max-h-64 overflow-y-auto">
            @forelse ($this->companies as $c)
                <div class="flex items-center justify-between py-2 hover:bg-gray-50 dark:hover:bg-gray-700 px-2">
                    <span>{{ $c->name_kr }}</span>

                    @if (!isset($minAmounts[$c->id]))
                        <x-button variant="primary" size="xs" wire:click="add({{ $c->id }})">
                            추가
                        </x-button>
                    @endif
                </div>
            @empty
                <p class="py-4 text-center text-sm text-gray-500">검색 결과가 없습니다.</p>
            @endforelse
        </div>

        <div class="pt-2">
            {{ $this->companies->links() }}
        </div>
    </section>

    {{-- 내 구독 종목 ─────────────────────────────────────────────────── --}}
    <section class="mt-10 space-y-4 bg-white dark:bg-gray-800 rounded-panel shadow-card p-6">
        <h2 class="text-lg font-semibold mb-2">내 구독 종목</h2>

        @if(count($minAmounts) === 0)
            <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                아직 구독 종목이 없습니다. 상단에서 회사를 검색해 추가해 보세요!
            </p>
        @else
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                    <th class="py-2">회사</th>
                    <th class="py-2 w-36 text-center">최소 금액 (원)</th>
                    <th class="py-2 w-20"></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($minAmounts as $id => $amount)
                    @php
                        $company = $this->companies->firstWhere('id', $id)
                                   ?? \App\Models\Company::find($id);
                    @endphp
                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td class="py-3">{{ $company?->name_kr }}</td>
                        <td class="py-3 text-center">
                            <input
                                type="number" min="0"
                                wire:model.debounce.500ms="minAmounts.{{ $id }}"
                                class="w-28 rounded-card border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900
                                       p-2 text-right focus:ring-primary focus:border-primary"/>
                        </td>
                        <td class="py-3 text-right">
                            <x-button variant="danger" size="xs" wire:click="remove({{ $id }})">
                                삭제
                            </x-button>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
