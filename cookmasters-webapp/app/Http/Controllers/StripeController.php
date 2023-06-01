<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Stripe\StripeClient;
use Illuminate\Http\Request;
use App\Models\SubscriptionPlans;
use Stripe\Exception\CardException;
use Illuminate\Support\Facades\Validator;

class StripeController extends Controller
{
    private $stripe;
    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.api_keys.secret_key'));
    }

    public function index()
    {
        return view('layouts.payment');
    }

    public function createPlan(SubscriptionPlans $subscriptionPlans)
    {
        $plan = $this->stripe->plans->create([
            'id' => $subscriptionPlans->id,
            'amount' => $subscriptionPlans->price * 100,
            'currency' => 'eur',
            'interval' => 'month',
            'product' => [
                'name' => $subscriptionPlans->name,
                'type' => 'service',
            ],
        ]);
        if ($subscriptionPlans->description != null) {
            $this->stripe->products->update(
                $plan->product,
                [
                    'description' => $subscriptionPlans->description
                ]
            );
        }
        $subscriptionPlans->stripe_id = $plan->id;
        $subscriptionPlans->stripe_plan = $plan->product;
        $subscriptionPlans->save();
    }

    public function updatePlan(SubscriptionPlans $subscriptionPlans)
    {
        // can't update plan price with stripe api
        // $this->stripe->plans->update(
            // $subscriptionPlans->stripe_plan,
            // [
                // 'amount' => $subscriptionPlans->price * 100,
                // 'currency' => 'eur',
                // 'interval' => 'month',
            // ]
        // );

        $this->stripe->products->update(
            $subscriptionPlans->stripe_plan,
            [
                'name' => $subscriptionPlans->name,
                'description' => $subscriptionPlans->description
            ]
        );
    }

    public function deletePlan($stripe_id, $stripe_plan)
    {
        $plan = $this->stripe->plans->retrieve($stripe_id);
        $plan->delete();

        $product = $this->stripe->products->retrieve($stripe_plan);
        $product->delete();
    }

    public function retrivePlanId(SubscriptionPlans $subscriptionPlans)
    {
        $plans = $this->stripe->plans->all();
        foreach ($plans as $plan) {
            $product = $this->stripe->products->retrieve($plan->product);
            if ($plan->amount == $subscriptionPlans->price * 100 && $product->name == $subscriptionPlans->name) {
                return [
                    "stripe_id" => $plan->id,
                    "stripe_plan" => $product->id
                ];
            }
        }
        return null;
    }

    public function createCustomer(User $user)
    {
        $customer = $this->stripe->customers->create([
            'name' => $user->firstname . ' ' . $user->lastname,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
        $user->stripe_id = $customer->id;
        $user->save();
    }

    public function updateCustomer(User $user)
    {
        $customer = $this->stripe->customers->update(
            $user->stripe_id,
            [
                'name' => $user->firstname . ' ' . $user->lastname,
                'email' => $user->email,
                'phone' => $user->phone,
            ]
        );
    }
    
    public function subscribeToPlan(SubscriptionPlans $subscriptionPlan, User $user)
    {
        // check if user already subscribed to plan
        if ($user->subscriptionPlan()->first() != null) {
            if ($user->subscriptionPlan()->first()->id == $subscriptionPlan->id) {
                return redirect()->back()->withErrors(['error' => 'You are already subscribed to this plan.']);
            }

            if ($user->stripe_id != null) {
                $this->unsubscribeToPlan($user);
            }

            if ($user->stripe_id == null && $user->subscriptionPlan()->first()->price > 0) {
                return redirect()->back()->withErrors(['error' => 'You are already subscribed to a plan but the plan is not linked to a stripe plan.']);
            }
        }

        if ($user->stripe_id == null) {
            $this->createCustomer($user);
        }

        // display payment page
        try {
            $session = $this->stripe->checkout->sessions->create([
                'success_url' => route('subscription-plans.check') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('subscription-plans.index'),
                'payment_method_types' => ['card'],
                'mode' => 'subscription',
                'customer' => $user->stripe_id,
                'line_items' => [[
                    'price' => $subscriptionPlan->stripe_id,
                    'quantity' => 1,
                ]],
            ]);
            return redirect()->away($session->url);
        } catch (Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }

        // subscribe to plan
        // $subscription = $this->stripe->subscriptions->create([
            // 'customer' => $user->stripe_id,
            
            // 'items' => [
                // [
                    // 'plan' => $subscriptionPlan->stripe_id,
                // ],
            // ],
        // ]);
        // return $subscription;
    }

    public function unsubscribeToPlan(User $user)
    {
        $subscription = $this->retriveSubscription($user->stripe_id);
        
        foreach ($subscription as $sub) {
            $sub->cancel();
        }
    }

    public function retriveSession($session_id)
    {
        $session = $this->stripe->checkout->sessions->retrieve($session_id);
        return $session;
    }

    public function retriveCustomer($customer_id)
    {
        $customer = $this->stripe->customers->retrieve($customer_id);
        return $customer;
    }

    public function retriveSubscription($customer_id)
    {
        $subscription = $this->stripe->subscriptions->all([
            'customer' => $customer_id,
        ])->data;
        
        $subList = [];
        foreach ($subscription as $sub) {
            $subList[] = $this->stripe->subscriptions->retrieve($sub->id);
        }

        return $subList;
    }

    public function retriveInvoice($invoice_id)
    {
        $invoice = $this->stripe->invoices->retrieve($invoice_id);
        return $invoice;
    }

    public function retriveAllInvoices($customer_id)
    {
        $invoices = $this->stripe->invoices->all([
            'customer' => $customer_id,
        ]);
        return $invoices;
    }

    public function retriveAllPaymentIntents($customer_id)
    {
        $paymentIntents = $this->stripe->paymentIntents->all([
            'customer' => $customer_id,
        ]);
        return $paymentIntents;
    }

    public function retriveAllPaymentIntentAndInvoices($customer_id)
    {
        $paymentsIntents = $this->retriveAllPaymentIntents($customer_id);
        $invoices = $this->retriveAllInvoices($customer_id);
        $return_args = [];
        foreach ($paymentsIntents as $paymentIntent) {
            foreach ($invoices as $invoice) {
                if ($paymentIntent->invoice == $invoice->id) {
                    $return_args[] = [
                        'invoice' => $invoice,
                        'paymentIntent' => $paymentIntent
                    ];
                    continue 2;
                }
            }
            $return_args[] = [
                'paymentIntent' => $paymentIntent,
                'invoice' => null
            ];
        }
        
        return $return_args;
    }
}