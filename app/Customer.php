<?php namespace App;

use App\Apricot\Checkout\ProductPriceGetter;
use App\Apricot\Libraries\CombinationLibraryNew;
use App\Apricot\Libraries\MoneyLibrary;
use App\Apricot\Libraries\PaymentDelegator;
use App\Apricot\Libraries\PaymentHandler;
use App\Apricot\Libraries\StripeLibrary;
use App\Apricot\Libraries\TaxLibrary;
use App\Events\CustomerWasBilled;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jenssegers\Date\Date;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;

/**
 * Class Customer
 *
 * @package App
 * @property integer $id
 * @property integer $user_id
 * @property integer $plan_id
 * @property string $birthday
 * @property string $gender
 * @property boolean $accept_newletters
 * @property integer $order_count
 * @property integer $balance
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property string $deleted_at
 * @property-read \App\Plan $plan
 * @property-read \App\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Order[] $orders
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\CustomerAttribute[] $customerAttributes
 * @property mixed $customer
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer wherePlanId($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereBirthday($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereGender($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereAcceptNewletters($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereOrderCount($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereBalance($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereCreatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Customer today()
 * @method static \Illuminate\Database\Query\Builder|\App\Customer rebillable()
 * @mixin \Eloquent
 */
class Customer extends Model
{

    use SoftDeletes;

