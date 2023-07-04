<?php

namespace App\Http\Controllers;

use App\Models\Transactions;
use Exception;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Http\Request;
use PDF;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('cart.index')->with([
            'cart' => session()->get('cart', []),
            'subtotal' => $this->subtotal()
        ]);
    }

    public function success()
    {
        return view('cart.success');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'quantity' => 'required|integer|min:1',
            'equipment_id' => 'required|exists:equipment,id'
        ]);

        $equipment = Equipment::findOrFail($validatedData['equipment_id']);

        $cart = session()->get('cart', []);

        if (isset($cart[$equipment->id])) {
            if ($cart[$equipment->id]['quantity'] + $validatedData['quantity'] > $equipment->availablequantity) {
                $cart[$equipment->id]['quantity'] = $equipment->availablequantity;
                return redirect()->route('cart.index')->withErrors([
                    'availablequantity' => 'The available quantity is exceeded!'
                ]);
            }
            $cart[$equipment->id]['quantity'] += $validatedData['quantity'];
        } else {
            $cart[$equipment->id] = [
                'quantity' => $validatedData['quantity'],
                'equipment' => $equipment
            ];
        }

        session()->put('cart', $cart);

        return redirect()->route('cart.index')->with('success', 'Equipment added to cart successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            if ($cart[$id]['equipment']->availablequantity < $validatedData['quantity']) {
                $cart[$id]['quantity'] = $cart[$id]['equipment']->availablequantity;
                session()->put('cart', $cart);
                return redirect()->route('cart.index')->withErrors([
                    'availablequantity' => 'The available quantity is exceeded ' . $cart[$id]['equipment']->availablequantity . ' in stock!'
                ]);
            }
            $cart[$id]['quantity'] = $validatedData['quantity'];
            session()->put('cart', $cart);
            return redirect()->route('cart.index')->with('success', 'Cart updated successfully!');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $validatedData = [
            'quantity' => 'required|integer|min:1'
        ];

        $cart = session()->get('cart', []);

        if (isset($cart[$id])) {
            unset($cart[$id]);
            session()->put('cart', $cart);
            return redirect()->route('cart.index')->with('success', 'Equipment removed from cart successfully!');
        }

        return redirect()->route('cart.index')->withErrors([
            'error' => 'Equipment not found in cart!'
        ]);
    }

    public function subtotal()
    {
        $cart = session()->get('cart', []);
        $subtotal = 0;

        foreach ($cart as $equipment) {
            $subtotal += $equipment['equipment']->price * $equipment['quantity'];
        }

        return $subtotal;
    }

    public function clear()
    {
        session()->remove('cart');
        return redirect()->route('cart.index')->with('success', 'Cart cleared successfully!');
    }

    public function checkout()
    {
        $cart = session()->get('cart', []);

        if (count($cart) == 0) {
            return redirect()->route('cart.index')->withErrors([
                'error' => 'Cart is empty!'
            ]);
        }

        // format cart
        foreach ($cart as $equipment) {
            $order[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $equipment['equipment']->price * 100,
                    'product_data' => [
                        'name' => $equipment['equipment']->name,
                        'images' => json_decode($equipment['equipment']->photos),
                    ],
                ],
                'quantity' => $equipment['quantity'],
            ];
        }

        // dd($order);

        $stripe = new StripeController();
        $session = $stripe->createSession($order);

        if ($session) {
            return redirect()->to($session->url);
        } else {
            return redirect()->route('cart.index')->withErrors([
                'error' => 'Checkout failed!'
            ]);
        }
    }

    public function check(Request $request)
    {
        try {
            $stripe = new StripeController();
            if (!($session = $stripe->retriveSession($request->session_id))) {
                return redirect()->back()->withErrors(['error' => 'Stripe session not found please contact support.']);
            }

            if (!($user = User::where('stripe_id', $session->customer)->first())) {
                return redirect()->back()->withErrors(['error' => 'User not found please contact support.']);
            }

            $cart = session()->get('cart', []);

            if ($this->subtotal() != $session->amount_total / 100) {
                return redirect()->back()->withErrors(['error' => 'Amounts are not same please contact support.']);
            }

            foreach ($cart as $equipment) {
                Transactions::create([
                    'user_id' => $user->id,
                    'equipment_id' => $equipment['equipment']->id,
                    'quantity' => $equipment['quantity'],
                    'price' => $equipment['equipment']->price,
                    'stripe_payment_intent_id' => $session->payment_intent,
                ]);
            }

        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('cart.index')->with('success', 'Checkout successfully!');
    }

    public function invoice($transaction)
    {
        try {
            $transaction = Transactions::where('stripe_payment_intent_id', $transaction)->get();
            if ($transaction->count() == 0) {
                throw new Exception('Transaction not found!');
            }
        } catch (Exception $e) {
            return redirect()->route('account.show')->withErrors([
                'error' => $e->getMessage()
            ]);
        }

        $subtotal = 0;
        foreach ($transaction as $item) {
            $subtotal += $item->price * $item->quantity;
        }

        $pdf = PDF::setOptions([
            'dpi' => 150,
            'defaultFont' => 'sans-serif',
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'isPhpEnabled' => true,
            'isJavascriptEnabled' => true,
        ]);
        
        $pdf->loadView('PDF.invoice', [
            'transaction' => $transaction,
            'user' => auth()->user(),
            'subtotal' => $subtotal,
        ]);

        return $pdf->download('invoice.pdf');
    }
}