<x-layouts.app heading="Presupuesto">
    <div class="space-y-6">
        <div class="rounded-2xl bg-emerald-600 p-5 text-white shadow-sm">
            <p class="text-sm text-emerald-100">Listo para asignar</p>
            <p class="mt-1 text-3xl font-bold tracking-tight">$0,00</p>
            <p class="mt-2 text-xs text-emerald-100">
                El motor de presupuesto base-cero se construye en la Fase 3.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h2 class="text-base font-semibold text-slate-800">
                ¡Hola, {{ auth()->user()->name }}! 👋
            </h2>
            <p class="mt-2 text-sm text-slate-500">
                Tu sesión con Google funciona y ya formás parte del presupuesto familiar
                <span class="font-medium text-slate-700">«{{ auth()->user()->currentBudget()?->name }}»</span>.
            </p>
        </div>
    </div>
</x-layouts.app>