    /**
     * The database table for the model
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = ['user_id', 'plan_id', 'balance', 'is_mailflowable', 'order_count', 'accept_newletters', 'locale'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany('App\Order', 'customer_id', 'id');
    }


    public function notes()
    {
        return $this->hasMany('App\Notes', 'customer_id', 'id');
    }



    public function marketing()
    {
        return $this->hasMany('App\Marketing', 'customer_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customerAttributes()
    {
        return $this->hasMany('App\CustomerAttribute', 'customer_id', 'id');
    }





    public function getMarketing()
    {
        return $this->marketing;
    }

    /**
     * @return Plan
     */
    public function getPlan()
    {
        return $this->plan;
    }


    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }


    public function getNotes()
    {
        return $this->notes;
    }


    /**
     * @return Coupon ambassador
     */
    public function couponAmbassador()
    {
        $ambassador = Coupon::where('ambas', '=', 1)->where('valid_from', '<=', date('Y-m-d'))
            ->where('valid_to', '>=', date('Y-m-d'))->get();


        return $ambassador;
    }

    public function cancelSubscription($force = false, $reason = '')
    {
        if ((!$this->getPlan()->isCancelable() && !$force) || $this->getPlan()->isCancelled()) {
            return false;
        }

        return $this->getPlan()->cancel($reason);
    }


    public function setPrevGoal($count){

        $this->prev_goal = $count;
        $this->save();
    }

    /**
     */
    public function getOrders()
    {
        return $this->orders;
    }

    public function isSubscribed()
    {
        return $this->getPlan()->isActive();
    }

    public function getSubscriptionPrice()
    {
        return $this->getPlan()->getTotal();
    }


    public function setSubscriptionPrice( $newamount )
    {
        return $this->getPlan()->setPrice( $newamount );
    }

    public function getStripeToken()
    { // todo move to handler
        return $this->getPlan()->getStripeToken();
    }

    public function getCustomerAttribute($name, $default = '')
    {
        if (!$attribute = $this->customerAttributes->where('identifier', $name)->first()) {
            return $default;
        }

        return $attribute->value;
    }

    public function getCustomerAttributes($onlyEditable = false)
    {
        $attributes = $this->customerAttributes();

        if ($onlyEditable) {
            $attributes = $attributes->editable();
        }

        return $attributes->get();
    }

    public function getName()
    {
        return $this->getUser()->getName();
    }

    public function getFirstname()
    {
        $name = $this->getName();

        $names = explode(' ', $name);

        return $names[0];
    }

    public function getLastName()
    {
        $name = $this->getName();

        $names = explode(' ', $name);

        return $names[count($names) - 1];
    }

    public function getEmail()
    {
        return $this->getUser()->getEmail();
    }

    public function hasBirthday()
    {
        return !is_null($this->getBirthday());
    }

    public function hasPlan()
    {
        return $this->plan; // eww.. fixme
    }

    public function getBirthday()
    {
        return $this->getCustomerAttribute('user_data.birthdate', $this->birthday);
    }

    public function getPhone()
    {
        return $this->getCustomerAttribute('phone', $this->phone);
    }

    public function getAge()
    {
        if ($this->getBirthday() === '' || $this->getBirthday() === null) {
            return false;
        }

        return Date::createFromFormat('Y-m-d', $this->getBirthday())->diffInYears();
    }

    public function getGender()
    {
        return $this->getCustomerAttribute('user_data.gender', $this->gender);
    }

    public function getOrderCount()
    {
        return $this->order_count;
    }


    public function repeat($amount = null) {

        if (!$this->isSubscribed()) {
            return false;
        }

        $order_plan = json_encode($this->getPlan()->getVitamins());

        $chargeId = 'free';
        $usedBalance = false;
        $prevAmount = 0;
        $repeat = true;
        try {
            \Event::fire(new CustomerWasBilled($this->id, MoneyLibrary::toCents($amount) ?: $this->getSubscriptionPrice(), $chargeId, 'subscription', $usedBalance, $prevAmount * -1, '', null, $order_plan, $repeat));
        } catch (\Exception $exception) {
            \Log::error($exception->getFile() . " on line " . $exception->getLine());
        }


       // $this->getPlan()->rebilled();

        return true;

    }



    public function rebill($amount = null)
    {
        if (!$this->isSubscribed()) {
            return false;
        }



        if(count($this->getPlan()->getVitamins()) < 3){

            if($this->getSubscriptionPrice() == 1895) {

                $this->setSubscriptionPrice(1595);

            }elseif( $this->getSubscriptionPrice() == 14900){

                $this->setSubscriptionPrice(12900);
            }
        }


        $pills = $this->getPlan()->getVitamins();

        if($this->getAge() >70 and $this->getGender() == 1){
            foreach($pills as $key=>$value){
              if($value == 1 or $value == 2){
                  $pills[$key] = 3;
              }
            }
        }

        if($this->getAge() > 50 and $this->getGender() == 2){
            foreach($pills as $key=>$value){
                if($value == 1){
                    $pills[$key] = 2;
                }
            }
        }

        $this->getPlan()->update( [
            'vitamins' => json_encode( $pills )
        ]);

        $order_plan = json_encode($this->getPlan()->getVitamins());

        if (!$this->charge(MoneyLibrary::toCents($amount) ?: $this->getSubscriptionPrice(), true, 'subscription', '', null, $order_plan )) {
            return false;
        }

        if(count($this->getMarketing()) > 0){

            $client = new Client;
            foreach ($this->getMarketing() as $market){

                $response = $client->post(
                    'https://www.google-analytics.com/collect',
                    [
                        'form_params' => [
                            'v' => 1,
                            'tid' => 'UA-88694785-1',
                            'cid' => $market->clientId,
                            'cd1' => $market->clientId,
                            't' => 'event',
                            'ni' => 1,
                            'cs' => $market->source,
                            'cm' => $market->medium,
                            'cn' => $market->campaign,
                            'pa' => 'purchase',
                            'ec' => 'ecommerce',
                            'ea' => 'purchase',
                            'z' =>  rand(),
                            'ti' => $this->getOrders()->last()->id,
                            'tr' => $this->getPlan()->getPrice()/100,
                            'pr1id' => 'subscription',
                            'pr1nm' => 'subscription',
                            'pr1pr' => $this->getPlan()->getPrice()/100

                        ]
                    ]
                );

            }
        }

        $this->getPlan()->setLastPaymentDate();
        $this->getPlan()->rebilled();

        return true;
    }

    public function getStripeCustomer() // todo convert to paymentHandler
    {
        $lib = new StripeLibrary();

        return $lib->getCustomer($this);
    }

    public function setCustomerAttribute($identifier, $value, $force = true)
    {
        $attribute = $this->customerAttributes()->where('identifier', $identifier)->first();

        if (!$attribute) {
            $this->customerAttributes()->create([
                'identifier' => $identifier,
                'value' => $value ?: ''
            ]);

            return true;
        }

        if (!$force && $attribute->value == $value) {
            return true;
        }

        return $attribute->update(['value' => $value ?: '']);
    }

    public function setCustomerAttributes($attributes = [])
    {
        foreach ($attributes as $identifier => $value) {
            $this->setCustomerAttribute($identifier, $value);
        }

        return true;
    }

    public function removePaymentOption()
    {
        $paymentMethod = PaymentDelegator::getMethod($this->getPlan()->getPaymentMethod());
        $paymentHandler = new PaymentHandler($paymentMethod);

        $deleteResponse = $paymentHandler->deleteMethodFor($this->getPlan()->getPaymentCustomerToken());

        if ($deleteResponse['purge_plan']) {
            $this->getPlan()->update(['payment_customer_token' => '', 'payment_method' => '']);
        }

        return true;
    }

    public function getOrderById($id)
    {
        return $this->orders()->where('id', $id)->first();
    }

    public function makeOrder($amount = 100, $chargeToken = null, $shipping = null, $product_name = 'subscription', $usedBalance = false, $balanceAmount = 0, $coupon = null, $gift = null, $order_plan, $repeat = false)
    {


        $taxing = new TaxLibrary($this->getCustomerAttribute('address_country', 'denmark'));

        if (!is_null($coupon) and !empty($coupon)) {

            $coup = $coupon->code;

        } else {

            if(!is_null($gift) and !empty($gift)){

                $coup = "Gift: ".$gift;

            } else{

                $coup = NULL;
            }


        }

        if($repeat){
            $setRepeat = 1;
        } else{
            $setRepeat = null;
        }

        $shipping = $shipping ?: $this->getPlan()->getShippingPrice();
        $taxes = $amount * $taxing->rate();

        $order = $this->orders()->create([
            'reference' => (str_random(8) . '-' . str_random(2) . '-' . str_pad($this->getOrders()
                        ->count() + 1, 4, '0', STR_PAD_LEFT)),
            'payment_token' => $chargeToken ?: '',
            'payment_method' => $this->getPlan()->getPaymentMethod(),
            'state' => ($chargeToken ? 'paid' : 'new'),
            'vitamins' => $order_plan,
            'currency' => $this->plan->currency,
            'total' => $amount,
            'total_shipping' => $shipping,
            'sub_total' => $amount - $shipping - $taxes,
            'total_taxes' => $taxes,
            'shipping_name' => $this->getName(),
            'shipping_street' => $this->getCustomerAttribute('address_line1')." ".$this->getCustomerAttribute('address_number'),
            'shipping_city' => $this->getCustomerAttribute('address_city'),
            'shipping_country' => $this->getCustomerAttribute('address_country'),
            'shipping_zipcode' => $this->getCustomerAttribute('address_postal'),
            'shipping_company' => $this->getCustomerAttribute('company'),
            'coupon' => $coup,
            'repeat' => $setRepeat,

        ]);

        $product = Product::where('name', $product_name)->first();
        $productPrice = ProductPriceGetter::getPrice($product_name);

        $order->lines()->create([
            'description' => $product_name,
            'amount' => $productPrice * $taxing->reversedRate() ?: 0,
            'tax_amount' => $productPrice * $taxing->rate() ?: 0,
            'total_amount' => $productPrice ?: 0
        ]);

        if ($usedBalance) {
            $order->lines()->create([
                'description' => 'balance',
                'amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $balanceAmount ?: 0
            ]);
        }

        if ($shipping > 0) {
            $order->lines()->create([
                'description' => 'shipping',
                'amount' => $shipping ?: 0,
                'tax_amount' => 0,
                'total_amount' => $shipping ?: 0
            ]);
        }

        if ($coupon) {
            $couponAmount = 0;

            if ($coupon->discount_type == 'percentage') {
                $couponAmount = $product->price * ($coupon->discount / 100);
            } elseif ($coupon->discount_type == 'amount') {
                $couponAmount = $coupon->discount;
            }

            $order->lines()->create([
                'description' => 'coupon',
                'amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $couponAmount * -1
            ]);
        }

        if (!$product->isSubscription()) {
            $order->update([
                'is_shippable' => 0
            ]);
        }

        return $order;
    }

    public function setBalance($amount)
    {
        $this->balance = $amount;
        $this->save();
    }

    public function deductBalance($amount)
    {
        $this->setBalance($this->balance -= $amount);
    }

    public function addBalance($amount)
    {
        $this->setBalance($this->balance += $amount);
    }

    public function charge($amount, $makeOrder = true, $product = 'subscription', $coupon, $gift, $order_plan)
    {
        if (!$this->getPlan()) {
            return false;
        }
        $coupon_free = $this->getPlan()->getCouponCount();
        $discount_type = $this->getPlan()->getDiscountType();

        if($coupon_free > 0 && $discount_type=='motnh') {
            $amount = 0;
            $this->getPlan()->setCouponCount($coupon_free - 1);
            $coupon = new Coupon();
            $coupon->code = "SHAREMONTH";

        }elseif($coupon_free > 0 && $discount_type=='percent'){

            $amount = $amount - ($amount * ($coupon_free/100));
            $coupon = new Coupon();
            $coupon->code = "SHAREPERCENT";
            $this->getPlan()->clearDiscount();

        }elseif($coupon_free > 0 && $discount_type==''){
            $amount = 0;
            $this->getPlan()->setCouponCount($coupon_free-1);
            $coupon= Coupon::where('code','=',$this->getPlan()->getLastCoupon())->first();
        }



        /** @var PaymentHandler $paymentHandler */
        $paymentHandler = new PaymentHandler(PaymentDelegator::getMethod($this->getPlan()->getPaymentMethod()));

        $chargeId = '';
        $usedBalance = false;
        $prevAmount = 0;

        if ($this->balance > 0) {
            $prevAmount = ($this->balance > $amount ? $amount : $this->balance);
            $amount -= ($this->balance > $amount ? $amount : $this->balance);
            $this->deductBalance($this->balance > $prevAmount ? $prevAmount : $this->balance);
            $chargeId = 'balance';
            $usedBalance = true;
            $coupon= Coupon::where('code','=',$this->getPlan()->getLastCoupon())->first();
        }

        if ($amount > 0) {
            try {
                $charge = $paymentHandler->makeRebill($amount, $this->getPlan()->getPaymentCustomer());

            } catch (\Exception $exception) {
                \Log::critical($exception->getMessage());
                \Bugsnag::notifyException($exception);

                return false;
            }

            if (!$charge) {
                return false;
            }

            $chargeId = $charge->id;
        } else {
            if ($chargeId == '') {
                $chargeId = 'free';
            }
        }

        if ($makeOrder) {
            try {
                \Log::test('Success rebill order create: '.$this->id);
                \Event::fire(new CustomerWasBilled($this->id, $amount, $chargeId, $product, $usedBalance, $prevAmount * -1, $coupon, $gift, $order_plan, false));
            } catch (\Exception $exception) {
                \Log::error($exception->getFile() . " on line " . $exception->getLine());
            }
        }

        return true;
    }

    public function scopeToday($query)
    {
        return $query->whereBetween('created_at', [
            Date::today()->setTime(0, 0, 0),
            Date::today()->setTime(23, 59, 59)
        ]);
    }


    public function hasNewRecommendations()
    {
        $alphabet = range('a', 'c');
        $combinations = $this->calculateCombinations();
        $newVitamins = [];
        $currentVitamins = [];
        $isSimilar = true;

        if (count($combinations) == 0) {
            return false;
        }

        foreach ($this->getVitaminModels() as $vitaminModel) {
            $currentVitamins[] = strtolower($vitaminModel->code);
        }

        foreach ($combinations as $vitamin) {
            $newVitamins[] = strtolower($vitamin);
        }

        if (count($newVitamins) != count($currentVitamins)) {
            $isSimilar = false;

            return !$isSimilar;
        }

        foreach ($currentVitamins as $index => $currentVitamin) {
            if ($currentVitamin != $newVitamins[$index]) {
                $isSimilar = false;
                continue;
            }
        }

        return !$isSimilar;
    }

    public function getUserData()
    {
        $attributes = $this->customerAttributes()->where('identifier', 'LIKE', 'user_data.%')->get();

        $data = new \stdClass();

        foreach ($attributes as $attribute) {
            $attributePoints = explode('.', $attribute->identifier);

            if (count($attributePoints) > 2) {
                if (!isset($data->{$attributePoints[1]})) {
                    $data->{$attributePoints[1]} = new \stdClass();
                }

                $data->{$attributePoints[1]}->{$attributePoints[2]} = $attribute->value ?: null;
            } else {
                $data->{$attributePoints[1]} = $attribute->value ?: null;
            }
        }

        return $data;
    }


    public function calculateCombinations()
    {
        if ($this->plan->isCustom()) {
            return [];
        }

        $combinationLibrary = new CombinationLibraryNew();

        $data = $this->getUserData();

        $combinationLibrary->generateResult($data);

        return $combinationLibrary->getResult();
    }


    public static function calculateAlmostCombinations ($data)
    {
        $combinationLibrary = new CombinationLibraryNew;

        $combinationLibrary->generateResult(  $data  );

        $codes = $combinationLibrary->getResult();

        return $codes;
    }


    public function scopeMailFlowable($query)
    {
        return $query->where('is_mailflowable', 1)->where('accept_newletters', 1);
    }

    public function getVitamins()
    {
        return $this->getPlan()->vitamins;
    }

    public function setVitamins($vitamins)
    {
        if (is_object($vitamins)) {
            $vitamins = $vitamins->toArray();
        }

        // todo update price too!

        return $this->getPlan()->update(['vitamins' => json_encode($vitamins)]);
    }

    public function getVitaminModels()
    {
        $vitamins = json_decode($this->getVitamins());

        if (is_null($vitamins)) {
            return [];
        }

        return Vitamin::whereIn('id', $vitamins)->get();
    }

    public function loadLabel($order = null)
    {
        if (is_null($order)) {
            $order = new Order();
        }

        return view('pdf.label', ['customer' => $this, 'order' => $order]);
    }

    public function loadSticker($order = null)
    {
        if (is_null($order)) {
            $order = new Order();
        }

        return view('pdf.sticker', ['customer' => $this, 'order' => $order]);
    }

    /**
     * @return \PDF
     */
    public function generateLabel()
    {
        return \PDF::loadView('pdf.label', ['customer' => $this])
            ->setPaper([0, 0, 570, 262])
            ->setOrientation('landscape');
    }

    /**
     * @return \PDF
     */
    public function generateSticker()
    {
        return \PDF::loadView('pdf.sticker', ['customer' => $this])
            ->setPaper([0, 0, 643, 907])
            ->setOrientation('portrait');
    }

    public function scopeRebillable($query)
    {
        return $query->join('plans', 'plans.id', '=', 'customers.plan_id')
            ->select('customers.*')
            ->whereNull('plans.deleted_at')
            ->whereNull('plans.subscription_cancelled_at')
            ->whereNotNull('plans.subscription_rebill_at')
            ->where('plans.subscription_rebill_at', '<=', Date::now()->addDay());
    }

    public function getPaymentMethods()
    {
        $plan = $this->getPlan();

        if ($plan->getPaymentMethod() == '') {
            return [
                'methods' => [],
                'type' => ''
            ];
        }

        $paymentMethod = PaymentDelegator::getMethod($plan->getPaymentMethod());
        $paymentHandler = new PaymentHandler($paymentMethod);

        return [
            'type' => $plan->getPaymentMethod(),
            'methods' => $paymentHandler->getCustomerMethods($plan->getPaymentCustomerToken())
        ];
    }

    public function updateUserdata($userData)
    {
        $data = [
            'user_data.locale' => $userData->locale ?: \App::getLocale(),
            'user_data.gender' => $userData->gender ?: '',
            'user_data.birthdate' => $userData->birthdate ? date('Y-m-d', strtotime($userData->birthdate)) : '',
            'user_data.age' => $userData->age ?: '',
            'user_data.skin' => $userData->skin ?: '',
            'user_data.outside' => $userData->outside ?: '',
            'user_data.pregnant' => $userData->pregnant ?: '',
            'user_data.pregnancy.date' => $userData->pregnancy->date ?: ($userData->pregnancy->week ? Date::now()
                ->subWeeks($userData->pregnancy->week)
                ->format('Y-m-d') : ''),
            'user_data.pregnancy.week' => $userData->pregnancy->week ?: '',
            'user_data.pregnancy.wish' => $userData->pregnancy->wish ?: '',
            'user_data.diet' => $userData->diet ?: '',
            'user_data.sports' => $userData->sports ?: '',
            'user_data.lacks_energy' => $userData->lacks_energy ?: '',
            'user_data.smokes' => $userData->smokes ?: '',
            'user_data.immune_system' => $userData->immune_system ?: '',
            'user_data.vegetarian' => $userData->vegetarian ?: '',
            'user_data.joints' => $userData->joints ?: '',
            'user_data.stressed' => $userData->stressed ?: '',
            'user_data.foods.fruits' => $userData->foods->fruits ?: '',
            'user_data.foods.vegetables' => $userData->foods->vegetables ?: '',
            'user_data.foods.bread' => $userData->foods->bread ?: '',
            'user_data.foods.wheat' => $userData->foods->wheat ?: '',
            'user_data.foods.dairy' => $userData->foods->dairy ?: '',
            'user_data.foods.meat' => $userData->foods->meat ?: '',
            'user_data.foods.fish' => $userData->foods->fish ?: '',
            'user_data.foods.butter' => $userData->foods->butter ?: ''
        ];

        if (isset($userData->custom) && isset($userData->custom->three) && $userData->custom->three != '' && !empty($userData->custom->three)) {
            $data['user_data.custom.three'] = $userData->custom->three;
        }

        if (isset($userData->priority)) {
            $data['user_data.priority'] = $userData->priority;
        }

        return $this->setCustomerAttributes($data);
    }

    public function updateCustomUserData($userData)
    {
        $data = [];

        if (isset($userData->gender) && (!isset($userData->custom) && !isset($userData->custom->one))) {
            $data['user_data.gender'] = $userData->gender;
            $data['user_data.birthdate'] = date('Y-m-d', strtotime($userData->birthdate));
            $data['user_data.age'] = $userData->age;
            $data['user_data.skin'] = $userData->skin;
            $data['user_data.outside'] = $userData->outside;
        }

        if (isset($userData->custom)) {
            if (isset($userData->custom->one)) {
                $data['user_data.custom.one'] = $userData->custom->one;
            }

            if (isset($userData->custom->two)) {
                $data['user_data.custom.two'] = $userData->custom->two;
            }

            if (isset($userData->custom->three)) {
                $data['user_data.custom.three'] = $userData->custom->three;
            }

            if (isset($userData->custom->four)) {
                $data['user_data.custom.four'] = $userData->custom->four;
            }
        }

        $this->getPlan()->setIsCustom(false);

        $this->setCustomerAttributes($data);

        $newCombinations = $this->calculateCombinations();
        $vitamins = [];

        foreach ($newCombinations as $vitaminCode) {
            $vitamin = Vitamin::select('id')->whereCode($vitaminCode)->first();

            if ($vitamin) {
                $vitamins[] = $vitamin->id;
            }
        }

        $this->setVitamins($vitamins);

        return $this;
    }

    public function unsetCustomUserdata()
    {
        $this->customerAttributes()->where('identifier', 'LIKE', 'user_data.custom.%')->delete();

        return $this;
    }

    public function unsetAllUserdata()
    {
        $this->customerAttributes()->where('identifier', 'LIKE', 'user_data.%')->delete();

        return $this;
    }

}