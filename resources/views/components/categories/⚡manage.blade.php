<?php

use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonth;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $newGroupName = '';
    public array $newCategoryInputs = [];   // [groupId => string]
    public array $groupNames = [];          // [groupId => name]  (rename inline)
    public array $categoryNames = [];       // [categoryId => name] (rename inline)

    public function mount(): void
    {
        $this->loadNames();
    }

    private function budget(): Budget
    {
        return auth()->user()->currentBudget();
    }

    private function loadNames(): void
    {
        $groups = $this->budget()->categoryGroups()
            ->where('is_system', false)->with('categories')->orderBy('position')->get();

        $this->groupNames = $groups->pluck('name', 'id')->all();
        $this->categoryNames = [];
        foreach ($groups as $group) {
            foreach ($group->categories as $category) {
                $this->categoryNames[$category->id] = $category->name;
            }
        }
    }

    #[Computed]
    public function groups(): Collection
    {
        return $this->budget()->categoryGroups()
            ->where('is_system', false)->with('categories')->orderBy('position')->get();
    }

    private function refresh(): void
    {
        unset($this->groups);
        $this->loadNames();
    }

    public function addGroup(): void
    {
        $name = trim($this->newGroupName);
        if ($name === '') {
            $this->addError('newGroupName', 'Poné un nombre.');

            return;
        }

        $this->budget()->categoryGroups()->create([
            'name' => $name,
            'position' => ($this->budget()->categoryGroups()->max('position') ?? -1) + 1,
        ]);

        $this->newGroupName = '';
        $this->refresh();
    }

    public function addCategory(int $groupId): void
    {
        $name = trim($this->newCategoryInputs[$groupId] ?? '');
        if ($name === '') {
            return;
        }

        $group = $this->budget()->categoryGroups()->where('is_system', false)->findOrFail($groupId);
        $group->categories()->create([
            'name' => $name,
            'position' => ($group->allCategories()->max('position') ?? -1) + 1,
        ]);

        $this->newCategoryInputs[$groupId] = '';
        $this->refresh();
    }

    public function updated(string $name, $value): void
    {
        if (str_starts_with($name, 'categoryNames.')) {
            $this->renameCategory((int) substr($name, strlen('categoryNames.')), $value);
        } elseif (str_starts_with($name, 'groupNames.')) {
            $this->renameGroup((int) substr($name, strlen('groupNames.')), $value);
        }
    }

    private function renameCategory(int $id, $value): void
    {
        $value = trim((string) $value);
        $category = $this->ownedCategory($id);
        if (! $category) {
            return;
        }
        if ($value === '') {
            $this->categoryNames[$id] = $category->name; // restaura el valor previo

            return;
        }
        $category->update(['name' => $value]);
    }

    private function renameGroup(int $id, $value): void
    {
        $value = trim((string) $value);
        $group = $this->budget()->categoryGroups()->where('is_system', false)->find($id);
        if (! $group) {
            return;
        }
        if ($value === '') {
            $this->groupNames[$id] = $group->name;

            return;
        }
        $group->update(['name' => $value]);
    }

    public function deleteCategory(int $id): void
    {
        $category = $this->ownedCategory($id);
        if (! $category) {
            return;
        }

        $hasHistory = $category->transactions()->exists()
            || CategoryMonth::where('category_id', $id)->where('assigned', '!=', 0)->exists();

        if ($hasHistory) {
            $category->update(['archived_at' => now()]);
            session()->flash('status', "«{$category->name}» se archivó (tenía movimientos; se conserva el historial).");
        } else {
            $category->delete();
            session()->flash('status', "«{$category->name}» se eliminó.");
        }

        $this->refresh();
    }

    public function deleteGroup(int $id): void
    {
        $group = $this->budget()->categoryGroups()->where('is_system', false)->find($id);
        if (! $group) {
            return;
        }

        if ($group->allCategories()->exists()) {
            session()->flash('error', 'Primero quitá las categorías del grupo.');

            return;
        }

        $group->delete();
        session()->flash('status', 'Grupo eliminado.');
        $this->refresh();
    }

    private function ownedCategory(int $id): ?Category
    {
        return Category::query()
            ->whereNull('linked_account_id') // no tocar categorías-sistema de pago de tarjeta
            ->whereHas('group', fn ($q) => $q->where('budget_id', $this->budget()->id)->where('is_system', false))
            ->find($id);
    }
}; ?>

<div class="space-y-5" wire:key="cat-manager">
    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="rounded-xl bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200">{{ session('error') }}</div>
    @endif

    <p class="text-sm text-slate-500">
        Editá los nombres tocándolos. Al quitar una categoría con movimientos se archiva
        (se conserva el historial); si no tiene, se elimina.
    </p>

    @foreach ($this->groups as $group)
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white" wire:key="grp-{{ $group->id }}">
            <div class="flex items-center gap-2 bg-slate-50 px-3 py-2">
                <input type="text" wire:model.blur="groupNames.{{ $group->id }}"
                       class="flex-1 rounded-lg border-transparent bg-transparent px-2 py-1 text-sm font-bold uppercase tracking-wide text-slate-600 focus:border-emerald-500 focus:bg-white focus:ring-emerald-500">
                <button wire:click="deleteGroup({{ $group->id }})"
                        wire:confirm="¿Eliminar el grupo «{{ $group->name }}»?"
                        class="rounded-md px-2 py-1 text-xs text-slate-400 hover:bg-red-50 hover:text-red-600">Eliminar grupo</button>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($group->categories as $category)
                    <div class="flex items-center gap-2 px-3 py-2" wire:key="cat-{{ $category->id }}">
                        <input type="text" wire:model.blur="categoryNames.{{ $category->id }}"
                               class="flex-1 rounded-lg border-slate-200 px-2 py-1.5 text-sm text-slate-800 focus:border-emerald-500 focus:ring-emerald-500">
                        <button wire:click="deleteCategory({{ $category->id }})"
                                wire:confirm="¿Quitar «{{ $category->name }}»?"
                                class="rounded-md p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" aria-label="Quitar">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </div>
                @endforeach

                <div class="flex items-center gap-2 px-3 py-2">
                    <input type="text" wire:model="newCategoryInputs.{{ $group->id }}"
                           wire:keydown.enter="addCategory({{ $group->id }})"
                           placeholder="+ Agregar categoría"
                           class="flex-1 rounded-lg border-dashed border-slate-300 px-2 py-1.5 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <button wire:click="addCategory({{ $group->id }})"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Agregar</button>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Nuevo grupo --}}
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-3">
        <label class="mb-1 block text-xs font-medium text-slate-500">Nuevo grupo</label>
        <div class="flex items-center gap-2">
            <input type="text" wire:model="newGroupName" wire:keydown.enter="addGroup"
                   placeholder="Ej: Mascotas"
                   class="flex-1 rounded-lg border-slate-300 px-2 py-1.5 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            <button wire:click="addGroup"
                    class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">Crear grupo</button>
        </div>
        @error('newGroupName') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>
