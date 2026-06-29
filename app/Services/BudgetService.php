<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonth;
use App\Models\MonthlyBudget;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Zero-based budget engine (envelope method).
 *
 * Core invariant:
 *   total balance of on-budget accounts = money to assign + Σ available of categories
 *
 * All amounts are handled in cents of the budget's base currency
 * (the amount_base field of transactions).
 *
 * Carryover (month-to-month flexibility): a category's "available" is
 * cumulative: Σ (assigned + activity) of all months up to the given month.
 */
class BudgetService
{
    /**
     * Normalize a month to its first-day date (YYYY-MM-01).
     */
    public function normalizeMonth(string|CarbonImmutable|Carbon $month): string
    {
        if (is_string($month)) {
            // Accepts "YYYY-MM" or "YYYY-MM-DD".
            $month = CarbonImmutable::parse(strlen($month) === 7 ? $month.'-01' : $month);
        }

        return CarbonImmutable::parse($month)->startOfMonth()->toDateString();
    }

    /**
     * Return (creating it if missing) the budget month.
     */
    public function monthlyBudget(Budget $budget, string|CarbonImmutable|Carbon $month): MonthlyBudget
    {
        $monthDate = $this->normalizeMonth($month);

        // whereDate avoids false negatives from the time component of the date field.
        $existing = MonthlyBudget::query()
            ->where('budget_id', $budget->id)
            ->whereDate('month', $monthDate)
            ->first();

        return $existing ?? MonthlyBudget::create([
            'budget_id' => $budget->id,
            'month' => $monthDate,
        ]);
    }

    /**
     * Assign (set) an amount to a category in a given month.
     */
    public function assign(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month, int $cents): CategoryMonth
    {
        $monthlyBudget = $this->monthlyBudget($budget, $month);

        return CategoryMonth::updateOrCreate(
            [
                'monthly_budget_id' => $monthlyBudget->id,
                'category_id' => $category->id,
            ],
            ['assigned' => $cents],
        );
    }

    /**
     * Move money from one category to another within the same month.
     */
    public function move(Budget $budget, Category $from, Category $to, string|CarbonImmutable|Carbon $month, int $cents): void
    {
        $this->assign($budget, $from, $month, $this->assigned($budget, $from, $month) - $cents);
        $this->assign($budget, $to, $month, $this->assigned($budget, $to, $month) + $cents);
    }

    /**
     * Amount assigned to a category in a month (0 if there is no record).
     */
    public function assigned(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month): int
    {
        $monthDate = $this->normalizeMonth($month);

        return (int) CategoryMonth::query()
            ->where('category_id', $category->id)
            ->whereHas('monthlyBudget', fn ($q) => $q
                ->where('budget_id', $budget->id)
                ->whereDate('month', $monthDate))
            ->sum('assigned');
    }

    /**
     * Activity (sum of transactions) of a category in a month, in base currency.
     * Negative = expense.
     *
     * For credit card payment categories, the month's "activity" combines the
     * funds coming in from credit purchases (positive) and the payments
     * made to the card (negative).
     */
    public function activity(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month): int
    {
        $start = CarbonImmutable::parse($this->normalizeMonth($month))->startOfMonth();
        $end = CarbonImmutable::parse($this->normalizeMonth($month))->endOfMonth();

        if ($this->isPaymentCategory($category)) {
            return $this->creditFunded($category, end: $end, start: $start)
                + $this->paymentActivity($category, end: $end, start: $start);
        }

        return (int) Transaction::query()
            ->where('category_id', $category->id)
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereBetween('date', [$start, $end])
            ->sum('amount_base');
    }

    /**
     * Cumulative available of a category up to (and including) the given month.
     *
     * Normal category: available = Σ assigned(≤month) + Σ activity(≤month)
     * Payment category: available = Σ assigned(≤month) + credit funds(≤month) + payments(≤month)
     */
    public function available(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month): int
    {
        $monthEnd = CarbonImmutable::parse($this->normalizeMonth($month))->endOfMonth();

        $assigned = (int) CategoryMonth::query()
            ->where('category_id', $category->id)
            ->whereHas('monthlyBudget', fn ($q) => $q
                ->where('budget_id', $budget->id)
                ->whereDate('month', '<=', $monthEnd->toDateString()))
            ->sum('assigned');

        if ($this->isPaymentCategory($category)) {
            return $assigned
                + $this->creditFunded($category, end: $monthEnd)
                + $this->paymentActivity($category, end: $monthEnd);
        }

        $activity = (int) Transaction::query()
            ->where('category_id', $category->id)
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereDate('date', '<=', $monthEnd)
            ->sum('amount_base');

        return $assigned + $activity;
    }

