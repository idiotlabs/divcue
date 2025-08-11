<div class="max-w-3xl mx-auto p-6 bg-white dark:bg-gray-800 shadow rounded-panel space-y-6">

    {{-- 제목 --}}
    <h1 class="text-2xl font-bold text-primary">알림 설정</h1>

    {{-- ───────────────── 검색 + 추가 ───────────────── --}}
    <div class="flex items-center gap-2">
        <input type="text"
               wire:model.debounce.300ms="search"
               placeholder="회사 검색…"
               class="flex-1 border border-gray-300 dark:border-gray-600 rounded-card p-2
                      bg-white dark:bg-gray-900 focus:ring-primary focus:border-primary" />

        @if ($search)
            <x-button variant="secondary" wire:click="$set('search', '')" class="text-xs">
                초기화
            </x-button>
        @endif
    </div>

    {{-- 회사 목록 + ‘추가’ 버튼 --}}
    <div class="rounded-card overflow-hidden border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-700">
            <tr>
                <th class="py-2 px-3 text-left">회사</th>
                <th class="py-2 px-3 w-20"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($this->companies as $c)
                <tr class="border-b border-gray-200 dark:border-gray-700
                           hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="py-2 px-3">{{ $c->name_kr }}</td>
                    <td class="py-2 px-3 text-right">
                        @unless (isset($minAmounts[$c->id]))
                            <x-button variant="primary" class="text-xs px-2 py-1"
                                      wire:click="add({{ $c->id }})">
                                추가
                            </x-button>
                        @endunless
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- 페이지네이션 --}}
    <div class="text-center">
        {{ $this->companies->links() }}
    </div>

    {{-- ───────────────── 내 구독 종목 ───────────────── --}}
    <h2 class="text-lg font-semibold mt-8">내 구독 종목</h2>

    <div class="rounded-card overflow-hidden border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-700">
            <tr>
                <th class="py-2 px-3 text-left">회사</th>
                <th class="py-2 px-3 w-32 text-center">최소 금액</th>
                <th class="py-2 px-3 w-16"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($minAmounts as $id => $amount)
                @php
                    $company = $this->companies->firstWhere('id', $id)
                               ?? \App\Models\Company::find($id);
                @endphp
                <tr class="border-b border-gray-200 dark:border-gray-700
                           hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="py-2 px-3">{{ $company?->name_kr }}</td>
                    <td class="py-2 px-3 text-center">
                        <input  type="number"
                                min="0"
                                wire:model.debounce.500ms="minAmounts.{{ $id }}"
                                class="w-24 border border-gray-300 dark:border-gray-600 rounded-card p-1
                                       bg-white dark:bg-gray-900 text-right
                                       focus:ring-primary focus:border-primary" />
                    </td>
                    <td class="py-2 px-3 text-right">
                        <x-button variant="danger" class="text-xs px-2 py-1"
                                  wire:click="remove({{ $id }})">
                            삭제
                        </x-button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    {{-- 빈 상태 안내 --}}
    @if(count($minAmounts) === 0)
        <p class="text-center text-gray-500 dark:text-gray-400 mt-4">
            아직 구독 종목이 없습니다. 상단에서 회사를 검색해 추가해 보세요!
        </p>
    @endif
</div>
