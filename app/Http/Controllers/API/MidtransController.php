<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Notification;
use PDO;

class MidtransController extends Controller
{
    public function callback(Request $request){
        // konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // instance midtrans notif
        $notification = new Notification();

        // assign variable
        $status = $notification->transaction_status;
        $type = $notification->payment_type;
        $fraud = $notification->fraud_type;
        $order_id = $notification->order_id;

        // transaksi berdasarkan id
        $transaction = Transaction::findOrFail($order_id);

        // handle notifikasi midtrans
        if($status == 'capture'){
            if($type == 'credit_card'){
                if($fraud == 'challenge'){
                    $transaction->status = 'PENDING';
                } else {
                    $transaction->status = 'SUCCESS';
                }
            }
        }
        elseif($status == 'settlement'){
            $transaction->status = 'SUCCESS';
        }
        elseif($status == 'pending'){
            $transaction->status = 'PENDING';
        }
        elseif($status == 'deny'){
            $transaction->status = 'CANCELLED';
        }
        elseif($status == 'expire'){
            $transaction->status = 'CANCELLED';
        }
        elseif($status == 'cancel'){
            $transaction->status = 'CANCELLED';
        }

        $transaction->save();
    }

    public function success(){
        return view('midtrans.success');
    }

    public function unfinish(){
        return view('midtrans.unfinish');
    }

    public function error(){
        return view('midtrans.error');
    }
}
