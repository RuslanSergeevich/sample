<?php namespace App\Http\Controllers;
use App\AlmostCustomers;
use App\Apricot\Checkout\Cart;
use App\Apricot\Checkout\Checkout;
use App\Apricot\Checkout\CheckoutCompletion;
use App\Apricot\Helpers\PaymentMethods;
use App\Apricot\Libraries\TaxLibrary;
use App\Apricot\Repositories\CouponRepository;
use App\Checkouts;
use App\Coupon;
use App\Giftcard;
use App\Http\Requests\CheckoutRequest;
use App\Jobs\GiftcardWasOrdered;
use App\Marketing;
use App\Product;
use App\Setting;
use App\User;
use Illuminate\Http\Request;


class CheckoutController extends Controller
{
    function __construct()
    {
    }
    function getCheckout(Request $request)
    {


        if (!Cart::exists()) {
            return \Redirect::back()->withErrors([trans('checkout.errors.no-cart-session')]);
        }
        $request->session()->set('product_name', $request->get('product_name', $request->session()->get('product_name', 'subscription')));
        $userData = \Session::get('user_data', \Request::old('user_data', Cart::getInfoItem('user_data', null)));
        if ($request->session()->get('product_name') == 'subscription' && ((!$userData || count($userData) == 0) && !\Session::has('vitamins'))) {
            return \Redirect::route('flow')->withErrors([trans('checkout.messages.vitamins-not-selected')]);
        }

        $giftcard = null;
        if (\Session::has('giftcard_id') && \Session::has('giftcard_token') && $request->session()->get('product_name') == 'subscription') {
            $giftcard = Giftcard::where('id', \Session::get('giftcard_id'))
                ->where('token', \Session::get('giftcard_token'))
                ->where('is_used', 0)
                ->where('currency', trans('general.currency'))
                ->first();
        }
        $product = Product::where('name', $request->session()->get('product_name', 'subscription'))->first();
        if (!$product) {
            return \Redirect::route('home');
        }
        if (!$userData) {
            $userData = json_decode('{}');
        }
        while (is_string($userData) && json_decode($userData)) {
            $userData = json_decode($userData);
        }
        return view('checkout.index', [
            'user_data' => json_encode($userData),
            'product' => $product,
            'giftcard' => $giftcard,
            'paymentMethods' => PaymentMethods::getAcceptedMethodsForCountry(\App::getLocale())
        ]);
    }
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    function getTaxRate(Request $request)
    {
        $zone = new TaxLibrary($request->get('zone'));
        return \Response::json(['rate' => $zone->rate()]);
    }
    /**
     * @param CheckoutRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    function postCheckout(CheckoutRequest $request)
    {
        $productName = $request->get('product_name', 'subscription');
        $paymentMethod = $request->get('payment_method');
        $couponCode = $request->get('coupon', '');
        $checkout = new Checkout();
        $taxZone = $request->get('address_country', trans('general.tax_zone'));
        $street = $request->get('address_street');
        $number = $request->get('address_number');
        $city = $request->get('address_city');
        $first_name = $request->get('first_name');
        $last_name = $request->get('last_name');
        $zipcode = $request->get('address_zip');
        $userData = $request->get('user_data');
        $name = sprintf('%s %s', $request->get('first_name'), $request->get('last_name'));
        $email = $request->get('email');
        if (\Auth::check() && \Auth::user()->isUser()) {
            $taxZone = \Auth::user()->customer->getCustomerAttribute('address_country', $request->get('address_country', trans('general.tax_zone')));
            $street = \Auth::user()->customer->getCustomerAttribute('address_street', $request->get('address_street'));
            $city = \Auth::user()->customer->getCustomerAttribute('address_city', $request->get('address_city'));
            $zipcode = \Auth::user()->customer->getCustomerAttribute('address_zip', $request->get('address_zip'));
            $name = \Auth::user()->getCustomer()->getName();
            $email = \Auth::user()->getEmail();
        }

        $checkout->setProductByName($productName)
            ->setPaymentMethod($paymentMethod)
            ->setTotal(Cart::getTotal())
            ->setSubscriptionPrice(Cart::getTotal())
            ->appendCoupon($couponCode)
            ->appendGiftcard($request->session()->get('giftcard_id'), $request->session()->get('giftcard_token'))
            ->setTaxLibrary($taxZone)
            ->createCustomer($request->get('name'), $request->get('email'));

        if (!$checkout->getCustomer()) {

            return \Redirect::back()
                ->withErrors(trans('checkout.errors.payment-error'))
                ->withInput();
        }

        $charge = $checkout->makeInitialPayment();
        if (!$charge) {
            return \Redirect::back()
                ->withErrors(trans('checkout.errors.payment-error'))
                ->withInput();
        }

        if ($paymentMethod == 'mollie'){
            \Log::info("Charge:");
            \Log::info((array)$charge);
        }

        if ($paymentMethod == 'mollie' and strpos($charge->id, 'tr_') !== 0) {
            \Log::error("Mollie charge create in post: " . $charge->id);
            return \Redirect::back()
                ->withErrors(trans('checkout.errors.payment-error'))
                ->withInput();
        }

        $request->session()->put('charge_id', $charge->id);
        $request->session()->put('payment_customer_id', $checkout->getCustomer()->id);
        $request->session()->put('name', $name);
        $request->session()->put('first_name', $first_name);
        $request->session()->put('last_name', $last_name);
        $request->session()->put('email', $email);
        $request->session()->put('address_street', $street);
        $request->session()->put('address_number', $number);
        $request->session()->put('address_city', $city);
        $request->session()->put('address_zip', $zipcode);
        $request->session()->put('address_country', $taxZone);
        $request->session()->put('company', $request->get('company'));
        $request->session()->put('cvr', $request->get('cvr'));
        $request->session()->put('phone', $request->get('phone'));
        $request->session()->put('product_name', $productName);
        $request->session()->put('user_data', $userData);
        $request->session()->put('password', bcrypt($request->get('password')));
        $request->session()->put('price', $checkout->getSubscriptionPrice());
        $request->session()->put('order_price', $checkout->getTotal());
        $request->session()->put('coupon', $couponCode);
        if (\Session::get('new_vitamin')) {
            $request->session()->put('new_vitamin', \Session::get('new_vitamin'));
        }


        if ( \Session::has( 'share' )){
            $request->session()->put('share', \Session::get('share'));
        }

        //Add duplicate session to DB
        if ($paymentMethod == 'mollie'){

            $mollieCheckout = new Checkouts();
            $mollieCheckout->charge_id = $checkout->getCustomer()->id;
            $mollieCheckout->data = json_encode($request->session()->all());
            $mollieCheckout->save();
        }

        if (isset($charge->links) && isset($charge->links->paymentUrl)) {
            return \Redirect::to($charge->links->paymentUrl);
        }
        return \Redirect::action('CheckoutController@getVerify', ['method' => $request->get('payment_method'), 'id' => $request->session()->get('charge_id')]);
    }
    /**
     * @param                          $method
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    function getVerify($method, $id, Request $request)
    {
        try {

            if($method == 'mollie' and strpos($request->session()->get('charge_id'), 'tr_') !== 0){

                $checkoutData = Checkouts::where('charge_id','=',$id)->get();

                if(count($checkoutData) == 0){

                    \Log::info("Redirect to home user from error : " . $id);

                    return \Redirect::route('home');
                }

                foreach(json_decode($checkoutData[0]->data) as $key => $value){

                    $request->session()->put($key, $value);

                }

                Checkouts::find($checkoutData[0]->id)->delete();

            }


            if($method == 'mollie' and !isset($checkoutData)){

                $checkoutDatas = Checkouts::where('charge_id','=',$id)->get();

                if(count($checkoutDatas) == 0){

                    \Log::info("Redirect to home user : " . $id);

                } else{

                    Checkouts::find($checkoutDatas[0]->id)->delete();
                }


            }

            $productName = $request->session()->get('product_name', 'subscription');

            $couponCode = $request->session()->get('coupon', '');
            $userData = $request->session()->get('user_data', $request->old('user_data', Cart::getInfoItem('user_data', null)));

            while (is_string($userData) && json_decode($userData)) {
                $userData = json_decode($userData);
            }

            try {
                $checkout = new Checkout();
                $checkout->setPaymentMethod($method)
                    ->setProductByName($productName)
                    ->setTotal(Cart::getTotal())
                    ->setSubscriptionPrice(Cart::getTotal())
                    ->appendCoupon($couponCode)
                    ->appendGiftcard($request->session()->get('giftcard_id'), $request->session()->get('giftcard_token'))
                    ->setTaxLibrary($request->session()->get('address_country'));


            } catch (\Exception $exception) {
                \Log::error("Checkout create error: " . $exception->getMessage() . ' in line ' . $exception->getLine() . " file " . $exception->getFile());
                return \Redirect::action('CheckoutController@getCheckout')->withErrors($exception->getMessage());
            }

            $isSuccessful = $checkout->getPaymentHandler()->isChargeValid($request->session()->get('charge_id'));

            if (!$isSuccessful) {

                \Log::error("Checkout isSuccessful error iD: ".$request->session()->get('charge_id'));

                return \Redirect::action('CheckoutController@getCheckout')
                    ->withErrors(trans('checkout.errors.payment-error'))
                    ->withInput([
                        'name' => $request->session()->get('name'),
                        'first_name' => $request->session()->get('first_name'),
                        'last_name' => $request->session()->get('last_name'),
                        'email' => $request->session()->get('email'),
                        'address_street' => $request->session()->get('address_street'),
                        'address_number' => $request->session()->get('address_number'),
                        'address_city' => $request->session()->get('address_city'),
                        'address_zip' => $request->session()->get('address_zip'),
                        'address_country' => $request->session()->get('address_country'),
                        'company' => $request->session()->get('company'),
                        'cvr' => $request->session()->get('cvr'),
                        'phone' => $request->session()->get('phone'),
                    ]);
            }

            $checkoutCompletion = new CheckoutCompletion($checkout);
            $password = $request->session()->get('password', null);

            if (!$password) {
                if ($userData && isset($userData->birthdate)) {
                    $password = date('Y-m-d', strtotime($userData->birthdate));
                } else {
                    $password = str_random(8);
                }
                $password = bcrypt($password);
            }

            $name = $request->session()->get('name');
            $email = $request->session()->get('email');

            try {

                $checkoutCompletion->createUser($name, $email, $password);

            } catch (\Exception $exception) {

                \Log::error("User create error: " . $exception->getMessage() . ' in line ' . $exception->getLine() . " file " . $exception->getFile());
                return \Redirect::action('CheckoutController@getCheckout')->withErrors($exception->getMessage());
            }

            if ($request->session()->get('giftcard_id')) {
                $gift = $request->session()->get('giftcard_token');
            } else {
                $gift = null;
            }
            try {
                $checkoutCompletion->setCustomerAttributes([
                    'address_city' => $request->session()->get('address_city'),
                    'address_line1' => $request->session()->get('address_street'),
                    'address_number' => $request->session()->get('address_number'),
                    'address_country' => $request->session()->get('address_country'),
                    'address_postal' => $request->session()->get('address_zip'),
                    'company' => $request->session()->get('company'),
                    'cvr' => $request->session()->get('cvr'),
                    'phone' => $request->session()->get('phone'),
                ])
                    ->setPlanPayment($request->session()->get('payment_customer_id'), $method)
                    ->setUserData(json_encode($userData))
                    ->updateCustomerPlan();


                if ($request->session()->get('share')) {
                    $checkoutCompletion->setSales($request->session()->get('share'));
                }

                if ($request->session()->get('new_vitamin')) {
                    $checkoutCompletion->updateCustomerPlan($request->session()->get('new_vitamin'));
                }

                $order_plan = json_encode($checkoutCompletion->getUser()->getCustomer()->getPlan()->getVitamins());

                if ($couponCode) {
                    $coupon = Coupon::where('code', '=', $couponCode)->first();
                    if ($coupon->discount_type == "free_shipping") {
                        $count = $coupon->discount - 1;
                        $checkoutCompletion->getUser()->getCustomer()->getPlan()->setCouponCount($count);
                    }
                    $checkoutCompletion->getUser()->getCustomer()->getPlan()->setLastCoupon($couponCode);
                }

                $checkoutCompletion->handleProductActions()
                    ->deductCouponUsage()
                    ->markGiftcardUsed()
                    ->fireCustomerWasBilled($request->session()->get('charge_id'), $gift, $order_plan)
                    ->queueEmail($password)
                    ->flush()
                    ->initUpsell()
                    ->loginUser();
            } catch (\Exception $exception) {
                \Log::error("Checkout error: " . $exception->getMessage() . ' in line ' . $exception->getLine() . " file " . $exception->getFile());
                //$checkoutCompletion->user->delete(); // todo dont delete!?! what if is a returning user
                return \Redirect::back()->withErrors($exception->getMessage()); // todo refund charge + withInput
            }
            $request->session()->flash('order_price', $checkout->getTotal());
            $request->session()->flash('order_currency', trans('general.currency'));
            Cart::clear();
            if ($checkout->getProduct()->isSubscription()) {
                $code = str_random(8);
                return \Redirect::action('CheckoutController@getSuccess')->with(['order_created' => true, 'upsell' => true, 'code' => $code]);
            }




            // Considering that theres only two products: subscription and giftcard, we can conclude that this is a giftcard.
            $this->dispatch(new GiftcardWasOrdered($checkoutCompletion->getGiftcard(), $checkoutCompletion->getUser()->getCustomer()));


            return \Redirect::action('CheckoutController@getSuccessNonSubscription', ['token' => $checkoutCompletion->getGiftcard()->token])
                ->with(['order_created' => true]);

        } catch (\Exception $exception) {
            \Log::error("Method error: " . $exception->getMessage() . ' in line ' . $exception->getLine() . " file " . $exception->getFile());
        }
    }
    /**
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    function getSuccess(Request $request)
    {
        if (!$request->session()->has('order_created')) {
            return \Redirect::route('home');
        }

        $code = '';
        if ($request->session()->has('code')) {
            $code = $request->session()->get('code');
        }

        if(\Cookie::get('utm_source') != null){

            $marketing = new Marketing();

            $marketing->customer_id = \Auth::user()->getCustomer()->id;
            $marketing->source = \Cookie::get('utm_source');
            $marketing->medium = \Cookie::get('utm_medium');
            $marketing->campaign = \Cookie::get('utm_campaign');

            $ga = explode('GA1.2.',$_COOKIE['_ga']);


            $marketing->clientId = $ga['1'];

            $marketing->save();
        }

        $vitamins = \Auth::user()->getCustomer()->getVitaminModels();
        $plans = \Auth::user()->getCustomer()->getOrders();
        $email = \Auth::user()->email;
        foreach ($plans as $plan) {
            $order_id = $plan->id;
        }
        return view('checkout.success', ['vitamins' => $vitamins, 'order_id' => $order_id, 'user_email' => $email, 'code' => $code]);
    }
    /**
     * @param                          $token
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    function getSuccessNonSubscription($token, Request $request)
    {
        if (!$request->session()->has('order_created')) {
            return \Redirect::route('home');
        }
        return view('checkout.success', [
            'giftcardToken' => $token
        ]);
    }
    /**
     * @param \App\Apricot\Repositories\CouponRepository $couponRepository
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    function applyCoupon(CouponRepository $couponRepository, Request $request)
    { // todo use a checkout model
        if (is_null($request->get('coupon')) || $request->get('coupon') == '') {
            return \Response::json(['message' => trans('checkout.messages.coupon-missing')], 400);
        }
        $coupon = $couponRepository->findByCoupon($request->get('coupon'));

        $giftcard = \App\Giftcard::whereToken( $request->get( 'coupon' ) )->whereIsUsed( 0 )->whereCurrency( trans( 'general.currency' ) )->first();


        if (!$coupon and !$giftcard) {
            \Session::forget('applied_coupon');
            return \Response::json(['message' => trans('checkout.messages.no-such-coupon')], 400);
        }

        /** @var Product $product */
        $product = Product::where('name', \Session::get('product_name', 'subscription'))->first();

