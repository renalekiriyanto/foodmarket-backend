<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Midtrans\Config;
use Midtrans\Snap;

class TransactionController extends Controller
{
    public function all(Request $request){
        $id = $request->input('id');
        $limit = $request->input('limit', 6);
        $food_id = $request->input('food_id');
        $status = $request->input('status');

        if($id){
            $transaction = Transaction::with(['food', 'user'])->find($id);

            if($transaction){
                return ResponseFormatter::success([
                    'food' => $transaction,
                    'Fetch transaction successfully'
                ]);
            } else {
                return ResponseFormatter::error(
                    null,
                    'Data not found', 404
                );
            }
        }

        $transaction = Transaction::with(['food', 'user'])
        ->where('user_id', Auth::user()->id);

        if($food_id){
            $transaction->where('food_id', $food_id);
        }
        if($status){
            $transaction->where('status', $status);
        }

        return ResponseFormatter::success(
            $transaction->paginate($limit),
            'Fetch transaction successfully'
        );
    }

    public function update(Request $request, $id){
        $transaction = Transaction::findOrFail($id);

        $transaction->update($request->all());

        return ResponseFormatter::success($transaction, 'Transaction updated');
    }

    public function checkout(Request $request){
        $request->validate([
            'food_id' => 'required|exists:food,id',
            'user_id' => 'required|exists:users,id',
            'quantity' => 'required',
            'total' => 'required',
            'status' => 'required',
        ]);

        $transaction = Transaction::create([
            'food_id' => $request->food_id,
            'user_id' => $request->user_id,
            'quantity' => $request->quantity,
            'total' => $request->total,
            'status' => $request->status,
            'paymen_url' => '',
        ]);

        // konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // membuat transaksi
        $midtrans = [
            'transaction_details' => [
                'order_id' => $transaction->id,
                'gross_amount' => $transaction->total,
            ],
            'customer_details' => [
                'first_name' => $transaction->user->name,
                'email' => $transaction->user->email
            ],
            'enabled_payments' => ['gopay', 'bank_transfer'],
            'vtweb' => []
        ];

        // memanggil midtrans
        try {
            $payment_url = Snap::createTransaction($midtrans)->redirect_url;

            $transaction->paymen_url = $payment_url;
            $transaction->save();
            // return ke api
            return ResponseFormatter::success($transaction, 'Transaction successfully');
        } catch (Exception $err) {
            return ResponseFormatter::error($err->getMessage(), 'Transaction failed');
        }

    }
}
