<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Payment\PagSeguro\CreditCard;

class CheckoutController extends Controller
{
    public function index()
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!session()->has('cart')) return redirect()->route('home');

        $this->makePagSeguroSession();

        $cartItems = array_map(function($line) {
            return $line['amount'] * $line['price'];
        }, session()->get('cart'));

        $cartItems = array_sum($cartItems);

        return view('checkout', compact('cartItems'));
    }

    private function makePagSeguroSession()
    {
        if (!session()->has('pagseguro_session_code')) {
            $sessionCode = \PagSeguro\Services\Session::create(
                \PagSeguro\Configuration\Configure::getAccountCredentials()
            );

            return session()->put('pagseguro_session_code', $sessionCode->getResult());
        }
    }

    public function proccess(Request $request)
    {
        try {
            $dataPost = $request->all();
            $cartItems = session()->get('cart');
            $user = auth()->user();
            $reference = 'XPTO';

            $creditCardPayment = new CreditCard($cartItems, $user, $dataPost, $reference);
            $result = $creditCardPayment->doPayment();

            $userOrder = [
                'reference' => $reference,
                'pagseguro_code' => $result->getCode(),
                'pagseguro_status' => $result->getStatus(),
                'items' => serialize($cartItems),
                'store_id' => 42
            ];

            $user->orders()->create($userOrder);

            session()->forget('cart');
            session()->forget('pagseguro_session_code');

            return response()->json([
                'data' => [
                    'status' => true,
                    'message' => 'Pedido criado com sucesso!',
                    'order' => $reference
                ]
            ]);
        } catch (\Exception $e) {
            $message = env('APP_DEBUG') ? $e->getMessage() : 'Erro ao processar Pedido!';
            
            return response()->json([
                'data' => [
                    'status' => false,
                    'message' => $message,
                ]
            ], 401);
        }
    }

    public function thanks()
    {
        return view('thanks');
    }
}