        if (!$product || $product->isGiftcard()) {
            return \Response::json(['message' => trans('checkout.messages.no-such-coupon')], 400);
        }

        if($coupon){
            \Session::put('applied_coupon', $coupon->code);
            return \Response::json([
                'message' => trans('checkout.messages.coupon-added'),
                'coupon' => [
                    'description' => $coupon->description,
                    'applies_to' => $coupon->applies_to,
                    'discount_type' => $coupon->discount_type,
                    'discount' => $coupon->discount,
                    'code' => $coupon->code
                ]
            ], 200);
        }

        if($giftcard){
            \Session::put( 'giftcard_id', $giftcard->id );
            \Session::put( 'giftcard_token', $giftcard->token );
            return \Response::json([
                'message' => trans('use-gifting.success')
            ], 200);
        }

    }

    function setAlmostCustomer(Request $request){
        if ($request->isMethod('get')){
            return \Response::json(['message' => 'Bad method!'], 400);
        }
        if($request->get('name')!=''){
            $name = $request->get('name');
        } else{
            $name = '';
        }
        $user = User::whereEmail($request->get('email'))->count();
        $customer = AlmostCustomers::where('email', '=', $request->get('email'))->count();
        if($user == 0 and $customer == 0){
            $almost = new AlmostCustomers();
            $almost->email = $request->get('email');
            $almost->location = $request->get('location');
            $almost->token = $request->get('token');
            $almost->name = $name;
            $almost->save();
        }
        return 'Success';
    }
}