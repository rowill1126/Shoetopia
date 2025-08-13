<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request; // Correctly import the Request class
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\Order;
use Session;
use Stripe;

class HomeController extends Controller
{
    public function index()
    {
        $product = Product::paginate(6);
        return view('home.userpage', compact('product'));
    }

    public function redirect()
    {
        $usertype = Auth::user()->usertype;
    
        if ($usertype == '1') {
            $total_product = Product::all()->count();
            $total_order = Order::all()->count();
            $total_user = User::all()->count();
            $order = Order::all();
            $total_revenue = 0;
    
            foreach ($order as $orderItem) {
                $total_revenue += $orderItem->price; // Calculate total revenue
            }
    
            $total_delivered = Order::where('delivery_status', '=', 'delivered')->count();
            $total_processing = Order::where('delivery_status', '=', 'processing')->count();
            $pendingCount = Order::where('payment_status', 'Pending')->count();
    
            // Set the cod_count in session
            session(['cod_count' => $pendingCount]);
    
            return view('admin.home', compact('pendingCount', 'total_product', 'total_order', 'total_user', 'total_revenue', 'total_delivered', 'total_processing'));
        } else {
            $product = Product::paginate(6);
            return view('home.userpage', compact('product'));
        }
    }
    

    public function updateOrderStatus(Request $request, $orderId)
    {
        // Update order status based on the request data
        $order = Order::find($orderId);
        $order->status = $request->input('status');
        $order->save();

        // Recalculate the pending orders count
        $this->updatePendingCount(); // Move the count update to a separate method

        return redirect()->back()->with('message', 'Order status updated!');
    }

    private function updatePendingCount()
    {
        // Recalculate the pending orders count
        $pendingCount = Order::where('payment_status', 'Pending')->count();
        session(['pending_count' => $pendingCount]);
    }

    public function product_details($id)
    {
        $product = Product::find($id);
        return view('home.product_details', compact('product'));
    }

    public function add_cart(Request $request, $id)
    {
        if (Auth::id()) {
            $user = Auth::user();
            $product = Product::find($id);
            $cart = new Cart;

            // Set cart properties
            $cart->name = $user->name;
            $cart->email = $user->email;
            $cart->phone = $user->phone;
            $cart->address = $user->address;
            $cart->user_id = $user->id;

            $cart->product_title = $product->title;

            // Calculate the price based on discount
            if ($product->discount_price != null) {
                $cart->price = $product->discount_price * $request->quantity;
            } else {
                $cart->price = $product->price * $request->quantity;
            }

            $cart->image = $product->image;
            $cart->Product_id = $product->id;
            $cart->quantity = $request->quantity;
            $cart->save();

            // Update cart count in session
            if (!session()->has('cart_count')) {
                session(['cart_count' => 1]);
            } else {
                session(['cart_count' => session('cart_count') + 1]);
            }

            return redirect()->back();
        } else {
            return redirect('login');
        }
    }

    public function show_cart()
    {
        // Check if the user is authenticated
        if (Auth::id()) {
            // Clear the cart count session variable
            session()->forget('cart_count');

            // Get the authenticated user's ID
            $id = Auth::user()->id;

            // Fetch the cart items for the user
            $cart = Cart::where('user_id', $id)->get();

            // Return the cart view with the fetched cart items
            return view('home.showcart', compact('cart'));
        } else {
            // Redirect to the login page if the user is not authenticated
            return redirect('login');
        }
    }

    public function remove_cart($id)
    {
        $cart = Cart::find($id);
        $cart->delete();

        return redirect()->back();
    }

    public function cash_order(Request $request)
    {
        $user = Auth::user();
        $userid = $user->id;
    
        // Get all cart items for the user
        $data = Cart::where('user_id', $userid)->get();
    
        // Initialize a counter for new COD orders
        $codCount = 0;
    
        foreach ($data as $item) {
            $order = new Order;
    
            // Populate the order details
            $order->name = $user->name;
            $order->email = $user->email;
            $order->phone = $user->phone;
            $order->notes = $request->notes;
            $order->contact_number = $request->contact_number;
            $order->current_address = $request->current_address;
    
            $order->address = $user->address;
            $order->user_id = $item->user_id;
            $order->product_title = $item->product_title;
            $order->price = $item->price;
            $order->quantity = $item->quantity;
            $order->image = $item->image;
            $order->product_id = $item->Product_id;
            $order->payment_status = 'Pending'; // Mark as pending
            $order->delivery_status = 'cash on delivery'; // COD status
            $order->save();
    
            // Increment the COD count for each new order
            $codCount++;
    
            // Delete the item from the cart after the order is placed
            Cart::find($item->id)->delete();
        }
    
        // Update the session with the new COD count, incrementing it
        $currentCodCount = session('cod_count', 0); // Get current count, default to 0 if not set
        session(['cod_count' => $currentCodCount + $codCount]); // Increment the count
    
        // Redirect back with a success message
        return redirect()->back()->with('message', 'Your order has been placed successfully! COD items are pending for delivery.');
    }
    
    
    


    public function stripe($totalprice)
    {
        return view('home.stripe', compact('totalprice'));
    }

    public function stripePost(Request $request, $totalprice)
    {
        Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

        Stripe\Charge::create([
            "amount" => $totalprice * 100,
            "currency" => "usd",
            "source" => $request->stripeToken,
            "description" => "Thanks for payment."
        ]);

        $user = Auth::user();
        $userid = $user->id;
        $data = Cart::where('user_id', '=', $userid)->get();

        foreach ($data as $data) {
            $order = new Order;

            $order->name = $data->name;
            $order->email = $data->email;
            $order->phone = $data->phone;
            $order->address = $data->address;
            $order->user_id = $data->user_id;
            $order->product_title = $data->product_title;
            $order->price = $data->price;
            $order->quantity = $data->quantity;
            $order->image = $data->image;
            $order->product_id = $data->Product_id;
            $order->payment_status = 'Paid';
            $order->delivery_status = 'processing';
            $order->save();

            $cart_id = $data->id;
            $cart = Cart::find($cart_id);
            $cart->delete();
        }

        Session::flash('success', 'Payment successful!');
        return back();
    }

    public function cancel($id)
    {
        $order = Order::find($id);
        $order->payment_status = 'Cancelled';
        $order->save();  // Add this line
        return redirect()->back();
    }

    public function show_order()
    {
        if (Auth::id()) {
            $user = Auth::user();
            $userid = $user->id;
            $order = Order::where('user_id', '=', $userid)->get();

            return view('home.order', compact('order'));
        } else {
            return redirect('login');
        }
    }

    public function cancel_order($id)
    {
        $order = Order::find($id);
        $order->delivery_status = 'canceled the order';
        $order->save();

        return redirect()->back();
    }
}
