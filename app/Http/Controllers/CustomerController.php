<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['name', 'phone_number', 'orders_count', 'created_at', 'updated_at'],
            'created_at',
            'desc',
        );

        $customers = Customer::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('phone_number', 'like', "%{$request->search}%"))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->withCount('orders')
            ->orderBy($sortBy === 'orders_count' ? 'orders_count' : $sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only('search', 'trashed', 'sort_by', 'sort_dir'),
        ]);
    }

    public function show(Customer $customer): Response
    {
        $customer->load(['orders' => fn ($q) => $q->latest()->limit(10)]);

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil ditambahkan.');
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('customers.index')
            ->with('success', 'Data customer berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        Customer::withTrashed()->findOrFail($id)->restore();

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        Customer::withTrashed()->findOrFail($id)->forceDelete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer berhasil dihapus permanen.');
    }
}