    /**
     * Money ready to assign, global for the budget.
     *
     * Credit cards are excluded from the "cash" side: spending on credit
     * does not reduce the RTA (it reduces the available of the expense's category), and the
     * money to pay the card stays reserved in its payment category.
     *
     *   RTA = balance of on-budget NON-card accounts − Σ available of all categories
     */
    public function readyToAssign(Budget $budget): int
    {
        $cashBalance = (int) Transaction::query()
            ->whereIn('account_id', $this->cashAccountIds($budget))
            ->sum('amount_base');

        $totalAssigned = (int) CategoryMonth::query()
            ->whereHas('monthlyBudget', fn ($q) => $q->where('budget_id', $budget->id))
            ->sum('assigned');

        // Activity of normal categories (includes purchases made with a card).
        $normalActivity = (int) Transaction::query()
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $this->paymentCategoryIds($budget))
            ->sum('amount_base');

        // Funds that credit purchases reserve in the payment categories.
        $paymentFunded = -1 * (int) Transaction::query()
            ->whereIn('account_id', $this->creditCardAccountIds($budget))
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $this->paymentCategoryIds($budget))
            ->sum('amount_base');

        // Payments made to the cards (transactions categorized to the payment category).
        $paymentActivity = (int) Transaction::query()
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereIn('category_id', $this->paymentCategoryIds($budget))
            ->sum('amount_base');

        $totalAvailable = $totalAssigned + $normalActivity + $paymentFunded + $paymentActivity;

        return $cashBalance - $totalAvailable;
    }

    /**
     * Record a card payment as a two-legged transfer:
     *  - outflow from the cash account, categorized to the payment category
     *  - inflow to the card (reduces the debt), without a category
     *
     * MVP: both accounts in the same currency.
     */
    public function payCreditCard(Account $from, Account $card, int $cents, string $date, ?int $userId = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($from, $card, $cents, $date, $userId) {
            $paymentCategory = $card->paymentCategory()->firstOrFail();

            $outflow = Transaction::create([
                'account_id' => $from->id,
                'date' => $date,
                'amount' => -$cents,
                'currency' => $from->currency,
                'category_id' => $paymentCategory->id,
                'user_id' => $userId,
                'cleared' => true,
            ]);

            $inflow = Transaction::create([
                'account_id' => $card->id,
                'date' => $date,
                'amount' => $cents,
                'currency' => $card->currency,
                'user_id' => $userId,
                'cleared' => true,
                'transfer_pair_id' => $outflow->id,
            ]);

            $outflow->update(['transfer_pair_id' => $inflow->id]);
        });
    }

    // --- Card helpers -------------------------------------------------------

    public function isPaymentCategory(Category $category): bool
    {
        return $category->linked_account_id !== null;
    }

    /**
     * Funds that credit purchases reserve in the payment category (positive).
     */
    protected function creditFunded(Category $paymentCategory, CarbonImmutable $end, ?CarbonImmutable $start = null): int
    {
        $budget = $paymentCategory->group->budget;

        return -1 * (int) Transaction::query()
            ->where('account_id', $paymentCategory->linked_account_id)
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $this->paymentCategoryIds($budget))
            ->when($start, fn ($q) => $q->whereDate('date', '>=', $start))
            ->whereDate('date', '<=', $end)
            ->sum('amount_base');
    }

    /**
     * Payments made to the card (transactions categorized to the payment category, negative).
     */
    protected function paymentActivity(Category $paymentCategory, CarbonImmutable $end, ?CarbonImmutable $start = null): int
    {
        return (int) Transaction::query()
            ->where('category_id', $paymentCategory->id)
            ->when($start, fn ($q) => $q->whereDate('date', '>=', $start))
            ->whereDate('date', '<=', $end)
            ->sum('amount_base');
    }

    /**
     * IDs of on-budget accounts (includes credit cards).
     *
     * @return array<int, int>
     */
    protected function onBudgetAccountIds(Budget $budget): array
    {
        return $budget->accounts()->where('on_budget', true)->pluck('id')->all();
    }

    /**
     * IDs of on-budget accounts that are NOT credit cards (cash/bank).
     *
     * @return array<int, int>
     */
    protected function cashAccountIds(Budget $budget): array
    {
        return $budget->accounts()->where('on_budget', true)
            ->where('type', '!=', 'credit_card')->pluck('id')->all();
    }

    /** @return array<int, int> */
    protected function creditCardAccountIds(Budget $budget): array
    {
        return $budget->accounts()->where('type', 'credit_card')->pluck('id')->all();
    }

    /**
     * IDs of the budget's credit card payment categories.
     *
     * @return array<int, int>
     */
    protected function paymentCategoryIds(Budget $budget): array
    {
        return Category::query()
            ->whereNotNull('linked_account_id')
            ->whereHas('group', fn ($q) => $q->where('budget_id', $budget->id))
            ->pluck('id')->all();
    }
}
