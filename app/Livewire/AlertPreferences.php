<?php

namespace App\Livewire;

use App\Models\AlertPreference;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class AlertPreferences extends Component
{
    use WithPagination;

    public $search = '';
    public $minAmounts = [];

    public function mount()
    {
        $prefs = AlertPreference::where('user_id', auth()->id())->get();
        foreach ($prefs as $p) {
            $this->minAmounts[$p->company_id] = $p->min_amount;
        }
    }

    /* 회사 리스트를 페이지네이트 + 검색 */
    public function getCompaniesProperty()
    {
        return Company::query()
            ->when($this->search, fn($q) =>
            $q->where('name_kr', 'like', "%{$this->search}%")
            )
            ->orderBy('name_kr')
            ->paginate(10);
    }

    /* 행 추가 */
    public function add($companyId)
    {
        AlertPreference::firstOrCreate([
            'user_id'    => auth()->id(),
            'company_id' => $companyId,
        ], ['min_amount' => 0]);

        $this->minAmounts[$companyId] = 0;
    }

    /* 행 삭제 */
    public function remove($companyId)
    {
        AlertPreference::where('user_id', auth()->id())
            ->where('company_id', $companyId)
            ->delete();

        unset($this->minAmounts[$companyId]);
    }

    public function updatedMinAmounts($value, $companyId)
    {
        $amount = max(0, (int)$value);

        AlertPreference::updateOrCreate(
            ['user_id' => auth()->id(), 'company_id' => $companyId],
            ['min_amount' => $amount]
        );
    }

    public function render()
    {
        return view('livewire.alert-preferences');
    }
}
