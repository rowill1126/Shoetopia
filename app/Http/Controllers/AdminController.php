<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use Notification;
use Illuminate\Support\Facades\Response;
use App\Notifications\SendEmailtNotification;

class AdminController extends Controller
{
    // Method to get pending order counts
    private function getPendingCount() {
        return Order::where('payment_status', 'pending')->count();
    }

    public function exportCsv()
    {
        $products = Product::all();

        // Create a file pointer connected to the output stream
        $handle = fopen('php://output', 'w');

        // Set the headers for the CSV file
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="products.csv"');

        // Add the CSV column headers
        fputcsv($handle, ['ID', 'Title', 'Description', 'Image', 'Category', 'Quantity', 'Price', 'Discount Price', 'Created At', 'Updated At']);

        // Loop through products and add them to the CSV file
        foreach ($products as $product) {
            fputcsv($handle, [
                $product->id,
                $product->title,
                $product->description,
                $product->image,
                $product->category,
                $product->quantity,
                $product->price,
                $product->discount_price,
                $product->created_at,
                $product->updated_at,
            ]);
        }

        fclose($handle);
        exit; // Make sure to exit to prevent any additional output
    }

    public function cancel($id)
    {
        $order = Order::find($id);
        $order->payment_status = 'Canceled';
        $order->save();
        return redirect()->back();
    }

    public function processing($id)
    {
        $order = Order::find($id);
        $order->payment_status = 'Processing';
        $order->save();
        return redirect()->back();
    }

    public function transit($id)
    {
        $order = Order::find($id);
        $order->payment_status = 'In Transit';
        $order->save();
        return redirect()->back();
    }

    public function deliver($id)
    {
        $order = Order::find($id);
        $order->payment_status = 'Delivered';
        $order->save();
        return redirect()->back();
    }

    public function reject($id)
    {
        $order = Order::find($id);
        $order->payment_status = 'Rejected';
        $order->save();
        return redirect()->back();
    }

    public function view_category()
    {
        $data = Category::all();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.category', compact('data', 'pendingCount'));
    }

    public function add_category(Request $request)
    {
        $data = new Category;
        $data->category_name = $request->category;
        $data->save();

        return redirect()->back()->with('message', 'Category Added Successfully');
    }

    public function delete_category($id)
    {
        $data = Category::find($id);
        $data->delete();

        return redirect()->back()->with('message', 'Category Deleted successfully');
    }

    public function view_product()
    {
        $category = Category::all();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.product', compact('category', 'pendingCount'));
    }

    public function add_product(Request $request)
    {
        $product = new Product;

        $product->title = $request->title;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->quantity = $request->quantity;
        $product->discount_price = $request->dis_price;
        $product->category = $request->category;

        $image = $request->image;
        $imagename = time() . '.' . $image->getClientOriginalExtension();
        $request->image->move('product', $imagename);
        $product->image = $imagename;

        $product->save();

        return redirect()->back()->with('message', 'Product Added Successfully');
    }

    public function show_product()
    {
        $product = Product::all();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.show_product', compact('product', 'pendingCount'));
    }

    public function delete_product($id)
    {
        $product = Product::find($id);
        $product->delete();

        return redirect()->back()->with('message', 'Product Deleted successfully');
    }

    public function update_product($id)
    {
        $product = Product::find($id);
        $category = Category::all();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.update_product', compact('product', 'category', 'pendingCount'));
    }

    public function update_product_confirm(Request $request, $id)
    {
        $product = Product::find($id);
        $product->title = $request->title;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->discount_price = $request->dis_price;
        $product->category = $request->category;
        $product->quantity = $request->quantity;

        $image = $request->image;
        if ($image) {
            $imagename = time() . '.' . $image->getClientOriginalExtension();
            $request->image->move('product', $imagename);
            $product->image = $imagename;
        }

        $product->save();

        return redirect()->back()->with('message', 'Product Updated Successfully');
    }

    public function order()
    {
        $order = Order::all();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.order', compact('order', 'pendingCount'));
    }





    public function delivered($id)
    {
        $order = Order::find($id);
        $order->delivery_status = "delivered";
        $order->payment_status = 'Paid';
        $order->save();

        return redirect()->back();
    }

    public function send_email($id)
    {
        $order = Order::find($id);
        return view('admin.email_info', compact('order'));
    }

    public function send_user_email(Request $request, $id)
    {
        $order = Order::find($id);
        $details = [
            'greeting' => $request->greeting,
            'firstline' => $request->firstline,
            'body' => $request->body,
            'button' => $request->button,
            'url' => $request->url,
            'lastline' => $request->lastline,
        ];

        Notification::send($order, new SendEmailtNotification($details));

        return redirect()->back();
    }



    public function pending_order()
{
    // Fetch orders with 'Pending' payment status
    $orders = Order::where('payment_status', 'Pending')->get();

    // Reset cod_count to 0 when viewing pending orders
    session(['cod_count' => 0]);

    // Render the view with the pending orders
    return view('admin.pending_order', compact('orders'));
}

    



    public function processing_order()
    {
        // Fetch only orders with payment_status 'Processing'
        $order = Order::where('payment_status', 'Processing')->get();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.processing_order', compact('order', 'pendingCount'));
    }

    public function intransit_order()
    {
        $order = Order::where('payment_status', 'In Transit')->get();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.intransit_order', compact('order', 'pendingCount'));
    }

    public function delivered_order()
    {
        $order = Order::where('payment_status', 'Delivered')->get();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.delivered_order', compact('order', 'pendingCount'));
    }

    public function rejected_order()
    {
        $order = Order::where('payment_status', 'Rejected')->get();
        $pendingCount = $this->getPendingCount(); // Get pending count
        return view('admin.rejected_order', compact('order', 'pendingCount'));
    }
}
